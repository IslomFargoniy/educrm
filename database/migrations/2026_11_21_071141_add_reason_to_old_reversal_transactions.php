<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Old "share taken back" / "lesson re-billed" rows had no reason. Work it out from what the charge looks like now:
 * the lesson was cancelled, the attendance is excused, the teacher of the lesson changed, or (share only) the charge
 * is still active, so the share went back because a payment was cancelled. Rows that match none keep no reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('transactions')
            ->whereIn('type', ['earning_reversal', 'charge_reversal'])
            ->orderBy('id')
            ->chunkById(200, function ($transactions) {
                foreach ($transactions as $transaction) {
                    $meta = json_decode($transaction->meta ?? '[]', true) ?: [];
                    if (! empty($meta['reason']) || empty($meta['charge_id'])) {
                        continue;
                    }

                    $charge = DB::table('lesson_charges')->where('id', $meta['charge_id'])->first();
                    if ($charge === null) {
                        continue;
                    }

                    $lesson = DB::table('lessons')->where('id', $charge->lesson_id)->first();
                    $attendanceStatus = $charge->attendance_id
                        ? DB::table('attendances')->where('id', $charge->attendance_id)->value('status')
                        : null;

                    $reason = match (true) {
                        $lesson !== null && $lesson->status === 'cancelled' => 'lesson_cancelled',
                        $attendanceStatus === 'excused' => 'excused',
                        $transaction->type === 'earning_reversal' && $charge->status === 'active' => 'payment_reversed',
                        $lesson !== null && (int) $charge->teacher_id !== (int) $lesson->teacher_id => 'teacher_changed',
                        default => null,
                    };

                    if ($reason !== null) {
                        DB::table('transactions')->where('id', $transaction->id)
                            ->update(['meta' => json_encode([...$meta, 'reason' => $reason], JSON_UNESCAPED_UNICODE)]);
                    }
                }
            });
    }

    public function down(): void
    {
        //
    }
};
