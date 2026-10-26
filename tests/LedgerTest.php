<?php

declare(strict_types=1);

namespace ExactLedger\Tests;

use ExactLedger\Anchor;
use ExactLedger\JournalEntry;
use ExactLedger\Ledger;
use ExactLedger\Line;
use ExactLedger\UnbalancedEntry;
use PHPUnit\Framework\TestCase;

final class LedgerTest extends TestCase
{
    private function ledgerWithThreeEntries(): Ledger
    {
        $ledger = new Ledger;
        $ledger->post('2026-01-01', 'Invoice INV-1', [Line::debit('1100', '1000.00'), Line::credit('4000', '1000.00')]);
        $ledger->post('2026-01-05', 'Payment for INV-1', [Line::debit('1000', '600.00'), Line::credit('1100', '600.00')]);
        $ledger->post('2026-01-09', 'Payment for INV-1', [Line::debit('1000', '400.00'), Line::credit('1100', '400.00')]);

        return $ledger;
    }

    public function test_a_balanced_entry_posts_and_balances_are_exact(): void
    {
        $ledger = $this->ledgerWithThreeEntries();

        $this->assertSame('0.0000', $ledger->balance('1100'));
        $this->assertSame('1000.0000', $ledger->balance('1000'));
        $this->assertNull($ledger->firstBrokenAt());
    }

    public function test_an_unbalanced_entry_is_refused_with_no_tolerance(): void
    {
        $this->expectException(UnbalancedEntry::class);

        (new Ledger)->post('2026-01-01', 'Off by a ten-thousandth', [
            Line::debit('1000', '100.0000'),
            Line::credit('4000', '99.9999'),
        ]);
    }

    public function test_three_float_unfriendly_lines_still_balance(): void
    {
        $entry = (new Ledger)->post('2026-01-01', 'Split', [
            Line::debit('1000', '0.1'),
            Line::debit('1000', '0.2'),
            Line::credit('4000', '0.3'),
        ]);

        $this->assertNotNull($entry->hash);
    }

    public function test_editing_an_entry_is_detected_at_that_entry(): void
    {
        $entries = $this->ledgerWithThreeEntries()->entries();
        $original = $entries[1];
        $entries[1] = new JournalEntry(
            $original->date,
            $original->description,
            [Line::debit('1000', '60.00'), Line::credit('1100', '60.00')],   // quietly changed
            $original->prevHash,
            $original->hash,
        );

        $this->assertSame(1, (new Ledger($entries))->firstBrokenAt());
    }

    public function test_deleting_from_the_middle_is_detected(): void
    {
        $entries = $this->ledgerWithThreeEntries()->entries();
        unset($entries[1]);

        $this->assertSame(1, (new Ledger(array_values($entries)))->firstBrokenAt());
    }

    public function test_the_chain_alone_cannot_see_its_own_end_but_the_anchor_can(): void
    {
        $ledger = $this->ledgerWithThreeEntries();
        $anchor = Anchor::of($ledger);

        $truncated = new Ledger(array_slice($ledger->entries(), 0, 2));

        $this->assertNull($truncated->firstBrokenAt());        // looks perfectly fine
        $this->assertTrue($anchor->shrunk($truncated));        // but it got smaller
        $this->assertTrue($anchor->tipMissing($truncated));
    }

    public function test_growth_is_not_a_finding(): void
    {
        $ledger = $this->ledgerWithThreeEntries();
        $anchor = Anchor::of($ledger);
        $ledger->post('2026-02-01', 'Invoice INV-2', [Line::debit('1100', '5'), Line::credit('4000', '5')]);

        $this->assertFalse($anchor->shrunk($ledger));
        $this->assertFalse($anchor->tipMissing($ledger));
    }

    public function test_the_hash_ignores_line_order_and_amount_formatting(): void
    {
        $a = (new Ledger)->post('2026-01-01', 'X', [Line::debit('1000', '120'), Line::credit('4000', '120.0000')]);
        $b = (new Ledger)->post('2026-01-01', 'X', [Line::credit('4000', '120.00'), Line::debit('1000', '120.0')]);

        $this->assertSame($a->hash, $b->hash);
    }
}
