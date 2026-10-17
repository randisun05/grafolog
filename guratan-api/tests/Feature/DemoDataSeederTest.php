<?php

namespace Tests\Feature;

use App\Models\HandwritingSample;
use App\Models\PersonalityReport;
use App\Models\SampleImage;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_every_workflow_stage_once(): void
    {
        Storage::fake(SampleImage::DISK);
        $this->seed(DatabaseSeeder::class);

        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(13, User::where('email', 'like', '%@guratan.test')->orWhere('email', 'like', '%@majujaya.test')->count());
        $this->assertSame(8, HandwritingSample::count());
        $this->assertSame(3, PersonalityReport::where('review_status', 'disetujui')->count());
        $this->assertSame(1, PersonalityReport::where('review_status', 'menunggu_review')->count());
        $this->assertSame(1, PersonalityReport::where('review_status', 'perlu_revisi')->count());
        $this->assertSame(8, SampleImage::count());

        $citraReport = PersonalityReport::whereHas('sample.user', fn ($q) => $q->where('email', 'citra@guratan.test'))->first();
        $this->assertNotNull($citraReport->sample->consultation);
        $this->assertStringNotContainsString('narasi tidak tersedia', json_encode($citraReport->data['sindrom'][0]));

        $this->postJson('/api/auth/login', ['email' => 'budi@guratan.test', 'password' => DemoDataSeeder::PASSWORD])->assertOk();
    }

    public function test_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(\RuntimeException::class);
        $this->app->call([new DemoDataSeeder, 'run']);
    }
}
