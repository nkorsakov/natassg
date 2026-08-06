<?php

namespace Tests\Feature;

use App\Models\DisbursementMethod;
use App\Models\User;
use App\Services\WalletService;
use App\Support\PublicFinance;
use Database\Seeders\DictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PublicFinanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $assistant;

    protected DisbursementMethod $method;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DictionarySeeder::class);

        $this->assistant = User::factory()->create([
            'email' => 'nataliya@skydesk.local',
            'is_admin' => false,
            'name' => 'Наталия Я.',
            'role_title' => 'Личный помощник',
        ]);
        $this->method = DisbursementMethod::query()->firstOrFail();

        config([
            'skydesk.public_pin' => '4608',
            'skydesk.public_finance_user_email' => 'nataliya@skydesk.local',
            'skydesk.public_finance_user_id' => null,
        ]);
    }

    public function test_cashflow_gate_requires_pin(): void
    {
        $this->get('/cashflow')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Finance/Public', false)
                ->where('unlocked', false)
                ->where('finance', null)
            );
    }

    public function test_wrong_pin_rejected(): void
    {
        $this->from('/cashflow')
            ->post('/cashflow/unlock', ['pin' => '0000'])
            ->assertSessionHasErrors('pin');

        $this->assertFalse((bool) session(PublicFinance::SESSION_UNLOCKED));
    }

    public function test_correct_pin_unlocks_live_finance(): void
    {
        app(WalletService::class)->topUp($this->assistant, [
            'amount' => 1500,
            'disbursement_method_id' => $this->method->slug,
            'occurred_at' => '2026-08-01',
            'title' => 'Пополнение',
        ]);

        $this->post('/cashflow/unlock', ['pin' => '4608'])
            ->assertRedirect('/cashflow');

        $this->get('/cashflow')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Finance/Public', false)
                ->where('unlocked', true)
                ->where('finance.subject.name', 'Наталия Я.')
                ->where('finance.wallet.on_hand', 1500)
                ->has('finance.transactions', 1)
            );
    }

    public function test_lock_closes_access(): void
    {
        $this->post('/cashflow/unlock', ['pin' => '4608'])->assertRedirect('/cashflow');
        $this->assertTrue((bool) session(PublicFinance::SESSION_UNLOCKED));

        $this->post('/cashflow/lock')->assertRedirect('/cashflow');
        $this->assertFalse((bool) session(PublicFinance::SESSION_UNLOCKED));
    }
}
