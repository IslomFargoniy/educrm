<?php

namespace Database\Seeders;

use App\Enums\CashboxMethod;
use App\Enums\LessonStatus;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\ExpenseCategory;
use App\Models\Group;
use App\Models\Lead;
use App\Models\Lesson;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\EnrollmentService;
use App\Services\FinanceService;
use App\Services\LessonScheduler;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Realistic demo data for local development (never runs in production).
 */
class DemoSeeder extends Seeder
{
    public function run(LessonScheduler $scheduler, EnrollmentService $enrollments, AttendanceService $attendance, FinanceService $finance): void
    {
        $superadmin = $this->user('Superadmin', '+998901234567', Role::Superadmin);
        Auth::login($superadmin);

        $branch = Branch::query()->firstOrCreate(['name' => 'Chilonzor'], ['address' => 'Chilonzor 9-kvartal', 'phone' => '+998712000000']);
        $rooms = collect(['101', '102', '103'])->map(fn ($name) => $branch->rooms()->firstOrCreate(['name' => $name], ['capacity' => 14]));

        foreach ([CashboxMethod::Cash, CashboxMethod::Card, CashboxMethod::Click, CashboxMethod::Payme] as $method) {
            $branch->cashboxes()->firstOrCreate(['method' => $method], ['name' => ucfirst($method->value)]);
        }

        $this->user('Admin Adminov', '+998901110000', Role::Admin, $branch);
        $this->user('Dilnoza Kassir', '+998901110001', Role::Manager, $branch);
        $teachers = collect([['Aziz Karimov', '+998901110002'], ['Bobur Toshev', '+998901110003'], ['Malika Yusupova', '+998901110004']])
            ->map(fn ($t) => $this->user($t[0], $t[1], Role::Teacher, $branch));

        $groupData = [
            ['Ingliz-1', 'English', [1, 3, 5], '09:00', '10:30', 600_000, 12],
            ['Ingliz-2', 'English', [2, 4, 6], '14:00', '15:30', 600_000, 12],
            ['Matematika-1', 'Matematika', [1, 3, 5], '16:00', '17:30', 480_000, 12],
            ['IELTS intensiv', 'IELTS', [1, 2, 3, 4, 5], '18:00', '19:30', 1_000_000, 20],
        ];
        $groups = collect($groupData)->map(function ($g, $i) use ($branch, $teachers, $rooms, $scheduler) {
            $group = Group::query()->firstOrCreate(['branch_id' => $branch->id, 'name' => $g[0]], [
                'subject' => $g[1], 'days' => $g[2], 'start_time' => $g[3], 'end_time' => $g[4],
                'teacher_id' => $teachers[$i % 3]->id, 'room_id' => $rooms[$i % 3]->id,
                'teacher_percent' => 40, 'start_date' => now()->startOfMonth()->subMonthNoOverflow(),
            ]);
            $group->prices()->firstOrCreate(['effective_from' => now()->startOfMonth()->subMonthNoOverflow()], ['monthly_price' => $g[5], 'lessons_per_month' => $g[6]]);
            $scheduler->generate($group, now()->startOfMonth(), now()->addMonthNoOverflow()->endOfMonth());

            return $group;
        });

        if (Student::query()->exists()) {
            return;
        }

        $names = ['Ali Valiyev', 'Olim Karimov', 'Sardor Aliyev', 'Madina Rasulova', 'Jasur Bekov', 'Nilufar Saidova', 'Akmal Qodirov', 'Zarina Umarova', 'Shoxrux Ergashev', 'Dilshod Rahimov', 'Kamola Ismoilova', 'Bekzod Nurmatov', 'Sevara Tursunova', 'Otabek Yuldashev', 'Laylo Hamidova', 'Farrux Sobirov'];

        foreach ($names as $i => $name) {
            $student = Student::query()->create(['full_name' => $name, 'phone' => '+99893'.str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT), 'branch_id' => $branch->id]);
            $parent = User::query()->firstOrCreate(['phone' => '+99894'.str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT)], ['name' => explode(' ', $name)[1].' (ota-ona)', 'password' => 'password']);
            $parent->assignRole(Role::Parent->value);
            $student->guardians()->attach($parent->id, ['relation' => 'parent']);
            $enrollment = $enrollments->enroll($student, $groups[$i % 4], now()->startOfMonth(), ['trial_lessons' => $i === 15 ? 2 : 0]);

            if ($i % 3 !== 0) {
                $finance->receivePayment($enrollment, $branch->cashboxes()->where('method', $i % 2 ? 'cash' : 'click')->first(), $i % 5 === 0 ? 200_000 : ($groups[$i % 4]->prices()->first()->monthly_price));
            }
        }

        Lesson::query()->whereDate('date', '<=', today())->where('status', LessonStatus::Planned)->orderBy('date')->get()
            ->each(function (Lesson $lesson) use ($attendance, $superadmin) {
                $roster = $attendance->rosterFor($lesson);
                $statuses = $roster->mapWithKeys(fn ($e, $index) => [$e->id => ($e->id + $lesson->id) % 9 === 0 ? 'absent' : (($e->id + $lesson->id) % 13 === 0 ? 'excused' : 'present')])->all();

                if ($statuses !== []) {
                    $attendance->save($lesson, $statuses, $superadmin);
                }
            });

        $cash = $branch->cashboxes()->where('method', 'cash')->first();
        $finance->expense($cash, ExpenseCategory::query()->firstOrCreate(['name' => 'Reklama']), 300_000, 'Instagram');
        $teacher = $teachers->first()->fresh();

        if ($teacher->balance > 100_000) {
            $finance->payTeacher($teacher, $cash->fresh(), 100_000);
        }

        foreach ([['Sherzod', 'instagram', 'new'], ['Gulnoza', 'telegram', 'contacted'], ['Anvar', 'referral', 'new']] as [$name, $source, $status]) {
            Lead::query()->create(['branch_id' => $branch->id, 'full_name' => $name, 'phone' => '+99897'.random_int(1000000, 9999999), 'source' => $source, 'status' => $status, 'interested_course' => 'English', 'next_contact_at' => today()]);
        }
    }

    private function user(string $name, string $phone, Role $role, ?Branch $branch = null): User
    {
        $user = User::query()->firstOrCreate(['phone' => $phone], ['name' => $name, 'password' => 'password']);
        $user->syncRoles([$role->value]);

        if ($branch) {
            $user->branches()->syncWithoutDetaching([$branch->id]);
        }

        return $user;
    }
}
