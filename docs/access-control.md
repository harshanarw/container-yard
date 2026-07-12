# Access Control Reference

> Auto-generated from `config/modules.php` and `RolePermissionSeeder`. **Do not hand-edit** — regenerate when modules or role assignments change.

This system uses **role-based access control (RBAC)**. Every screen and action is gated by a permission **code** in the form `module.action` (e.g. `yard.cargo-transfer.complete`). There are **58 modules** and **252 permission codes** across **10 sections**.

## How it works

- **Codes** are defined in `config/modules.php` (the single source of truth).
- **Roles** are granted codes in `RolePermissionSeeder` using patterns with wildcards — e.g. `yard.*` grants every `yard.…` code, `billing.*.view` grants view on every billing module, `*` grants everything.
- **Assign interactively** in **Settings → Access Control**, or run the seeders below.

```bash
php artisan permissions:sync                        # register codes from config/modules.php
php artisan db:seed --class=RolePermissionSeeder    # (re)assign role → code patterns
php artisan config:clear                            # modules.php is cached config
```

> **System-only** modules are reserved for the System Administrator super-user (who bypasses RBAC) and are not grantable to ordinary roles.

## Roles at a glance

| Role | Scope |
|------|-------|
| **Administrator** | Full access to everything (`*`). |
| **Billing Manager** | All billing; view + toggle on master tariffs; view customers, containers, reports. |
| **Billing Clerk** | Billing view / create / PDF; view customers & containers. |
| **Yard Supervisor** | All yard operations, surveys, estimates, work orders, approvals, containers; view customers & reports. |
| **Gate Officer** | Gate-in/out, reefer sessions, hires, cargo transfers, guard post; view containers & customers. |
| **Inspector** | Surveys & estimates; view containers & customers. |
| **Security Officer** | Guard post; view containers. |

> The **System Administrator** is a super-user that bypasses RBAC entirely — it is not listed above.

## Modules & permissions

### Billing

| Module | Code | Actions | What it controls | Default roles |
|--------|------|---------|------------------|---------------|
| **Storage Billing** | `billing.storage` | `view` `create` `edit` `delete` `approve` `pdf` `email` | Standalone storage invoices (read-only entry; generated from Storage & Handling). | Administrator; Billing Manager; Billing Clerk _(view, create, pdf)_ |
| **Storage & Handling Billing** | `billing.storage-handling` | `view` `create` `edit` `delete` `approve` `pdf` | Generate & manage combined storage + lift-on/off (handling) invoices. | Administrator; Billing Manager; Billing Clerk _(view, create, pdf)_ |
| **Reefer Electricity Billing** | `billing.reefer` | `view` `create` `edit` `delete` `approve` `pdf` | Reefer electricity invoices from completed plug sessions. | Administrator; Billing Manager; Billing Clerk _(view, create, pdf)_ |
| **Repair Invoices** | `billing.repair` | `view` `create` `edit` `delete` `approve` `pdf` | Repair invoices raised from approved estimates. | Administrator; Billing Manager; Billing Clerk _(view, create, pdf)_ |
| **General Invoicing** | `billing.general` | `view` `create` `edit` `delete` `post` `void` `pdf` `email` | Miscellaneous AR: tax invoices, invoices, debit notes; posting to the ledger. | Administrator; Billing Manager; Billing Clerk _(view, create, pdf)_ |

### Yard

| Module | Code | Actions | What it controls | Default roles |
|--------|------|---------|------------------|---------------|
| **Yard Gate Operations** | `yard` | `view` `gate-in` `gate-out` `movement-edit` `movement-delete` `backdate` | The gate: record gate-in / gate-out movements, edit/delete them, backdate. | Administrator; Yard Supervisor; Gate Officer _(view, gate-in, gate-out)_ |
| **Yard Jobs** | `yard.jobs` | `view` `edit` | Yard jobs — the umbrella that ties a container’s movements together. | Administrator; Yard Supervisor |
| **Reefer Sessions** | `yard.reefer` | `view` `plug-in` `plug-out` `temp-log` | Reefer plug sessions: plug-in / plug-out and temperature logging. | Administrator; Yard Supervisor; Gate Officer |
| **Container Hires** | `yard.hire` | `view` `create` `off_hire` `cancel` | Put a container on hire to a customer and off-hire / cancel it. | Administrator; Yard Supervisor; Gate Officer _(view, create)_ |
| **Cargo Transfers (Container Substitution)** | `yard.cargo-transfer` | `view` `create` `complete` | Cargo rental / container substitution: swap cargo into a yard box, gate the empty box out, complete on collection. | Administrator; Yard Supervisor; Gate Officer |

### Operations

| Module | Code | Actions | What it controls | Default roles |
|--------|------|---------|------------------|---------------|
| **Surveys & Inquiries** | `surveys` | `view` `create` `edit` `delete` | Container surveys & inquiries (damage/condition/PTI). | Administrator; Yard Supervisor; Inspector |
| **Repair Estimates** | `estimates` | `view` `create` `edit` `delete` `approve` | Repair estimates and their approval. | Administrator; Yard Supervisor; Inspector |
| **Work Orders** | `work-orders` | `view` `create` `edit` `delete` `approve` | Work orders generated from approved estimates. | Administrator; Yard Supervisor |
| **Approvals** | `approvals` | `view` `approve` `reject` | The approval inbox: approve / reject pending requests. | Administrator; Yard Supervisor |
| **Guard Post** | `guard-post` | `view` `create` `edit` `delete` | Guard-post capture queue at the entrance. | Administrator; Gate Officer _(view, create)_; Security Officer |

### Customers & Containers

| Module | Code | Actions | What it controls | Default roles |
|--------|------|---------|------------------|---------------|
| **Customers** | `customers` | `view` `create` `edit` `delete` | Customer master records. | Administrator; Billing Manager _(view)_; Billing Clerk _(view)_; Yard Supervisor _(view)_; Gate Officer _(view)_; Inspector _(view)_ |
| **Containers** | `containers` | `view` `create` `edit` `delete` `hold` `pti` | Container master records; holds and PTI. | Administrator; Billing Manager _(view)_; Billing Clerk _(view)_; Yard Supervisor; Gate Officer _(view)_; Inspector _(view)_; Security Officer _(view)_ |
| **Container Bookings** | `container-bookings` | `view` `create` `edit` `allocate` `cancel` `delete` | Export bookings (EDO) and container allocation against them. | Administrator |

### Masters — Tariffs

| Module | Code | Actions | What it controls | Default roles |
|--------|------|---------|------------------|---------------|
| **Reefer Electricity Tariff** | `masters.reefer-tariff` | `view` `create` `edit` `delete` `toggle` | Reefer electricity rate cards. | Administrator; Billing Manager _(view, toggle)_ |
| **Storage Tariff** | `masters.storage-tariff` | `view` `create` `edit` `delete` `toggle` | Storage rate cards (per customer / equipment). | Administrator; Billing Manager _(view, toggle)_ |
| **Handling Tariff** | `masters.handling-tariff` | `view` `create` `edit` `delete` `toggle` | Lift-on / lift-off (handling) rate cards. | Administrator; Billing Manager _(view, toggle)_ |
| **M&R Tariff** | `masters.mr-tariff` | `view` `create` `edit` `delete` | Maintenance & Repair rate cards. | Administrator; Billing Manager _(view)_ |
| **Washing / Cleaning Tariff** | `masters.washing-tariff` | `view` `create` `edit` `delete` `toggle` | Washing / cleaning rate cards. | Administrator; Billing Manager _(view, toggle)_ |

### Masters — Operations

| Module | Code | Actions | What it controls | Default roles |
|--------|------|---------|------------------|---------------|
| **Gate-In Job Types** | `masters.job-types` | `view` `create` `edit` `delete` | Gate-in / gate-out job type definitions. | Administrator; Billing Manager _(view)_ |

### Masters — Reference

| Module | Code | Actions | What it controls | Default roles |
|--------|------|---------|------------------|---------------|
| **Charge Codes** | `masters.charge-codes` | `view` `create` `edit` `delete` | Billing charge codes and their revenue mapping. | Administrator; Billing Manager _(view)_ |
| **Tax Codes** | `masters.tax-codes` | `view` `create` `edit` `delete` | VAT / SSCL tax codes. | Administrator; Billing Manager _(view)_ |
| **Currencies** | `masters.currencies` | `view` `create` `edit` `delete` | Currencies used across the system. | Administrator; Billing Manager _(view)_ |
| **Banks** | `masters.banks` | `view` `create` `edit` `delete` | Bank accounts master. | Administrator; Billing Manager _(view)_ |
| **Exchange Rates** | `masters.exchange-rates` | `view` `create` `edit` `delete` | FX rates for foreign-currency documents. | Administrator; Billing Manager _(view)_ |
| **Equipment Types** | `masters.equipment-types` | `view` `create` `edit` `delete` | Container equipment types (size/type/ISO). | Administrator; Billing Manager _(view)_ |
| **Container Grades** | `masters.container-grades` | `view` `create` `edit` `delete` | Container condition grades. | Administrator; Billing Manager _(view)_ |
| **Storage Zones & Slots** | `masters.storage-zones` | `view` `create` `edit` `delete` | Yard zones and stacking slots. | Administrator; Billing Manager _(view)_ |
| **Customer Types** | `masters.customer-types` | `view` `create` `edit` `delete` | Customer classification types. | Administrator; Billing Manager _(view)_ |
| **M&R Codes** | `masters.mr-codes` | `view` `create` `edit` `delete` | M&R code sets (location/component/damage/repair). | Administrator; Billing Manager _(view)_ |
| **Repair Categories** | `masters.repair-categories` | `view` `create` `edit` `delete` | Repair categories used to group work. | Administrator; Billing Manager _(view)_ |
| **Damage Assessment Rules** | `masters.damage-rules` | `view` `create` `edit` `delete` | Damage-to-repair assessment rules. | Administrator; Billing Manager _(view)_ |
| **Checklist Items** | `masters.checklist-items` | `view` `create` `edit` `delete` | Survey checklist master items. | Administrator; Billing Manager _(view)_ |
| **Countries & States 🔒** | `masters.countries` | `view` `create` `edit` `delete` | Countries & state/province reference (system-only). | _System Administrator only_ |

### Finance

| Module | Code | Actions | What it controls | Default roles |
|--------|------|---------|------------------|---------------|
| **Finance Setup (Fiscal Years)** | `finance.setup` | `view` `create` `edit` `delete` | Fiscal (financial) years. | Administrator |
| **Accounting Periods** | `finance.periods` | `view` `close` `reopen` | Accounting periods: close / reopen. | Administrator |
| **Chart of Accounts** | `finance.coa` | `view` `create` `edit` `delete` | Chart of accounts. | Administrator |
| **Account Mappings** | `finance.mappings` | `view` `create` `edit` `delete` | Account mappings (which GL account each transaction hits). | Administrator |
| **General Ledger** | `finance.gl` | `view` `create` `post` `void` | General ledger: manual journals, posting, voiding. | Administrator |
| **AR / Invoice Posting** | `finance.ar` | `view` `post` `void` | AR invoice postings — view, post, void. | Administrator |
| **Receipts** | `finance.receipts` | `view` `create` `edit` `confirm` `void` `delete` `pdf` `email` | Customer receipts: create, confirm (posts cash), void. | Administrator |
| **Payment Vouchers** | `finance.vouchers` | `view` `create` `edit` `confirm` `void` `pdf` `email` | Supplier payment vouchers. | Administrator |
| **AR Credit Notes** | `finance.ar-credit-notes` | `view` `create` `edit` `approve` `delete` `pdf` `email` | AR credit notes and their approval. | Administrator |
| **AP Credit Notes** | `finance.ap-credit-notes` | `view` `create` `edit` `approve` `delete` `pdf` `email` | AP (supplier) credit notes and approval. | Administrator |
| **AP / Supplier Invoices** | `finance.ap` | `view` `create` `post` `void` `delete` | AP / supplier invoices and posting. | Administrator |
| **Bank Reconciliation** | `finance.bank-reconciliation` | `view` `create` `edit` `delete` | Bank statement import and reconciliation. | Administrator |

### Reports

| Module | Code | Actions | What it controls | Default roles |
|--------|------|---------|------------------|---------------|
| **Reports** | `reports` | `view` | Operational & financial reports. | Administrator; Billing Manager; Yard Supervisor |
| **Container Inquiry** | `container-inquiry` | `view` | Container lookup / inquiry screen. | Administrator |

### Settings

| Module | Code | Actions | What it controls | Default roles |
|--------|------|---------|------------------|---------------|
| **User Management** | `settings.users` | `view` `create` `edit` `delete` | User accounts. | Administrator |
| **Company Settings 🔒** | `settings.company` | `view` `edit` | Company settings (system-only). | _System Administrator only_ |
| **Document Storage 🔒** | `settings.cloud-storage` | `view` `edit` | Document storage configuration (system-only). | _System Administrator only_ |
| **Approval Workflows** | `settings.approval-workflows` | `view` `create` `edit` `delete` | Approval workflow rules. | Administrator |
| **Access Control** | `access-control` | `view` `create` `edit` `delete` | Roles & permissions administration. | Administrator |
| **Audit Log** | `audit-log` | `view` | System audit trail (read-only). | Administrator |

---

🔒 = system-only module (System Administrator only). Role cells show the role name for **full** access, or `(specific actions)` for partial access. A blank/`none` cell means no default role has it — grant it manually in Settings → Access Control.
