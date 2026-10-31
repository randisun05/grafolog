<?php

namespace Tests\Feature\Api\Games;

use App\Models\Aspek;
use App\Models\Indikator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

class TebakKepribadianControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    private function seedFourAspekWithIndikator(): Indikator
    {
        // seedMinimalAspek() menangani field wajib Sindrom (polaritas_inferred
        // dst) + ScoringRuleBand dengan benar - dipakai apa adanya, bukan
        // Sindrom::create() manual yang gampang lupa field wajib.
        $this->seedMinimalAspek(4);
        $aspekList = Aspek::orderBy('id')->get();

        return Indikator::create([
            'kode' => '01-1', 'aspek_id' => $aspekList->first()->id, 'nama' => 'Indikator uji',
            'keterangan' => 'Huruf besar dan miring ke kanan.',
        ]);
    }

    public function test_question_does_not_leak_correct_answer(): void
    {
        $this->seedFourAspekWithIndikator();

        $response = $this->getJson('/api/games/tebak-kepribadian/question');

        $response->assertOk()->assertJsonCount(4, 'choices');
        $this->assertArrayNotHasKey('correct', $response->json());
        $this->assertArrayNotHasKey('aspek_id', $response->json());
    }

    public function test_answer_correctly_identifies_correct_and_incorrect(): void
    {
        $indikator = $this->seedFourAspekWithIndikator();
        $wrongAspek = Aspek::where('id', '!=', $indikator->aspek_id)->first();

        $this->postJson('/api/games/tebak-kepribadian/answer', [
            'indikator_id' => $indikator->id,
            'aspek_id' => $indikator->aspek_id,
        ])->assertOk()->assertJsonPath('correct', true);

        $this->postJson('/api/games/tebak-kepribadian/answer', [
            'indikator_id' => $indikator->id,
            'aspek_id' => $wrongAspek->id,
        ])->assertOk()->assertJsonPath('correct', false);
    }

    public function test_answer_returns_neutral_explanation_not_a_report_narasi(): void
    {
        $indikator = $this->seedFourAspekWithIndikator();

        $response = $this->postJson('/api/games/tebak-kepribadian/answer', [
            'indikator_id' => $indikator->id,
            'aspek_id' => $indikator->aspek_id,
        ]);

        $response->assertJsonPath('penjelasan', 'umum');
    }
}
