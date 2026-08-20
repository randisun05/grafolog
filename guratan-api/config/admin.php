<?php

/*
|--------------------------------------------------------------------------
| Bootstrap Administrator (MGA pivot, provisioning decision 2026-08-01)
|--------------------------------------------------------------------------
|
| Read by database/seeders/AdministratorSeeder.php to create the FIRST
| admin account. Leave ADMIN_EMAIL/ADMIN_PASSWORD unset to skip seeding -
| never falls back to a guessable default. Every admin/supervisor account
| after this one is created via POST /api/admin/users by an already-logged-in
| Administrator, not by re-running this seeder.
|
*/

return [
    'bootstrap_admin' => [
        'name' => env('ADMIN_NAME', 'Administrator'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],
];
