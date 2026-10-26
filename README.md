# exact-ledger

[![tests](https://github.com/abdulbasit264/exact-ledger/actions/workflows/tests.yml/badge.svg)](https://github.com/abdulbasit264/exact-ledger/actions/workflows/tests.yml)

Exact money arithmetic and a **tamper-evident, hash-chained double-entry journal** in plain PHP — no framework, no dependencies.

It is extracted from the accounting core of a multi-tenant logistics ERP I built and run in production (invoicing, AR/AP, commissions, inventory, payroll, all posting through one general ledger). The production version lives inside a private Laravel codebase; this repository is the same ideas, small enough to read in five minutes, with tests.

## The three problems it solves

### 1. Floats are not money

```php
(int) (0.29 * 100);                    // 28 — a cent disappears
array_sum(array_fill(0, 10, 0.1));     // 0.9999999999999999, not 1.0
```

`Money` keeps every amount as a **scaled integer at 4 decimal places** (`123.45` → `1_234_500`). Strings are parsed digit by digit, so a float never holds the value. Integers add and compare exactly, so *"debits equal credits"* is a real `===`, not `abs($a - $b) < 0.00005`.

Display precision comes from the **currency**, not a hard-coded 2: `Money::format('12345', 'JPY')` is `12,345`, `Money::format('1.25', 'KWD')` is `1.250`.

### 2. A ledger should notice when someone edits it

Every entry is hashed (SHA-256) together with the **previous entry's hash**, so the journal is a chain:

- edit an old entry → its hash no longer matches → `firstBrokenAt()` names it
- delete one from the middle → the next entry's `prevHash` points at nothing → caught

What goes into the hash is deliberately narrow — the accounting facts only. Row ids and posting timestamps are left out, because they legitimately differ after a database restore, and a verifier that cries wolf on every restore is worse than none. Amounts are normalised (`120` and `120.0000` hash the same) and lines are sorted, so the same accounting always produces the same hash.

### 3. A chain cannot see its own end

Delete the **newest** entry and what remains is a perfectly valid chain — the easiest tamper of all needs no recomputation. `Anchor` records a high-water mark (entry count + tip hash) after each clean verification and reports a ledger that has **shrunk**. Corrections are reversing entries, which append, so "fewer entries than last time" has no legitimate cause.

Stated plainly, because an oversold security control is worse than none: an anchor stored next to the ledger turns a one-statement deletion into a deliberate two-place forgery and catches accidental loss outright; it does not stop someone with write access to both. Store it elsewhere to raise the bar.

## Usage

```php
use ExactLedger\{Ledger, Line, Anchor};

$ledger = new Ledger;
$ledger->post('2026-01-01', 'Invoice INV-1', [
    Line::debit('1100', '1000.00'),   // Accounts receivable
    Line::credit('4000', '1000.00'),  // Sales
]);
$ledger->post('2026-01-05', 'Payment', [
    Line::debit('1000', '1000.00'),   // Cash
    Line::credit('1100', '1000.00'),
]);

$ledger->balance('1100');      // "0.0000"
$ledger->firstBrokenAt();      // null — chain intact

$anchor = Anchor::of($ledger); // store this somewhere else
// ...later
$anchor->shrunk($ledger);      // true if entries were removed from the end
```

An entry that does not balance to the last 0.0001 is refused with `UnbalancedEntry`.

## Tests

```bash
composer install
composer test
```

The tests show each failure mode directly: the float bug, an edited entry, a deletion from the middle, and a truncation the chain alone misses but the anchor catches.

## In production

In the full system the same pieces run nightly: a `ledger:verify` command walks every tenant's chain and checks the anchor, a reconciliation job compares each control account with its sub-ledger, and either finding raises an ops alert. The predecessor row is locked while a new entry is linked, so two concurrent posts can't fork the chain.

---

MIT licensed · [Abdul Basit](https://www.linkedin.com/in/imabdulbasit)
