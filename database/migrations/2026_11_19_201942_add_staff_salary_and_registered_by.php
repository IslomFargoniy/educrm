<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff pay: fixed monthly salary plus a KPI for every new student they registered who made a first payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('salary_fixed')->nullable()->after('locale');
            $table->unsignedInteger('kpi_first_payment')->nullable()->after('salary_fixed');
        });

        Schema::table('students', function (Blueprint $table) {
            // The staff member who took the student in (sent them to the right course).
            $table->foreignId('registered_by')->nullable()->after('lead_id')->constrained('users')->nullOnDelete();
        });

        // Students who came from a lead: the lead's responsible person took them in.
        DB::table('students')->whereNotNull('lead_id')->orderBy('id')->each(function ($student): void {
            $assignee = DB::table('leads')->where('id', $student->lead_id)->value('assigned_to');

            if ($assignee !== null) {
                DB::table('students')->where('id', $student->id)->update(['registered_by' => $assignee]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', fn (Blueprint $table) => $table->dropConstrainedForeignId('registered_by'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['salary_fixed', 'kpi_first_payment']));
    }
};
