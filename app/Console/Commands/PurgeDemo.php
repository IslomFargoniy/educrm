<?php

namespace App\Console\Commands;

use App\Services\DemoSimulator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:purge {--force : Skip the confirmation}')]
#[Description('Delete the "Demo filial" branch, its students, finance records and demo accounts')]
class PurgeDemo extends Command
{
    public function handle(DemoSimulator $simulator): int
    {
        if (! $this->option('force') && ! $this->confirm('Delete all demo data?')) {
            return self::FAILURE;
        }

        $result = $simulator->purge();
        $this->info("Deleted: {$result['students']} students, {$result['transactions']} transactions, {$result['users']} demo accounts.");

        return self::SUCCESS;
    }
}
