<?php

namespace Tests\Feature\Api;

use App\Models\CvField;
use App\Models\CvGroup;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Alur klien individu tahap 1: katalog publik, mode online/offline, persyaratan, jadwal (2026-10-30). */
class ClientOrderStageOneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    public function test_public_catalog_and_detail_expose_modes_and_requirements(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $p = Product::where('kode', 'konseling')->first();
        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/products/{$p->id}", [
            'modes' => ['online', 'offline'],
            'requirements' => [['judul' => 'KTP', 'keterangan' => 'Untuk verifikasi'], ['judul' => 'Tujuan konseling']],
        ])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/products/konseling')->assertOk()
            ->assertJsonPath('modes', ['online', 'offline'])->assertJsonPath('requirements.0.judul', 'KTP');
        $this->getJson('/api/products/tidak-ada')->assertNotFound();
        $this->getJson('/api/products')->assertOk()->assertJsonFragment(['kode' => 'konseling']);
        // Default: online saja.
        $this->getJson('/api/products/master')->assertJsonPath('modes', ['online']);

        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/products/{$p->id}", ['modes' => ['tatap-muka']])->assertStatus(422);
    }

    public function test_order_stores_mode_schedule_and_validates_offline_rules(): void
    {
        $p = Product::where('kode', 'konseling')->first();
        $p->update(['modes' => ['online', 'offline']]);
        $client = User::factory()->create(['role' => 'user']);
        $base = ['product_id' => $p->id];
        $post = fn (array $x) => $this->actingAs($client, 'sanctum')->postJson('/api/orders', $base + $x);

        $post(['delivery_mode' => 'offline'])->assertStatus(422); // offline tanpa lokasi
        $post(['delivery_mode' => 'online', 'scheduled_for' => now()->subDay()->toDateString()])->assertStatus(422); // tanggal lampau
        $post(['delivery_mode' => 'online', 'scheduled_for' => now()->addDays(5)->toDateString(), 'upload_deadline' => now()->addDays(9)->toDateString()])->assertStatus(422); // deadline setelah jadwal

        $res = $post(['delivery_mode' => 'offline', 'location_note' => 'Bandung, kafe X', 'scheduled_for' => now()->addDays(7)->toDateString()])
            ->assertCreated();
        $res->assertJsonPath('delivery_mode', 'offline')->assertJsonPath('location_note', 'Bandung, kafe X');

        $online = $post(['delivery_mode' => 'online', 'location_note' => 'diabaikan'])->assertCreated();
        $online->assertJsonPath('location_note', null);

        $p->update(['modes' => ['online']]);
        $post(['delivery_mode' => 'offline', 'location_note' => 'X'])->assertStatus(422);
    }

    public function test_handwriting_product_gets_default_upload_deadline_and_default_mode(): void
    {
        $client = User::factory()->create(['role' => 'user']);
        $master = Product::where('kode', 'master')->first();
        $res = $this->actingAs($client, 'sanctum')->postJson('/api/orders', ['product_id' => $master->id, 'upload_deadline' => now()->addDays(2)->toDateString()])->assertCreated();
        $res->assertJsonPath('delivery_mode', 'online')->assertJsonPath('upload_deadline', now()->addDays(2)->toDateString());
    }

    public function test_required_cv_fields_block_ordering_until_filled(): void
    {
        $g = CvGroup::create(['name' => 'P']);
        $f = CvField::create(['cv_group_id' => $g->id, 'key' => 'nama_lengkap', 'label' => 'Nama lengkap', 'type' => 'text', 'applies_to' => 'client', 'required' => true]);
        $client = User::factory()->create(['role' => 'user']);
        $product = Product::where('kode', 'konseling')->first();

        $this->actingAs($client, 'sanctum')->getJson('/api/cv')->assertJsonPath('missing_required', 1);
        $this->actingAs($client, 'sanctum')->postJson('/api/orders', ['product_id' => $product->id])->assertStatus(422);

        $this->actingAs($client, 'sanctum')->putJson('/api/cv', ['values' => ['nama_lengkap' => 'Budi']])->assertOk();
        $this->actingAs($client, 'sanctum')->getJson('/api/cv')->assertJsonPath('missing_required', 0);
        $this->actingAs($client, 'sanctum')->postJson('/api/orders', ['product_id' => $product->id])->assertCreated();

        // Variabel wajib untuk grafolog tidak menghalangi klien.
        $f->update(['applies_to' => 'grafolog']);
        $other = User::factory()->create(['role' => 'user']);
        $this->actingAs($other, 'sanctum')->postJson('/api/orders', ['product_id' => $product->id])->assertCreated();
    }
}
