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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->unsignedBigInteger('amount');
            $table->timestamp('occurred_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reverses_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->boolean('is_flagged')->default(false);
            $table->string('note')->nullable();
            $table->json('meta')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();
            $table->index(['type', 'occurred_at']);
            $table->index(['branch_id', 'occurred_at']);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('account_type', 20);
            $table->unsignedBigInteger('account_id');
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['account_type', 'account_id', 'occurred_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashbox_id')->constrained();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamp('paid_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receipt_no', 30)->unique();
            $table->string('note')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reverse_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('lesson_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained()->nullOnDelete();
            $table->date('lesson_date');
            $table->string('month', 7);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('covered_amount')->default(0);
            $table->unsignedBigInteger('written_off_amount')->default(0);
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('teacher_percent')->default(0);
            $table->boolean('is_substitute')->default(false);
            $table->string('status', 20);
            $table->timestamps();
            $table->index(['enrollment_id', 'month', 'status']);
            $table->index(['teacher_id', 'status']);
        });

        Schema::create('charge_coverages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_charge_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('teacher_amount')->default(0);
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('covered_at');
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20);
            $table->string('recipient');
            $table->string('template', 50);
            $table->text('body');
            $table->string('status', 20);
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('charge_coverages');
        Schema::dropIfExists('lesson_charges');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('transactions');
    }
};
