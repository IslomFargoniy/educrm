<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Group;
use App\Models\Student;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

/**
 * Imports students, parents, group membership and opening balances from Excel.
 *
 * Columns: full name, phone, parent name, parent phone, group, joined date, balance (+ prepaid / − debt).
 */
class StudentImporter
{
    public const HEADERS = ['F.I.Sh', 'Telefon', 'Ota-ona ismi', 'Ota-ona telefoni', 'Guruh', "Qo'shilgan sana (YYYY-MM-DD)", 'Balans (+ oldindan / − qarz)'];

    public function __construct(private EnrollmentService $enrollments, private FinanceService $finance) {}

    /**
     * Read and check the file without saving anything.
     *
     * @return list<array{line: int, full_name: string, phone: ?string, parent_name: ?string, parent_phone: ?string, group: string, group_id: ?int, joined_at: ?string, balance: int, errors: list<string>}>
     */
    public function preview(string $path, int $branchId): array
    {
        $groups = Group::query()->where('branch_id', $branchId)->get(['id', 'name'])
            ->mapWithKeys(fn (Group $g) => [mb_strtolower(trim($g->name)) => $g->id]);
        $rows = [];
        $reader = new Reader;
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $index => $row) {
                if ($index === 1) {
                    continue;
                }

                $cells = array_map(fn ($value) => match (true) {
                    $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
                    is_scalar($value) => trim((string) $value),
                    default => '',
                }, $row->toArray());

                if (implode('', $cells) === '') {
                    continue;
                }

                [$name, $phone, $parentName, $parentPhone, $group, $joined, $balance] = array_pad($cells, 7, '');
                $errors = [];
                $phone = Phone::normalize($phone ?: null);
                $parentPhone = Phone::normalize($parentPhone ?: null);
                $groupId = $groups[mb_strtolower($group)] ?? null;

                if ($name === '') {
                    $errors[] = __('Name is empty.');
                }

                if ($phone !== null && ! Phone::isValid($phone)) {
                    $errors[] = __('Wrong phone number.');
                }

                if ($parentPhone !== null && ! Phone::isValid($parentPhone)) {
                    $errors[] = __('Wrong parent phone number.');
                }

                if ($group !== '' && $groupId === null) {
                    $errors[] = __('Group ":group" not found.', ['group' => $group]);
                }

                try {
                    $joinedAt = $joined !== '' ? Carbon::parse($joined)->toDateString() : today()->toDateString();
                } catch (Throwable) {
                    $joinedAt = null;
                    $errors[] = __('Wrong date.');
                }

                $amount = (int) preg_replace('/[^\d\-]/', '', str_replace('−', '-', $balance));

                if ($amount !== 0 && $groupId === null) {
                    $errors[] = __('Balance needs a group.');
                }

                $rows[] = [
                    'line' => $index,
                    'full_name' => $name,
                    'phone' => $phone,
                    'parent_name' => $parentName ?: null,
                    'parent_phone' => $parentPhone,
                    'group' => $group,
                    'group_id' => $groupId,
                    'joined_at' => $joinedAt,
                    'balance' => $amount,
                    'errors' => $errors,
                ];
            }

            break;
        }

        $reader->close();

        return $rows;
    }

    /**
     * Save rows without errors. Returns the number of imported students and the parent accounts
     * created on the way with their passwords: nobody else ever sees them, so the admin hands them out.
     *
     * @param  list<array{line: int, full_name: string, phone: ?string, parent_name: ?string, parent_phone: ?string, group: string, group_id: ?int, joined_at: ?string, balance: int, errors: list<string>}>  $rows
     * @return array{count: int, parents: list<array{name: string, phone: string, password: string, children: list<string>}>}
     */
    public function import(array $rows, int $branchId): array
    {
        $count = 0;
        $parents = [];

        DB::transaction(function () use ($rows, $branchId, &$count, &$parents) {
            foreach ($rows as $row) {
                if ($row['errors'] !== []) {
                    continue;
                }

                $student = Student::query()->firstOrCreate(
                    ['full_name' => $row['full_name'], 'phone' => $row['phone']],
                    ['branch_id' => $branchId],
                );

                if ($row['parent_phone']) {
                    $password = Str::password(8, symbols: false);
                    $parent = User::query()->firstOrCreate(
                        ['phone' => $row['parent_phone']],
                        ['name' => $row['parent_name'] ?? $row['full_name'], 'password' => $password],
                    );

                    if ($parent->wasRecentlyCreated) {
                        $parents[$parent->id] = ['name' => $parent->name, 'phone' => $parent->phone, 'password' => $password, 'children' => []];
                    }

                    if (isset($parents[$parent->id])) {
                        $parents[$parent->id]['children'][] = $student->full_name;
                    }

                    $parent->hasRole(Role::Parent->value) || $parent->assignRole(Role::Parent->value);
                    $student->guardians()->syncWithoutDetaching([$parent->id => ['relation' => 'parent']]);
                }

                if ($row['group_id']) {
                    $group = Group::query()->findOrFail($row['group_id']);
                    $enrollment = $group->enrollments()->where('student_id', $student->id)->whereIn('status', ['active', 'trial'])->first()
                        ?? $this->enrollments->enroll($student, $group, Carbon::parse($row['joined_at']));
                    $this->finance->openingBalance($enrollment, (int) $row['balance']);
                }

                $count++;
            }
        });

        return ['count' => $count, 'parents' => array_values($parents)];
    }
}
