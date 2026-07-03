<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\CurrencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Canonical exchange-rate lookup for every entry form (receipts, vouchers,
 * credit notes, supplier invoices, storage/handling & reefer billing, manual
 * journals). Returns the foreign→base rate for a currency on a date, sourced
 * from the daily Exchange Rate master via CurrencyService::rateFor().
 *
 * Accepts the currency as `from` or `currency` (both param names are in use
 * across the forms). Response: {currency, base, rate, found, source}.
 */
class FxRateController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from'     => ['nullable', 'string', 'max:10'],
            'currency' => ['nullable', 'string', 'max:10'],
            'date'     => ['nullable', 'date'],
        ]);

        $currency = $data['from'] ?? ($data['currency'] ?? '');

        return response()->json(CurrencyService::rateFor($currency, $data['date'] ?? null));
    }
}
