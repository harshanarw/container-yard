<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\EquipmentType;
use App\Services\Reporting\ContainerStockAsAt;
use App\Support\Export\ContainerStockWorkbook;
use App\Support\Export\TabularExport;
use Illuminate\Http\Request;

/**
 * Container stock as at a date.
 *
 * "What was in the yard on 30 September, for this shipping line?" -- a question
 * the Inventory report cannot answer, because it reads the container master,
 * where `status`, `gate_in_date` and `customer_id` all describe today and are
 * overwritten on every visit.
 *
 * Its own permission rather than `reports.view`, like Gate Data Check and
 * Weekly Revenue: this one goes out to a shipping line as a statement of what
 * they had on the ground, so it reads more like an account than an operations
 * screen.
 */
class ContainerStockController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:container-stock.view');
    }

    public function index(Request $request)
    {
        [$asAt, $filters] = $this->parameters($request);

        $rows    = ContainerStockAsAt::rows($asAt, $filters);
        $summary = ContainerStockAsAt::summary($rows);

        // Containers whose movements cannot be placed in time are absent from
        // the rows. Saying so is the difference between a short count the
        // customer queries and one they can reconcile.
        $unplaceable = ContainerStockAsAt::unplaceableCount();

        $customers = Customer::where('status', 'active')->orderBy('name')->get();
        $typeCodes = EquipmentType::orderBy('type_code')->pluck('type_code')->unique()->values();

        return view('reports.container-stock', compact(
            'rows', 'summary', 'asAt', 'filters', 'customers', 'typeCodes', 'unplaceable',
        ));
    }

    /**
     * The same stock, as a file.
     *
     * Mirrors the columns on screen with the badges resolved to the words they
     * stand for -- a spreadsheet cannot show a colour, and "Require Repair" is
     * what the person reading the file needs.
     *
     * The as-at date is carried twice on purpose: in the filename, so the file
     * can be filed and found, and as the **first column of every row**, so it
     * survives being sorted, filtered or pasted into another sheet. A stock file
     * that has lost its date cannot be checked against anything later, which for
     * a document sent to a customer is worse than not producing it.
     */
    public function export(Request $request)
    {
        [$asAt, $filters] = $this->parameters($request);

        $rows      = ContainerStockAsAt::rows($asAt, $filters);
        $asAtLabel = \Illuminate\Support\Carbon::parse($asAt)->format('Y-m-d');

        // The workbook says on its face whose stock this is and on what date,
        // because it is the copy that gets sent to a shipping line. The CSV
        // stays a flat data file: a header block above the table is exactly
        // what breaks a CSV for anything that reads it as data.
        // `normalise()` on TabularExport is private, so the format is compared
        // here. Where the writer cannot produce a styled workbook this falls
        // through to the flat file rather than failing the download.
        $wantsXlsx = strtolower(trim((string) $request->input('format'))) === TabularExport::XLSX;

        if ($wantsXlsx && ContainerStockWorkbook::available()) {
            return ContainerStockWorkbook::stream($rows, [
                'asAt'     => $asAtLabel,
                'customer' => $filters['customer_id']
                    ? Customer::find($filters['customer_id'])?->name
                    : null,
                'filters'  => $this->filterSummary($filters),
                'summary'  => ContainerStockAsAt::summary($rows),
            ]);
        }

        return TabularExport::stream(
            $request->input('format'),
            'container-stock-as-at-' . $asAtLabel,
            ContainerStockWorkbook::HEADINGS,
            function () use ($rows, $asAtLabel) {
                foreach ($rows as $row) {
                    yield [
                        $asAtLabel,
                        $row['container_no'],
                        $row['size'],
                        $row['type_code'],
                        $this->words($row['cargo_status']),
                        // Blank rather than "Operating" for a dry box: a reefer
                        // mode on a general-purpose container would read as a
                        // fact about it that is not true.
                        $row['reefer_mode'] ? $this->words($row['reefer_mode']) : '',
                        $this->words($row['condition']),
                        $row['customer'],
                        $row['gate_in_time']?->format('Y-m-d H:i'),
                        $row['days_in_yard'],
                        $row['location'],
                        $row['job_no'],
                        $row['job_type'],
                        $this->words($row['stage']),
                    ];
                }
            },
        );
    }

    /** The filters in words, for the sheet header: "Size 40 - Laden". */
    private function filterSummary(array $filters): string
    {
        return collect([
            $filters['size']         ? 'Size ' . $filters['size'] : null,
            $filters['type_code']    ? 'Type ' . $filters['type_code'] : null,
            $filters['cargo_status'] ? $this->words($filters['cargo_status']) : null,
            $filters['condition']    ? $this->words($filters['condition']) : null,
        ])->filter()->implode(' - ');
    }

    /**
     * The date and filters, resolved once.
     *
     * Both the screen and the export read them from here so a file can never
     * describe a different selection from the page it was downloaded off --
     * the same reason `availableStockRows()` and `inventoryQuery()` exist.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function parameters(Request $request): array
    {
        $validated = $request->validate([
            // Defaults to yesterday: today's stock is still moving, and the
            // question is nearly always asked about a closed day.
            'as_at'        => 'nullable|date|before_or_equal:' . now()->toDateString(),
            'customer_id'  => 'nullable|exists:customers,id',
            'size'         => 'nullable|in:20,40,45',
            'type_code'    => 'nullable|string|max:4',
            'cargo_status' => 'nullable|in:empty,laden',
            'condition'    => 'nullable|in:sound,damaged,require_repair',
        ], [], ['as_at' => 'as-at date']);

        return [
            $validated['as_at'] ?? now()->subDay()->toDateString(),
            [
                'customer_id'  => $validated['customer_id']  ?? null,
                'size'         => $validated['size']         ?? null,
                'type_code'    => $validated['type_code']    ?? null,
                'cargo_status' => $validated['cargo_status'] ?? null,
                'condition'    => $validated['condition']    ?? null,
            ],
        ];
    }

    /** `require_repair` reads as "Require Repair" in a file with no badges. */
    private function words(?string $value): string
    {
        return $value ? ucwords(str_replace('_', ' ', $value)) : '';
    }
}
