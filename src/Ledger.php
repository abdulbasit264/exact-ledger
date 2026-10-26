<?php

declare(strict_types=1);

namespace ExactLedger;

/**
 * An append-only, hash-chained double-entry journal.
 *
 * - `post()` refuses an entry unless debits equal credits EXACTLY (scaled integers, no
 *   epsilon), then links it to the previous entry's hash.
 * - `verify()` walks the chain and names the first entry that was edited, or whose
 *   predecessor was removed from the middle.
 * - A chain cannot see the end of itself: delete the newest entry and what remains is
 *   perfectly consistent. `Anchor` records a high-water mark (count + tip hash) so a
 *   ledger that has SHRUNK is reported too. Corrections are reversing entries, which
 *   append, so "fewer entries than last time" has no legitimate cause.
 */
final class Ledger
{
    /** @var list<JournalEntry> */
    private array $entries = [];

    /** @param  list<JournalEntry>  $entries  an existing chain, e.g. loaded from storage */
    public function __construct(array $entries = [])
    {
        $this->entries = $entries;
    }

    /** @param  list<Line>  $lines */
    public function post(string $date, string $description, array $lines): JournalEntry
    {
        if (count($lines) < 2) {
            throw new UnbalancedEntry('An entry needs at least two lines.');
        }

        $debits = Money::sum(array_map(fn (Line $l) => $l->debit, $lines));
        $credits = Money::sum(array_map(fn (Line $l) => $l->credit, $lines));

        if ($debits === 0 || $debits !== $credits) {
            throw new UnbalancedEntry(sprintf(
                'Debits %s do not equal credits %s.',
                Money::toString($debits),
                Money::toString($credits),
            ));
        }

        $prev = $this->tip();
        $entry = new JournalEntry($date, $description, array_values($lines));
        $entry = $entry->withChain($prev, LedgerHash::for($entry, $prev));

        $this->entries[] = $entry;

        return $entry;
    }

    /**
     * Index of the first entry that breaks the chain, or null if the chain is intact.
     */
    public function firstBrokenAt(): ?int
    {
        $prev = null;
        foreach ($this->entries as $i => $entry) {
            if ($entry->prevHash !== $prev || $entry->hash !== LedgerHash::for($entry, $prev)) {
                return $i;
            }
            $prev = $entry->hash;
        }

        return null;
    }

    public function tip(): ?string
    {
        return $this->entries === [] ? null : $this->entries[array_key_last($this->entries)]->hash;
    }

    public function count(): int
    {
        return count($this->entries);
    }

    /** Net balance of one account (debits minus credits), as a decimal string. */
    public function balance(string $account): string
    {
        $net = 0;
        foreach ($this->entries as $entry) {
            foreach ($entry->lines as $line) {
                if ($line->account === $account) {
                    $net += Money::scale($line->debit) - Money::scale($line->credit);
                }
            }
        }

        return Money::toString($net);
    }

    /** @return list<JournalEntry> */
    public function entries(): array
    {
        return $this->entries;
    }
}
