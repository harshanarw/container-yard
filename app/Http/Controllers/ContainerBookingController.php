<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\ContainerBooking;
use App\Models\ContainerBookingLine;
use App\Models\ContainerGrade;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Services\BookingService;
use App\Services\NumberSequenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContainerBookingController extends Controller
{
    public function __construct(private BookingService $bookings)
    {
        $this->middleware('can:container-bookings.view')->only(['index', 'show']);
        $this->middleware('can:container-bookings.create')->only(['create', 'store']);
        $this->middleware('can:container-bookings.allocate')->only(['allocate', 'autoAllocate', 'deallocate']);
        $this->middleware('can:container-bookings.cancel')->only(['cancel']);
        $this->middleware('can:container-bookings.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = ContainerBooking::with(['customer', 'lines'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        $bookings   = $query->paginate(20)->withQueryString();
        $customers  = Customer::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return view('container-bookings.index', compact('bookings', 'customers'));
    }

    public function create()
    {
        return view('container-bookings.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_no'        => ['required', 'string', 'max:60', 'unique:container_bookings,booking_no'],
            'customer_id'       => ['required', 'exists:customers,id'],
            'valid_from'        => ['nullable', 'date'],
            'valid_to'          => ['nullable', 'date', 'after_or_equal:valid_from'],
            'remarks'           => ['nullable', 'string', 'max:1000'],
            'lines'             => ['required', 'array', 'min:1'],
            'lines.*.size'      => ['required', 'string', 'max:10'],
            'lines.*.type_code' => ['required', 'string', 'max:10'],
            'lines.*.grade_id'  => ['nullable', 'exists:container_grades,id'],
            'lines.*.quantity'  => ['required', 'integer', 'min:1'],
        ]);

        $booking = DB::transaction(function () use ($validated) {
            $booking = ContainerBooking::create([
                'booking_no'  => $validated['booking_no'],
                'customer_id' => $validated['customer_id'],
                'status'      => 'open',
                'valid_from'  => $validated['valid_from'] ?? null,
                'valid_to'    => $validated['valid_to'] ?? null,
                'remarks'     => $validated['remarks'] ?? null,
                'created_by'  => auth()->id(),
            ]);

            foreach ($validated['lines'] as $l) {
                $booking->lines()->create([
                    'size'      => strtoupper($l['size']),
                    'type_code' => strtoupper($l['type_code']),
                    'grade_id'  => $l['grade_id'] ?? null,
                    'quantity'  => (int) $l['quantity'],
                ]);
            }

            return $booking;
        });

        return redirect()->route('container-bookings.show', $booking)
            ->with('success', "Booking {$booking->booking_no} created.");
    }

    public function show(ContainerBooking $containerBooking)
    {
        $containerBooking->load(['customer', 'lines.grade', 'lines.containers.equipmentType', 'createdBy']);

        // Available stock the operator can allocate, grouped for the pickers.
        $available = Container::available()->with('grade')
            ->orderBy('available_since')
            ->get(['id', 'container_no', 'size', 'type_code', 'grade_id', 'available_since']);

        return view('container-bookings.show', compact('containerBooking', 'available'));
    }

    public function allocate(Request $request, ContainerBooking $containerBooking)
    {
        $validated = $request->validate([
            'line_id'        => ['required', 'integer', 'exists:container_booking_lines,id'],
            'container_ids'  => ['required', 'array', 'min:1'],
            'container_ids.*' => ['integer', 'exists:containers,id'],
        ]);

        $line = $containerBooking->lines()->findOrFail($validated['line_id']);

        $done = 0;
        try {
            foreach ($validated['container_ids'] as $cid) {
                $container = Container::find($cid);
                if ($container) {
                    $this->bookings->allocate($line, $container);
                    $done++;
                }
            }
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage() . ($done ? " ({$done} allocated before the error.)" : ''));
        }

        return back()->with('success', "{$done} container(s) reserved to {$line->label}.");
    }

    public function autoAllocate(Request $request, ContainerBooking $containerBooking, ContainerBookingLine $line)
    {
        abort_unless($line->container_booking_id === $containerBooking->id, 404);

        $request->validate(['count' => ['nullable', 'integer', 'min:1']]);
        $count = (int) ($request->input('count') ?: $line->unallocated);

        $done = $this->bookings->autoAllocate($line, $count);

        return back()->with($done ? 'success' : 'error',
            $done ? "Auto-allocated {$done} oldest available container(s) to {$line->label}."
                  : 'No matching available containers to allocate.');
    }

    public function deallocate(ContainerBooking $containerBooking, Container $container)
    {
        try {
            $this->bookings->deallocate($container);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Container {$container->container_no} released back to available.");
    }

    public function cancel(ContainerBooking $containerBooking)
    {
        $this->bookings->cancel($containerBooking);

        return back()->with('success', "Booking {$containerBooking->booking_no} cancelled; reserved containers returned to available.");
    }

    public function destroy(ContainerBooking $containerBooking)
    {
        if ($containerBooking->totalReleased() > 0) {
            return back()->with('error', 'Cannot delete a booking that already has released containers. Cancel it instead.');
        }

        // Free any reserved containers first.
        $this->bookings->cancel($containerBooking);
        $containerBooking->delete();

        return redirect()->route('container-bookings.index')->with('success', 'Booking deleted.');
    }

    private function formOptions(): array
    {
        $customers      = Customer::where('status', 'active')->orderBy('name')->get(['id', 'name']);
        $grades         = ContainerGrade::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
        $equipmentTypes = EquipmentType::query()->orderBy('size')->orderBy('type_code')
            ->get(['id', 'size', 'type_code'])
            ->map(fn ($e) => ['size' => $e->size, 'type_code' => $e->type_code])
            ->unique(fn ($e) => $e['size'] . $e['type_code'])->values();

        return compact('customers', 'grades', 'equipmentTypes');
    }
}
