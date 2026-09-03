# Weekly Performance — container count summary

A per-customer count of yard lifts, broken into weeks across a date range, split
by size and by laden/empty, with Mounting and Demounting as separate rows.

---

## 1. What the sample actually specifies

The attached workbook is one sheet, 26 columns wide, and the structure lives in
its merged ranges rather than in its text. Decoded:

| Rows | Columns | Holds |
| --- | --- | --- |
| 4–7 | `A` | `CUSTOMER` (merged down four header rows) |
| 4–7 | `B` | unlabelled — the Mounting / Demounting row label |
| 5 | `C:F`, `G:J`, `K:N`, `O:R`, `S:V` | five week bands, **each merged and each empty** |
| 6 | `C:D`, `E:F` (per band) | `EMPTY`, `LADEN` |
| 7 | per column | `20`, `40` |
| 4–5 | `W:Z` | `TOTAL` |
| 6 | `W:X`, `Y:Z` | `EMPTY`, `LADEN` |
| 38 | `A:B` merged | `TOTAL` row |

So each week band is four columns — `Empty 20`, `Empty 40`, `Laden 20`,
`Laden 40` — and the row-end `TOTAL` band repeats the same four.

**The week bands in row 5 are blank in the sample.** That is precisely the slot
the requirement asks us to fill: "indicate the date range under each week
segment". Nothing needs inventing; the layout already reserves the space.

Three further things the sample establishes that a written spec would probably
have missed:

- **Zero renders as blank, not `0`.** Look at LUXMI, DELTA, CCD, NOORANI and
  LAL & SONS — both their rows are entirely empty cells.
- **Every customer is listed whether or not they moved anything.** Those five
  customers have no movements at all in the period and still occupy two rows
  each. In a performance report a zero week is itself the finding, so this is
  deliberate, not an artefact.
- **The bottom `TOTAL` row combines Mounting and Demounting.** Verified against
  the sample's own arithmetic: column C sums to 40 across *both* row types
  (`1+9+6+3+1+3+6+2+2+1+6`), and cell `C38` reads 40. So that row is total lifts
  — the yard's handling workload — not a per-direction total.

Title, row 2: `PERFORMANCE UPDATE [NO. OF UNITS]- AUGUST 2026`.

Customer and label columns are yellow-filled (`FFFFFF00`) and bold; header rows
are bold, centred and bordered; data cells are centred and bordered.

---

## 2. The data supports this without a single join

`gate_movements` carries every dimension on the row itself:

| Column | Values | Serves |
| --- | --- | --- |
| `customer_id` | FK to `customers` | the customer grouping |
| `movement_type` | `in` / `out` | Demounting / Mounting |
| `size` | `20`, `40`, `45` | the size breakdown |
| `cargo_status` | `empty`, `laden` | the laden/empty split |
| `gate_in_time`, `gate_out_time` | timestamps | which week the lift falls in |

That the movement row records size and cargo status **as they were at the gate**
matters more than it looks. A box that arrives laden and leaves empty is counted
laden on its Demounting row and empty on its Mounting row, which is what the
yard actually did. Reading those attributes off `containers` instead would
report the box's state today and quietly misstate history.

### Mounting and Demounting

The mapping matches the convention already in the billing code
(`StorageHandlingController.php:167-181`):

| Report row | Lift | Query | Dated by |
| --- | --- | --- | --- |
| **Demounting** | Lift Off — box comes off the truck into the yard | `movement_type = 'in'` | `gate_in_time` |
| **Mounting** | Lift On — box goes onto the truck | `movement_type = 'out'` | `gate_out_time` |

Reusing the billing definition is the point: the count on this report and the
handling lines on a Storage & Handling invoice will reconcile, because both
are counting the same events by the same rule. If they ever diverge, that is a
bug in one of them rather than two defensible answers.

### What is excluded

A movement whose relevant timestamp is `NULL` has not happened yet — a gate pass
raised but not completed. Those are excluded. `movement_status = 'pending'` with
a timestamp already set is still a real lift and is counted; the timestamp, not
the status flag, is the evidence that the crane moved.

---

## 3. Week bucketing — and a conflict worth settling first

The chosen rule is **calendar weeks, Monday–Sunday, clipped to the range**.

**The sample contradicts that, and it is worth knowing before we build.** The
sample covers August 2026 and has exactly **five** week bands. August 2026
begins on a Saturday, so Monday–Sunday clipped would produce **six**:

```
band 1  01 Aug – 02 Aug   (Sat–Sun, part week)
band 2  03 Aug – 09 Aug
band 3  10 Aug – 16 Aug
band 4  17 Aug – 23 Aug
band 5  24 Aug – 30 Aug
band 6  31 Aug – 31 Aug   (part week)
```

Five bands is what **7-day blocks from the start date** produces: 1–7, 8–14,
15–21, 22–28, 29–31. So the yard's existing sheet appears to use 7-day blocks.

**Settled: seven-day blocks is the default**, with the two calendar rules on a
selector beside the date range. The weeks should follow the range the operator
gave rather than a calendar they did not ask about — and that is also what the
yard's sheet does, as the five August bands show. Both calendar rules stay
because week boundaries that never move are the right answer for comparing one
report against another, which is a different question.

Either way the band count is **variable**, driven by the range. The sample's
five is a fact about August, not a fixed width — a two-week range renders two
bands, a quarter renders thirteen or fourteen.

Week bucketing runs against the **application timezone**, not UTC. A lift at
23:30 local on a Sunday belongs to that Sunday's week, and would fall into the
next one if the timestamp were bucketed raw.

---

## 4. Layout

Per the decision to give 45' its own column pair, each week band is **six**
columns rather than the sample's four:

```
                        │  WEEK 1              │  WEEK 2              │  TOTAL
                        │  03–09 Aug 2026      │  10–16 Aug 2026      │
                        │  EMPTY   │  LADEN    │  EMPTY   │  LADEN    │  EMPTY   │  LADEN
CUSTOMER      │         │ 20 40 45 │ 20 40 45  │ 20 40 45 │ 20 40 45  │ 20 40 45 │ 20 40 45
──────────────┼─────────┼──────────┼───────────┼──────────┼───────────┼──────────┼─────────
RSL           │ Demount │  1  5    │           │          │           │  1  5    │
              │ Mount   │  9  2    │           │          │           │  9  2    │
ABANS AUTO    │ Demount │          │    12     │          │           │          │    12
              │ Mount   │          │    49     │          │           │          │    49
──────────────┴─────────┼──────────┼───────────┼──────────┼───────────┼──────────┼─────────
TOTAL DEMOUNTING        │  1  5    │    12     │          │           │  1  5    │    12
TOTAL MOUNTING          │  9  2    │    49     │          │           │  9  2    │    49
GRAND TOTAL             │ 10  7    │    61     │          │           │ 10  7    │    61
```

**Three footer rows, not the sample's one.** The sample's single `TOTAL` row is
the grand total — total lifts, both directions added together, which is what
its own arithmetic shows. Splitting it into Demounting and Mounting above the
grand total answers "how much did we lift" and "which way" separately, without
losing the figure the sheet already carried.

Demounting leads, matching the order of the pair under every customer above. A
footer that reversed them would invite reading the wrong line.

Four header rows, matching the sample's four: week number, date range,
`EMPTY`/`LADEN`, then the sizes. `TOTAL` spans the first two.

For a five-week range that is `2 + 5×6 + 6 = 38` columns. Wide, so the grid
scrolls inside its own container rather than making the page scroll — the same
treatment the other wide reports already use.

**Zero renders blank** on screen, in print and in the Excel — matching the
sample, which leaves LUXMI and the other quiet customers entirely empty. The
flat CSV writes `0` instead: it exists to be parsed, and a blank cell is not a
number. That one difference is deliberate and gets a line in the report footnote
so nobody files it as a bug.

**Title** follows the sample: `PERFORMANCE UPDATE [NO. OF UNITS] — AUGUST 2026`
when the range is exactly one calendar month, and
`— 04 AUG 2026 TO 19 SEP 2026` otherwise.

### Which customers appear

Default: **all active customers, ordered by name**, matching the sample —
including the ones with nothing to show. A "only customers with movements"
toggle handles the case where the list has grown long enough that the blanks
are noise, and a single-customer filter narrows it to one.

There is no `OTHER` bucket in `customers`; the sample's `OTHER` row is a
customer record by that name. Nothing to build — but worth confirming that
un-attributed movements really do get filed against it rather than against
whatever customer happens to be first.

---

## 5. Computation

One grouped query per direction, bucketed into weeks in PHP:

```sql
SELECT customer_id, size, cargo_status, DATE(gate_in_time) AS d, COUNT(*) AS n
  FROM gate_movements
 WHERE movement_type = 'in'
   AND gate_in_time >= ? AND gate_in_time < ?     -- half-open, so 23:59:59 is not lost
 GROUP BY customer_id, size, cargo_status, d
```

…and the same for `'out'` on `gate_out_time`.

**Grouping by date and bucketing in PHP, rather than computing the week index in
SQL.** A `FLOOR(DATEDIFF(...)/7)` would be one query tighter, but it only works
for uniform 7-day blocks — the Monday–Sunday rule has a short first band, so the
index is not a division. Keeping the week logic in one PHP class means both
rules share the same tested code and neither is expressed in SQL where it cannot
be unit-tested.

The grouped result is bounded by distinct (customer × date × size × status)
combinations that actually have movements — a busy month is low thousands of
rows, which pivots in memory without concern.

**Index required.** `gate_movements` has no index supporting this; the only
composite one is `(movement_type, mr_status)`. Both queries filter on
`movement_type` plus a timestamp range:

```php
$table->index(['movement_type', 'gate_in_time'],  'gm_type_in_idx');
$table->index(['movement_type', 'gate_out_time'], 'gm_type_out_idx');
```

Without them this is a full scan of every movement the yard has ever recorded,
every time someone opens the report.

---

## 6. Where it lives

- **`App\Services\Reporting\WeeklyPerformanceReport`** — the whole computation.
  `build(string $from, string $to, string $weekRule, array $filters): array`
  returning `['weeks' => …, 'rows' => …, 'totals' => …, 'sizes' => …]`.
- **`App\Services\Reporting\WeekBreakdown`** — the two week rules, alone and
  testable, with no knowledge of containers.
- **`App\Support\Export\WeeklyPerformanceWorkbook`** — the sample-shaped xlsx:
  merges, styles, widths, frozen panes. Separate from `TabularExport` because
  the shape is genuinely different, not because the code is.
- **`ReportController::weeklyPerformance()`**, `exportWeeklyPerformance()` and
  `exportWeeklyPerformanceCsv()` — thin, all reading the one service call.

`ReportController` applies `can:reports.view` as **constructor middleware**
(`ReportController.php:18-21`), so a new action inherits the check. That is the
opposite of the finance controllers, where authorization is per-action and every
export has to repeat it. Worth stating explicitly so nobody "helpfully" adds a
redundant `authorize()` here, or omits a required one there.

---

## 7. Phases

**Phase 1 — the computation.** `WeekBreakdown` and `WeeklyPerformanceReport`,
plus their tests. No UI. The arithmetic is the part that has to be right, and it
can be proven before a single Blade file exists.

**Phase 2 — the screen.** Route, nav entry, and a filter bar in the same shape
as the other reports: date range, week rule, customer, and "only customers with
movements". The grid below it is a live preview of the workbook — same headers,
same bands, same blank zeros — so what the operator sees is what downloads.

The download buttons sit beside Print, from the shared `export-buttons` partial,
and **carry the current filters** — `request()->query()` by default, exactly as
the finance reports do.

That last point is the one worth guarding. `ReportController`'s own docblock
calls it "the commonest bug in this kind of feature, and a silent one": an
operator filters the screen, hits export, and is handed the unfiltered set.
The screen and every download read one service call with one set of arguments,
so there is no second query to drift.

**Phase 3 — the Excel download, shaped like the sample.** Not a flattened
approximation: merged week bands with the date range under each, four stacked
header rows, the yellow customer columns, borders, frozen panes, and zeros left
blank. The file the yard opens is the file it already circulates.

This is possible with the dependency already installed. **An earlier draft of
this plan said openspout could not merge cells and that a sample-shaped workbook
would need PhpSpreadsheet. That was wrong** — openspout 4 supports
`Options::mergeCells()`, background colour, borders, bold, alignment, column
widths and freeze panes, and a proof of concept reproducing this exact layout
has been generated and verified. No new dependency, and no reason to settle for
a flat sheet.

`TabularExport` is not the right vehicle here and will not be forced to be one.
It owns a deliberately narrow contract — one heading row, one row per record,
escaping and filenames — which is what makes it safe for the seventeen reports
that fit it. A four-row merged header is a different shape, so this report gets
its own writer, `WeeklyPerformanceWorkbook`, reusing `TabularExport::filename()`
for the timestamped name so downloads stay consistent across the app.

**A CSV also stays on offer**, flattened to one heading row per column
(`W1 03–09 Aug · Empty · 20`). Not everything that reads this report is Excel,
and a merged workbook is unparseable to a script.

**Phase 4 — the printed page.**

Browser print rather than a DomPDF route, deliberately. Every report screen in
this app prints that way and none of them has a PDF endpoint; inventing one here
would add a convention for a single report. It is also the wrong tool for the
job — DomPDF handles a thirty-eight-column table poorly, and the browser already
renders this exact grid correctly.

What the printed page needs beyond the screen's:

- **Landscape.** Thirty-eight columns cannot be read on the short edge at any
  font size.
- **A masthead.** On screen the period, the week rule and the active filters
  live in the filter bar and the card header, and neither survives printing. A
  sheet handed round the yard that does not say what it was filtered to cannot
  be checked by the person holding it, so the print-only block states the
  period, the rule, the lift count, the customer filter and when it was printed.
- **A repeating header.** `thead { display: table-header-group }`, or page two
  of a quarterly print is a grid of numbers with nothing naming its columns.
- **Unsplit pairs.** `page-break-inside: avoid` on each row, because a
  customer's name is merged across their Demounting and Mounting rows and a
  break between them leaves the second row anonymous.
- **Explicit colour adjust.** Browsers drop backgrounds when printing unless
  asked; the total band and header fills carry structure. Where a printer still
  refuses them, heavier borders on the band starts keep it readable.

### 4c as built

The workbook is verified by opening it back up rather than by trusting it was
written: cells by A1 reference, and the merge ranges that turn four rows of text
into banded headers. Two reader options had to be turned on for that to mean
anything — `SHOULD_LOAD_MERGE_CELLS`, obviously, and `SHOULD_PRESERVE_EMPTY_ROWS`,
because without it the reader resequences its row indices past the two blanks in
the title block and every reference lands two rows out, silently, on cells that
still hold plausible values.

---

## 8. Tests

**Week bucketing** — both rules; a range shorter than one week; a range starting
mid-week; a single-day range; a range spanning a year boundary; the timezone
case above.

**Mapping** — an `in` movement lands on Demounting, an `out` on Mounting, and
never both. A movement with a null timestamp is excluded.

**Cells** — size and cargo status come from the movement row, proven by a
container whose current size or status differs from the one recorded at the gate.

**Totals, the invariant that makes the grid trustworthy** — each row's `TOTAL`
band equals the sum of its week bands; the bottom `TOTAL` row equals the sum of
the customer rows; and the grand total equals the raw count of movements in the
range. That last one is what catches a bucketing bug: if a lift lands in no week
at all, every subtotal still agrees with itself and only the raw count disagrees.

**Presentation** — a customer with no movements still renders two rows; zero
renders blank on screen, in print and in the Excel, and `0` in the flat CSV.

**The workbook itself** — the generated xlsx is opened back up and asserted
against: the merge ranges are where they should be, the date range sits under
its week number, and a cell's value lands in the column its header claims. A
merged-header file is easy to get subtly wrong by one column, and impossible to
notice by reading the code.

**Permission** — a role without `reports.view` is refused, on the screen and on
the export.

---

## 9. Deployment

One migration (the two indexes). **No seeders** — `reports.view` already exists
and every role that should see this already holds it. **No data fix** — the
report only reads.

---

## 10. To confirm

1. **The week rule.** The sample implies 7-day blocks; the stated choice is
   Monday–Sunday. Both get built; only the default is in question.
2. **45' columns.** Chosen as their own pair, which widens each band from the
   sample's four columns to six. Worth one look at the rendered sheet before
   Phase 2 is signed off.
3. **`OTHER`** — a real customer record, or a bucket that needs building?
