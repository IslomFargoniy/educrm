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
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('phone', 20)->nullable()->unique()->after('name');
            $table->string('locale', 5)->default('uz')->after('phone');
            $table->boolean('is_active')->default(true)->after('locale');
            $table->bigInteger('balance')->default(0)->after('is_active');
            $table->string('telegram_chat_id')->nullable()->unique()->after('balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropUnique(['telegram_chat_id']);
            $table->dropColumn(['phone', 'locale', 'is_active', 'balance', 'telegram_chat_id']);
        });
    }
};
