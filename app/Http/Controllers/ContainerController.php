<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\YardLocation;
use Illuminate\Http\Request;

class ContainerController extends Controller
{
    public function index(Request $request)
    {
        $containers = Container::with(['customer', 'equipmentType'])
            ->when($request->search, fn ($q, $v) =>
                $q->where('container_no', 'like', "%{$v}%")
                  ->orWhere('owner_name', 'like', "%{$v}%")
                  ->orWhere('manufacturer', 'like', "%{$v}%")
            )
            ->when($request->category, fn ($q, $v) => $q->where('category', $v))
            ->when($request->status,   fn ($q, $v) => $q->where('status', $v))
            ->when($request->size,     fn ($q, $v) => $q->where('size', $v))
            ->orderBy('container_no')
            ->paginate(25)
            ->withQueryString();

        return view('containers.index', compact('containers'));
    }

    public function create()
    {
        $customers      = Customer::where('status', 'active')->orderBy('name')->get();
        $equipmentTypes = EquipmentType::active()->orderBy('sort_order')->get();

        return view('containers.create', compact('customers', 'equipmentTypes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $validated = $this->deriveEquipmentFields($validated);

        $container = Container::create($validated);

        return redirect()->route('containers.show', $container)
            ->with('success', "Container {$container->container_no} created successfully.");
    }

    public function show(Container $container)
    {
        $container->load([
            'customer', 'equipmentType',
            'gateMovements' => fn ($q) => $q->latest()->take(10),
            'yardLocation',
        ]);

        return view('containers.show', compact('container'));
    }

    public function edit(Container $container)
    {
        $customers      = Customer::where('status', 'active')->orderBy('name')->get();
        $equipmentTypes = EquipmentType::active()->orderBy('sort_order')->get();

        return view('containers.edit', compact('container', 'customers', 'equipmentTypes'));
    }

    public function update(Request $request, Container $container)
    {
        $validated = $request->validate($this->rules($container->id));
        $validated = $this->deriveEquipmentFields($validated);

        $container->update($validated);

        return redirect()->route('containers.show', $container)
            ->with('success', 'Container master record updated successfully.');
    }

    public function destroy(Container $container)
    {
        if ($container->gateMovements()->exists()) {
            return back()->with('error', 'Cannot delete container with gate movements on record.');
        }

        // Release yard slot if still occupied
        YardLocation::where('container_id', $container->id)->update([
            'container_id'    => null,
            'status'          => 'empty',
            'last_updated_at' => now(),
        ]);

        $container->delete();

        return redirect()->route('containers.index')
            ->with('success', 'Container master record deleted.');
    }

    // AJAX: look up a container number and return master fields (used by Gate-In form)
    public function masterLookup(Request $request)
    {
        $no = strtoupper(trim($request->query('container_no', '')));

        if (!$no) {
            return response()->json(['found' => false]);
        }

        $container = Container::with('equipmentType')
            ->where('container_no', $no)
            ->first();

        if (!$container) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found'            => true,
            'category'         => $container->category,
            'equipment_type_id'=> $container->equipment_type_id,
            'size'             => $container->size,
            'type_code'        => $container->type_code,
            'manufacture_year' => $container->manufacture_year,
            'manufacturer'     => $container->manufacturer,
            'owner_code'       => $container->owner_code,
            'owner_name'       => $container->owner_name,
            'gross_weight_kg'  => $container->gross_weight_kg,
            'tare_weight_kg'   => $container->tare_weight_kg,
            'max_payload_kg'   => $container->max_payload_kg,
            'csc_plate_no'     => $container->csc_plate_no,
            'csc_expiry_date'  => $container->csc_expiry_date?->format('Y-m-d'),
            'status'           => $container->status,
            'customer_id'      => $container->customer_id,
        ]);
    }

    private function rules(?int $exceptId = null): array
    {
        $uniqueRule = 'unique:containers,container_no' . ($exceptId ? ",{$exceptId}" : '');

        return [
            'container_no'      => ['required', 'string', 'max:12', $uniqueRule, 'regex:/^[A-Z]{4}[0-9]{7}$/'],
            'category'          => ['required', 'in:consignee,owned,leased'],
            'equipment_type_id' => ['nullable', 'exists:equipment_types,id'],
            'manufacture_year'  => ['nullable', 'integer', 'min:1970', 'max:' . (date('Y') + 1)],
            'manufacturer'      => ['nullable', 'string', 'max:100'],
            'owner_code'        => ['nullable', 'string', 'max:20'],
            'owner_name'        => ['nullable', 'string', 'max:100'],
            'gross_weight_kg'   => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'tare_weight_kg'    => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'max_payload_kg'    => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'csc_plate_no'      => ['nullable', 'string', 'max:50'],
            'csc_expiry_date'   => ['nullable', 'date'],
            'notes'             => ['nullable', 'string'],
            'customer_id'       => ['nullable', 'exists:customers,id'],
        ];
    }

    private function deriveEquipmentFields(array $data): array
    {
        if (!empty($data['equipment_type_id'])) {
            $eqt = EquipmentType::find($data['equipment_type_id']);
            if ($eqt) {
                $data['size']      = $eqt->size;
                $data['type_code'] = $eqt->type_code;
            }
        }
        return $data;
    }
}
