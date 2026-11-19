<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // The first (trial) lesson the lead is signed up for.
            $table->dateTime('trial_at')->nullable()->after('next_contact_at');
            $table->foreignId('trial_group_id')->nullable()->after('trial_at')->constrained('groups')->nullOnDelete();
            $table->index(['branch_id', 'trial_at']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'trial_at']);
            $table->dropConstrainedForeignId('trial_group_id');
            $table->dropColumn('trial_at');
        });
    }
};
