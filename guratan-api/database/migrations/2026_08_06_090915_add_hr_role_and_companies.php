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
     * MGA Fase 06: HR is a 5th role (Company/Candidate/Assignment work).
     * Enum widening uses the same driver-aware approach as
     * 2026_08_03_060730_expand_users_role_enum.php - raw SQL for real
     * MySQL (no doctrine/dbal), Schema::table()->change() for the sqlite
     * test DB.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['user', 'grafolog', 'administrator', 'supervisor', 'hr'])
                    ->default('user')->change();
            });
        } else {
            DB::statement(
                "ALTER TABLE users MODIFY COLUMN role ENUM('user', 'grafolog', 'administrator', 'supervisor', 'hr') NOT NULL DEFAULT 'user'"
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('role')
                ->constrained('companies')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['user', 'grafolog', 'administrator', 'supervisor'])
                    ->default('user')->change();
            });
        } else {
            DB::statement(
                "ALTER TABLE users MODIFY COLUMN role ENUM('user', 'grafolog', 'administrator', 'supervisor') NOT NULL DEFAULT 'user'"
            );
        }

        Schema::dropIfExists('companies');
    }
};
