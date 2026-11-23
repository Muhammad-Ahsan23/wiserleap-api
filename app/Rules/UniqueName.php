<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Case-insensitive unique check ("Insights" and "insights" are the same name),
 * independent of the database collation.
 */
class UniqueName implements ValidationRule
{
    public function __construct(
        private readonly string $table,
        private readonly string $label,
        private readonly ?int $ignoreId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = DB::table($this->table)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $value)])
            ->when($this->ignoreId, fn ($q) => $q->where('id', '!=', $this->ignoreId))
            ->exists();

        if ($exists) {
            $fail("A {$this->label} called \"{$value}\" already exists.");
        }
    }
}
