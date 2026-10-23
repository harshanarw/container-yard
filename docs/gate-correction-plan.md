# Gate corrections: equipment type, and the empty-with-a-seal

Two requests, one small and one that needs care:

1. A wrong equipment type picked at gate-in must be correctable from Movement
   Edit — behind a deliberate control, and only while no size-sensitive bill
   has been raised.
2. An **Empty** container with a seal number must be refused at save.

---

## What the code does today

Six findings. Three change what the work is.

### 1. `updateMovement` already accepts `equipment_type_id` — with no UI and no guard

`YardController:1852` puts `equipment_type_id` in the validation rules for a
gate-in, and `:1902` acts on it:

```php
if (!empty($validated['equipment_type_id'])) {
    $eqt = EquipmentType::find($validated['equipment_type_id']);
    $updateData['size']           = $eqt->size;
    $updateData['container_type'] = $eqt->type_code;
}
```

No field on `movement-edit.blade.php` posts it — Size / Type is rendered as
read-only text at line 85. So this is dead code from the screen's point of view
and a **back door** from the request's: anyone who can edit a movement can POST
`equipment_type_id` and silently change that movement's size, with no check on
whether it has been billed.

It also writes **only the movement**. `containers.equipment_type_id` is left
alone, and so is the paired gate-out. So using it today splits the record rather
than correcting it.

This is not new work to be added. It is existing behaviour to be put behind the
control you are asking for.

### 2. The Container Master screen already changes equipment type, freely

`ContainerController:385`:

```php
if (!empty($data['equipment_type_id'])) {
    $eqt = EquipmentType::find($data['equipment_type_id']);
    $data['size']      = $eqt->size;
    $data['type_code'] = $eqt->type_code;
}
```

No billing check, no movement sync. So a master record and its movements can
already disagree, and there is no screen that would show you they do.

Whatever guard we build for Movement Edit has to apply here too, or the rule has
a door next to it standing open.

### 3. `yard_storage` carries no size or equipment type

Checked the schema: the storage row has `container_id` and dates and no
equipment columns at all. Storage billing resolves the type **from the container
master, at preview time** (`StorageHandlingController` → `$container->equipment_type_id`
→ `StorageMasterDetail::resolve()`).

Two consequences:

- Correcting the **master** is what actually re-prices an unbilled stay. Correct
  only the movement and the storage still prices at the wrong rate.
- No storage row needs rewriting. Nothing to migrate.

### 4. Only three things are size- or type-sensitive

| Priced by | Keyed on | Affected by a correction |
| --- | --- | --- |
| Storage | `storage_master_details.equipment_type_id` | **yes** |
| Handling (lift on/off) | `handling_tariff_rates.container_size` | **yes** |
| Washing | `washing_tariffs.container_size` (nullable = all sizes) | **yes** |
| Reefer electricity | customer + service type + mode | no |
| Repair (M&R) | component / damage / repair codes | no |

Verified in the tariff models. So "etc" is narrower than it looks — but see §5,
because a *type* change can be bigger than a price change.

### 5. Crossing the reefer boundary is not a pricing question

`20GP → 40HC` changes what is charged. `40HC → 40RF` changes what the container
*is*: PTI requirement, plug sessions, `reefer_mode` at the gate, M&R status
rungs, and whether reefer electricity is billable at all. `EquipmentType::isReefer()`
is `type_code in ('RF','RH')`, and a dozen behaviours hang off it.

A correction that crosses that boundary on a container with reefer plug sessions
or reefer invoices is a different proposition from one that does not, and should
be refused rather than warned about.

### 6. `buildDeleteBlocks()` is already the dependency check we need

`YardController:1719-1786` already answers "what exists for this container's
cycle" across Storage & Handling lines, Storage invoice details, Reefer
Electricity lines and Repair invoices, keyed on the cycle date and blocking only
on `draft/issued/paid`. The movement delete screen calls it through
`deleteCheck()` and renders blocks and warnings.

The equipment-type guard is the same question with a narrower answer set. It
should reuse this, not grow a second copy that drifts.

### And one more, on the seal

`sealRequirementError()` (`:917`) is called from `gateIn` and `gateOut` — **not
from `updateMovement`**. So the existing laden-needs-a-seal policy can be
bypassed today by saving the gate-in and then editing it. Worth closing in the
same pass.

---

## Part A — Empty must not carry a seal

Small, self-contained, no schema change.

### A1. One policy function, three call sites

Rename the concern rather than adding a second function beside the first:

```php
private function sealPolicyError(bool $isLaden, ?string $sealNo, ?string $reason): ?string
{
    // An empty box has nothing to seal. A seal number on one is a typo, or a
    // cargo status that was never corrected after the box turned out to be
    // loaded — and the second reading is why this blocks rather than clears
    // the field: the operator has to say which it was.
    if (! $isLaden) {
        return filled($sealNo)
            ? 'This container is marked Empty but carries Seal Number "' . $sealNo . '". '
            . 'An empty container has nothing to seal. Either clear the seal number, '
            . 'or change Laden / Empty to Laden if the box is loaded.'
            : null;
    }

    // ... existing laden branch unchanged
}
```

Called from `gateIn` (`:410`), `gateOut` (`:1177`) and — new — `updateMovement`,
which closes the bypass in finding 6.

### A2. The same rule in the browser, so it is caught before the round trip

`gate.blade.php` already has the pattern at `:3253`: a small IIFE watching
`cargoStatusIn` that shows and hides the no-seal reason picker. Extend it to
flag the empty-with-seal combination inline on both fields and block submit,
mirroring whatever the form already does for other blocking validations.

The server rule is the authority; the client rule is the courtesy.

### A3. Existing rows first — the lesson from the reefer episode

Switching on a rule before looking at what the data holds is what produced the
NOR tariff problem. **Before this ships**, count what is already there:

```sql
SELECT movement_type, cargo_status, COUNT(*)
FROM gate_movements
WHERE cargo_status = 'empty' AND seal_no IS NOT NULL AND seal_no <> ''
GROUP BY movement_type, cargo_status;
```

If that returns rows, the rule must fire on the **submitted value**, not on the
stored one, so that opening an old movement to fix an unrelated field does not
present an error about something the operator did not touch. Decision 1 below.

---

## Part B — Correcting the equipment type

### B1. The control

Next to **Size / Type** in the summary block (`movement-edit.blade.php:85`), an
edit icon, shown only on a gate-in and only to a holder of the new permission.
It opens a modal; the field is never a plain dropdown on the form, which is what
you asked for and is right: this is not an edit, it is a correction with
consequences, and it should feel like one.

The modal does three things in order:

1. **Shows what is recorded now** — the movement's size/type and the master's,
   side by side. If finding 2 has already bitten this container they will differ,
   and the operator should see that before choosing.
2. **Asks for the correct equipment type** and a short reason (free text, stored
   on the audit entry).
3. **Pre-flights the change** against the chosen type and shows the result
   before the Apply button is live.

### B2. The pre-flight

A new `GET /yard/movements/{movement}/equipment-check?equipment_type_id=…`,
modelled on `deleteCheck`, returning `{safe, blocks[], warnings[], current, target}`.

Blocks — the correction is refused:

| Condition | Why |
| --- | --- |
| A Storage & Handling, Storage or Washing invoice (`draft/issued/paid`) exists for this cycle | the line was priced from the old size |
| The change crosses the reefer boundary **and** reefer plug sessions or reefer electricity lines exist for this cycle | §5: it changes what the box is, not what it costs |
| The movement is not a gate-in | the gate-out follows its gate-in |

Warnings — shown, correction allowed:

| Condition | Why |
| --- | --- |
| A cancelled invoice exists for the cycle | the figures on it will no longer match the box |
| The container has left the yard | historical correction; nothing downstream re-prices |
| The master already disagrees with the movement | finding 2 — say so, because this will reconcile them |
| A survey or estimate is linked | `estimates.equipment_type_id` is its own copy and is not touched |

The block set reuses the invoice logic factored out of `buildDeleteBlocks()` —
one method, two callers, no second copy.

### B3. What the correction writes

In one transaction:

| Target | Change |
| --- | --- |
| `containers` | `equipment_type_id`, `size`, `type_code` |
| the gate-in movement | `size`, `container_type` |
| the paired gate-out, if any | `size`, `container_type` |
| audit trail | `AuditService::log()` — before, after, reason, user |

Nothing else. `yard_storage` holds no type (finding 3), and an unbilled stay
re-prices by itself the next time the preview runs, because storage resolves the
type from the master.

Both movements, because they describe one visit: correcting only the arrival
leaves the departure claiming a 20' box left when a 40' one did, and the
handling bill reads the departure.

### B4. Close the two open doors

- `updateMovement` stops accepting `equipment_type_id` (finding 1). The modal's
  own endpoint becomes the only way in, and it checks first.
- `ContainerController::update` runs the same pre-flight when
  `equipment_type_id` changes on a container that has movements, and refuses on
  the same conditions (finding 2). Without this the guard is decoration.

### B5. Permission

A new action on the existing `yard` module in `config/modules.php`:

```php
'actions' => ['view', 'gate-in', 'gate-out', 'movement-edit',
              'movement-delete', 'backdate', 'equipment-change'],
```

Separate from `movement-edit` because the blast radius is different: this one
reaches the container master and re-prices unbilled storage. `permissions:sync`
adds it; the grant is a UI step, as always.

---

## Order of work

| Step | Content | Size |
| --- | --- | --- |
| 1 | Part A — seal policy, three call sites, client-side mirror, the SQL count | small |
| 2 | Factor the invoice-dependency check out of `buildDeleteBlocks()` | small |
| 3 | `equipment-check` endpoint + the block/warning rules | medium |
| 4 | The modal, and the edit icon on Size / Type | medium |
| 5 | The apply endpoint, transaction, audit entry | small |
| 6 | Close `updateMovement`; guard `ContainerController::update` | small |
| 7 | Permission, deployment note | small |

Step 1 is independent and can ship on its own.

### Tests

- Empty + seal is refused at gate-in, at gate-out, and on edit; laden + seal
  passes; empty with a blank seal passes; the laden no-seal-reason exception
  still works.
- Each block fires: billed storage, billed handling, billed washing, reefer
  crossing with sessions present.
- Each warning fires without blocking.
- A clean correction writes all four targets and leaves the storage row alone.
- An unbilled stay re-prices at the new type's rate on the next preview — the
  one that proves the correction reached billing.
- `updateMovement` no longer accepts `equipment_type_id`.
- The container master screen refuses the same change on a billed container.

---

## Decisions needed

1. **The seal rule — hard, or a setting?** `require_seal_for_laden` is a company
   setting; the natural symmetry is `block_seal_on_empty`, defaulting to on. Or
   a plain rule with no toggle. Which?
2. **Gate-out too?** The gate-out form has its own seal field (`:905`). I would
   apply the same rule there. Confirm.
3. **Correction scope** — I propose master *and* both movements (§B3). The
   alternative is movements only, which leaves storage pricing from a wrong
   master. Confirm.
4. **Permission** — new `yard.equipment-change`, or reuse `yard.backdate` as the
   existing "supervisor may rewrite history" marker?
5. **Reefer crossing** — block when reefer work exists (my proposal), or warn and
   allow?
