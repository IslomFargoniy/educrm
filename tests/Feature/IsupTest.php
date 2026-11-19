<?php

use App\Enums\Role;
use App\Jobs\PrepareDevice;
use App\Jobs\SendNotification;
use App\Jobs\SyncStudentFace;
use App\Models\Device;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Student;
use App\Models\StudentVisit;
use App\Models\User;
use App\Services\Isup\FaceSync;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    config(['isup.token' => str_repeat('t', 32), 'isup.gateway_url' => 'http://gateway.test']);
    $this->gateway = ['X-Gateway-Token' => str_repeat('t', 32)];
    $this->travelTo('2026-11-19 13:00:00');
});

/**
 * A student studying in a group of the device's branch, with a parent linked to Telegram.
 *
 * @return array{0: Device, 1: Enrollment, 2: Group}
 */
function studyingStudent(): array
{
    $device = Device::factory()->create();
    $group = Group::factory()->create(['branch_id' => $device->branch_id, 'start_time' => '14:00', 'end_time' => '15:30']);
    $student = Student::factory()->create(['branch_id' => $device->branch_id]);
    $enrollment = Enrollment::factory()->create(['student_id' => $student->id, 'group_id' => $group->id]);
    $student->guardians()->attach(User::factory()->create(['telegram_chat_id' => '123']), ['relation' => 'mother']);

    return [$device, $enrollment, $group];
}

function faceEvent(Device $device, int|string $employeeNo, int $serial, string $time = '2026-11-19T13:00:00+05:00'): array
{
    return [
        'gatewayDeviceId' => $device->isup_id,
        'dateTime' => $time,
        'eventType' => 'AccessControllerEvent',
        'AccessControllerEvent' => ['majorEventType' => 5, 'subEventType' => 75, 'employeeNoString' => (string) $employeeNo, 'serialNo' => $serial],
    ];
}

it('answers the gateway only with the shared token', function () {
    $device = Device::factory()->create();

    $this->getJson(route('isup.device-key', ['device_id' => $device->isup_id]))->assertUnauthorized();
    $this->getJson(route('isup.device-key', ['device_id' => $device->isup_id]), ['X-Gateway-Token' => 'wrong'])->assertUnauthorized();

    $this->getJson(route('isup.device-key', ['device_id' => $device->isup_id]), $this->gateway)
        ->assertOk()->assertJson(['key' => $device->isup_key]);
    $this->getJson(route('isup.device-key', ['device_id' => 'stranger']), $this->gateway)->assertNotFound();

    $device->update(['is_active' => false]);
    $this->getJson(route('isup.device-key', ['device_id' => $device->isup_id]), $this->gateway)->assertNotFound();
});

it('records a pass once and tells the family "arrived" first and "registered" afterwards, by Telegram only', function () {
    Queue::fake();
    [$device, $enrollment] = studyingStudent();

    $this->postJson(route('isup.events'), faceEvent($device, $enrollment->student_id, 10), $this->gateway)->assertOk();
    $this->postJson(route('isup.events'), faceEvent($device, $enrollment->student_id, 10), $this->gateway)->assertOk();
    $this->travel(2)->hours();
    $this->postJson(route('isup.events'), faceEvent($device, $enrollment->student_id, 11, now()->toIso8601String()), $this->gateway)->assertOk();

    expect(StudentVisit::query()->where('student_id', $enrollment->student_id)->count())->toBe(2);
    Queue::assertPushed(SendNotification::class, 2);
    Queue::assertPushed(SendNotification::class, fn (SendNotification $job) => $job->template === 'arrived' && $job->telegramOnly);
    Queue::assertPushed(SendNotification::class, fn (SendNotification $job) => $job->template === 'visit' && $job->telegramOnly);
});

it('uses the server time when a live event comes from a device with a wrong clock', function () {
    Queue::fake();
    [$device, $enrollment] = studyingStudent();

    $this->postJson(route('isup.events'), faceEvent($device, $enrollment->student_id, 5, '2026-11-19T13:09:00+05:00'), $this->gateway)->assertOk();

    expect(StudentVisit::query()->firstOrFail()->scanned_at->format('H:i'))->toBe('13:00');
});

it('pre-marks students who came in before the lesson, but writes attendance only when the teacher saves', function () {
    Queue::fake();
    [$device, $enrollment, $group] = studyingStudent();
    $absent = Enrollment::factory()->create(['group_id' => $group->id, 'student_id' => Student::factory()->create(['branch_id' => $device->branch_id])->id]);
    $lesson = Lesson::factory()->create(['group_id' => $group->id, 'teacher_id' => $group->teacher_id, 'date' => today(), 'start_time' => '14:00', 'end_time' => '15:30']);
    $this->postJson(route('isup.events'), faceEvent($device, $enrollment->student_id, 1), $this->gateway)->assertOk();
    $this->actingAs(userWithRole(Role::Superadmin));

    $this->get(route('lessons.show', $lesson))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('roster', fn ($roster) => collect($roster)->firstWhere('enrollment_id', $enrollment->id)['arrived_at'] === '13:00'
            && collect($roster)->firstWhere('enrollment_id', $absent->id)['arrived_at'] === null));

    $token = User::query()->findOrFail(auth()->id())->createToken('test')->plainTextToken;
    $students = $this->withToken($token)->getJson(route('api.lessons.show', $lesson))->assertOk()->json('students');
    expect(collect($students)->firstWhere('enrollment_id', $enrollment->id)['arrived_at'])->toBe('13:00')
        ->and($lesson->attendances()->count())->toBe(0);
});

it('does not count a pass from long before the lesson as an arrival', function () {
    Queue::fake();
    [$device, $enrollment, $group] = studyingStudent();
    $lesson = Lesson::factory()->create(['group_id' => $group->id, 'date' => today(), 'start_time' => '16:00', 'end_time' => '17:30']);
    $this->postJson(route('isup.events'), faceEvent($device, $enrollment->student_id, 1), $this->gateway)->assertOk();
    $this->actingAs(userWithRole(Role::Superadmin));

    $this->get(route('lessons.show', $lesson))->assertInertia(fn (AssertableInertia $page) => $page->where('roster.0.arrived_at', null));
});

it('keeps the face photo private and sends it to the devices after an upload', function () {
    Queue::fake();
    Storage::fake('local');
    [, $enrollment] = studyingStudent();
    $this->actingAs(userWithRole(Role::Superadmin));

    $this->post(route('students.face.store', $enrollment->student_id), ['photo' => UploadedFile::fake()->image('face.jpg', 1200, 1600)])->assertSessionHasNoErrors();

    $student = $enrollment->student->fresh();
    Storage::disk('local')->assertExists($student->face_photo);
    Queue::assertPushed(SyncStudentFace::class, fn ($job) => $job->studentId === $student->id);
    [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($student->face_photo));
    expect(max($width, $height))->toBe(640);

    auth()->logout();
    $this->get(route('students.face.show', $student))->assertRedirect();
    $this->get(FaceSync::faceUrl($student))->assertOk();
    $this->get(route('isup.face', ['student' => $student->id]))->assertForbidden();
});

it('writes the student and the face to the devices of the branch and removes them after they leave', function () {
    Storage::fake('local');
    Http::fake(['gateway.test/*' => Http::response(['success' => true, 'response' => '{"statusCode":1}'])]);
    [$device, $enrollment] = studyingStudent();
    $student = $enrollment->student;
    $student->update(['face_photo' => 'faces/x.jpg']);

    app(FaceSync::class)->syncStudent($student);

    Http::assertSent(fn (HttpRequest $r) => $r['url'] === '/ISAPI/AccessControl/UserInfo/SetUp?format=json'
        && json_decode($r['body'], true)['UserInfo']['employeeNo'] === (string) $student->id
        && $r->header('X-Gateway-Token') === [str_repeat('t', 32)]);
    Http::assertSent(fn (HttpRequest $r) => $r['url'] === '/ISAPI/Intelligent/FDLib/FDSetUp?format=json'
        && str_contains(json_decode($r['body'], true)['faceURL'], '/isup/faces/'.$student->id));
    expect($device->students()->wherePivot('status', 'synced')->count())->toBe(1);

    $enrollment->update(['status' => 'left']);
    $this->artisan('isup:reconcile')->assertSuccessful();

    Http::assertSent(fn (HttpRequest $r) => $r['url'] === '/ISAPI/AccessControl/UserInfo/Delete?format=json');
    expect($device->students()->first()->sync->status)->toBe('removed');
});

it('marks a device online and prepares it when it connects', function () {
    Queue::fake();
    $device = Device::factory()->create(['is_online' => false]);

    $this->postJson(route('isup.device-status'), ['device_id' => $device->isup_id, 'status' => 'online', 'ip' => '10.0.0.5', 'serial' => 'AB123'], $this->gateway)->assertOk();

    expect($device->fresh())->is_online->toBeTrue()->serial->toBe('AB123');
    Queue::assertPushed(PrepareDevice::class, fn ($job) => $job->deviceId === $device->id);
});

it('lets an admin add a device and shows what to type into it', function () {
    Http::fake(['gateway.test/*' => Http::response([])]);
    $admin = userWithRole(Role::Superadmin);
    $branch = Device::factory()->create()->branch;
    $this->actingAs($admin);

    $this->post(route('admin.devices.store'), ['branch_id' => $branch->id, 'name' => 'Chiqish', 'direction' => 'out'])->assertSessionHasNoErrors();

    $this->get(route('admin.devices.index'))->assertInertia(fn (AssertableInertia $page) => $page
        ->component('admin/devices')
        ->has('devices', 2)
        ->where('server.port', 7660)
        ->where('gatewayRunning', true));
});

it('gives a new device a short Device ID and an easy key, and lets the admin type their own', function () {
    $this->actingAs(userWithRole(Role::Superadmin));
    config(['isup.device_id_prefix' => 'crm.mensalc.uz', 'isup.default_key' => '11221122']);
    $device = Device::factory()->create();

    expect($device->isup_id)->toBe('crm.mensalc.uz-'.$device->id)
        ->and($device->isup_key)->toBe('11221122');

    config(['isup.default_key' => null]);
    expect(Device::factory()->create()->isup_key)->toMatch('/^[a-z]{4}\d{4}$/');

    $this->put(route('admin.devices.update', $device), ['branch_id' => $device->branch_id, 'name' => 'Kirish', 'direction' => 'in', 'isup_id' => 'filial1', 'isup_key' => 'mensa2026'])
        ->assertSessionHasNoErrors();
    expect($device->fresh())->isup_id->toBe('filial1')->isup_key->toBe('mensa2026');

    $this->put(route('admin.devices.update', $device), ['branch_id' => $device->branch_id, 'name' => 'Kirish', 'direction' => 'in', 'isup_key' => '123'])
        ->assertSessionHasErrors('isup_key');
});
