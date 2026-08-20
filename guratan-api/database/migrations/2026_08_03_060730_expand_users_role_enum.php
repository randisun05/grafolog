<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * MySQL (real DB) uses raw SQL - doctrine/dbal (needed for Blueprint's
     * ->change() on MySQL enum columns) isn't installed, see the original
     * role column in 2026_07_25_153636_add_role_to_users_table.php. SQLite
     * (test DB, phpunit.xml) goes through Schema::table()->change() instead,
     * which Laravel's SQLite grammar handles natively (table rebuild) without
     * doctrine/dbal.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['user', 'grafolog', 'administrator', 'supervisor'])
                    ->default('user')->change();
            });

            return;
        }

        DB::statement(
            "ALTER TABLE users MODIFY COLUMN role ENUM('user', 'grafolog', 'administrator', 'supervisor') NOT NULL DEFAULT 'user'"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['user', 'grafolog'])->default('user')->change();
            });

            return;
        }

        DB::statement(
            "ALTER TABLE users MODIFY COLUMN role ENUM('user', 'grafolog') NOT NULL DEFAULT 'user'"
        );
    }
};
