<?php

declare(strict_types=1);

namespace ExactLedger;

/** A posted journal entry: the accounting facts plus its link in the hash chain. */
final readonly class JournalEntry
{
    /** @param  list<Line>  $lines */
    public function __construct(
        public string $date,
        public string $description,
        public array $lines,
        public ?string $prevHash = null,
        public ?string $hash = null,
    ) {}

    public function withChain(?string $prevHash, string $hash): self
    {
        return new self($this->date, $this->description, $this->lines, $prevHash, $hash);
    }
}
