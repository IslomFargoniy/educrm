<?php

use App\Support\PersonName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, string> table => full name column
     */
    private array $tables = ['students' => 'full_name', 'leads' => 'full_name', 'users' => 'name'];

    public function up(): void
    {
        foreach ($this->tables as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->string('first_name')->nullable()->after($column);
                $blueprint->string('last_name')->nullable()->after('first_name');
            });

            DB::table($table)->select(['id', $column])->orderBy('id')->each(function (object $row) use ($table, $column) {
                [$firstName, $lastName] = PersonName::split($row->{$column});
                DB::table($table)->where('id', $row->id)->update(['first_name' => $firstName, 'last_name' => $lastName]);
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->tables) as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn(['first_name', 'last_name']));
        }
    }
};
