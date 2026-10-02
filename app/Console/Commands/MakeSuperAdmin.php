<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

#[Signature('app:make-super-admin {email : Email of the user to promote (created if missing)} {--name= : Name for a new user} {--password= : Password for a new user} {--revoke : Remove super-admin access instead}')]
#[Description('Grant (or revoke) platform super-admin access, which can create and set up hotels')]
class MakeSuperAdmin extends Command
{
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if ($this->option('revoke')) {
            if ($user === null) {
                $this->error("No user with email [{$email}].");

                return self::FAILURE;
            }

            $user->forceFill(['is_super_admin' => false])->save();
            $this->info("Super-admin access revoked for {$email}.");

            return self::SUCCESS;
        }

        if ($user === null) {
            $name = (string) ($this->option('name') ?: $this->ask('Name'));
            $password = (string) ($this->option('password') ?: $this->secret('Password (min 8 characters)'));

            if ($name === '' || strlen($password) < 8) {
                $this->error('A name and a password of at least 8 characters are required to create a user.');

                return self::FAILURE;
            }

            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
            ]);
            $user->forceFill(['email_verified_at' => now()]);
        }

        $user->forceFill(['is_super_admin' => true])->save();
        $this->info("{$email} is now a super-admin. Sign in and open /admin/hotels.");

        return self::SUCCESS;
    }
}
