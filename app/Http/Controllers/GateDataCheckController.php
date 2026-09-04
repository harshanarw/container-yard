<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\GateCheckReview;
use App\Models\GateMovement;
use App\Services\Diagnostics\GateDataCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Gate Data Check — gate movements whose timestamps cannot be true.
 *
 * The screen finds; it does not fix. Every finding links to the movement edit
 * form, which already knows how to correct a gate time and how to refuse a
 * correction that is still contradictory.
 */
class GateDataCheckController extends Controller
{
    public function index(Request $request, GateDataCheck $check)
    {
        $this->authorize('gate-check.view');

        $filters = $this->filters($request);
        $all     = $check->findings($filters);

        // One query for every note, rather than a lookup per row.
        $reviews = GateCheckReview::with('reviewer:id,name')
            ->whereIn('gate_movement_id', $all->pluck('movement.id'))
            ->get()
            ->keyBy(fn (GateCheckReview $r) => $r->key());

        $all = $all->map(function (array $finding) use ($reviews) {
            $finding['review'] = $reviews[$finding['movement']->id . ':' . $finding['check']] ?? null;

            return $finding;
        });

        return view('reports.gate-data-check', [
            'open'      => $all->whereNull('review')->values(),
            'reviewed'  => $all->whereNotNull('review')->values(),
            'filters'   => $filters,
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'checks'    => GateDataCheck::labels(),
        ]);
    }

    /**
     * Record that a finding was looked at and has nothing to correct.
     *
     * A note is required, and deliberately. The button exists for findings with
     * no right answer — a container released with no arrival ever captured —
     * and the note is what stops it becoming a way to clear the list without
     * looking. Whoever reads this in six months needs to know why.
     */
    public function review(Request $request, GateMovement $movement)
    {
        $this->authorize('gate-check.review');

        $validated = $request->validate([
            'check' => 'required|string|in:' . implode(',', array_keys(GateDataCheck::labels())),
            'note'  => 'required|string|min:5|max:500',
        ]);

        GateCheckReview::updateOrCreate(
            ['gate_movement_id' => $movement->id, 'check' => $validated['check']],
            ['note' => $validated['note'], 'reviewed_by' => Auth::id()],
        );

        return back()->with('success', 'Marked as reviewed.');
    }

    /** Remove a note, putting the finding back on the open list. */
    public function unreview(Request $request, GateMovement $movement)
    {
        $this->authorize('gate-check.review');

        $validated = $request->validate([
            'check' => 'required|string|in:' . implode(',', array_keys(GateDataCheck::labels())),
        ]);

        GateCheckReview::where('gate_movement_id', $movement->id)
            ->where('check', $validated['check'])
            ->delete();

        return back()->with('success', 'Reopened.');
    }

    /**
     * @return array{from:?string,to:?string,customer_id:?int}
     */
    private function filters(Request $request): array
    {
        $request->validate([
            'from'        => 'nullable|date',
            'to'          => 'nullable|date|after_or_equal:from',
            'customer_id' => 'nullable|integer|exists:customers,id',
        ]);

        // The dates start empty rather than defaulting to this month. A
        // future-dated arrival falls outside any range ending today, so a
        // defaulted range would hide the very finding most worth seeing.
        return [
            'from'        => $request->input('from'),
            'to'          => $request->input('to'),
            'customer_id' => $request->filled('customer_id') ? (int) $request->input('customer_id') : null,
        ];
    }
}
