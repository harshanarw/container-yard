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

**Phase 1 — the exporter, and the four existing CSVs moved onto it.**
No new report gains an export yet, and no output changes. This is the phase that
proves the exporter produces byte-identical CSV to what those four already emit,
which is exactly the guarantee that makes the rest safe.

**Phase 2 — Excel on those same four.** One format flag. If Phase 1 is right,
this is small.

**Phase 3 — the four operational reports that have nothing:** Inventory, Stock,
Storage, Billing. These are the ones named in the request.

**Phase 4 — the eleven finance reports.** A bigger family and a separate
conversation: several of them (Balance Sheet, Income Statement) are hierarchical
rather than tabular, so "export the table" is not a well-defined operation for
them without deciding how to flatten the tree.

---

## 4. The decision: how to write xlsx

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
