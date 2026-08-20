<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Bootstrap Administrator provisioning (MGA pivot, agreed decision 2026-08-01):
 * the FIRST admin account comes from here, not self-registration -
 * /auth/register only ever accepts role=user|grafolog (see RegisterRequest).
 * Every subsequent admin/supervisor account is created by an already-logged-in
 * Administrator via POST /api/admin/users, not by running this again.
 *
 * Silently skips if ADMIN_EMAIL isn't set in .env - never creates an account
 * with a guessable default password.
 */
class AdministratorSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('admin.bootstrap_admin.email');
        $password = config('admin.bootstrap_admin.password');

        if (! $email || ! $password) {
            Log::info('AdministratorSeeder: ADMIN_EMAIL/ADMIN_PASSWORD not set, skipping.');

            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => config('admin.bootstrap_admin.name', 'Administrator'),
                'password' => $password,
                'role' => 'administrator',
            ]
        );
    }
}
