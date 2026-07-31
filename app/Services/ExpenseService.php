<?php

namespace App\Services;

use App\Models\Advance;
use App\Models\AdvanceStatus;
use App\Models\Expense;
use App\Models\Receipt;
use App\Support\DictionaryResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExpenseService
{
    public function addExpense(Advance $advance, array $data): Expense
    {
        $amountMinor = array_key_exists('amount_minor', $data)
            ? (int) $data['amount_minor']
            : DictionaryResolver::rublesToMinor($data['amount'] ?? 0);

        $expense = Expense::create([
            'advance_id' => $advance->id,
            'amount_minor' => $amountMinor,
            'description' => $data['description'] ?? null,
        ]);

        if ($advance->status?->slug === 'issued') {
            $advance->status_id = DictionaryResolver::advanceStatusId('reporting');
            $advance->save();
        }

        return $expense->fresh('receipts');
    }

    public function addReceipt(Expense $expense, UploadedFile $file): Receipt
    {
        $dir = "receipts/{$expense->advance_id}/{$expense->id}";
        $name = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($dir, $name, 'public');

        return Receipt::create([
            'expense_id' => $expense->id,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize() ?: 0,
        ]);
    }

    public function destroyReceipt(Receipt $receipt): void
    {
        Storage::disk('public')->delete($receipt->path);
        $receipt->delete();
    }
}
