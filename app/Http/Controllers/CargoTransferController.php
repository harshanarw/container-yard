<?php

namespace App\Http\Controllers;

use App\Models\CargoTransfer;
use App\Models\Container;
use App\Models\GateMovement;
use App\Services\CargoTransferService;
use Illuminate\Http\Request;

class CargoTransferController extends Controller
{
    public function __construct(private CargoTransferService $service) {}

    public function index()
    {
        // Pending: CARGO_RENTAL_IN gate-ins whose box is still in the yard and has
        // no transfer recorded yet.
        $done = CargoTransfer::where('status', '!=', 'cancelled')->pluck('source_gate_movement_id')->all();

        $pending = GateMovement::query()
            ->where('movement_type', 'in')
            ->where('job_type_code', 'CARGO_RENTAL_IN')
            ->whereNotIn('id', $done ?: [0])
            ->whereHas('container', fn ($q) => $q->where('status', 'in_yard'))
            ->with(['container', 'customer', 'yardJob'])
            ->latest('id')
            ->get();

        $transfers = CargoTransfer::with(['customer', 'sourceContainer', 'substituteContainer', 'yardJob'])
            ->latest('id')
            ->paginate(20);

        return view('yard.cargo-transfers.index', compact('pending', 'transfers'));
    }

    public function create(GateMovement $movement)
    {
        $movement->load(['container.equipmentType', 'customer', 'yardJob']);

        if ($movement->job_type_code !== 'CARGO_RENTAL_IN' || $movement->movement_type !== 'in') {
            return redirect()->route('yard.cargo-transfers.index')
                ->with('error', 'That movement is not a cargo-rental gate-in.');
        }

        if (CargoTransfer::where('source_gate_movement_id', $movement->id)->where('status', '!=', 'cancelled')->exists()) {
            return redirect()->route('yard.cargo-transfers.index')
                ->with('error', 'A cargo transfer already exists for this gate-in.');
        }

        // Candidate substitute boxes: present in the yard, not the source box, not on
        // an active hire, and not already holding cargo from another transfer.
        $activeSubs = CargoTransfer::where('status', 'active')->pluck('substitute_container_id')->all();

        $substitutes = Container::query()
            ->whereIn('status', ['in_yard', 'available'])
            ->where('id', '!=', $movement->container_id)
            ->whereNotIn('id', $activeSubs ?: [0])
            ->whereDoesntHave('activeHire')
            ->with('equipmentType')
            ->orderBy('container_no')
            ->get();

        return view('yard.cargo-transfers.create', compact('movement', 'substitutes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'source_gate_movement_id' => ['required', 'exists:gate_movements,id'],
            'substitute_container_id' => ['required', 'exists:containers,id'],
            'substitute_source'       => ['required', 'in:yard_owned,on_hired'],
            'transfer_date'           => ['required', 'date'],
            'cargo_description'        => ['nullable', 'string', 'max:1000'],
            'daily_rate'              => ['nullable', 'numeric', 'min:0'],
            'handling_charge'         => ['nullable', 'numeric', 'min:0'],
            'notes'                   => ['nullable', 'string', 'max:1000'],
        ]);

        $movement = GateMovement::findOrFail($validated['source_gate_movement_id']);

        try {
            $transfer = $this->service->transfer($movement, $validated, auth()->id() ?? 1);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('yard.cargo-transfers.show', $transfer)
            ->with('success', 'Cargo transfer recorded. The substitute box is now on customer storage'
                . ($transfer->is_reefer ? ' with reefer electricity' : '') . ', and the empty box has been gated out.');
    }

    public function show(CargoTransfer $cargoTransfer)
    {
        $cargoTransfer->load([
            'customer', 'yardJob.jobType',
            'sourceContainer', 'substituteContainer.equipmentType',
            'sourceGateMovement', 'sourceGateOutMovement',
            'substituteYardStorage', 'reeferPlugSession', 'createdBy',
        ]);

        return view('yard.cargo-transfers.show', ['transfer' => $cargoTransfer]);
    }
}
