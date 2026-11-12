<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Like `exists:table,column`, but only matches rows that belong to the signed-in user's company.
 * Prevents a user from referencing another company's accounts, customers, items, etc. by ID.
 */
class TenantExists implements ValidationRule
{
    /** @var array<string, mixed> */
    private array $wheres = [];

    public function __construct(private readonly string $table, private readonly string $column = 'id')
    {
    }

    public function where(string $column, mixed $value): self
    {
        $this->wheres[$column] = $value;

        return $this;
    }

    /** Active currency code of the user's company (case-insensitive). */
    public static function currency(): self
    {
        return (new self('accounting_currencies', 'code'))->where('status', true);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = auth()->user();
        $query = DB::table($this->table);
        $query = $this->column === 'code'
            ? $query->whereRaw('UPPER('.$this->column.') = ?', [strtoupper((string) $value)])
            : $query->where($this->column, $value);

        if (!$user?->is_superuser) {
            $query->where('company_id', $user?->company_id ?? 0);
        }

        foreach ($this->wheres as $column => $expected) {
            $query->where($column, $expected);
        }

        if (!$query->exists()) {
            $fail('The selected :attribute is invalid.');
        }
    }
}
