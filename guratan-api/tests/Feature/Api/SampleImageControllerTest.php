<?php

namespace Tests\Feature\Api;

use App\Models\HandwritingSample;
use App\Models\Project;
use App\Models\SampleImage;
use App\Models\User;
use App\Support\MinimalPdf;
use App\Support\PdfScan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SampleImageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(SampleImage::DISK);
    }

    private function clientSample(): array
    {
        $client = User::factory()->create(['role' => 'user']);
        $project = Project::create(['source' => 'client', 'created_by' => $client->id]);
        $sample = HandwritingSample::create([
            'project_id' => $project->id, 'user_id' => $client->id, 'created_by' => $client->id,
            'tier' => 'comprehensive', 'status' => 'pending',
        ]);

        return [$client, $sample];
    }

    private function pdf(string $name = 'scan.pdf', float $w = 210, float $h = 297): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, MinimalPdf::make('Contoh', $w, $h));
    }

    private function upload(User $user, HandwritingSample $sample, int $count = 1)
    {
        $files = array_map(fn ($i) => $this->pdf("scan{$i}.pdf"), range(1, $count));

        return $this->actingAs($user, 'sanctum')->post("/api/samples/{$sample->id}/images", ['images' => $files], ['Accept' => 'application/json']);
    }

    public function test_owner_can_upload_images_to_private_disk(): void
    {
        [$client, $sample] = $this->clientSample();

        $this->upload($client, $sample, 2)->assertCreated()->assertJsonCount(2)->assertJsonMissingPath('0.path');

        $image = SampleImage::first();
        Storage::disk(SampleImage::DISK)->assertExists($image->path);
        $this->assertStringStartsWith("samples/{$sample->id}/", $image->path);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'unggah_foto_tulisan', 'actor_user_id' => $client->id]);
    }

    public function test_pdf_page_size_is_recorded_and_must_be_a4_or_f4(): void
    {
        [$client, $sample] = $this->clientSample();
        $post = fn ($file) => $this->actingAs($client, 'sanctum')
            ->post("/api/samples/{$sample->id}/images", ['images' => [$file]], ['Accept' => 'application/json']);

        $post($this->pdf('a4.pdf'))->assertCreated()
            ->assertJsonPath('0.page_width_mm', 210)->assertJsonPath('0.page_height_mm', 297)->assertJsonPath('0.page_count', 1);
        $post($this->pdf('f4.pdf', 215, 330))->assertCreated();
        $post($this->pdf('landscape.pdf', 297, 210))->assertCreated(); // A4 mendatar
        // Hasil foto yang dibungkus PDF: halaman 90x160 mm (rasio layar ponsel) ditolak.
        $post($this->pdf('foto.pdf', 90, 160))->assertStatus(422)->assertJsonValidationErrors('images.0');
    }

    public function test_photos_and_fake_pdfs_are_rejected(): void
    {
        [$client, $sample] = $this->clientSample();
        $post = fn ($file) => $this->actingAs($client, 'sanctum')
            ->post("/api/samples/{$sample->id}/images", ['images' => [$file]], ['Accept' => 'application/json']);

        $post(UploadedFile::fake()->image('foto.jpg', 3000, 2000))->assertStatus(422)->assertJsonValidationErrors('images.0');
        $post(UploadedFile::fake()->createWithContent('palsu.pdf', 'bukan pdf sungguhan'))->assertStatus(422);
        $this->assertDatabaseCount('sample_images', 0);
    }

    public function test_pdf_with_active_content_is_rejected(): void
    {
        [$client, $sample] = $this->clientSample();
        $evil = UploadedFile::fake()->createWithContent('scan.pdf', MinimalPdf::make('x')."\n1 0 obj << /S /JavaScript /JS (app.alert(1)) >> endobj");
        $this->actingAs($client, 'sanctum')->post("/api/samples/{$sample->id}/images", ['images' => [$evil]], ['Accept' => 'application/json'])
            ->assertStatus(422);
        $this->assertDatabaseCount('sample_images', 0);
    }

    public function test_pdf_scan_reads_compressed_object_streams(): void
    {
        $page = '<< /Type /Page /MediaBox [0 0 595.28 841.89] >>';
        $pdf = "%PDF-1.5\n1 0 obj\n<< /Type /ObjStm /N 1 /First 4 /Length 99 >>\nstream\n".gzcompress("0 0 \n{$page}")."\nendstream\nendobj\n%%EOF";
        $path = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($path, $pdf);

        $this->assertSame(['valid' => true, 'pages' => 1, 'width_mm' => 210, 'height_mm' => 297, 'paper' => 'A4', 'unsafe' => false], PdfScan::inspect($path));
    }

    public function test_stranger_cannot_upload_or_view(): void
    {
        [$client, $sample] = $this->clientSample();
        $this->upload($client, $sample);
        $image = SampleImage::first();
        $stranger = User::factory()->create(['role' => 'user']);

        $this->upload($stranger, $sample)->assertForbidden();
        $this->actingAs($stranger, 'sanctum')->get("/api/samples/{$sample->id}/images/{$image->id}")->assertForbidden();
    }

    public function test_assigned_grafolog_and_admin_can_view_with_audit_log(): void
    {
        [$client, $sample] = $this->clientSample();
        $this->upload($client, $sample);
        $image = SampleImage::first();
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $admin = User::factory()->create(['role' => 'administrator']);
        $sample->assignment()->create(['grafolog_id' => $grafolog->id, 'assigned_by' => $admin->id, 'status' => 'assigned']);

        $this->actingAs($grafolog, 'sanctum')->get("/api/samples/{$sample->id}/images/{$image->id}")->assertOk();
        $this->actingAs($admin, 'sanctum')->get("/api/samples/{$sample->id}/images/{$image->id}")->assertOk();

        $this->assertDatabaseHas('audit_logs', ['aksi' => 'lihat_foto_tulisan', 'actor_user_id' => $grafolog->id]);
    }

    public function test_image_of_other_sample_returns_404(): void
    {
        [$client, $sample] = $this->clientSample();
        [$client2, $sample2] = $this->clientSample();
        $this->upload($client2, $sample2);
        $foreign = SampleImage::first();

        $this->actingAs($client, 'sanctum')->get("/api/samples/{$sample->id}/images/{$foreign->id}")->assertNotFound();
    }

    public function test_cannot_upload_after_report_completed(): void
    {
        [$client, $sample] = $this->clientSample();
        $sample->update(['status' => 'completed']);

        $this->upload($client, $sample)->assertForbidden();
    }

    public function test_max_images_per_sample_enforced(): void
    {
        [$client, $sample] = $this->clientSample();
        $this->upload($client, $sample, 5)->assertCreated();
        $this->upload($client, $sample, 5)->assertCreated();

        $this->upload($client, $sample, 1)->assertUnprocessable();
    }

    public function test_uploader_can_delete_image(): void
    {
        [$client, $sample] = $this->clientSample();
        $this->upload($client, $sample);
        $image = SampleImage::first();

        $this->actingAs($client, 'sanctum')->deleteJson("/api/samples/{$sample->id}/images/{$image->id}")->assertNoContent();

        Storage::disk(SampleImage::DISK)->assertMissing($image->path);
        $this->assertDatabaseCount('sample_images', 0);
    }
}
