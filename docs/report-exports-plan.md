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
| Storage report | `storage.report` | **none** |
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

**Four of eighteen report screens export anything. None exports Excel.**

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

**Phase 3 — the four operational reports that have nothing:** Inventory, Stock,
Storage, Billing. These are the ones named in the request.

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
