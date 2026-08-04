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
use App\Support\SkyDeskPresenter;
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
        $this->assertSame(
            '2026-07-15',
            WalletTransaction::query()->latest('id')->value('occurred_at')?->toDateString()
        );
    }

    public function test_received_credits_advance_account_not_wallet(): void
    {
        $advances = app(AdvanceService::class);
        $advance = $advances->create($this->user, [
            'title' => 'Каршеринг',
            'amount' => 20000,
            'status_id' => 'pending',
            'disbursement_method_id' => $this->method->slug,
        ]);

        $advances->update($advance, [
            'status_id' => 'received',
            'disbursement_method_id' => $this->method->slug,
            'amount' => 20000,
        ]);

        $advance = $advance->fresh('status');
        $advances->markReceived($advance);
        $advances->markReceived($advance->fresh());

        $this->assertSame(1, WalletTransaction::where('advance_id', $advance->id)
            ->where('type', WalletTransaction::TYPE_INCOME)
            ->where('account', WalletTransaction::ACCOUNT_ADVANCE)
            ->count());
        $this->assertSame(0, (int) $this->user->wallet()->value('balance_minor'));
        $this->assertSame(2000000, $advances->remainingMinor($advance));
        $this->assertSame('received', $advance->fresh('status')->status->slug);
    }

    public function test_received_requires_amount_and_method(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(AdvanceService::class)->create($this->user, [
            'title' => 'Без суммы',
            'amount' => 0,
            'status_id' => 'received',
            'disbursement_method_id' => $this->method->slug,
        ]);
    }

    public function test_expense_on_advance_and_autoclose(): void
    {
        $advances = app(AdvanceService::class);
        $expenses = app(ExpenseService::class);

        $advance = $advances->create($this->user, [
            'title' => 'Закупка',
            'amount' => 1000,
            'status_id' => 'received',
            'disbursement_method_id' => $this->method->slug,
        ]);

        $this->assertSame(0, (int) $this->user->wallet()->value('balance_minor'));
        $this->assertSame(100000, $advances->remainingMinor($advance));

        $expenses->addExpense($this->user, [
            'amount' => 1000,
            'article_id' => $this->article->slug,
            'supplier_id' => $this->supplier->id,
            'description' => 'Всё',
            'debit_account' => 'advance',
        ], $advance);

        $this->assertSame(0, $advances->remainingMinor($advance->fresh()));
        $this->assertSame('closed', $advance->fresh('status')->status->slug);
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
        $advance = $advances->create($this->user, [
            'title' => 'Малый',
            'amount' => 100,
            'status_id' => 'received',
            'disbursement_method_id' => $this->method->slug,
        ]);

        app(ExpenseService::class)->addExpense($this->user, [
            'amount' => 250,
            'article_id' => $this->article->slug,
            'supplier_id' => $this->supplier->id,
            'debit_account' => 'advance',
        ], $advance);

        $this->assertSame(0, $advances->remainingMinor($advance->fresh()));
        $this->assertSame(15000, (int) $this->user->wallet()->value('balance_minor')); // 300 - 150 overspend
        $this->assertSame('closed', $advance->fresh('status')->status->slug);
    }

    public function test_close_to_wallet_transfers_remainder(): void
    {
        $advances = app(AdvanceService::class);
        $advance = $advances->create($this->user, [
            'title' => 'Частично',
            'amount' => 1000,
            'status_id' => 'received',
            'disbursement_method_id' => $this->method->slug,
        ]);

        app(ExpenseService::class)->addExpense($this->user, [
            'amount' => 400,
            'article_id' => $this->article->slug,
            'supplier_id' => $this->supplier->id,
            'debit_account' => 'advance',
        ], $advance);

        $advances->closeToWallet($advance->fresh());

        $this->assertSame(60000, (int) $this->user->wallet()->value('balance_minor'));
        $this->assertSame(0, $advances->remainingMinor($advance->fresh()));
        $this->assertSame('closed', $advance->fresh('status')->status->slug);
    }

    public function test_close_writeoff_does_not_credit_wallet(): void
    {
        $advances = app(AdvanceService::class);
        $advance = $advances->create($this->user, [
            'title' => 'Writeoff',
            'amount' => 500,
            'status_id' => 'received',
            'disbursement_method_id' => $this->method->slug,
        ]);

        $advances->closeWriteOff($advance);

        $this->assertSame(0, (int) $this->user->wallet()->value('balance_minor'));
        $this->assertSame(0, $advances->remainingMinor($advance->fresh()));
        $this->assertSame('closed', $advance->fresh('status')->status->slug);
    }

    public function test_attach_and_detach_unassigned(): void
    {
        $advances = app(AdvanceService::class);
        $expenses = app(ExpenseService::class);

        $advance = $advances->create($this->user, [
            'title' => 'Attach',
            'amount' => 1000,
            'status_id' => 'received',
            'disbursement_method_id' => $this->method->slug,
        ]);

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
        $this->assertSame('reporting', $advance->fresh('status')->status->slug);

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

        $advance = $advances->create($this->user, [
            'title' => 'Удаляемая трата',
            'amount' => 1000,
            'status_id' => 'received',
            'disbursement_method_id' => $this->method->slug,
        ]);

        $expense = $expenses->addExpense($this->user, [
            'amount' => 400,
            'article_id' => $this->article->slug,
            'supplier_id' => $this->supplier->id,
            'debit_account' => 'advance',
            'description' => 'Временная',
        ], $advance);

        $this->assertSame(60000, $advances->remainingMinor($advance->fresh()));
        $this->assertDatabaseHas('wallet_transactions', [
            'expense_id' => $expense->id,
            'account' => WalletTransaction::ACCOUNT_ADVANCE,
            'amount_minor' => -40000,
        ]);

        $expenses->destroyExpense($this->user, $expense);

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
        $this->assertSame(0, WalletTransaction::query()->where('expense_id', $expense->id)->count());
        $this->assertSame(
            0,
            WalletTransaction::query()
                ->where('advance_id', $advance->id)
                ->where('type', WalletTransaction::TYPE_EXPENSE)
                ->count()
        );
        $this->assertSame(100000, $advances->remainingMinor($advance->fresh()));

        $wallet = $this->user->fresh()->wallet->load(['transactions.advance', 'transactions.expense.article']);
        $presented = SkyDeskPresenter::wallet($wallet, collect([$advance->fresh()]));
        $titles = collect($presented['transactions'])->pluck('title');
        $this->assertFalse($titles->contains('Временная'));
        $this->assertFalse($titles->contains('Трата'));
    }

    public function test_http_income_and_received(): void
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
                'status_id' => 'received',
                'disbursement_method_id' => $this->method->slug,
            ])
            ->assertRedirect();

        $this->assertSame(10000, (int) $this->user->wallet()->value('balance_minor'));
        $this->assertDatabaseHas('wallet_transactions', [
            'type' => WalletTransaction::TYPE_INCOME,
            'account' => WalletTransaction::ACCOUNT_ADVANCE,
            'amount_minor' => 25000,
        ]);
    }

    public function test_received_creates_wallet_row_if_missing(): void
    {
        $this->user->wallet()->delete();
        $this->assertNull($this->user->fresh()->wallet);

        $advance = app(AdvanceService::class)->create($this->user, [
            'title' => 'No wallet yet',
            'amount' => 100,
            'status_id' => 'received',
            'disbursement_method_id' => $this->method->slug,
        ]);

        $this->assertNotNull($this->user->fresh()->wallet);
        $this->assertSame(0, (int) $this->user->wallet()->value('balance_minor'));
        $this->assertSame('received', $advance->status->slug);
    }
}
