# Job Costing — Analysis & Implementation Plan

Tie **both revenue (AR) and cost (AP)** to jobs / containers so every container
visit produces a **Revenue − Cost = Margin** figure, reconciled to the general
ledger.

---

## 1. What this is (accounting terms)

**Job costing** = *container-visit P&L*. Every revenue and cost item is tagged
with a **job dimension** (`yard_job_id`) and **container** (`container_id`), and
the P&L aggregates by that tag. Two universal principles:

1. **Dimension accounting** — the job/container is a *tag* carried on every
   financial line: AR invoice lines, AP bill lines, and GL journal lines.
2. **Reconciles to the GL** — with the dimension on the ledger line, the sum of
   all job margins equals the GL P&L.

Maps to the requirements:
- *"Other income for a job"* → an **AR line** tagged to the job.
- *"On-hire fee from the shipping line"* → an **AP (cost) line** tagged to the job.
- *"On-hire→off-hire as a separate job"* → the hire period is its own job.

---

## 2. Current state (findings)

| Layer | Job link? | Notes |
|---|---|---|
| `JobPnlService` | by `container_id`, **revenue-only** | Sums storage+handling+reefer+repair *invoiced* by container. No cost side, no general invoices, not by `job_id`. |
| Repair / estimate / WO / reefer-session | ✅ `yard_job_id` (000262) | Job-linked |
| Storage / reefer / S&H invoices | ⚠️ container at **line** level | No job header |
| **General invoices** | ❌ nothing | AR "other income" gap |
| **Supplier invoices / payment vouchers / AP credit notes** | ❌ nothing | Entire cost side undimensioned |
| **GL** (`gl_journals` / `gl_entries`) | ❌ no dimension | Can't reconcile job costing to the ledger |
| Container hire | yard-as-**lessor** (revenue out) | No yard-as-lessee (cost); hires not linked to a job |

**Three structural problems:** (1) no cost side — it's revenue tracking, not job
costing; (2) general AR + all AP undimensioned; (3) P&L is container-scoped, so a
container's many gate-in/out cycles get mis-attributed to whichever job is viewed.

---

## 3. Industry standard vs. here

Depot / freight ERP norm: `job_id` + `container_id` on **every** AR & AP line
and on GL lines; Job P&L = Revenue − Cost = Margin, reconciled to GL; misc income
and external costs both tagged to the job; lessor on-hire is a costed job. The
foundation here is half-built (revenue, container-scoped).

---

## 4. Recommended solution — a Job Costing dimension

Put `yard_job_id` + `container_id` on every financial line (AR **and** AP),
propagate it to the GL entry at posting time, capture internal costs, and rebuild
`JobPnlService` two-sided, aggregating by `job_id`.

- **Line-level tagging** (one invoice/bill can span jobs); header job flows to
  lines as a default.
- **AR revenue lines**: `general_invoice_lines` (+ storage/reefer/repair, mostly
  container-derivable).
- **AP cost lines**: `supplier_invoice_lines`, `payment_vouchers` (direct
  expense).
- **GL propagation**: the dimension originates on the document line; the
  `PostingEngine` copies it onto the `gl_entries` (P&L) line at post time; manual
  journals get a job/container picker. Document-based and GL-based agree by
  construction.
- **On-hire-from-lessor = its own job**: a lessee on-hire creates a `YardJob`,
  the lessor fee is AP cost tagged to it, off-hire closes it. (Reuses the
  `container_hire_id` slot reserved on `cargo_transfers`.)

### Why dimension the GL (not just documents)
Document-only job costing is sufficient for ~90% of a container job's money and
is a fine operational view. Dimensioning the GL adds: (1) **reconciliation** —
sum of job margins = GL P&L; (2) **non-invoice items** — manual journals,
adjustments, write-offs, FX, allocations; (3) **single source of truth**. The
tag flows document → GL automatically, so both views stay in sync.

### Draft vs Posted in the P&L
Shown in **separate layers**, never mixed:

| Layer | Source | Counts toward margin? |
|---|---|---|
| Accrued / WIP | live accruals (storage running, estimates not billed) | No — pipeline |
| Draft | draft invoices/bills (sub-ledger) | No — pipeline |
| **Realized** | **posted `gl_entries`** | **Yes — the margin** |

The realized P&L reads from posted `gl_entries` (reconciled; drafts can't inflate
it); draft + accrued show as a separate **Pending / Pipeline** figure.

### Job costing is a **P&L-account** concept
Only **revenue** and **expense** (P&L) GL lines carry the job dimension. Balance-
sheet control lines (AR, AP, bank, tax) do not — they're not part of job margin.

---

## 5. Implementation plan (phased)

### Phase A — Capture the dimension (documents + GL)
- **A1 Schema**: nullable `yard_job_id` + `container_id` on `general_invoice_lines`,
  `supplier_invoice_lines`, `payment_vouchers`, and `gl_entries`.
- **A2 Models**: fillable + `yardJob()` / `container()` relations.
- **A3 Posting propagation**: `PostingEngine` accepts + persists per-line
  `job_id`/`container_id`; AR (general) revenue lines and AP (supplier invoice +
  voucher) expense lines carry their dimension onto the GL entry; manual journals
  gain a job/container picker.
- **A4 UI**: job/container picker on general-invoice lines, supplier-invoice
  lines, payment vouchers; "Bill/Cost to Job" prefill.
- **A5 Guards**: container-belongs-to-job soft check; keep nullable.
- **A6 Backfill**: derive job for container-linked repair/storage/reefer.
- **A7 Tests**: tagged AR/AP line → posted `gl_entries` carries the dimension.

### Phase B — Job Costing engine (`JobPnlService` v2)
Two-sided, keyed by `job_id`, reading posted `gl_entries` for **Realized** margin
and draft docs + accruals for **Pending**. Revenue (storage, handling, reefer,
repair, general) − Cost (supplier bills, direct vouchers, WO actuals, lessor) =
Margin, with per-container breakdown and drill-down.

### Phase C — On-hire-from-lessor as a job
Lessee on-hire → creates a `YardJob`, captures lessor cost (AP) against it;
off-hire closes it. Wire to cargo-substitution `container_hire_id`.

### Phase D — (folded into A) GL dimensioning — chosen: **yes**, done via A3 propagation.

### Phase E — Reporting + regression tests.

---

## 6. Decisions taken
- **Ledger depth**: dimension **both** source documents and GL lines.
- **Lessor cost basis**: actual supplier invoice for now; per-diem accrual later
  if needed.
- **Start**: Phase A, beginning A1–A3 (schema + models + posting propagation).
