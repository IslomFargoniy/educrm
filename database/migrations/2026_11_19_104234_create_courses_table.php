<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable()->after('subject')->constrained()->nullOnDelete();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable()->after('interested_course')->constrained()->nullOnDelete();
        });

        // Existing free-text subjects and lead courses become the first courses.
        $names = DB::table('groups')->whereNotNull('subject')->pluck('subject')
            ->merge(DB::table('leads')->whereNotNull('interested_course')->pluck('interested_course'))
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique(fn (string $name) => mb_strtolower($name))
            ->sort()
            ->values();

        foreach ($names as $name) {
            $courseId = DB::table('courses')->insertGetId(['name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            $lower = mb_strtolower($name);
            DB::table('groups')->whereRaw('LOWER(TRIM(subject)) = ?', [$lower])->update(['course_id' => $courseId, 'subject' => $name]);
            DB::table('leads')->whereRaw('LOWER(TRIM(interested_course)) = ?', [$lower])->update(['course_id' => $courseId, 'interested_course' => $name]);
        }
    }

    public function down(): void
    {
        Schema::table('leads', fn (Blueprint $table) => $table->dropConstrainedForeignId('course_id'));
        Schema::table('groups', fn (Blueprint $table) => $table->dropConstrainedForeignId('course_id'));
        Schema::dropIfExists('courses');
    }
};
