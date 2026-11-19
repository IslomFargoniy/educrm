<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            // Why the student left the group: graduated, dropped out or expelled.
            $table->string('leave_reason', 16)->nullable()->after('left_at');
            $table->string('leave_note')->nullable()->after('leave_reason');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', fn (Blueprint $table) => $table->dropColumn(['leave_reason', 'leave_note']));
    }
};
