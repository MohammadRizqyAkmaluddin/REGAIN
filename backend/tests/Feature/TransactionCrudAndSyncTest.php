<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Services\UserOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionCrudAndSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_crud_offline_sync_and_cross_user_authorization(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        app(UserOnboardingService::class)->initializeUser($userA, '500000');
        app(UserOnboardingService::class)->initializeUser($userB, '500000');

        $foodCatA = $userA->categories()->where('slug', 'food-beverage')->firstOrFail();
        $coffeeSubA = $foodCatA->subcategories()->where('slug', 'kopi')->firstOrFail();

        // Create transaction
        $createRes = $this->actingAs($userA, 'sanctum')->postJson('/api/v1/transactions', [
            'type' => 'expense',
            'category_id' => $foodCatA->id,
            'subcategory_id' => $coffeeSubA->id,
            'amount' => 28000,
            'description' => 'Kopi susu gula aren',
            'transaction_date' => '2026-11-25',
            'transaction_time' => '08:15',
        ])->assertCreated();

        $txId = $createRes->json('data.id');

        // User B must be forbidden from viewing, editing, or deleting User A's transaction
        $this->actingAs($userB, 'sanctum')->getJson("/api/v1/transactions/{$txId}")->assertForbidden();
        $this->actingAs($userB, 'sanctum')->putJson("/api/v1/transactions/{$txId}", ['amount' => 1000])->assertForbidden();
        $this->actingAs($userB, 'sanctum')->deleteJson("/api/v1/transactions/{$txId}")->assertForbidden();

        // User A can search and update
        $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/transactions?search=gula+aren')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($userA, 'sanctum')
            ->putJson("/api/v1/transactions/{$txId}", ['amount' => 30000])
            ->assertOk()
            ->assertJsonPath('data.amount', '30000.00');

        // Offline batch sync deduplicates by client_uuid
        $uuid = (string) Str::uuid();
        $payload = [
            'transactions' => [[
                'client_uuid' => $uuid,
                'type' => 'expense',
                'category_id' => $foodCatA->id,
                'subcategory_id' => $coffeeSubA->id,
                'amount' => 25000,
                'description' => 'Transaksi offline',
                'transaction_date' => '2026-11-25',
                'transaction_time' => '10:00',
            ]],
        ];

        $this->actingAs($userA, 'sanctum')->postJson('/api/v1/transactions/sync', $payload)
            ->assertOk()
            ->assertJsonPath('results.0.status', 'synced')
            ->assertJsonPath('results.0.deduplicated', false);

        // Sending exact same client_uuid again must deduplicate
        $this->actingAs($userA, 'sanctum')->postJson('/api/v1/transactions/sync', $payload)
            ->assertOk()
            ->assertJsonPath('results.0.status', 'synced')
            ->assertJsonPath('results.0.deduplicated', true);

        $this->assertSame(1, Transaction::where('user_id', $userA->id)->where('client_uuid', $uuid)->count());

        // Delete works for owner
        $this->actingAs($userA, 'sanctum')->deleteJson("/api/v1/transactions/{$txId}")->assertOk();
    }
}
