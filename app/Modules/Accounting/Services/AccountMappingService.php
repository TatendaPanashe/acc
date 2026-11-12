<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountMapping;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Resolves which GL account an automatic posting uses (see config accounting.account_mappings).
 */
class AccountMappingService
{
    /** @var array<string, Account> */
    private array $cache = [];

    /** @return array<string, array{0: string, 1: string}> key => [label, default code] */
    public static function definitions(): array
    {
        return collect(config('accounting.account_mappings'))->collapse()->all();
    }

    public function account(string $key, int $companyId): Account
    {
        $cacheKey = $companyId.':'.$key;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $definition = self::definitions()[$key] ?? throw new \InvalidArgumentException("Unknown account mapping [{$key}].");

        $mappedId = AccountMapping::withoutGlobalScopes()->where('company_id', $companyId)->where('key', $key)->value('account_id');
        $account = Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->when($mappedId, fn ($q) => $q->whereKey($mappedId), fn ($q) => $q->where('code', $definition[1]))
            ->first();

        if (!$account) {
            throw ValidationException::withMessages([
                'account' => "No GL account is set for \"{$definition[0]}\" (default code {$definition[1]}). Set it under Accounting Settings → Account Mapping.",
            ]);
        }

        if (!$account->status || !$account->allow_posting) {
            throw ValidationException::withMessages([
                'account' => "Account {$account->code} {$account->name}, used for \"{$definition[0]}\", is inactive or does not allow posting.",
            ]);
        }

        return $this->cache[$cacheKey] = $account;
    }

    /** GL account that receives/pays money for a payment method. */
    public function tenderAccount(?string $paymentMethod, int $companyId): Account
    {
        return $this->account(self::tenderKey($paymentMethod), $companyId);
    }

    public static function tenderKey(?string $paymentMethod): string
    {
        $method = Str::lower((string) $paymentMethod);

        return match (true) {
            // Mobile wallets first: "EcoCash" / "TeleCash" contain the word "cash".
            Str::contains($method, ['mobile', 'ecocash', 'onemoney', 'innbucks', 'telecash']) => 'mobile_money',
            Str::contains($method, 'cash') => 'cash_on_hand',
            Str::contains($method, ['card', 'swipe', 'visa', 'mastercard', 'debit']) => 'card',
            default => 'bank',
        };
    }

    /** IDs of every account mapped to receive or pay money (used to identify cash & bank accounts). */
    public function tenderAccountIds(int $companyId): array
    {
        $ids = [];
        foreach (['cash_on_hand', 'bank', 'card', 'mobile_money'] as $key) {
            try {
                $ids[] = $this->account($key, $companyId)->id;
            } catch (ValidationException) {
                // unmapped tender: ignore
            }
        }

        return array_values(array_unique($ids));
    }

    public function forget(): void
    {
        $this->cache = [];
    }
}
