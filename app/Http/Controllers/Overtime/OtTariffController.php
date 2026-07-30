<?php

namespace App\Http\Controllers\Overtime;

use App\Http\Controllers\Controller;
use App\Models\OtTariffRule;
use App\Models\OtTariffVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * OT tariff maintenance: effective-dated versions, each holding the day-category ×
 * period (A/B) rate rules the OT receipt is priced from.
 *
 * Rate revisions clone into a NEW version rather than editing the old one — a
 * version that has issued receipts is frozen, so printed/posted receipts keep the
 * rate they were billed at.
 */
class OtTariffController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:ot.settings.view')->only(['index', 'show']);
        $this->middleware('can:ot.settings.edit')->only([
            'create', 'store', 'update', 'destroy', 'cloneVersion',
            'storeRule', 'updateRule', 'toggleRule', 'destroyRule',
        ]);
        $this->middleware('can:ot.settings.approve')->only(['activate', 'retire']);
    }

    // ── Versions ─────────────────────────────────────────────────────────────

    public function index()
    {
        $versions = OtTariffVersion::withCount('rules')
            ->orderByDesc('effective_from')->orderByDesc('id')
            ->get();

        $today = Carbon::today();

        return view('overtime.tariffs.index', [
            'versions'  => $versions,
            'effective' => $versions->first(fn (OtTariffVersion $v) => $v->isEffectiveOn($today)),
        ]);
    }

    public function create()
    {
        return view('overtime.tariffs.create', [
            'version' => new OtTariffVersion([
                'currency'        => 'LKR',
                'approval_status' => 'draft',
                'effective_from'  => now()->toDateString(),
                'active'          => true,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedVersion($request);

        $version = OtTariffVersion::create($data + ['created_by' => Auth::id()]);

        return redirect()->route('overtime.tariffs.show', $version)
            ->with('success', "Tariff version {$version->version_code} created. Add its rate rules below.");
    }

    public function show(OtTariffVersion $otTariffVersion)
    {
        $otTariffVersion->load(['rules' => fn ($q) => $q->orderBy('day_category')->orderBy('priority')]);

        return view('overtime.tariffs.show', [
            'version'    => $otTariffVersion,
            'byCategory' => $otTariffVersion->rules->groupBy('day_category'),
            'locked'     => $otTariffVersion->isLocked(),
            'lockReason' => $otTariffVersion->lockReason(),
        ]);
    }

    public function update(Request $request, OtTariffVersion $otTariffVersion)
    {
        if ($otTariffVersion->isLocked()) {
            return back()->with('error', $otTariffVersion->lockReason());
        }

        $otTariffVersion->update($this->validatedVersion($request, $otTariffVersion));

        return redirect()->route('overtime.tariffs.show', $otTariffVersion)
            ->with('success', 'Tariff version updated.');
    }

    /** Rate revision: copy the version header + every rule into a fresh draft. */
    public function cloneVersion(Request $request, OtTariffVersion $otTariffVersion)
    {
        $v = $request->validate([
            'version_code'   => ['required', 'string', 'max:40', 'unique:ot_tariff_versions,version_code'],
            'name'           => ['required', 'string', 'max:150'],
            'effective_from' => ['required', 'date'],
            'rate_change_pct' => ['nullable', 'numeric', 'between:-100,1000'],
        ]);

        $clone = DB::transaction(function () use ($otTariffVersion, $v) {
            $clone = OtTariffVersion::create([
                'version_code'     => $v['version_code'],
                'name'             => $v['name'],
                'effective_from'   => $v['effective_from'],
                'effective_to'     => null,
                'currency'         => $otTariffVersion->currency,
                'source_reference' => $otTariffVersion->source_reference,
                'approval_status'  => 'draft',   // review before it can bill
                'active'           => true,
                'created_by'       => Auth::id(),
            ]);

            $factor = 1 + ((float) ($v['rate_change_pct'] ?? 0) / 100);

            foreach ($otTariffVersion->rules as $rule) {
                $copy = $rule->replicate(['ot_tariff_version_id', 'created_at', 'updated_at']);
                $copy->ot_tariff_version_id = $clone->id;
                $copy->rate_amount          = round((float) $rule->rate_amount * $factor, 2);
                $copy->save();
            }

            return $clone;
        });

        return redirect()->route('overtime.tariffs.show', $clone)
            ->with('success', "Cloned {$otTariffVersion->version_code} into draft {$clone->version_code} with "
                . $clone->rules()->count() . ' rule(s). Review the rates, then activate it.');
    }

    /** Make this the billing version, closing the open-ended one it supersedes. */
    public function activate(OtTariffVersion $otTariffVersion)
    {
        if ($otTariffVersion->rules()->where('active', true)->count() === 0) {
            return back()->with('error', 'Add at least one active rate rule before activating this version.');
        }

        $superseded = DB::transaction(function () use ($otTariffVersion) {
            $otTariffVersion->update([
                'approval_status' => 'active',
                'active'          => true,
                'approved_by'     => Auth::id(),
            ]);

            // Effective-dating: an older open-ended active version is closed the day
            // before this one starts, so exactly one version covers any given date.
            $prior = OtTariffVersion::where('id', '!=', $otTariffVersion->id)
                ->where('approval_status', 'active')
                ->whereNull('effective_to')
                ->whereDate('effective_from', '<', $otTariffVersion->effective_from)
                ->get();

            foreach ($prior as $old) {
                $old->update(['effective_to' => $otTariffVersion->effective_from->copy()->subDay()]);
            }

            return $prior;
        });

        $msg = "Tariff version {$otTariffVersion->version_code} is now active.";
        if ($superseded->isNotEmpty()) {
            $msg .= ' Closed ' . $superseded->pluck('version_code')->implode(', ')
                . ' on ' . $otTariffVersion->effective_from->copy()->subDay()->format('d M Y') . '.';
        }

        return redirect()->route('overtime.tariffs.show', $otTariffVersion)->with('success', $msg);
    }

    public function retire(OtTariffVersion $otTariffVersion)
    {
        // Retiring the only version that covers today leaves every out-of-hours
        // gate-in "unconfigured", which blocks the gate. Require a successor first.
        $today = Carbon::today();
        if ($otTariffVersion->isEffectiveOn($today)) {
            $replacement = OtTariffVersion::where('id', '!=', $otTariffVersion->id)
                ->get()
                ->contains(fn (OtTariffVersion $v) => $v->isEffectiveOn($today));

            if (! $replacement) {
                return back()->with('error',
                    'This is the only tariff version effective today. Activate a replacement before retiring it, '
                    . 'otherwise out-of-hours gate-ins will have no rate to bill.');
            }
        }

        $otTariffVersion->update(['approval_status' => 'retired', 'active' => false]);

        return redirect()->route('overtime.tariffs.index')
            ->with('success', "Tariff version {$otTariffVersion->version_code} retired.");
    }

    public function destroy(OtTariffVersion $otTariffVersion)
    {
        if ($otTariffVersion->receipts()->exists()) {
            return back()->with('error',
                'Receipts have been issued against this version, so it cannot be deleted. Retire it instead.');
        }

        $code = $otTariffVersion->version_code;
        $otTariffVersion->delete(); // ot_tariff_rules cascade

        return redirect()->route('overtime.tariffs.index')
            ->with('success', "Tariff version {$code} deleted.");
    }

    // ── Rules ────────────────────────────────────────────────────────────────

    public function storeRule(Request $request, OtTariffVersion $otTariffVersion)
    {
        if ($otTariffVersion->isLocked()) {
            return back()->with('error', $otTariffVersion->lockReason());
        }

        $otTariffVersion->rules()->create($this->validatedRule($request, $otTariffVersion));

        return redirect()->route('overtime.tariffs.show', $otTariffVersion)
            ->with('success', 'Rate rule added.');
    }

    public function updateRule(Request $request, OtTariffVersion $otTariffVersion, OtTariffRule $rule)
    {
        $this->assertRuleBelongsTo($otTariffVersion, $rule);

        if ($otTariffVersion->isLocked()) {
            return back()->with('error', $otTariffVersion->lockReason());
        }

        $rule->update($this->validatedRule($request, $otTariffVersion, $rule));

        return redirect()->route('overtime.tariffs.show', $otTariffVersion)
            ->with('success', "Rate rule {$rule->rule_code} updated.");
    }

    public function toggleRule(OtTariffVersion $otTariffVersion, OtTariffRule $rule)
    {
        $this->assertRuleBelongsTo($otTariffVersion, $rule);

        if ($otTariffVersion->isLocked()) {
            return back()->with('error', $otTariffVersion->lockReason());
        }

        $rule->update(['active' => ! $rule->active]);

        return back()->with('success',
            "Rate rule {$rule->rule_code} " . ($rule->active ? 'activated' : 'deactivated') . '.');
    }

    public function destroyRule(OtTariffVersion $otTariffVersion, OtTariffRule $rule)
    {
        $this->assertRuleBelongsTo($otTariffVersion, $rule);

        if ($otTariffVersion->isLocked()) {
            return back()->with('error', $otTariffVersion->lockReason());
        }

        $code = $rule->rule_code;
        $rule->delete();

        return redirect()->route('overtime.tariffs.show', $otTariffVersion)
            ->with('success', "Rate rule {$code} deleted.");
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function validatedVersion(Request $request, ?OtTariffVersion $existing = null): array
    {
        $data = $request->validate([
            'version_code' => [
                'required', 'string', 'max:40',
                Rule::unique('ot_tariff_versions', 'version_code')->ignore($existing?->id),
            ],
            'name'             => ['required', 'string', 'max:150'],
            'effective_from'   => ['required', 'date'],
            'effective_to'     => ['nullable', 'date', 'after_or_equal:effective_from'],
            'currency'         => ['required', 'string', 'size:3'],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'approval_status'  => ['required', Rule::in(array_keys(OtTariffVersion::APPROVAL_STATUSES))],
            'active'           => ['sometimes', 'boolean'],
        ]);

        $data['currency'] = strtoupper($data['currency']);
        $data['active']   = $request->boolean('active');

        // Only an approver may PROMOTE a version into billing. Editing the name or
        // reference of an already-active version stays open to editors.
        $promoting = $data['approval_status'] === 'active' && $existing?->approval_status !== 'active';

        if ($promoting && ! Auth::user()->can('ot.settings.approve')) {
            throw ValidationException::withMessages([
                'approval_status' => 'You do not have permission to activate a tariff version. Save it as draft and ask an approver.',
            ]);
        }

        return $data;
    }

    private function validatedRule(Request $request, OtTariffVersion $version, ?OtTariffRule $existing = null): array
    {
        $data = $request->validate([
            'rule_code'                 => ['required', 'string', 'max:40'],
            'display_name'              => ['required', 'string', 'max:150'],
            'movement_type'             => ['required', Rule::in(array_keys(OtTariffRule::MOVEMENT_TYPES))],
            'day_category'              => ['required', Rule::in(array_keys(OtTariffRule::DAY_CATEGORIES))],
            'period_code'               => ['required', Rule::in(array_keys(OtTariffRule::PERIODS))],
            'start_time'                => ['required', 'date_format:H:i'],
            'end_time'                  => ['required', 'date_format:H:i'],
            'ends_next_day'             => ['sometimes', 'boolean'],
            'rate_amount'               => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'charge_basis'              => ['required', Rule::in(array_keys(OtTariffRule::CHARGE_BASES))],
            'allow_receipt_extension'   => ['sometimes', 'boolean'],
            'billing_mode_on_extension' => ['required', Rule::in(array_keys(OtTariffRule::EXTENSION_MODES))],
            'priority'                  => ['required', 'integer', 'min:0', 'max:999'],
            'active'                    => ['sometimes', 'boolean'],
        ]);

        $data['ends_next_day']           = $request->boolean('ends_next_day');
        $data['allow_receipt_extension'] = $request->boolean('allow_receipt_extension');
        $data['active']                  = $request->boolean('active');
        $data['currency']                = $version->currency; // one currency per version

        // A same-day window must move forward in time. A window that rolls past
        // midnight (e.g. 17:00 → 05:00, or a 24:00 end stored as 00:00) needs the
        // next-day flag — that is what makes the validity window span two dates.
        if (! $data['ends_next_day'] && $data['end_time'] <= $data['start_time']) {
            throw ValidationException::withMessages([
                'end_time' => 'The end time must be after the start time — or tick "ends next day" if the window runs past midnight.',
            ]);
        }

        $dupCode = OtTariffRule::where('ot_tariff_version_id', $version->id)
            ->where('rule_code', $data['rule_code'])
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
            ->exists();

        if ($dupCode) {
            throw ValidationException::withMessages(['rule_code' => 'This version already has a rule with that code.']);
        }

        // Two rules for the same day category, period and direction would make the
        // rate the operator is offered ambiguous. "Custom" periods are exempt.
        if ($data['period_code'] !== 'custom') {
            $dupSlab = OtTariffRule::where('ot_tariff_version_id', $version->id)
                ->where('day_category', $data['day_category'])
                ->where('period_code', $data['period_code'])
                ->where('movement_type', $data['movement_type'])
                ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                ->exists();

            if ($dupSlab) {
                throw ValidationException::withMessages([
                    'period_code' => 'This version already defines period '
                        . strtoupper($data['period_code']) . ' for '
                        . OtTariffRule::DAY_CATEGORIES[$data['day_category']]
                        . ' (' . OtTariffRule::MOVEMENT_TYPES[$data['movement_type']] . ').',
                ]);
            }
        }

        return $data;
    }

    /** Nested route guard — a rule id from another version must not be reachable. */
    private function assertRuleBelongsTo(OtTariffVersion $version, OtTariffRule $rule): void
    {
        abort_unless($rule->ot_tariff_version_id === $version->id, 404);
    }
}
