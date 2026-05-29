# Container Yard Database Seeder Guide

## Overview

The seeder system is split into two parts:

### 1. **Core Seeders** (Always Run)
These are executed automatically with `php artisan migrate:fresh --seed` and populate all master/reference data needed for the application to function.

**22 core seeders in dependency order:**
1. `CountrySeeder` - Countries master list
2. `CountryStateSeeder` - States/provinces/districts (depends on countries)
3. `EquipmentTypeSeeder` - Container equipment types
4. `CurrencySeeder` - Currencies
5. `TaxCodeSeeder` - Tax codes (VAT, SSCL, etc.)
6. `ChargeCodeSeeder` - Charge codes for invoicing
7. `YardLocationSeeder` - Yard zones, bays, tiers
8. `ChecklistMasterItemSeeder` - Inspection checklist template items
9. `MrCodeSeeder` - M&R maintenance/repair codes (location, component, damage, repair, material, responsibility)
10. `SystemAdminSeeder` - Default system admin user
11. `UserSeeder` - Sample staff users (inspector, supervisor, billing clerk, gate operator)
12. `UserProfileSeeder` - Extended user profile fields
13. `SystemSettingsSeeder` - Company settings, prefixes, defaults
14. `CustomerTypeSeeder` - Customer type labels (Shipping Line, Forwarder, etc.)
15. `CustomerTypeShortCodeSeeder` - Short codes on customer types
16. `CustomerSeeder` - Sample shipping lines and customers
17. `ContainerSeeder` - Sample containers (4 base + others via InquirySeeder)
18. `StorageTariffSeeder` - Storage rate tariffs (headers + details)
19. `TariffCargoStatusSeeder` - Backfill cargo_status on tariff details
20. `HandlingTariffSeeder` - Handling charges tariffs per customer
21. `TariffChargeCodeBackfillSeeder` - Link charge_code_id on tariff rows
22. `MrTariffSeeder` - M&R repair tariffs (standard + customer-specific)
23. `InquirySeeder` - Sample inspections/surveys
24. `EstimateSeeder` - Sample repair estimates
25. `NormalizeCargoStatusSeeder` - Backfill cargo_status: 'full' → 'laden'
26. `InvoiceValueBackfillSeeder` - Backfill line_value/total_value on invoices

**What exists after core seeders:**
- ✅ All master data (countries, customers, equipment types, codes, tariffs)
- ✅ All users and roles
- ✅ 4-11 sample containers (depends on InquirySeeder)
- ✅ 8 sample inspections/surveys
- ✅ 6 sample repair estimates
- ❌ **NO gate movements**
- ❌ **NO yard storage records**
- ❌ **NO invoices**
- ❌ **NO work orders**

### 2. **Optional Demo Data Seeders** (Manual Run)
These are NOT run by default. Run them separately to populate transaction/operational history for testing and demos.

**6 optional demo seeders (run in order or together):**

1. **`GateMovementSeeder`** - Gate in/out movements
   - Creates realistic gate in/out cycles for all 11 sample containers
   - 50% of containers get a gate out movement
   - Timestamps aligned with container gate_in_date
   
2. **`YardStorageSeeder`** - Yard storage calculations
   - Calculates storage periods from gate movements
   - Applies storage tariffs
   - Generates storage charges and taxes
   - **Requires:** GateMovementSeeder

3. **`StorageInvoiceSeeder`** - Storage invoices
   - Creates invoices from yard storage records
   - Generates invoice details with line items
   - **Requires:** YardStorageSeeder

4. **`StorageHandlingInvoiceSeeder`** - Handling invoices
   - Creates handling invoices for each gate movement
   - Uses handling tariffs per customer
   - Applies lift-off rates for gate-in, lift-on rates for gate-out
   - **Requires:** GateMovementSeeder

5. **`WorkOrderSeeder`** - Work orders
   - Creates work orders from approved estimates
   - Converts estimate lines to work order lines
   - **Requires:** EstimateSeeder (and manually mark estimates as 'approved')

6. **`RepairInvoiceSeeder`** - Repair invoices
   - Creates repair invoices from work orders
   - Calculates labor + materials + ancillary costs
   - **Requires:** WorkOrderSeeder

---

## Usage

### Fresh Installation
```bash
# 1. Create database and run migrations + core seeders
php artisan migrate:fresh --seed

# This gives you a clean, working foundation with master data and sample records.
```

### Add Optional Demo Data
```bash
# Run all optional demo seeders at once
php artisan db:seed --class=OptionalDemoDataSeeder

# Or run individually if you want more control:
php artisan db:seed --class=GateMovementSeeder
php artisan db:seed --class=YardStorageSeeder
php artisan db:seed --class=StorageInvoiceSeeder
php artisan db:seed --class=StorageHandlingInvoiceSeeder

# For work orders & repair invoices (requires manually approving some estimates first):
php artisan db:seed --class=WorkOrderSeeder
php artisan db:seed --class=RepairInvoiceSeeder
```

### Reset to Clean State
```bash
# Delete all optional demo data and start over
php artisan migrate:fresh --seed
```

---

## Data Relationships

### Container Lifecycle
```
Container (from ContainerSeeder)
  ↓
GateMovement (in) ← GateMovementSeeder
  ↓
YardStorage ← YardStorageSeeder
  ↓
StorageInvoice ← StorageInvoiceSeeder
  ↓
GateMovement (out) ← GateMovementSeeder (50% of containers)
```

### Repair Workflow
```
Inquiry/Survey (from InquirySeeder)
  ↓
Estimate (from EstimateSeeder)
  ↓
WorkOrder ← WorkOrderSeeder (only from approved estimates)
  ↓
RepairInvoice ← RepairInvoiceSeeder
```

### Handling Charges
```
GateMovement (any) ← GateMovementSeeder
  ↓
StorageHandlingInvoice ← StorageHandlingInvoiceSeeder
  └─ Uses HandlingTariff per customer/size
```

---

## Sample Data Summary

### After `php artisan migrate:fresh --seed` (Core Only)
| Table | Records | Notes |
|-------|---------|-------|
| users | 8 | Admin + 7 staff |
| customers | 10 | Shipping lines, forwarders |
| containers | 11 | 4 from ContainerSeeder + 7 extra from InquirySeeder |
| inquiries | 8 | Surveys, inspections |
| estimates | 6 | Repair estimates (various statuses) |
| gate_movements | 0 | **NOT SEEDED** |
| yard_storage | 0 | **NOT SEEDED** |
| storage_invoices | 0 | **NOT SEEDED** |
| storage_handling_invoices | 0 | **NOT SEEDED** |
| work_orders | 0 | **NOT SEEDED** |
| repair_invoices | 0 | **NOT SEEDED** |

### After `php artisan db:seed --class=OptionalDemoDataSeeder`
| Table | Records | Notes |
|-------|---------|-------|
| gate_movements | ~16-17 | ~11 in + ~5-6 out |
| yard_storage | ~11 | One per container |
| storage_invoices | ~11 | Based on storage records |
| storage_handling_invoices | ~16-17 | Based on gate movements |
| work_orders | ~3-4 | Only if estimates are marked approved |
| repair_invoices | ~3-4 | Based on work orders |

---

## Customization

### Adding More Sample Data
Edit the seeder files directly. Example seeders use realistic:
- Dates (relative to now/today)
- Container numbers (realistic IICL format)
- Names and contact info
- Commercial rates from actual tariffs

### Adjusting Tariffs or Costs
Edit the source seeders:
- `StorageTariffSeeder` for storage rates
- `HandlingTariffSeeder` for handling rates
- `MrTariffSeeder` for repair rates

Changes take effect on next `php artisan migrate:fresh --seed`.

### Linking to Your Own Data
The seeders use `firstOrCreate()` where appropriate, so if you have actual customer/tariff data in production, update the seeder lookups to match.

---

## Troubleshooting

### "No active tariff found for size X"
**Cause:** StorageTariffSeeder didn't create tariffs for that container size.
**Fix:** Check `StorageTariffSeeder` includes that size, or manually add via admin UI.

### "No containers found" when running GateMovementSeeder
**Cause:** ContainerSeeder didn't run first.
**Fix:** Run `php artisan migrate:fresh --seed` before optional seeders.

### Work orders show 0 records after WorkOrderSeeder
**Cause:** No estimates have status='approved'.
**Fix:** Manually change a few estimate statuses to 'approved' in the database, then run WorkOrderSeeder.

### Invoices show $0 total
**Cause:** Tariff rates are 0 or missing.
**Fix:** Check `StorageTariffSeeder` and `HandlingTariffSeeder` have non-zero rates.

---

## Notes for Developers

- **Seeders are idempotent:** Running the same seeder twice won't duplicate data (uses `firstOrCreate()` where safe).
- **Dependency order matters:** Run in the listed order, or use `OptionalDemoDataSeeder` which handles it automatically.
- **Timestamps are realistic:** Most demo data uses relative dates (`now()->subDays(X)`) so aging makes sense.
- **Currency:** Defaults to USD; override in seeder if needed.
- **Tax:** Defaults to 18% VAT; adjust `$taxPercentage` in seeders as needed.

---

## Quick Start Checklist

```
[ ] 1. php artisan migrate:fresh --seed               # Fresh install with core data
[ ] 2. Verify containers, estimates, inquiries exist   # Check basic data
[ ] 3. php artisan db:seed --class=OptionalDemoDataSeeder  # Add transaction history
[ ] 4. Log in and explore gate movements, invoices, etc.
[ ] 5. Ready for testing!
```
