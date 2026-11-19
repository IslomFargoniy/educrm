<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Result of the last call about the debt (colour tag on the phone number).
            $table->string('call_status', 24)->nullable()->after('face_photo');
            $table->timestamp('call_status_at')->nullable()->after('call_status');
            $table->foreignId('call_status_by')->nullable()->after('call_status_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('call_status_by');
            $table->dropColumn(['call_status', 'call_status_at']);
        });
    }
};
