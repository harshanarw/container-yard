<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExchangeRateController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:masters.exchange-rates.view')->only(['index']);
        $this->middleware('can:masters.exchange-rates.create')->only(['store']);
        $this->middleware('can:masters.exchange-rates.edit')->only(['update']);
        $this->middleware('can:masters.exchange-rates.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $settings        = CompanySetting::current();
        $defaultCurrency = $settings->default_currency_code ?? 'LKR';
        $currencies      = Currency::where('is_active', true)->orderBy('code')->get();

        $rates = ExchangeRate::with(['createdBy'])
            ->when($request->date_from, fn ($q, $v) => $q->where('rate_date', '>=', $v))
            ->when($request->date_to,   fn ($q, $v) => $q->where('rate_date', '<=', $v))
            ->when($request->from_currency, fn ($q, $v) => $q->where('from_currency_code', strtoupper($v)))
            ->when($request->to_currency,   fn ($q, $v) => $q->where('to_currency_code',   strtoupper($v)))
            ->orderByDesc('rate_date')
            ->orderBy('from_currency_code')
            ->paginate(25)
            ->withQueryString();

        return view('masters.exchange-rates.index', compact(
            'rates', 'currencies', 'defaultCurrency'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'rate_date'          => ['required', 'date'],
            'from_currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'to_currency_code'   => ['required', 'string', 'size:3', 'exists:currencies,code',
                                     'different:from_currency_code'],
            'rate'               => ['required', 'numeric', 'min:0.0001'],
            'notes'              => ['nullable', 'string', 'max:255'],
        ]);

        $data['from_currency_code'] = strtoupper($data['from_currency_code']);
        $data['to_currency_code']   = strtoupper($data['to_currency_code']);
        $data['rate']               = (float) $data['rate'];

        $exists = ExchangeRate::where('rate_date',          $data['rate_date'])
            ->where('from_currency_code', $data['from_currency_code'])
            ->where('to_currency_code',   $data['to_currency_code'])
            ->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->with('error', "A rate for {$data['from_currency_code']} → {$data['to_currency_code']} on {$data['rate_date']} already exists. Edit the existing entry instead.");
        }

        $data['created_by'] = auth()->id();

        ExchangeRate::create($data);

        return back()->with('success',
            "Rate saved: 1 {$data['from_currency_code']} = {$data['rate']} {$data['to_currency_code']} on {$data['rate_date']}."
        );
    }

    public function update(Request $request, ExchangeRate $exchangeRate)
    {
        $data = $request->validate([
            'rate_date'          => ['required', 'date'],
            'from_currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'to_currency_code'   => ['required', 'string', 'size:3', 'exists:currencies,code',
                                     'different:from_currency_code'],
            'rate'               => ['required', 'numeric', 'min:0.0001'],
            'notes'              => ['nullable', 'string', 'max:255'],
        ]);

        $data['from_currency_code'] = strtoupper($data['from_currency_code']);
        $data['to_currency_code']   = strtoupper($data['to_currency_code']);
        $data['rate']               = (float) $data['rate'];

        $duplicate = ExchangeRate::where('rate_date',          $data['rate_date'])
            ->where('from_currency_code', $data['from_currency_code'])
            ->where('to_currency_code',   $data['to_currency_code'])
            ->where('id', '!=', $exchangeRate->id)
            ->exists();

        if ($duplicate) {
            return back()->with('error',
                "Another entry for {$data['from_currency_code']} → {$data['to_currency_code']} on {$data['rate_date']} already exists."
            );
        }

        $exchangeRate->update($data);

        return back()->with('success',
            "Rate updated: 1 {$data['from_currency_code']} = {$data['rate']} {$data['to_currency_code']} on {$data['rate_date']}."
        );
    }

    public function destroy(ExchangeRate $exchangeRate)
    {
        $label = "1 {$exchangeRate->from_currency_code} = {$exchangeRate->rate} {$exchangeRate->to_currency_code} ({$exchangeRate->rate_date->format('d M Y')})";
        $exchangeRate->delete();

        return back()->with('success', "Exchange rate deleted: {$label}.");
    }
}
