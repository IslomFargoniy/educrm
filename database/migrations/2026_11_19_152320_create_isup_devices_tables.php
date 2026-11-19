<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('direction', 8)->default('in');
            $table->string('isup_id', 32)->unique();
            $table->text('isup_key');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_online')->default(false);
            $table->string('ip', 64)->nullable();
            $table->string('serial', 64)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('events_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('face_photo')->nullable()->after('note');
        });

        Schema::create('device_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16);
            $table->string('error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['device_id', 'student_id']);
        });

        Schema::create('student_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('employee_no', 32)->nullable();
            $table->string('event_serial', 32)->nullable();
            $table->string('direction', 8);
            $table->dateTime('scanned_at');
            $table->timestamps();
            $table->unique(['device_id', 'event_serial']);
            $table->index(['student_id', 'scanned_at']);
            $table->index(['branch_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_visits');
        Schema::dropIfExists('device_student');
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn('face_photo'));
        Schema::dropIfExists('devices');
    }
};
