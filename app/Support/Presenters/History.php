<?php

namespace App\Support\Presenters;

use App\Enums\ChargeStatus;
use App\Enums\LedgerAccount;
use App\Enums\TransactionType;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\LedgerEntry;
use App\Models\LessonCharge;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Builds balance history tables (cashbox, teacher, student) in one format:
 * date · type · details · income · expense · balance after.
 */
final class History
{
    /**
     * @return array{opening: int, income: int, expense: int, closing: int, rows: list<array<string, mixed>>}
     */
    public static function forAccount(LedgerAccount $type, int $id, CarbonInterface $from, CarbonInterface $to): array
    {
        $entries = LedgerEntry::query()
            ->where('account_type', $type)
            ->where('account_id', $id)
            ->whereBetween('occurred_at', [$from->startOfDay(), $to->endOfDay()])
            ->with('transaction.creator:id,name')
            ->orderBy('id')
            ->get();

        $opening = self::openingBalance($type, $id, $from);
        $rows = array_values($entries->map(fn (LedgerEntry $entry) => self::row($entry))->all());

        if ($type === LedgerAccount::Teacher) {
            $rows = self::groupLessonShares($rows);
        }

        return self::summary($opening, $rows);
    }

    /**
     * Student history of one group: money rows plus excused and free lessons (no money moved).
     *
     * @return array{opening: int, income: int, expense: int, closing: int, rows: list<array<string, mixed>>}
     */
    public static function forEnrollment(Enrollment $enrollment, CarbonInterface $from, CarbonInterface $to): array
    {
        $money = LedgerEntry::query()
            ->where('account_type', LedgerAccount::Enrollment)
            ->where('account_id', $enrollment->id)
            ->whereBetween('occurred_at', [$from->startOfDay(), $to->endOfDay()])
            ->with('transaction.creator:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (LedgerEntry $entry) => self::row($entry));

        $billedAttendanceIds = LessonCharge::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('status', [ChargeStatus::Active, ChargeStatus::PendingTrial])
            ->whereNotNull('attendance_id')
            ->pluck('attendance_id')
            ->all();

        $silent = Attendance::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereNotIn('id', $billedAttendanceIds)
            ->whereBetween('updated_at', [$from->startOfDay(), $to->endOfDay()])
            ->with('lesson:id,date')
            ->get()
            ->map(fn (Attendance $attendance) => [
                'id' => 'a'.$attendance->id,
                'date' => $attendance->updated_at->toIso8601String(),
                'type' => $attendance->status->isBillable() ? 'free_lesson' : 'excused_lesson',
                'meta' => ['lesson_date' => $attendance->lesson->date->toDateString(), 'attendance' => $attendance->status->value],
                'income' => 0,
                'expense' => 0,
                'balance' => null,
                'note' => null,
                'by' => null,
                'flagged' => false,
                'reversed' => false,
            ]);

        $opening = self::openingBalance(LedgerAccount::Enrollment, $enrollment->id, $from);
        $rows = $money->concat($silent)->sortBy('date')->values()->all();
        $running = $opening;

        foreach ($rows as &$row) {
            if ($row['balance'] === null) {
                $row['balance'] = $running;
            }
            $running = $row['balance'];
        }
        unset($row);

        return self::summary($opening, array_values($rows));
    }

    /**
     * Lessons the teacher taught but students have not paid for yet.
     *
     * @return array{total: int, items: list<array<string, mixed>>}
     */
    public static function pendingForTeacher(User $teacher): array
    {
        $items = LessonCharge::query()
            ->where('teacher_id', $teacher->id)
            ->where('status', ChargeStatus::Active)
            ->whereRaw('amount > covered_amount + written_off_amount')
            ->with('enrollment.student:id,full_name', 'enrollment.group:id,name')
            ->orderBy('lesson_date')
            ->get()
            ->map(fn (LessonCharge $charge) => [
                'id' => $charge->id,
                'student' => $charge->enrollment->student->full_name,
                'group' => $charge->enrollment->group->name,
                'lesson_date' => $charge->lesson_date->toDateString(),
                'amount' => $charge->teacherShareOf($charge->amount) - $charge->teacherShareOf($charge->covered_amount),
                'is_substitute' => $charge->is_substitute,
            ]);

        return ['total' => (int) $items->sum('amount'), 'items' => array_values($items->all())];
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(LedgerEntry $entry): array
    {
        $transaction = $entry->transaction;

        return [
            'id' => $entry->id,
            'date' => $entry->occurred_at->toIso8601String(),
            'type' => $transaction->type->value,
            'meta' => $transaction->meta ?? [],
            'income' => max(0, $entry->amount),
            'expense' => max(0, -$entry->amount),
            'balance' => $entry->balance_after,
            'note' => $transaction->note,
            'by' => $transaction->creator?->name,
            'flagged' => $transaction->is_flagged,
            'reversed' => $transaction->reversed_at !== null,
        ];
    }

    /**
     * One row per lesson for teacher shares paid on time; late payments stay separate.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function groupLessonShares(array $rows): array
    {
        $result = [];
        $index = [];

        foreach ($rows as $row) {
            $isOnTimeShare = $row['type'] === TransactionType::TeacherEarning->value
                && ! ($row['meta']['is_late_payment'] ?? false)
                && isset($row['meta']['lesson_id']);

            if (! $isOnTimeShare) {
                $result[] = $row;

                continue;
            }

            $key = $row['meta']['lesson_id'].'|'.substr($row['date'], 0, 10);
            $child = [
                'student' => $row['meta']['student'] ?? '',
                'lesson_amount' => $row['meta']['lesson_amount'] ?? 0,
                'covered' => $row['meta']['covered'] ?? 0,
                'percent' => $row['meta']['percent'] ?? 0,
                'share' => $row['income'],
            ];

            if (isset($index[$key])) {
                $position = $index[$key];
                $result[$position]['income'] += $row['income'];
                $result[$position]['balance'] = $row['balance'];
                $result[$position]['children'][] = $child;
                $result[$position]['meta']['students'] = count($result[$position]['children']);

                continue;
            }

            $row['type'] = 'lesson_share';
            $row['children'] = [$child];
            $row['meta']['students'] = 1;
            $index[$key] = count($result);
            $result[] = $row;
        }

        return $result;
    }

    private static function openingBalance(LedgerAccount $type, int $id, CarbonInterface $from): int
    {
        return (int) (LedgerEntry::query()
            ->where('account_type', $type)
            ->where('account_id', $id)
            ->where('occurred_at', '<', $from->startOfDay())
            ->orderByDesc('id')
            ->value('balance_after') ?? 0);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{opening: int, income: int, expense: int, closing: int, rows: list<array<string, mixed>>}
     */
    private static function summary(int $opening, array $rows): array
    {
        $collection = new Collection($rows);
        $income = (int) $collection->sum('income');
        $expense = (int) $collection->sum('expense');

        return [
            'opening' => $opening,
            'income' => $income,
            'expense' => $expense,
            'closing' => $opening + $income - $expense,
            'rows' => array_reverse($rows),
        ];
    }
}
