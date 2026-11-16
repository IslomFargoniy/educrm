<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('subject')->nullable();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('teacher_percent')->default(40);
            $table->json('days');
            $table->time('start_time');
            $table->time('end_time');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('group_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('monthly_price');
            $table->unsignedSmallInteger('lessons_per_month');
            $table->date('effective_from');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['group_id', 'effective_from']);
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->date('joined_at');
            $table->date('left_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedSmallInteger('trial_lessons')->default(0);
            $table->foreignId('transferred_from_id')->nullable()->constrained('enrollments')->nullOnDelete();
            $table->unsignedTinyInteger('teacher_percent')->nullable();
            $table->string('discount_type', 10)->nullable();
            $table->unsignedBigInteger('discount_value')->default(0);
            $table->bigInteger('balance')->default(0);
            $table->timestamps();
            $table->index(['group_id', 'status']);
        });

        Schema::create('enrollment_freezes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_substitute')->default(false);
            $table->boolean('is_makeup')->default(false);
            $table->string('status', 20)->default('planned');
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
            $table->index(['group_id', 'date']);
            $table->index(['date', 'status']);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('note')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['lesson_id', 'enrollment_id']);
        });

        Schema::create('attendance_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete();
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_audits');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('enrollment_freezes');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('group_prices');
        Schema::dropIfExists('groups');
    }
};
