<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\EquipmentType;
use App\Services\ContainerOcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContainerOcrController extends Controller
{
    public function __construct(private ContainerOcrService $ocr) {}

    public function process(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'max:10240'], // 10 MB
        ]);

        $result = $this->ocr->extractFromImage($request->file('image'));

        $containerNo    = $result['container_no'];
        $masterData     = null;
        $equipmentMatch = null;
        $inYard         = false;
        $inYardSince    = null;

        if ($containerNo) {
            // Container master lookup
            $master = Container::where('container_no', $containerNo)->first();

            if ($master) {
                $masterData = [
                    'equipment_type_id' => $master->equipment_type_id,
                    'customer_id'       => $master->customer_id,
                    'tare_weight'       => $master->tare_weight,
                    'max_gross_weight'  => $master->max_gross_weight,
                ];

                // Use the Container status field — same source of truth as containerLookup
                if ($master->status === 'in_yard') {
                    $inYard      = true;
                    $inYardSince = $master->gate_in_date?->format('d M Y');
                }
            }

            // If OCR picked up an ISO type code, try to match equipment type
            if ($result['iso_type']) {
                $eqt = EquipmentType::where('eqt_code', $result['iso_type'])->first();
                if ($eqt) {
                    $equipmentMatch = [
                        'id'   => $eqt->id,
                        'code' => $eqt->eqt_code,
                        'name' => $eqt->description,
                        'size' => $eqt->size,
                        'type' => $eqt->type_code,
                    ];
                }
            }
        }

        return response()->json([
            'success'        => $containerNo !== null,
            'container_no'   => $containerNo,
            'iso_type'       => $result['iso_type'],
            'tare_kg'        => $result['tare_kg'],
            'max_gross_kg'   => $result['max_gross_kg'],
            'in_yard'        => $inYard,
            'in_yard_since'  => $inYardSince,
            'master'         => $masterData,
            'equipment_match'=> $equipmentMatch,
            'raw_text'       => $result['raw_text'],
            'message'        => $containerNo
                ? 'Container number extracted successfully.'
                : 'Could not read a container number from the image. Please try again or enter manually.',
        ]);
    }
}
