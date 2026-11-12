<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\JournalEntry;
use Illuminate\Database\Eloquent\Model;

/**
 * Posts the GL side of stock movements (amounts in base currency):
 *   goods received:   Dr Inventory         Cr Goods received not invoiced
 *   sales fulfilment: Dr Cost of sales     Cr Inventory
 *   stores issue:     Dr Stores consumption Cr Inventory
 *   opening stock:    Dr Inventory         Cr Opening balance equity
 * Supplier invoices for received stock should be coded to the GRNI account to clear it.
 */
class InventoryPostingService
{
    public function __construct(
        private readonly JournalPostingService $journalPosting,
        private readonly AccountMappingService $mappings,
        private readonly ExchangeRateService $rates,
    ) {
    }

    public function goodsReceived(Model $source, string $number, mixed $date, float $value): ?JournalEntry
    {
        return $this->post($source, 'GRN-'.$number, $date, 'inventory', 'grni', $value, 'Goods received '.$number);
    }

    public function salesIssue(Model $source, string $number, mixed $date, float $value): ?JournalEntry
    {
        return $this->post($source, 'COGS-'.$number, $date, 'cogs', 'inventory', $value, 'Cost of goods sold '.$number);
    }

    public function storesIssue(Model $source, string $number, mixed $date, float $value): ?JournalEntry
    {
        return $this->post($source, 'ISS-'.$number, $date, 'inventory_consumption', 'inventory', $value, 'Stores issued '.$number);
    }

    public function openingStock(Model $source, string $number, mixed $date, float $value): ?JournalEntry
    {
        return $this->post($source, 'OPEN-'.$number, $date, 'inventory', 'opening_balance', $value, 'Opening stock '.$number);
    }

    private function post(Model $source, string $entryNumber, mixed $date, string $debitKey, string $creditKey, float $value, string $memo): ?JournalEntry
    {
        return $this->transfer($source, $entryNumber, $date, $debitKey, $creditKey, $value, $memo, 'inventory');
    }

    /**
     * Posts a two-line entry in base currency. $debit / $credit are account-mapping keys (string)
     * or account ids (int). Returns null for a zero amount.
     */
    public function transfer(Model $source, string $entryNumber, mixed $date, string|int $debit, string|int $credit, float $value, string $memo, string $module = 'production'): ?JournalEntry
    {
        $debitKey = $debit;
        $creditKey = $credit;
        $value = round($value, 2);
        if ($value <= 0) {
            return null;
        }

        $company = (int) $source->company_id;
        $entry = new JournalEntry([
            'entry_number' => $entryNumber,
            'entry_date' => $date,
            'source_module' => $module,
            'source_type' => $source::class,
            'source_id' => $source->getKey(),
            'currency' => $this->rates->baseCurrency($company),
            'exchange_rate' => 1,
            'memo' => $memo,
            'created_by' => auth()->id(),
        ]);
        $entry->company_id = $company;
        $entry->save();

        foreach ([[$debitKey, $value, 0], [$creditKey, 0, $value]] as [$key, $debit, $credit]) {
            $entry->lines()->create([
                'company_id' => $company,
                'account_id' => is_int($key) ? $key : $this->mappings->account($key, $company)->id,
                'description' => $memo,
                'debit' => $debit,
                'credit' => $credit,
                'fx_debit' => $debit,
                'fx_credit' => $credit,
            ]);
        }

        return $this->journalPosting->post($entry);
    }
}
