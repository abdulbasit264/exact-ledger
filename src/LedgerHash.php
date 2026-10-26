<?php

declare(strict_types=1);

namespace ExactLedger;

/**
 * The ledger's tamper-evidence hash. One place computes it, so posting and verifying
 * cannot drift apart — a verifier that disagrees with the writer reports tampering that
 * never happened, which is worse than no verifier at all.
 *
 * What goes in is deliberately narrow: the facts that must not change (date, description,
 * every line's account / debit / credit / memo) plus the previous entry's hash, which is
 * what makes it a chain rather than a set of independent checksums.
 *
 * What stays out: row ids and posting timestamps. Ids differ between a database and its
 * restored copy, so including them would make every restore look like tampering.
 */
final class LedgerHash
{
    public static function for(JournalEntry $entry, ?string $prevHash): string
    {
        // Normalised through Money so "120.0000" and "120" hash identically, and sorted so
        // the same accounting always produces the same hash regardless of line order.
        $lines = array_map(
            fn (Line $line) => implode('|', [
                $line->account,
                (string) Money::scale($line->debit),
                (string) Money::scale($line->credit),
                $line->memo,
            ]),
            $entry->lines,
        );
        sort($lines);

        return hash('sha256', implode("\n", [
            'v1',                                   // so the format can change deliberately
            $entry->date,
            $entry->description,
            implode(';', $lines),
            (string) $prevHash,
        ]));
    }
}
