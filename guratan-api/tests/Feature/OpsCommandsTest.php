<?php

namespace Tests\Feature;

use App\Models\HandwritingSample;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Perintah operasional: preflight, cadangan, privatisasi gambar lama (2026-10-19). */
class OpsCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflight_fails_on_unsafe_production_config_and_passes_core_checks(): void
    {
        config(['app.debug' => true, 'mail.default' => 'log']);
        $this->artisan('app:preflight')->expectsOutputToContain('GAGAL')->assertExitCode(1);

        config(['app.debug' => false, 'queue.default' => 'database', 'sanctum.expiration' => 100]);
        User::factory()->create(['role' => 'administrator', 'email' => 'real@perusahaan.id']);
        $this->artisan('app:preflight')->expectsOutputToContain('Ringkasan: 0 gagal')->assertExitCode(0);
        $this->artisan('app:preflight', ['--strict' => true])->assertExitCode(1); // peringatan (mail log, DOKU, scheduler) dianggap gagal
    }

    public function test_privatize_moves_legacy_public_images_to_private_disk(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('public')->put('uploads/a.jpg', 'isi-gambar');
        $user = User::factory()->create();
        $s = HandwritingSample::create(['user_id' => $user->id, 'created_by' => $user->id, 'tier' => 'rapid', 'status' => 'completed', 'image_path' => 'uploads/a.jpg']);
        HandwritingSample::create(['user_id' => $user->id, 'created_by' => $user->id, 'tier' => 'rapid', 'status' => 'completed', 'image_path' => 'uploads/hilang.jpg']);

        $this->artisan('samples:privatize-legacy-images', ['--dry-run' => true])->expectsOutputToContain('Dipindahkan 1, tidak ditemukan di disk publik 1');
        Storage::disk('public')->assertExists('uploads/a.jpg');

        $this->artisan('samples:privatize-legacy-images')->assertExitCode(0);
        Storage::disk('public')->assertMissing('uploads/a.jpg');
        Storage::disk('local')->assertExists('uploads/a.jpg');
        $this->assertSame('isi-gambar', Storage::disk('local')->get('uploads/a.jpg'));
        $this->assertNotNull($s->fresh()->image_path);
    }

    public function test_db_backup_copies_sqlite_file_and_prunes_old(): void
    {
        Storage::fake('local');
        $tmp = tempnam(sys_get_temp_dir(), 'db').'.sqlite';
        file_put_contents($tmp, 'SQLITE-DATA');
        config(['database.connections.bk' => ['driver' => 'sqlite', 'database' => $tmp]]);
        foreach (['backups/db-20200101-000000.sqlite', 'backups/db-20200102-000000.sqlite'] as $old) {
            Storage::disk('local')->put($old, 'x');
        }

        $this->artisan('db:backup', ['--keep' => 2, '--connection' => 'bk'])->assertExitCode(0);
        $files = Storage::disk('local')->files('backups');
        $this->assertCount(2, $files);
        $this->assertNotContains('backups/db-20200101-000000.sqlite', $files);
        $newest = collect($files)->sort()->last();
        $this->assertSame('SQLITE-DATA', Storage::disk('local')->get($newest));
        unlink($tmp);
    }
}
