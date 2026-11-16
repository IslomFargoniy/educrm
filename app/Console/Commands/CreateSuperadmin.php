<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Support\Phone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('app:superadmin {phone} {name=Superadmin} {--password=}')]
#[Description('Create (or reset) a superadmin account')]
class CreateSuperadmin extends Command
{
    public function handle(): int
    {
        $this->callSilently('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
        $phone = Phone::normalize($this->argument('phone'));

        if (! Phone::isValid($phone)) {
            $this->error('Phone must look like +998901234567');

            return self::FAILURE;
        }

        $password = $this->option('password') ?: Str::password(10, symbols: false);
        $user = User::query()->updateOrCreate(['phone' => $phone], ['name' => $this->argument('name'), 'password' => $password, 'is_active' => true]);
        $user->assignRole(Role::Superadmin->value);
        $this->line("Superadmin: {$phone} / {$password}");

        return self::SUCCESS;
    }
}
