<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('consented_at')->nullable()->after('remember_token');
            $table->string('consent_source', 20)->nullable()->after('consented_at');
            $table->string('consent_version', 20)->nullable()->after('consent_source');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['consented_at', 'consent_source', 'consent_version']);
        });
    }
};
