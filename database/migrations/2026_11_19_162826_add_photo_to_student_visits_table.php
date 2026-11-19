<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_visits', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('scanned_at');
        });
    }

    public function down(): void
    {
        Schema::table('student_visits', fn (Blueprint $table) => $table->dropColumn('photo'));
    }
};
