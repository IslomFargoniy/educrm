<?php

namespace App\Console\Commands;

use App\Services\DemoSimulator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:simulate {--students=100 : Number of students} {--months=3 : Months of history up to yesterday} {--seed=2026 : Random seed}')]
#[Description('Fill a separate "Demo filial" branch with a simulated history (remove it with demo:purge)')]
class SimulateDemo extends Command
{
    public function handle(DemoSimulator $simulator): int
    {
        if ($simulator->exists()) {
            $this->error('Demo branch already exists. Run demo:purge first.');

            return self::FAILURE;
        }

        $this->info('Simulating, this takes a few minutes...');
        $stats = $simulator->simulate((int) $this->option('students'), (int) $this->option('months'), (int) $this->option('seed'));

        $this->table(['Metric', 'Value'], collect($stats)->map(fn (int $value, string $key) => [$key, number_format($value, 0, '.', ' ')])->values()->all());

        return self::SUCCESS;
    }
}
