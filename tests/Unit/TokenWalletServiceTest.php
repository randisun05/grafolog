<?php

namespace Tests\Unit;

use App\Models\TokenPurchase;
use App\Models\User;
use App\Services\TokenWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TokenWalletServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_adds_to_balance_and_records_ledger_entry(): void
    {
        $user = User::factory()->create(['role' => 'grafolog', 'token_balance' => 5]);
        $wallet = new TokenWalletService;

        $entry = $wallet->credit($user, 10);

        $this->assertSame(15, $user->fresh()->token_balance);
        $this->assertSame('purchase', $entry->type);
        $this->assertSame(10, $entry->delta);
        $this->assertSame(15, $entry->balance_after);
    }

    public function test_debit_subtracts_from_balance_and_records_ledger_entry(): void
    {
        $user = User::factory()->create(['role' => 'grafolog', 'token_balance' => 10]);
        $wallet = new TokenWalletService;

        $entry = $wallet->debit($user, 3);

        $this->assertSame(7, $user->fresh()->token_balance);
        $this->assertSame('consumption', $entry->type);
        $this->assertSame(-3, $entry->delta);
        $this->assertSame(7, $entry->balance_after);
    }

    public function test_debit_throws_402_when_balance_insufficient(): void
    {
        $user = User::factory()->create(['role' => 'grafolog', 'token_balance' => 2]);
        $wallet = new TokenWalletService;

        try {
            $wallet->debit($user, 5);
            $this->fail('Expected HttpException was not thrown.');
        } catch (HttpException $e) {
            $this->assertSame(402, $e->getStatusCode());
        }

        $this->assertSame(2, $user->fresh()->token_balance);
        $this->assertDatabaseCount('token_ledger_entries', 0);
    }

    public function test_refs_are_stored_on_the_ledger_entry(): void
    {
        $user = User::factory()->create(['role' => 'grafolog', 'token_balance' => 0]);
        $purchase = TokenPurchase::create([
            'user_id' => $user->id, 'quantity' => 5, 'unit_price' => 1000, 'base_amount' => 5000,
            'amount' => 5000, 'status' => 'paid', 'invoice_number' => 'TOKEN-TEST-1',
        ]);
        $wallet = new TokenWalletService;

        $entry = $wallet->credit($user, 5, ['token_purchase_id' => $purchase->id]);

        $this->assertSame($purchase->id, $entry->token_purchase_id);
    }
}
