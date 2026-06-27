<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\ExchangeRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FxRateController extends Controller
{
    /**
     * Look up the exchange rate (base-currency units per 1 unit of `from`) for the
     * receipt / voucher entry forms. Returns 1.0 when `from` is the base currency,
     * and null when no rate is on record so the UI can prompt for manual entry.
     */
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'string', 'max:10'],
            'date' => ['nullable', 'date'],
        ]);

        $from = strtoupper($data['from']);
        $base = CompanySetting::baseCurrency();

        if ($from === $base) {
            return response()->json(['rate' => 1.0, 'base' => $base, 'source' => 'base']);
        }

        $rate = ExchangeRate::getRate($from, $base, $data['date'] ?? null);

        return response()->json([
            'rate'   => $rate !== null ? (float) $rate : null,
            'base'   => $base,
            'source' => $rate !== null ? 'master' : 'none',
        ]);
    }
}
