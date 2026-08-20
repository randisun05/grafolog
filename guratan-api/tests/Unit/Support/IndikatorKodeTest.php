<?php

namespace Tests\Unit\Support;

use App\Support\IndikatorKode;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IndikatorKodeTest extends TestCase
{
    #[DataProvider('kodeProvider')]
    public function test_parses_posisi_and_varian(string $kode, ?int $expectedPosisi, ?string $expectedVarian): void
    {
        $result = IndikatorKode::parse($kode);

        $this->assertSame($expectedPosisi, $result['posisi']);
        $this->assertSame($expectedVarian, $result['varian']);
    }

    public static function kodeProvider(): array
    {
        return [
            'apostrophe, no varian' => ["01-1'", 1, null],
            'lettered varian a' => ['01-3a', 3, 'a'],
            'lettered varian b' => ['01-3b', 3, 'b'],
            'two-digit posisi' => ['02-10a', 10, 'a'],
            'zero-padded posisi' => ['02-01a', 1, 'a'],
            'single-digit aspek, no leading zero' => ['4-10', 10, null],
            'single-digit aspek with varian' => ['5-2a', 2, 'a'],
            'space separator instead of dash' => ['31 5b', 5, 'b'],
            'dup suffix does not become a varian' => ['35-6-dup3', 6, null],
            'dup suffix on lettered varian keeps varian' => ['02-9b-dup2', 9, 'b'],
            'unrecognized pattern returns nulls' => ['tidak-valid-sama-sekali', null, null],
        ];
    }
}
