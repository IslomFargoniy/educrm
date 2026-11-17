<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

/**
 * @return array<string, mixed>
 */
function importRow(int $line, string $name, ?string $parentName, ?string $parentPhone, array $errors = []): array
{
    return [
        'line' => $line, 'full_name' => $name, 'phone' => null, 'parent_name' => $parentName, 'parent_phone' => $parentPhone,
        'group' => '', 'group_id' => null, 'joined_at' => today()->toDateString(), 'balance' => 0, 'errors' => $errors,
    ];
}

function studentWithParent(Branch $branch): array
{
    $student = Student::factory()->create(['branch_id' => $branch->id]);
    $parent = User::factory()->create(['password' => 'eski-parol']);
    $parent->assignRole(Role::Parent->value);
    $student->guardians()->attach($parent->id, ['relation' => 'parent']);

    return [$student, $parent];
}

it('gives the admin the logins and passwords of the parents the import created, once', function () {
    $branch = Branch::factory()->create();
    $admin = userWithRole(Role::Admin, [$branch]);
    $existing = User::factory()->create(['phone' => '+998935556677']);
    $existing->assignRole(Role::Parent->value);

    $rows = [
        importRow(2, 'Ali Valiyev', 'Vali Valiyev', '+998901112233'),
        importRow(3, 'Hasan Valiyev', 'Vali Valiyev', '+998901112233'),
        importRow(4, 'Olim Karimov', 'Karim Karimov', '+998935556677'),
        importRow(5, 'Xato', 'Xato Ota', '+998900000000', ['Wrong date.']),
    ];

    $this->actingAs($admin)
        ->withSession(['import_preview' => ['branch_id' => $branch->id, 'rows' => $rows]])
        ->post(route('admin.import.confirm'))
        ->assertRedirect()
        ->assertSessionHas('import_result', function (array $result) {
            expect($result['count'])->toBe(3)
                ->and($result['parents'])->toHaveCount(1)
                ->and($result['parents'][0]['phone'])->toBe('+998901112233')
                ->and($result['parents'][0]['children'])->toBe(['Ali Valiyev', 'Hasan Valiyev']);

            $parent = User::query()->where('phone', '+998901112233')->firstOrFail();

            return Hash::check($result['parents'][0]['password'], $parent->password) && $parent->hasRole(Role::Parent->value);
        });

    // The page shows the list on the next request only.
    $this->get(route('admin.import.index'))->assertInertia(fn (Assert $page) => $page->where('result.parents.0.phone', '+998901112233'));
    $this->get(route('admin.import.index'))->assertInertia(fn (Assert $page) => $page->where('result', null));

    expect(User::query()->where('phone', '+998900000000')->exists())->toBeFalse()
        ->and(Hash::check('password', $existing->refresh()->password))->toBeTrue();
});

it('lets staff of the student\'s branch reset a parent password', function () {
    $branch = Branch::factory()->create();
    $manager = userWithRole(Role::Manager, [$branch]);
    [$student, $parent] = studentWithParent($branch);
    $parent->createToken('mobile');

    $this->actingAs($manager)
        ->post(route('students.guardians.password', [$student, $parent]))
        ->assertRedirect();

    $parent->refresh();
    expect(Hash::check('eski-parol', $parent->password))->toBeFalse()
        ->and($parent->tokens()->count())->toBe(0);
});

it('does not let other branches, users without the permission or other students reset a parent password', function () {
    $branch = Branch::factory()->create();
    [$student, $parent] = studentWithParent($branch);
    [$otherStudent] = studentWithParent($branch);

    $this->actingAs(userWithRole(Role::Manager, [Branch::factory()->create()]))
        ->post(route('students.guardians.password', [$student, $parent]))->assertForbidden();

    $this->actingAs(userWithRole(Role::Teacher, [$branch]))
        ->post(route('students.guardians.password', [$student, $parent]))->assertForbidden();

    $this->actingAs(userWithRole(Role::Manager, [$branch]))
        ->post(route('students.guardians.password', [$otherStudent, $parent]))->assertNotFound();

    expect(Hash::check('eski-parol', $parent->refresh()->password))->toBeTrue();
});

it('does not reset the password of a staff member who is also a parent', function () {
    $branch = Branch::factory()->create();
    [$student, $parent] = studentWithParent($branch);
    $parent->assignRole(Role::Admin->value);

    $this->actingAs(userWithRole(Role::Manager, [$branch]))
        ->post(route('students.guardians.password', [$student, $parent]))->assertForbidden();

    expect(Hash::check('eski-parol', $parent->refresh()->password))->toBeTrue();
});

it('does not let staff of another branch change a student\'s parents', function () {
    $branch = Branch::factory()->create();
    [$student, $parent] = studentWithParent($branch);
    $this->actingAs(userWithRole(Role::Manager, [Branch::factory()->create()]));

    $this->post(route('students.guardians.store', $student), ['name' => 'Ota', 'phone' => '+998901234567', 'relation' => 'father'])->assertForbidden();
    $this->delete(route('students.guardians.destroy', [$student, $parent]))->assertForbidden();

    expect($student->guardians()->count())->toBe(1);
});
