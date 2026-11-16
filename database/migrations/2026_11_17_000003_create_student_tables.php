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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('phone', 20);
            $table->string('source', 30);
            $table->string('interested_course')->nullable();
            $table->string('status', 20)->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('next_contact_at')->nullable();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });

        Schema::create('lead_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('text');
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('phone', 20)->nullable();
            $table->date('birth_date')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index('full_name');
        });

        Schema::create('guardian_student', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('relation', 20)->default('parent');
            $table->primary(['user_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guardian_student');
        Schema::dropIfExists('students');
        Schema::dropIfExists('lead_notes');
        Schema::dropIfExists('leads');
    }
};
