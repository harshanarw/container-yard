<?php

namespace App\Http\Controllers;

use App\Services\PlateOcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlateOcrController extends Controller
{
    public function __construct(private PlateOcrService $ocr) {}

    public function process(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'max:10240'], // 10 MB
        ]);

        try {
            $result = $this->ocr->extractFromImage($request->file('image'));
        } catch (\Throwable $e) {
            \Log::error('Plate OCR failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success'  => false,
                'plate_no' => null,
                'raw_text' => '',
                'message'  => 'OCR processing error: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success'  => $result['plate_no'] !== null,
            'plate_no' => $result['plate_no'],
            'raw_text' => $result['raw_text'],
            'message'  => $result['plate_no']
                ? 'Vehicle plate extracted — please verify before accepting.'
                : 'Could not read a plate number from the image. Please enter manually.',
        ]);
    }
}
