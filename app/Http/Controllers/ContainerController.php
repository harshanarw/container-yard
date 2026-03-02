<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContainerRequest;
use App\Http\Requests\UpdateContainerRequest;
use App\Models\Container;
use App\Models\Customer;
use App\Models\YardLocation;
use Illuminate\Http\Request;

class ContainerController extends Controller
{
    public function index(Request $request)
    {
        $containers = Container::with('customer')
            ->when($request->search, fn ($q, $search) =>
                $q->where('container_no', 'like', "%{$search}%")
            )
            ->when($request->customer_id, fn ($q, $id)     => $q->where('customer_id', $id))
            ->when($request->status,      fn ($q, $status) => $q->where('status', $status))
            ->when($request->size,        fn ($q, $size)   => $q->where('size', $size))
            ->when($request->condition,   fn ($q, $cond)   => $q->where('condition', $cond))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $customers = Customer::where('status', 'active')->orderBy('name')->get();

        return view('containers.index', compact('containers', 'customers'));
    }

    public function create()
    {
        $customers     = Customer::where('status', 'active')->orderBy('name')->get();
        $emptySlots    = YardLocation::where('status', 'empty')->orderBy('row')->orderBy('bay')->orderBy('tier')->get();

        return view('containers.create', compact('customers', 'emptySlots'));
    }

    public function store(StoreContainerRequest $request)
    {
        $data = $request->validated();
        $data['csc_plate_valid'] = $request->boolean('csc_plate_valid');

        $container = Container::create($data);

        // Mark yard slot as occupied
        if ($data['location_row'] && $data['location_bay'] && $data['location_tier']) {
            YardLocation::where([
                'row'  => $data['location_row'],
                'bay'  => $data['location_bay'],
                'tier' => $data['location_tier'],
            ])->update([
                'container_id'   => $container->id,
                'status'         => 'occupied',
                'last_updated_at' => now(),
            ]);
        }

        return redirect()->route('containers.index')
            ->with('success', "Container {$container->container_no} added successfully.");
    }

    public function show(Container $container)
    {
        $container->load(['customer', 'gateMovements', 'inquiries.damages', 'estimates', 'yardLocation']);

        return view('containers.show', compact('container'));
    }

    public function edit(Container $container)
    {
        $customers  = Customer::where('status', 'active')->orderBy('name')->get();
        $emptySlots = YardLocation::where('status', 'empty')
            ->orWhere('container_id', $container->id)
            ->orderBy('row')->orderBy('bay')->orderBy('tier')
            ->get();

        return view('containers.edit', compact('container', 'customers', 'emptySlots'));
    }

    public function update(UpdateContainerRequest $request, Container $container)
    {
        $data = $request->validated();
        $data['csc_plate_valid'] = $request->boolean('csc_plate_valid');

        // Release old yard slot
        YardLocation::where('container_id', $container->id)->update([
            'container_id'    => null,
            'status'          => 'empty',
            'last_updated_at' => now(),
        ]);

        $container->update($data);

        // Assign new yard slot
        if ($data['location_row'] && $data['location_bay'] && $data['location_tier']) {
            YardLocation::where([
                'row'  => $data['location_row'],
                'bay'  => $data['location_bay'],
                'tier' => $data['location_tier'],
            ])->update([
                'container_id'    => $container->id,
                'status'          => 'occupied',
                'last_updated_at' => now(),
            ]);
        }

        return redirect()->route('containers.show', $container)
            ->with('success', 'Container updated successfully.');
    }

    public function destroy(Container $container)
    {
        if ($container->gateMovements()->exists() || $container->inquiries()->exists()) {
            return back()->with('error', 'Cannot delete container with gate movements or inquiries.');
        }

        // Release yard slot
        YardLocation::where('container_id', $container->id)->update([
            'container_id'    => null,
            'status'          => 'empty',
            'last_updated_at' => now(),
        ]);

        $container->delete();

        return redirect()->route('containers.index')
            ->with('success', 'Container deleted successfully.');
    }
}
