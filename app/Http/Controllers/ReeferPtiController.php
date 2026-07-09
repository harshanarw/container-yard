<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\ReeferPtiInspection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReeferPtiController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:containers.pti')->only(['store']);
    }

    /**
     * Record a reefer pre-trip inspection (PTI) and denormalise the latest
     * result onto the container. A passing PTI (optionally time-boxed via
     * valid_until) is what gate-out checks before releasing a reefer for export.
     */
    public function store(Request $request, Container $container)
    {
        abort_unless($container->isReefer(), 422, 'PTI applies to reefer containers only.');

        $validated = $request->validate([
            'set_point_temp' => ['nullable', 'numeric', 'min:-40', 'max:40'],
            'result'         => ['required', 'in:pass,fail'],
            'findings'       => ['nullable', 'string', 'max:1000'],
            'valid_until'    => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        DB::transaction(function () use ($container, $validated) {
            $container->ptiInspections()->create([
                'inspected_by'   => auth()->id(),
                'inspected_at'   => now(),
                'set_point_temp' => $validated['set_point_temp'] ?? null,
                'result'         => $validated['result'],
                'findings'       => $validated['findings'] ?? null,
                'valid_until'    => $validated['result'] === 'pass' ? ($validated['valid_until'] ?? null) : null,
            ]);

            $container->update([
                'pti_status' => $validated['result'] === 'pass' ? 'passed' : 'failed',
                'pti_at'     => now(),
            ]);
        });

        $msg = $validated['result'] === 'pass'
            ? "PTI passed for {$container->container_no}."
            : "PTI failed for {$container->container_no} — the container is not fit for export release.";

        return back()->with('success', $msg);
    }
}
