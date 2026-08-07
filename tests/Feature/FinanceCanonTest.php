<?php

namespace Tests\Feature;

use App\Enums\AdvanceStatus;
use App\Models\DisbursementMethod;
use App\Models\ExpenseArticle;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\AdvanceService;
use App\Services\ExpenseService;
use App\Services\WalletService;
use App\Support\SkyDeskPresenter;
use Database\Seeders\DictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
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

    protected function fundAdvance(AdvanceService $advances, array $data = []): \App\Models\Advance
    {
        $advance = $advances->create($this->user, array_merge([
            'title' => 'Аванс',
            'amount' => 1000,
            'disbursement_method_id' => $this->method->slug,
        ], $data));
        $advances->approve($advance);
        $advances->receive($advance->fresh(), [
            'disbursement_method_id' => $this->method->slug,
            'issued_at' => '2026-08-01',
            'amount' => $data['amount'] ?? 1000,
        ]);

        return $advance->fresh();
    }

    public function test_wallet_income_increases_wallet_and_on_hand(): void
    {
        app(WalletService::class)->topUp($this->user, [
            'amount' => 1000,
            'disbursement_method_id' => $this->method->slug,
            'occurred_at' => '2026-07-15',
        ]);

        $this->assertSame(100000, (int) $this->user->wallet()->value('balance_minor'));
        $this->assertDatabaseHas('wallet_transactions', [
            'type' => WalletTransaction::TYPE_INCOME,
            'account' => WalletTransaction::ACCOUNT_WALLET,
            'amount_minor' => 100000,
        ]);
    }

    public function test_create_is_always_pending(): void
    {
        $advance = app(AdvanceService::class)->create($this->user, [
            'title' => 'Заявка',
            'amount' => 5000,
        ]);

        $this->assertSame(AdvanceStatus::Pending, $advance->statusEnum());
        $this->assertSame(0, WalletTransaction::query()->where('advance_id', $advance->id)->count());
    }

    public function test_approve_then_receive_credits_advance_account(): void
    {
        $advances = app(AdvanceService::class);
        $advance = $advances->create($this->user, [
            'title' => 'Каршеринг',
            'amount' => 20000,
            'disbursement_method_id' => $this->method->slug,
        ]);

        $advances->approve($advance);
        $this->assertSame(AdvanceStatus::Approved, $advance->fresh()->statusEnum());

        $advances->receive($advance->fresh(), [
            'disbursement_method_id' => $this->method->slug,
            'issued_at' => '2026-08-01',
        ]);

        $advance = $advance->fresh();
        $this->assertSame(AdvanceStatus::Reporting, $advance->statusEnum());
        $this->assertSame(2000000, $advances->remainingMinor($advance));
        $this->assertSame(0, (int) $this->user->wallet()->value('balance_minor'));
        $this->assertSame(1, WalletTransaction::where('advance_id', $advance->id)
            ->where('type', WalletTransaction::TYPE_INCOME)
            ->where('account', WalletTransaction::ACCOUNT_ADVANCE)
            ->count());
    }

    public function test_receive_from_pending_forbidden(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $advances = app(AdvanceService::class);
        $advance = $advances->create($this->user, [
            'title' => 'Рано',
            'amount' => 1000,
            'disbursement_method_id' => $this->method->slug,
        ]);

        $advances->receive($advance, [
            'disbursement_method_id' => $this->method->slug,
        ]);
    }

    public function test_pending_and_approved_show_zero_spent(): void
    {
        $advances = app(AdvanceService::class);
        $advance = $advances->create($this->user, [
            'title' => 'Ещё заявка',
            'amount' => 5000,
        ]);

        $presented = SkyDeskPresenter::advance($advance->fresh());
        $this->assertSame('pending', $presented['status_id']);
        $this->assertSame(0.0, (float) $presented['spent']);
        $this->assertSame(0.0, (float) $presented['remaining']);

        $advances->approve($advance);
        $presented = SkyDeskPresenter::advance($advance->fresh());
        $this->assertSame('approved', $presented['status_id']);
        $this->assertSame(0.0, (float) $presented['spent']);
    }

    public function test_expense_on_advance_and_autoclose(): void
    {
        $advances = app(AdvanceService::class);
        $expenses = app(ExpenseService::class);
        $advance = $this->fundAdvance($advances, ['amount' => 1000, 'title' => 'Закупка']);

        $expenses->addExpense($this->user, [
            'amount' => 1000,
            'article_id' => $this->article->slug,
            'supplier_id' => $this->supplier->id,
            'description' => 'Всё',
            'debit_account' => 'advance',
        ], $advance);

        $this->assertSame(0, $advances->remainingMinor($advance->fresh()));
        $this->assertSame(AdvanceStatus::Closed, $advance->fresh()->statusEnum());
    }

    public function test_unassigned_expense_reduces_on_hand_not_wallet(): void
    {
        app(WalletService::class)->topUp($this->user, [
            'amount' => 500,
            'disbursement_method_id' => $this->method->slug,
        ]);
        app(ExpenseService::class)->addExpense($this->user, [
            'amount' => 200,
            'article_id' => $this->article->slug,
            'supplier_id' => $this->supplier->id,
            'debit_account' => 'unassigned',
        ]);

        $this->assertSame(50000, (int) $this->user->wallet()->value('balance_minor'));
        $wallet = SkyDeskPresenter::wallet($this->user->fresh()->wallet, collect());
        $this->assertSame(50000, $wallet['wallet_minor']);
        $this->assertSame(20000, $wallet['unassigned_minor']);
        $this->assertSame(30000, $wallet['on_hand_minor']);
    }

    public function test_wallet_expense_reduces_wallet(): void
    {
        app(WalletService::class)->topUp($this->user, [
            'amount' => 500,
            'disbursement_method_id' => $this->method->slug,
        ]);
        app(ExpenseService::class)->addExpense($this->user, [
            'amount' => 200,
            'article_id' => $this->article->slug,
            'supplier_id' => $this->supplier->id,
            'debit_account' => 'wallet',
        ]);

        $this->assertSame(30000, (int) $this->user->wallet()->value('balance_minor'));
    }

    public function test_overspend_takes_tail_from_wallet(): void
    {
        app(WalletService::class)->topUp($this->user, [
            'amount' => 300,
            'disbursement_method_id' => $this->method->slug,
        ]);
        $advances = app(AdvanceService::class);
        $advance = $this->fundAdvance($advances, ['amount' => 100, 'title' => 'Малый']);

        app(ExpenseService::class)->addExpense($this->user, [
            'amount' => 250,
            'article_id' => $this->article->slug,
            'supplier_id' => $this->supplier->id,
            'debit_account' => 'advance',
        ], $advance);

        $this->assertSame(0, $advances->remainingMinor($advance->fresh()));
        $this->assertSame(15000, (int) $this->user->wallet()->value('balance_minor'));
        $this->assertSame(AdvanceStatus::Closed, $advance->fresh()->statusEnum());
    }

    public function test_close_to_wallet_transfers_remainder(): void
    {
        $advances = app(AdvanceService::class);
        $advance = $this->fundAdvance($advances, ['amount' => 1000, 'title' => 'Частично']);

        app(ExpenseService::class)->addExpense($this->user, [
            'amount' => 400,
            'article_id' => $this->article->slug,
            'supplier_id' => $this->supplier->id,
            'debit_account' => 'advance',
        ], $advance);

        $advances->closeToWallet($advance->fresh());

        $this->assertSame(60000, (int) $this->user->wallet()->value('balance_minor'));
        $this->assertSame(0, $advances->remainingMinor($advance->fresh()));
        $this->assertSame(AdvanceStatus::Closed, $advance->fresh()->statusEnum());
    }

    public function test_attach_and_detach_unassigned(): void
    {
        $advances = app(AdvanceService::class);
        $expenses = app(ExpenseService::class);
        $advance = $this->fundAdvance($advances, ['amount' => 1000, 'title' => 'Attach']);

        $expense = $expenses->addExpense($this->user, [
            'amount' => 300,
            'article_id' => $this->article->slug,
            'supplier_id' => $this->supplier->id,
            'debit_account' => 'unassigned',
        ]);

        $expenses->attachToAdvance($advance, $expense, 'advance');
        $this->assertSame($advance->id, $expense->fresh()->advance_id);
        $this->assertSame('advance', $expense->fresh()->debit_account);
        $this->assertSame(70000, $advances->remainingMinor($advance->fresh()));
        $this->assertSame(AdvanceStatus::Reporting, $advance->fresh()->statusEnum());

        $expenses->detachFromAdvance($advance->fresh(), $expense->fresh());
        $this->assertNull($expense->fresh()->advance_id);
        $this->assertSame('unassigned', $expense->fresh()->debit_account);
        $this->assertSame(100000, $advances->remainingMinor($advance->fresh()));
    }

    public function test_expense_without_supplier_allowed(): void
    {
        app(WalletService::class)->topUp($this->user, [
            'amount' => 500,
            'disbursement_method_id' => $this->method->slug,
        ]);

        $expense = app(ExpenseService::class)->addExpense($this->user, [
            'amount' => 100,
            'article_id' => $this->article->slug,
            'debit_account' => 'wallet',
            'description' => 'Без поставщика',
        ]);

        $this->assertNull($expense->supplier_id);
        $this->assertSame(40000, (int) $this->user->wallet()->value('balance_minor'));
    }

    public function test_destroy_expense_removes_ledger_and_restores_advance(): void
    {
        $advances = app(AdvanceService::class);
        $expenses = app(ExpenseService::class);
        $advance = $this->fundAdvance($advances, ['amount' => 1000, 'title' => 'Удаляемая трата']);

        $expense = $expenses->addExpense($this->user, [
            'amount' => 400,
            'article_id' => $this->article->slug,
            'supplier_id' => $this->supplier->id,
            'debit_account' => 'advance',
            'description' => 'Временная',
        ], $advance);

        $this->assertSame(60000, $advances->remainingMinor($advance->fresh()));
        $expenses->destroyExpense($this->user, $expense);

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
        $this->assertSame(100000, $advances->remainingMinor($advance->fresh()));
    }

    public function test_destroy_wallet_expense_restores_balance(): void
    {
        app(WalletService::class)->topUp($this->user, [
            'amount' => 500,
            'disbursement_method_id' => $this->method->slug,
        ]);
        $expense = app(ExpenseService::class)->addExpense($this->user, [
            'amount' => 200,
            'article_id' => $this->article->slug,
            'debit_account' => 'wallet',
        ]);
        app(ExpenseService::class)->destroyExpense($this->user, $expense);
        $this->assertSame(50000, (int) $this->user->wallet()->value('balance_minor'));
    }

    public function test_http_approve_and_receive(): void
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
                'disbursement_method_id' => $this->method->slug,
            ])
            ->assertRedirect();

        $advance = \App\Models\Advance::query()->latest('id')->first();
        $this->assertSame(AdvanceStatus::Pending, $advance->statusEnum());

        $this->actingAs($this->user)
            ->post("/advances/{$advance->id}/approve")
            ->assertRedirect();
        $this->assertSame(AdvanceStatus::Approved, $advance->fresh()->statusEnum());

        $this->actingAs($this->user)
            ->post("/advances/{$advance->id}/receive", [
                'disbursement_method_id' => $this->method->slug,
                'issued_at' => '2026-08-02',
                'amount' => 250,
            ])
            ->assertRedirect();

        $this->assertSame(AdvanceStatus::Reporting, $advance->fresh()->statusEnum());
        $this->assertDatabaseHas('wallet_transactions', [
            'type' => WalletTransaction::TYPE_INCOME,
            'account' => WalletTransaction::ACCOUNT_ADVANCE,
            'amount_minor' => 25000,
        ]);
    }

    public function test_update_rejects_status_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $advances = app(AdvanceService::class);
        $advance = $advances->create($this->user, ['title' => 'X', 'amount' => 100]);
        $advances->update($advance, ['status_id' => 'approved']);
    }

    public function test_receive_creates_wallet_row_if_missing(): void
    {
        $this->user->wallet()->delete();
        $this->assertNull($this->user->fresh()->wallet);

        $advances = app(AdvanceService::class);
        $advance = $advances->create($this->user, [
            'title' => 'No wallet yet',
            'amount' => 100,
            'disbursement_method_id' => $this->method->slug,
        ]);
        $advances->approve($advance);
        $advances->receive($advance->fresh(), [
            'disbursement_method_id' => $this->method->slug,
        ]);

        $this->assertNotNull($this->user->fresh()->wallet);
        $this->assertSame(0, (int) $this->user->wallet()->value('balance_minor'));
        $this->assertSame(AdvanceStatus::Reporting, $advance->fresh()->statusEnum());
    }
}
