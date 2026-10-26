<?php

declare(strict_types=1);

namespace ExactLedger;

/**
 * High-water mark for the hash chain, so TRUNCATION is detectable.
 *
 * Stated plainly, because a security control oversold is worse than none: if the anchor
 * lives next to the ledger, someone with write access can edit both. What it does is turn
 * a one-statement deletion into a deliberate two-place forgery, and catch accidental loss
 * (a bad restore, a stray DELETE) outright. Store it somewhere else to raise the bar further.
 */
final readonly class Anchor
{
    public function __construct(
        public int $count,
        public ?string $tip,
    ) {}

    public static function of(Ledger $ledger): self
    {
        return new self($ledger->count(), $ledger->tip());
    }

    /**
     * Only ever compares DOWNWARD: growth is normal, a decrease is the finding. Never record
     * a new anchor over a shrunk ledger — that would launder exactly the tamper it catches.
     */
    public function shrunk(Ledger $ledger): bool
    {
        return $ledger->count() < $this->count;
    }

    /** The entry the anchor recorded as the tip must still be in the chain. */
    public function tipMissing(Ledger $ledger): bool
    {
        if ($this->tip === null) {
            return false;
        }
        foreach ($ledger->entries() as $entry) {
            if ($entry->hash === $this->tip) {
                return false;
            }
        }

        return true;
    }
}
