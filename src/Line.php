<?php

declare(strict_types=1);

namespace ExactLedger;

/** One side of a journal entry. Amounts are kept as given and normalised by Money. */
final readonly class Line
{
    public function __construct(
        public string $account,
        public string $debit = '0',
        public string $credit = '0',
        public string $memo = '',
    ) {}

    public static function debit(string $account, string $amount, string $memo = ''): self
    {
        return new self($account, debit: $amount, memo: $memo);
    }

    public static function credit(string $account, string $amount, string $memo = ''): self
    {
        return new self($account, credit: $amount, memo: $memo);
    }
}
