<?php

namespace Tests\Feature\Api;

use App\Models\ContentBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_content_endpoint_requires_no_auth(): void
    {
        ContentBlock::create(['key' => 'landing_eyebrow', 'value' => 'Contoh Eyebrow']);

        $response = $this->getJson('/api/content');

        $response->assertOk()->assertJsonPath('landing_eyebrow', 'Contoh Eyebrow');
    }

    public function test_returns_flat_key_value_shape(): void
    {
        ContentBlock::create(['key' => 'landing_eyebrow', 'value' => 'A']);
        ContentBlock::create(['key' => 'landing_tagline', 'value' => 'B']);

        $response = $this->getJson('/api/content');

        $response->assertOk()->assertExactJson(['landing_eyebrow' => 'A', 'landing_tagline' => 'B']);
    }
}
