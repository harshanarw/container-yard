<?php

namespace Database\Seeders\Finance;

use App\Models\Account;
use Illuminate\Database\Seeder;

class DefaultCoaSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = $this->accountData();

        // First pass: create all accounts without parent links
        $codeToId = [];
        foreach ($accounts as $row) {
            $acc = Account::updateOrCreate(
                ['code' => $row['code']],
                [
                    'name'                 => $row['name'],
                    'classification'       => $row['classification'],
                    'normal_balance'       => $row['normal_balance'],
                    'is_posting'           => $row['is_posting'] ?? false,
                    'is_control'           => $row['is_control'] ?? false,
                    'is_receivable'        => $row['is_receivable'] ?? false,
                    'is_payable'           => $row['is_payable'] ?? false,
                    'is_cash_bank'         => $row['is_cash_bank'] ?? false,
                    'is_system'            => $row['is_system'] ?? false,
                    'is_active'            => true,
                    'sort_order'           => $row['sort_order'] ?? 0,
                    'opening_balance'      => 0,
                    'opening_balance_type' => $row['normal_balance'] === 'debit' ? 'debit' : 'credit',
                    'parent_id'            => null,
                ]
            );
            $codeToId[$row['code']] = $acc->id;
        }

        // Second pass: set parent_id
        foreach ($accounts as $row) {
            if (!empty($row['parent'])) {
                $parentId = $codeToId[$row['parent']] ?? null;
                if ($parentId) {
                    Account::where('code', $row['code'])->update(['parent_id' => $parentId]);
                }
            }
        }
    }

    private function accountData(): array
    {
        return [
            // ── ASSETS (normal_balance: debit) ───────────────────────────────
            ['code' => '1000', 'name' => 'Current Assets',              'classification' => 'asset', 'normal_balance' => 'debit',  'sort_order' => 10],
            ['code' => '1010', 'name' => 'Cash & Bank',                 'classification' => 'asset', 'normal_balance' => 'debit',  'parent' => '1000', 'sort_order' => 11],
            ['code' => '1011', 'name' => 'Petty Cash',                  'classification' => 'asset', 'normal_balance' => 'debit',  'parent' => '1010', 'is_posting' => true, 'is_cash_bank' => true, 'is_system' => true, 'sort_order' => 12],
            ['code' => '1012', 'name' => 'Bank - Current Account',      'classification' => 'asset', 'normal_balance' => 'debit',  'parent' => '1010', 'is_posting' => true, 'is_cash_bank' => true, 'is_system' => true, 'sort_order' => 13],
            ['code' => '1013', 'name' => 'Bank - Savings Account',      'classification' => 'asset', 'normal_balance' => 'debit',  'parent' => '1010', 'is_posting' => true, 'is_cash_bank' => true, 'sort_order' => 14],
            ['code' => '1100', 'name' => 'Accounts Receivable',         'classification' => 'asset', 'normal_balance' => 'debit',  'parent' => '1000', 'sort_order' => 20],
            ['code' => '1101', 'name' => 'Trade Debtors (Control)',     'classification' => 'asset', 'normal_balance' => 'debit',  'parent' => '1100', 'is_posting' => true, 'is_control' => true, 'is_receivable' => true, 'is_system' => true, 'sort_order' => 21],
            ['code' => '1102', 'name' => 'Other Debtors',               'classification' => 'asset', 'normal_balance' => 'debit',  'parent' => '1100', 'is_posting' => true, 'sort_order' => 22],
            ['code' => '1103', 'name' => 'WHT Receivable (Income Tax)', 'classification' => 'asset', 'normal_balance' => 'debit',  'parent' => '1100', 'is_posting' => true, 'sort_order' => 23],
            ['code' => '1200', 'name' => 'Advance Payments Made',       'classification' => 'asset', 'normal_balance' => 'debit',  'parent' => '1000', 'sort_order' => 30],
            ['code' => '1201', 'name' => 'Advance Payments to Suppliers', 'classification' => 'asset', 'normal_balance' => 'debit', 'parent' => '1200', 'is_posting' => true, 'is_system' => true, 'sort_order' => 31],
            ['code' => '1300', 'name' => 'Tax Receivable',              'classification' => 'asset', 'normal_balance' => 'debit',  'parent' => '1000', 'sort_order' => 40],
            ['code' => '1301', 'name' => 'Input VAT Receivable',        'classification' => 'asset', 'normal_balance' => 'debit',  'parent' => '1300', 'is_posting' => true, 'sort_order' => 41],
            ['code' => '1302', 'name' => 'SSCL Receivable',             'classification' => 'asset', 'normal_balance' => 'debit',  'parent' => '1300', 'is_posting' => true, 'sort_order' => 42],
            ['code' => '1400', 'name' => 'Prepayments',                 'classification' => 'asset', 'normal_balance' => 'debit',  'parent' => '1000', 'sort_order' => 50],
            ['code' => '1401', 'name' => 'Prepaid Expenses',            'classification' => 'asset', 'normal_balance' => 'debit',  'parent' => '1400', 'is_posting' => true, 'sort_order' => 51],
            ['code' => '1402', 'name' => 'Security Deposits',           'classification' => 'asset', 'normal_balance' => 'debit',  'parent' => '1400', 'is_posting' => true, 'sort_order' => 52],
            ['code' => '1500', 'name' => 'Fixed Assets',                'classification' => 'asset', 'normal_balance' => 'debit',  'sort_order' => 60],
            ['code' => '1501', 'name' => 'Property & Equipment',        'classification' => 'asset', 'normal_balance' => 'debit',  'parent' => '1500', 'is_posting' => true, 'sort_order' => 61],
            ['code' => '1502', 'name' => 'Accumulated Depreciation',    'classification' => 'asset', 'normal_balance' => 'credit', 'parent' => '1500', 'is_posting' => true, 'sort_order' => 62],

            // ── LIABILITIES (normal_balance: credit) ─────────────────────────
            ['code' => '2000', 'name' => 'Current Liabilities',         'classification' => 'liability', 'normal_balance' => 'credit', 'sort_order' => 100],
            ['code' => '2010', 'name' => 'Accounts Payable',            'classification' => 'liability', 'normal_balance' => 'credit', 'parent' => '2000', 'sort_order' => 110],
            ['code' => '2011', 'name' => 'Trade Creditors (Control)',   'classification' => 'liability', 'normal_balance' => 'credit', 'parent' => '2010', 'is_posting' => true, 'is_control' => true, 'is_payable' => true, 'is_system' => true, 'sort_order' => 111],
            ['code' => '2012', 'name' => 'Other Creditors',             'classification' => 'liability', 'normal_balance' => 'credit', 'parent' => '2010', 'is_posting' => true, 'sort_order' => 112],
            ['code' => '2020', 'name' => 'Advance Receipts from Customers', 'classification' => 'liability', 'normal_balance' => 'credit', 'parent' => '2000', 'sort_order' => 120],
            ['code' => '2021', 'name' => 'Customer Advance Receipts',   'classification' => 'liability', 'normal_balance' => 'credit', 'parent' => '2020', 'is_posting' => true, 'is_system' => true, 'sort_order' => 121],
            ['code' => '2100', 'name' => 'Tax Payable',                 'classification' => 'liability', 'normal_balance' => 'credit', 'parent' => '2000', 'sort_order' => 130],
            ['code' => '2101', 'name' => 'Output VAT Payable',          'classification' => 'liability', 'normal_balance' => 'credit', 'parent' => '2100', 'is_posting' => true, 'is_system' => true, 'sort_order' => 131],
            ['code' => '2102', 'name' => 'SSCL Payable',                'classification' => 'liability', 'normal_balance' => 'credit', 'parent' => '2100', 'is_posting' => true, 'is_system' => true, 'sort_order' => 132],
            ['code' => '2103', 'name' => 'Withholding Tax Payable',     'classification' => 'liability', 'normal_balance' => 'credit', 'parent' => '2100', 'is_posting' => true, 'sort_order' => 133],
            ['code' => '2200', 'name' => 'Accrued Liabilities',         'classification' => 'liability', 'normal_balance' => 'credit', 'parent' => '2000', 'sort_order' => 140],
            ['code' => '2201', 'name' => 'Accrued Expenses',            'classification' => 'liability', 'normal_balance' => 'credit', 'parent' => '2200', 'is_posting' => true, 'sort_order' => 141],
            ['code' => '2300', 'name' => 'Long-term Liabilities',       'classification' => 'liability', 'normal_balance' => 'credit', 'sort_order' => 150],
            ['code' => '2301', 'name' => 'Long-term Loans',             'classification' => 'liability', 'normal_balance' => 'credit', 'parent' => '2300', 'is_posting' => true, 'sort_order' => 151],

            // ── EQUITY (normal_balance: credit) ──────────────────────────────
            ['code' => '3000', 'name' => 'Shareholders Equity',         'classification' => 'equity', 'normal_balance' => 'credit', 'sort_order' => 200],
            ['code' => '3001', 'name' => 'Share Capital',               'classification' => 'equity', 'normal_balance' => 'credit', 'parent' => '3000', 'is_posting' => true, 'sort_order' => 201],
            ['code' => '3002', 'name' => 'Retained Earnings',           'classification' => 'equity', 'normal_balance' => 'credit', 'parent' => '3000', 'is_posting' => true, 'is_system' => true, 'sort_order' => 202],
            ['code' => '3003', 'name' => 'Current Year Profit / Loss',  'classification' => 'equity', 'normal_balance' => 'credit', 'parent' => '3000', 'is_posting' => true, 'is_system' => true, 'sort_order' => 203],

            // ── INCOME (normal_balance: credit) ──────────────────────────────
            ['code' => '4000', 'name' => 'Operating Revenue',           'classification' => 'income', 'normal_balance' => 'credit', 'sort_order' => 300],
            ['code' => '4001', 'name' => 'Storage Revenue',             'classification' => 'income', 'normal_balance' => 'credit', 'parent' => '4000', 'is_posting' => true, 'is_system' => true, 'sort_order' => 301],
            ['code' => '4002', 'name' => 'Handling Revenue',            'classification' => 'income', 'normal_balance' => 'credit', 'parent' => '4000', 'is_posting' => true, 'is_system' => true, 'sort_order' => 302],
            ['code' => '4003', 'name' => 'Repair (M&R) Revenue',        'classification' => 'income', 'normal_balance' => 'credit', 'parent' => '4000', 'is_posting' => true, 'is_system' => true, 'sort_order' => 303],
            ['code' => '4004', 'name' => 'Reefer Electricity Revenue',  'classification' => 'income', 'normal_balance' => 'credit', 'parent' => '4000', 'is_posting' => true, 'is_system' => true, 'sort_order' => 304],
            ['code' => '4005', 'name' => 'Survey & Inspection Revenue', 'classification' => 'income', 'normal_balance' => 'credit', 'parent' => '4000', 'is_posting' => true, 'is_system' => true, 'sort_order' => 305],
            ['code' => '4006', 'name' => 'Other Operational Revenue',   'classification' => 'income', 'normal_balance' => 'credit', 'parent' => '4000', 'is_posting' => true, 'sort_order' => 306],
            ['code' => '4100', 'name' => 'Other Income',                'classification' => 'income', 'normal_balance' => 'credit', 'sort_order' => 310],
            ['code' => '4101', 'name' => 'Interest Income',             'classification' => 'income', 'normal_balance' => 'credit', 'parent' => '4100', 'is_posting' => true, 'sort_order' => 311],
            ['code' => '4102', 'name' => 'Foreign Exchange Gain',       'classification' => 'income', 'normal_balance' => 'credit', 'parent' => '4100', 'is_posting' => true, 'sort_order' => 312],

            // ── EXPENSES (normal_balance: debit) ─────────────────────────────
            ['code' => '5000', 'name' => 'Cost of Revenue',             'classification' => 'expense', 'normal_balance' => 'debit', 'sort_order' => 400],
            ['code' => '5001', 'name' => 'Labour Costs',                'classification' => 'expense', 'normal_balance' => 'debit', 'parent' => '5000', 'is_posting' => true, 'sort_order' => 401],
            ['code' => '5002', 'name' => 'Material Costs',              'classification' => 'expense', 'normal_balance' => 'debit', 'parent' => '5000', 'is_posting' => true, 'sort_order' => 402],
            ['code' => '6000', 'name' => 'Operating Expenses',          'classification' => 'expense', 'normal_balance' => 'debit', 'sort_order' => 410],
            ['code' => '6001', 'name' => 'Salaries & Wages',            'classification' => 'expense', 'normal_balance' => 'debit', 'parent' => '6000', 'is_posting' => true, 'sort_order' => 411],
            ['code' => '6002', 'name' => 'Utilities',                   'classification' => 'expense', 'normal_balance' => 'debit', 'parent' => '6000', 'is_posting' => true, 'sort_order' => 412],
            ['code' => '6003', 'name' => 'Rent & Facilities',           'classification' => 'expense', 'normal_balance' => 'debit', 'parent' => '6000', 'is_posting' => true, 'sort_order' => 413],
            ['code' => '6004', 'name' => 'Depreciation',                'classification' => 'expense', 'normal_balance' => 'debit', 'parent' => '6000', 'is_posting' => true, 'sort_order' => 414],
            ['code' => '6005', 'name' => 'Office & Administrative',     'classification' => 'expense', 'normal_balance' => 'debit', 'parent' => '6000', 'is_posting' => true, 'sort_order' => 415],
            ['code' => '7000', 'name' => 'Other Expenses',              'classification' => 'expense', 'normal_balance' => 'debit', 'sort_order' => 420],
            ['code' => '7001', 'name' => 'Interest Expense',            'classification' => 'expense', 'normal_balance' => 'debit', 'parent' => '7000', 'is_posting' => true, 'sort_order' => 421],
            ['code' => '7002', 'name' => 'Foreign Exchange Loss',       'classification' => 'expense', 'normal_balance' => 'debit', 'parent' => '7000', 'is_posting' => true, 'sort_order' => 422],
            ['code' => '7003', 'name' => 'Bank Charges',                'classification' => 'expense', 'normal_balance' => 'debit', 'parent' => '7000', 'is_posting' => true, 'is_system' => true, 'sort_order' => 423],
            ['code' => '7004', 'name' => 'Bad Debt Write-Off',          'classification' => 'expense', 'normal_balance' => 'debit', 'parent' => '7000', 'is_posting' => true, 'is_system' => true, 'sort_order' => 424],
            ['code' => '7005', 'name' => 'Discount Allowed',            'classification' => 'expense', 'normal_balance' => 'debit', 'parent' => '7000', 'is_posting' => true, 'sort_order' => 425],
        ];
    }
}
