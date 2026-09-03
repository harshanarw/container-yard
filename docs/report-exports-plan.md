# Report Exports — CSV and Excel

**Requirement.** Every report should offer both CSV and Excel (`.xlsx`).

---

## 1. What exists today

I went through every route whose screen renders a table of report data.

**Operational reports**

| Report | Route | Export today |
| --- | --- | --- |
| Inventory | `reports.inventory` | **none** |
| Stock (Available Stock) | `containers.available-stock` | **none** |
| Billing report | `reports.billing` | **none** |
| M&R Status | `reports.mr-status` | CSV |
| Daily Movements | `reports.daily-movements` | CSV + CODECO |
| Container Inquiry | `container-inquiry.export` | CSV |

**Finance reports**

| Report | Export today |
| --- | --- |
| Income Statement | **none** |
| Balance Sheet | **none** |
| FX Gain/Loss | **none** |
| FX Revaluation | **none** |
| Customer Statement | **none** |
| Supplier Statement | **none** |
| VAT / SSCL Return | **none** |
| WHT Report | **none** |
| AR Aging | **none** |
| AP Aging | **none** |
| Job Margin | CSV |

**Four of seventeen report screens export anything. None exports Excel.**

**One correction to an earlier draft of this table.** `storage.report` was listed
here as an operational report. It is not: `StorageReportController` reports *disk
usage* over uploaded files — sizes, uploaders, paths — which is a sysadmin screen
and not part of this family. It is out of scope, and available on request.

Two facts shape everything below.

### 1.1 There are four separate CSV implementations, and no shared one

`ReportController` (twice), `ContainerInquiryController`, `JobMarginReportController`
and `BankController` each hand-roll the same thing: open `php://output`, write a
heading row with `fputcsv`, chunk the query, write rows, close.

They are consistent by accident rather than by construction. Adding a second
format to each, one at a time, turns four copies into eight. **The consolidation
is not a tidy-up alongside this work — it is the thing that makes this work
small.**

### 1.2 Nothing in the codebase can write `.xlsx`

No reference anywhere to `PhpSpreadsheet`, `Maatwebsite\Excel`, `OpenSpout` or
any other spreadsheet library. Excel output needs one — or code we write and own.

**And a new dependency cannot be deployed through git here**, because
`composer.json` and `composer.lock` are **not tracked in this repository**. Every
other change this session shipped as files; this one cannot. It would mean
running `composer require` directly on the live server — the operation that just
failed there. §4 is about that, and it is the decision this plan turns on.

---

## 2. Shape

One definition per report, two formats out of it.

```php
App\Support\Export\TabularExport

    ::stream(string $format, string $basename, array $headings, callable $rows)
```

`$rows` is a callable returning a generator, so the chunking the current exports
already do is preserved rather than replaced by "load everything into an array".
A report then declares its data once:

```php
return TabularExport::stream(
    $request->get('format', 'csv'),
    'mr-status',
    ['Container No', 'Customer', /* … */],
    fn () => $this->mrStatusRows($request),
);
```

and the format comes off the query string. One route per report instead of two,
and the row-building code cannot drift between formats because there is only one
copy of it.

**Worth knowing about xlsx:** it is a zip of XML, so unlike CSV it cannot be
streamed straight to the browser — the archive's index is written last. Any
implementation buffers to a temp file and then sends it. Memory stays bounded if
rows are generated lazily, but disk is touched. For the sizes here (a yard of
~900 containers, a month of movements) that is nothing; it is worth stating
because it is the one way the two formats genuinely differ.

---

## 3. Order of work

**Phase 1 — the exporter, and the four existing CSVs moved onto it. — done**
No new report gains an export yet, and no output changes. This is the phase that
proves the exporter produces byte-identical CSV to what those four already emit,
which is exactly the guarantee that makes the rest safe.

**Phase 2 — Excel on those same four. — done (dormant until openspout lands)**
One format flag. Phase 1 being right is what made it small.

**Phase 3 — the operational reports that have nothing: Inventory, Stock,
Billing. — done** These are the ones named in the request.

**Phase 4 — the eleven finance reports.** A bigger family and a separate
conversation: several of them (Balance Sheet, Income Statement) are hierarchical
rather than tabular, so "export the table" is not a well-defined operation for
them without deciding how to flatten the tree.

---

## 4. The decision: how to write xlsx — **settled: A, with the composer files tracked**

| | What it means | Deployment |
| --- | --- | --- |
| **A. `openspout/openspout`** | Purpose-built streaming spreadsheet writer, MIT, no framework coupling. Matches the lazy-row design the existing exports already use. | `composer require` **on the live server** — composer files are not in git |
| **B. `maatwebsite/excel`** | The Laravel-idiomatic choice, but it wraps PhpSpreadsheet, which holds the whole sheet in memory. Fine for a yard-sized report; the wrong shape for a year of movements. | Same, plus a heavier dependency tree |
| **C. Write a small xlsx writer** | ~150 lines over `ZipArchive` (present) — a worksheet, a shared-string table, a bold header row. Covers strings, numbers and dates, which is all a report needs. No dependency at all. | Ships as ordinary files, like everything else this session |
| **D. HTML table sent as `application/vnd.ms-excel`** | The classic shortcut. Excel opens it, but it is not an xlsx: modern Excel shows a "format doesn't match extension" warning every time, and it cannot be opened by anything else expecting a real spreadsheet. | No dependency |

**D is not worth doing** — a warning dialog on every export is a support call a
week, and the file is a lie about its own format.

**Between A and C**, the deciding factor is not code quality — it is that the
composer files are untracked. A is the better library; C is the only option that
deploys the same way as everything else here.

**A third path worth naming:** track `composer.json` and `composer.lock` in the
repository. That is a good idea regardless — without the lock file, the live
server and CI can silently drift onto different package versions — and it turns
A into an ordinary file-based deployment. It is a small change to make once and
it removes this whole class of problem.

---

## 5. Cover

**Unit** (no database — the writer is a pure function of headings and rows):

- a CSV row with a comma, a quote and a newline in it survives a round trip
- a leading `=`, `+`, `-` or `@` in a cell is neutralised (a CSV opened in Excel
  will otherwise execute it as a formula — a real injection route, and reports
  carry customer-supplied text like container numbers and notes)
- an xlsx with no rows still opens
- numbers stay numeric and dates stay dates rather than becoming text

**Feature, per report:**

- both formats download with the right filename and content type
- the filter set applied to the screen is applied to the export — this is the
  bug this kind of feature always has, and it is silent
- the exported row count matches what the screen says it found
- an unknown `format` falls back to CSV rather than erroring

**Phase 1 specifically:** the four migrated exports produce byte-identical output
to what they produced before.

---

## 6. Settled, and what it forces

**openspout, with `composer.json` and `composer.lock` brought into the
repository.** Tracking them is worth doing on its own account: without the lock
file the live server and CI can silently run different package versions, and this
plan is simply the first time that has actually blocked something.

**One catch.** Those two files do not exist in the working checkout at all — it
is a partial mirror, and `.gitignore` excludes only `.claude/`, so they were
never committed rather than deliberately ignored. They cannot be added from here,
and writing a `composer.json` from scratch would risk overwriting the real one.
That step belongs on the developer machine:

```bash
composer require openspout/openspout
git add composer.json composer.lock
git commit -m "Track composer files; add openspout for Excel exports"
git push
```

### So the build splits on the dependency, not around it

**Phase 1 needs nothing** — the shared exporter and moving the four existing CSVs
onto it. It ships as ordinary files and improves things immediately, whether or
not openspout ever arrives.

**Phase 2 degrades gracefully.** The xlsx writer checks for openspout and, when
it is absent, the Excel option is simply not offered — the same pattern
`App\Support\Qr` already uses for `simplesoftwareio/simple-qrcode`, which
returns null and lets the document render without a QR rather than failing. So
the code can ship before the dependency does, and Excel appears the moment the
package lands. No second deployment of application code.

This is the ordering that keeps a dependency the repository cannot yet carry off
the critical path.

---

## 7. Phase 1 as built

`App\Support\Export\TabularExport` owns the framing — response, filename,
content type, escaping — and nothing else. Reports keep their own headings and
row values verbatim, which is what makes a migration a move rather than a data
change.

**Four report exports moved onto it:** M&R Status, Daily Movements, Container
Inquiry, Job Margin. All four already produced `name-Ymd-His.csv`, so nobody's
downloads were renamed.

**`BankController::export` was deliberately left alone.** It is not a report: its
columns are the ones `import()` reads back, so it is a round-trip template, and
changing its framing or escaping risks breaking the import.

### Two things the rewrite had to preserve

**Chunking.** The exports page their queries so a year of movements need not fit
in memory. Rows are supplied as a callable returning a generator rather than an
array, so that survives — `chunk()` became `lazy()`, which pages identically.

**Container Inquiry's batching.** It chunks gate-ins and then batch-fetches that
chunk's gate-outs to avoid an N+1. A generator cannot yield from inside
`chunk()`'s callback, so the loop became `lazy()->chunk()`. That keeps both the
paging and the batching, but it is a real rewrite rather than a move — hence a
feature test pinning the headings, the gate-out pairing, the day count, and that
the screen's filters reach the file.

### One intended difference

A spreadsheet executes a cell beginning `=`, `+`, `-` or `@` as a formula, so
`=cmd|'/c calc'!A0` typed into a container remark is a working command injection
against whoever opens the export. Those cells are now prefixed with an
apostrophe, which makes the spreadsheet read them as text.

Two exclusions keep it from touching ordinary data, and they are why the migrated
exports still emit the same bytes:

- **Numbers pass through** — Job Margin prints negatives, and `-1250.00` has to
  stay a number rather than becoming `'-1250.00`.
- **Single characters pass through** — three of these reports use a lone `-` as
  their "no value" placeholder, and one character cannot be a formula.

---

## 8. Phase 2 as built

`TabularExport::xlsx()` writes through openspout, and `stream()` dispatches to it
when the format asks for Excel. All four screens gained an **Export Excel**
control beside their CSV one.

**The code ships before the dependency, and lies dormant until it arrives.**
`availableFormats()` reports Excel only when the writer is actually installed;
the four screens ask before drawing the button, and an explicit `format=xlsx`
falls back to CSV rather than failing. So this deploys now and lights up on
`composer require openspout/openspout` with no further application change.

**Availability is checked more precisely than "is the class there".** openspout 3
also has an `XLSX\Writer`, but it is not directly constructible — you had to go
through a factory that version 4 removed. The check therefore requires the
constructor to take no required argument, which identifies the exact API this
class was written against. An unexpected version means Excel is quietly not
offered rather than fatal on first click. Install with `composer require
openspout/openspout:^4.0`.

**Excel is written to a temp file, then streamed.** A spreadsheet is a zip and a
zip writes its index last, so it cannot go straight down the wire. Rows are still
pulled from the generator one at a time, so memory stays flat — it is disk that
is touched, and the temp file is deleted as the response finishes. There is a
test for that cleanup, because a leaked file per export fills a disk quietly.

**Excel cells are deliberately not formula-guarded.** The CSV apostrophe exists
because a CSV carries no types and the spreadsheet has to guess. An xlsx records
the type, so a string stays a string and cannot be reinterpreted as a formula;
adding the apostrophe there would put a stray character in front of legitimate
text. There is a test asserting its absence.

**One hardening found while wiring it:** the format arrives straight off a query
string, so `?format[]=x` hands the code an array. Casting that to a string would
raise, which is the opposite of what an unrecognised format should do — it now
normalises to empty and falls back to CSV.

### Verified

openspout could not be installed in the environment this was written in, so the
xlsx path shipped unexecuted. Installing it turned all six of those tests
green on the first run — the response headers, the bytes being a genuine zip
containing a worksheet, an empty report still opening, the temp-file cleanup, and
the absence of the CSV apostrophe. openspout 4's API is exactly `new Writer()`,
`openToFile()`, `Row::fromValues()` as written.

One test had to change, and it was the test rather than the code. The
unknown-format list included `xlsx`, written when xlsx genuinely was unknown;
once the package landed it became a supported format and correctly produced a
spreadsheet. Asking for Excel where it *cannot* be produced is a different claim
and now has its own test, which skips when the writer is present.

---

## 9. Phase 3 as built

Exports added to **Inventory**, **Available Stock** and the **Billing report** —
CSV and, where the writer is installed, Excel. Three rather than four: the
"Storage report" turned out to be a disk-usage screen, as noted in §1.

**Two of them already had an Export CSV button that did nothing.** Inventory and
Billing both linked to `?export=csv` on their own URL — a parameter neither
controller has ever read, so the page simply reloaded. Operators have had a
button that quietly failed. This phase is less "add an export" than "make the
existing one real".

**The query is defined once per report** and read by both the screen and the
export, so filtering the screen and then exporting cannot hand over the
unfiltered set. That is the bug this kind of feature always has and nobody
notices until a customer receives somebody else's containers, so each report has
a test for it.

**Available Stock exports the roll-up, not a container list**, because that is
what the screen is: one row per size · type · grade. Held and PTI-lapsed counts
ride along — they are computed for the screen already but only surface there in a
tooltip, and they are what somebody planning allocations wants beside the
not-ready total.

**One security gap closed on the way.** The new stock export needed adding to
`ContainerController`'s `can:containers.view` list. Without it the route was
reachable by anyone authenticated, since the middleware there is per-action —
and a hidden button is not a permission.

Values are written as the screen reads them rather than as the database stores
them: condition badges resolve to "Require Repair", cargo to "Empty"/"Laden",
M&R codes to their labels. Amounts are written unformatted so a spreadsheet sums
them as numbers instead of reading them as text.

---

## 10. Phase 4 — the finance reports

### 10.1 The real inventory

My earlier count of eleven was low. Searching route *names* for "report" missed
three screens that are reports by any reasonable reading, and one more lives
outside the `reports.` prefix:

| Report | Route | Shape |
| --- | --- | --- |
| Journals list | `finance.gl.journals.index` | flat, paginated |
| Account Ledger | `finance.gl.account-ledger` | flat entries + running balance |
| Trial Balance | `finance.gl.trial-balance` | flat rows, grouped by classification |
| FX Gain/Loss | `finance.reports.fx-gain-loss` | flat entries + per-source summary |
| FX Revaluation | `finance.reports.fx-revaluation` | flat |
| WHT Report | `finance.reports.wht-report` | flat |
| AR Aging | `finance.ar.aging` | one row per customer, bucket columns |
| AP Aging | `finance.ap.aging` | one row per supplier, bucket columns |
| Customer Statement | `finance.reports.customer-statement` | flat lines, **plus opening and closing balances** |
| Supplier Statement | `finance.reports.supplier-statement` | same |
| **Income Statement** | `finance.reports.income-statement` | **hierarchical** — groups → accounts, subtotals, totals |
| **Balance Sheet** | `finance.reports.balance-sheet` | **hierarchical** — three sections → groups → accounts |
| **VAT / SSCL Return** | `finance.reports.vat-sscl-return` | **two tables plus a computed box set** |
| Job Margin | `finance.reports.job-margin` | done in Phases 1–2 |

**Thirteen to do.** Ten are flat and are the same job as Phase 3. Three are not,
and they are what this section is really about.

### 10.2 The rule that matters more here than anywhere else

A financial figure that disagrees with the screen is worse than no export at all.
So no export may **re-derive** anything: each one reads the same computed data the
view is handed. For most of these the computation already lives in a service
(`StatementService`, `VatSsclReturnService`, `WhtReportService`,
`FxRevaluationService`) and is trivially shared.

Income Statement and Balance Sheet are the exceptions — each does roughly eighty
lines of grouping and balance arithmetic *inline in the controller*. Those have to
be extracted before they can be exported, or the file becomes a second
implementation of the accounts, free to drift from the screen. Extracting them is
most of the work in this phase, and it is worth doing on its own merits.

Every one of these methods calls `$this->authorize(...)` inline rather than
relying on constructor middleware, so each export repeats its screen's
authorization — the same gap Phase 3 turned up on the stock export.

### 10.3 The shape problem

A CSV is one flat table. Three of these reports are not.

- **Income Statement / Balance Sheet** are trees: section, group, account,
  subtotal, total.
- **VAT / SSCL Return** is a form: an output table, an input table, and a set of
  computed boxes (net VAT payable, SSCL payable).

Excel could hold each part on its own sheet. CSV cannot. Taking that route would
make the two formats carry genuinely different documents — and the entire point of
`TabularExport` is that they cannot diverge.

**The alternative is to flatten, with the structure carried in columns:**

```
Section    Level  Row Type   Code   Account / Label              Amount
────────────────────────────────────────────────────────────────────────
Income     1      Group      4000   Operating Revenue
Income     2      Account    4001   Storage Revenue           1,240,500.00
Income     2      Account    4002   Handling Revenue            318,900.00
Income     1      Subtotal   4000   Operating Revenue         1,559,400.00
…
           0      Total             TOTAL REVENUE             1,559,400.00
           0      Total             NET PROFIT                  412,880.00
```

Every row is a row, so both formats carry the same file; the reader can filter
`Row Type = Account` to get just the postings, or read it top to bottom as the
statement it is. The same three columns turn the VAT return into one sheet with
`Section` of `Output`, `Input` or `Summary`.

This is the standard shape for exported financial statements, and it is the only
one that keeps CSV and Excel honest about being the same report.

### 10.4 Order

**4a — the eight flat ones. — done** Trial Balance, Account Ledger, Journals, FX
Gain/Loss, FX Revaluation, WHT, AR Aging, AP Aging. Extract each query or service
call, export it. Directly analogous to Phase 3.

**4b — the two statements. — done** Flat lines, but opening and closing balances are
context rather than rows. They become labelled rows at the top and bottom, which
is how a printed statement already reads.

**4c — the three structured ones.** Income Statement and Balance Sheet need their
computation extracted from the controller first; the VAT return needs its three
parts stacked under a `Section` column.

4a and 4b are mechanical. 4c is where the judgement is, and it is last so the
first ten are shipping while it is discussed.

### 10.5 Cover

Per report, as in Phase 3: both formats download, the filters reach the file, an
unknown format falls back, and the export carries the screen's authorization.

Two additional checks that only matter here:

- **The export totals equal the screen's totals.** Asserted directly, because
  these are the numbers someone files a return with.
- **A hierarchical export's `Account` rows sum to its `Subtotal` rows, and those
  to its `Total`.** That is what makes the flattening trustworthy rather than
  merely tidy — if the tree were flattened wrongly, this is the assertion that
  catches it.

---

## 11. Phase 4a as built

CSV and Excel on **GL Journals, Account Ledger, Trial Balance, FX Gain/Loss,
FX Revaluation, WHT, AR Aging and AP Aging**.

Each screen's computation moved into a private method that both the view action
and the export read, so no file re-derives a financial figure. The trial-balance
test asserts that directly — the file's total row against the view's own totals —
and would catch anyone later "optimising" the export into a second query.

The buttons come from a new `partials.export-buttons`: eight screens with eight
different header layouts, so one partial beats eight bespoke edits, and Excel is
offered only where the writer is installed.

### Four things worth recording

**Route ordering.** `journals/export` was first registered after
`journals/{journal}`, where the wildcard swallows it and tries to resolve
"export" as a journal id. It now sits before it, with a note saying why.

**A misleading variable.** `accountLedger` passes `$runningBalance` to the view,
but it holds the *opening* figure — the view does its own running arithmetic and
never reads it. The export's closing-balance row initially used it and was
therefore wrong; it now accumulates its own, with the same sign rule the view
applies.

**Pagination.** The journals screen shows thirty at a time. A file containing
only the first thirty would be a quiet trap, so the export ignores pagination and
carries the whole filtered set, paged lazily. There is a test comparing its row
count to the paginator's `total()`.

**Structure in columns, not sheets.** Three of these eight carry more than one
table — FX Gain/Loss has a per-source roll-up, FX Revaluation a per-side summary
and a missing-rate list, WHT is grouped by party across two sections. Each is
flattened under a `Section` (and for WHT a `Row Type`) column rather than split
across sheets a CSV could not hold. That is the same shape §10.3 proposes for
4c, now proven on three real reports before the two statements need it.

The missing-rate rows on FX Revaluation are worth their own mention: a balance
that could not be revalued is omitted from the net, so dropping those rows would
make the total look complete when it is not.

---

## 12. Phase 4b as built

CSV and Excel on the **Customer** and **Supplier** statements.

Both screens already delegate to one `_statement` partial, so the buttons went in
once rather than twice — and only once a party is chosen, since the export
requires one and offering it on the empty filter screen would hand back a 404.

Opening balance, totals and closing balance bracket the rows as labelled lines,
the same treatment the account ledger got in 4a. They are context rather than
transactions, but a statement cannot be reconciled without them, which is exactly
why a printed one carries them too.

**Nothing is recomputed.** `StatementService` already tracks a running balance
per line, so the file reports what it computed rather than adding the columns up
again and risking a different answer. A test compares the exported closing
balance with the screen's.

**Ten of thirteen finance reports now export**, all routed and all repeating
their screen's authorization — verified rather than assumed, since these
controllers authorize per-action and an export that forgets is simply open.

Remaining: the three structured ones in 4c.

---

## 13. Phase 4c as built

CSV and Excel on the **Income Statement**, the **Balance Sheet** and the
**VAT / SSCL Return** — the three that could not simply be walked row by row.

**The two statements share one flattening.** `Section, Level, Row Type, Code,
Account / Label, Amount`, exactly as §10.3 proposed, produced by a single
private `hierarchySection()` that both exports call. A reader who wants the
account detail filters `Row Type` to `Account`; one who wants the shape sorts by
`Section` and `Level`. Nothing about the tree is expressed by indenting a label,
because indentation is invisible to everything but an eye.

**Both computations came out of their actions first.** `incomeStatementData()`
and `balanceSheetData()` now hold the grouping and balance arithmetic, and the
action is one line that hands it to the view. Eighty lines of accounts
duplicated into an export is a statement free to drift from the one on screen,
and nobody would notice until someone compared the two.

**Every group carries a subtotal, even where the screen suppresses it.** Both
screens hide the subtotal when there is only one group — on paper that is
tidiness, but in a file it breaks the arithmetic, because a reader summing the
`Subtotal` rows would come up short by whatever the suppressed group held.
Untouched accounts are dropped, matching the screens, which costs nothing: a
zero adds zero to every subtotal above it.

**Current Year Earnings is not an account**, so an export that walked the equity
ladder would leave it out and the sheet would silently fail to balance — a file
has no warning triangle. It is emitted as its own one-row group, so the equity
accounts still add up to `TOTAL EQUITY`. The balance difference the screen shows
as a tick or a triangle is stated as a figure on a `Check` row.

**The VAT return is one sheet**, with `Section` of `Output`, `Input` or
`Summary` carrying what the two tables and two settlement panels carried on
screen. Each settlement figure stays in its own column, so a reader summing a
column is reading one quantity throughout. Input SSCL is carried and labelled
*not creditable*: dropping it would hide a real cost from the filer, and leaving
it unlabelled in a column of SSCL figures would read as an offset, which it is
not.

**The invariant is tested, not asserted.** Within each section, the `Account`
rows sum to the `Subtotal` rows and those to the `Total` — the property that
makes the flattening trustworthy rather than merely tidy. That, plus agreement
with the screen on net profit, current-year earnings, the balance difference and
both VAT settlements.

**Thirteen of thirteen finance reports now export**, all in both formats, all
repeating their screen's `authorize()`.

