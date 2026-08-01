<?php

namespace Tests\Feature;

use App\Models\DisbursementMethod;
use App\Models\ExpenseArticle;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\AdvanceService;
use App\Services\ExpenseService;
use App\Services\WalletService;
use Database\Seeders\DictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceCanonTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected ExpenseArticle $article;

    protected DisbursementMethod $method;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DictionarySeeder::class);

        $this->user = User::factory()->create();
        $this->article = ExpenseArticle::query()->firstOrFail();
        $this->method = DisbursementMethod::query()->firstOrFail();
        $this->supplier = Supplier::create([
            'user_id' => $this->user->id,
            'name' => 'Поставщик Тест',
        ]);
    }

    public function test_topup_increases_balance(): void
    {
        app(WalletService::class)->topUp($this->user, [
            'amount' => 1000,
            'disbursement_method_id' => $this->method->slug,
        ]);

        $this->assertSame(100000, (int) $this->user->wallet()->value('balance_minor'));
        $this->assertDatabaseHas('wallet_transactions', [
            'type' => WalletTransaction::TYPE_TOPUP,
            'amount_minor' => 100000,
        ]);
    }

    public function test_issue_is_idempotent(): void
    {
        $advances = app(AdvanceService::class);
        $advance = $advances->create($this->user, [
            'title' => 'Каршеринг',
            'amount' => 20000,
            'status_id' => 'pending',
            'disbursement_method_id' => $this->method->slug,
        ]);

        $advances->update($advance, [
            'status_id' => 'issued',
            'disbursement_method_id' => $this->method->slug,
            'amount' => 20000,
        ]);

        $advance = $advance->fresh();
        $advances->creditIssue($advance);
        $advances->creditIssue($advance->fresh());

        $this->assertSame(1, WalletTransaction::where('advance_id', $advance->id)->where('type', 'issue')->count());
        $this->assertSame(2000000, (int) $this->user->wallet()->value('balance_minor'));
    }

    public function test_issue_requires_amount_and_method(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(AdvanceService::class)->create($this->user, [
            'title' => 'Без суммы',
            'amount' => 0,
            'status_id' => 'issued',
            'disbursement_method_id' => $this->method->slug,
        ]);
    }

    public function test_expense_on_advance_reduces_balance_and_autoclose(): void
    {
        $advances = app(AdvanceService::class);
        $expenses = app(ExpenseService::class);

        $advance = $advances->create($this->user, [
            'title' => 'Закупка',
            'amount' => 1000,
            'status_id' => 'issued',
            'disbursement_method_id' => $this->method->slug,
        ]);

        $this->assertSame(100000, (int) $this->user->wallet()->value('balance_minor'));

        $expenses->addExpense($this->user, [
            'amount' => 1000,
            'article_id' => $this->article->slug,
            'supplier_id' => $this->supplier->id,
            'description' => 'Всё',
        ], $advance);

        $this->assertSame(0, (int) $this->user->wallet()->value('balance_minor'));
        $this->assertSame('closed', $advance->fresh('status')->status->slug);
    }

    public function test_free_expense_from_topup(): void
    {
        app(WalletService::class)->topUp($this->user, [
            'amount' => 500,
            'disbursement_method_id' => $this->method->slug,
        ]);
        app(ExpenseService::class)->addExpense($this->user, [
            'amount' => 200,
            'article_id' => $this->article->slug,
            'supplier_id' => $this->supplier->id,
        ]);

        $this->assertSame(30000, (int) $this->user->wallet()->value('balance_minor'));
    }

    public function test_release_does_not_change_balance(): void
    {
        $advances = app(AdvanceService::class);
        $advance = $advances->create($this->user, [
            'title' => 'Частично',
            'amount' => 1000,
            'status_id' => 'issued',
            'disbursement_method_id' => $this->method->slug,
        ]);

        app(ExpenseService::class)->addExpense($this->user, [
            'amount' => 400,
            'article_id' => $this->article->slug,
            'supplier_id' => $this->supplier->id,
        ], $advance);

        $before = (int) $this->user->wallet()->value('balance_minor');
        $advances->releaseToFree($advance->fresh());
        $after = (int) $this->user->wallet()->value('balance_minor');

        $this->assertSame($before, $after);
        $this->assertSame(60000, $after);
        $this->assertSame('closed', $advance->fresh('status')->status->slug);
        $this->assertDatabaseHas('wallet_transactions', [
            'advance_id' => $advance->id,
            'type' => WalletTransaction::TYPE_RELEASE,
            'amount_minor' => 0,
        ]);
    }

    public function test_return_and_writeoff_decrease_balance(): void
    {
        $advances = app(AdvanceService::class);

        $a1 = $advances->create($this->user, [
            'title' => 'Return',
            'amount' => 1000,
            'status_id' => 'issued',
            'disbursement_method_id' => $this->method->slug,
        ]);
        $advances->returnToBoss($a1);
        $this->assertSame(0, (int) $this->user->wallet()->value('balance_minor'));

        $a2 = $advances->create($this->user, [
            'title' => 'Writeoff',
            'amount' => 500,
            'status_id' => 'issued',
            'disbursement_method_id' => $this->method->slug,
        ]);
        $advances->writeOffUnknown($a2);
        $this->assertSame(0, (int) $this->user->wallet()->value('balance_minor'));
    }

    public function test_http_topup_and_issue(): void
    {
        $this->actingAs($this->user)
            ->post('/wallet/topups', [
                'amount' => 100,
                'disbursement_method_id' => $this->method->slug,
            ])
            ->assertRedirect();

        $this->actingAs($this->user)
            ->post('/advances', [
                'title' => 'HTTP',
                'amount' => 250,
                'status_id' => 'issued',
                'disbursement_method_id' => $this->method->slug,
            ])
            ->assertRedirect();

        $this->assertSame(35000, (int) $this->user->wallet()->value('balance_minor'));
    }

    public function test_issue_creates_wallet_if_missing(): void
    {
        $this->user->wallet()->delete();
        $this->assertNull($this->user->fresh()->wallet);

        $advance = app(AdvanceService::class)->create($this->user, [
            'title' => 'No wallet yet',
            'amount' => 100,
            'status_id' => 'issued',
            'disbursement_method_id' => $this->method->slug,
        ]);

        $this->assertNotNull($this->user->fresh()->wallet);
        $this->assertSame(10000, (int) $this->user->wallet()->value('balance_minor'));
        $this->assertSame('issued', $advance->status->slug);
    }
}
