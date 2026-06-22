<?php

/*
|--------------------------------------------------------------------------
| Email notification categories
|--------------------------------------------------------------------------
|
| Single source of truth for the category keys used across the email system.
| Three consumers, three lists:
|
|   external → email_configs            (sender driver + common CC per category)
|   customer → customer_email_contacts  (per-customer TO / optional CC)
|   internal → internal_notification_emails (staff-only recipients)
|
| Each entry carries the label/icon/colour used by the settings + profile UI so
| views loop over config instead of hard-coding arrays, and controllers derive
| their validation `in:` lists from array_keys() of these.
|
*/

return [

    // Customer-facing categories — drive the common sender/CC config (email_configs).
    'external' => [
        'estimate'        => ['label' => 'Repair Estimates', 'icon' => 'bi-file-earmark-text', 'color' => 'primary'],
        'invoice'         => ['label' => 'Invoices',         'icon' => 'bi-receipt',           'color' => 'success'],
        'movement_report' => ['label' => 'Movement Reports', 'icon' => 'bi-truck',             'color' => 'info'],
        'stock_report'    => ['label' => 'Stock Reports',    'icon' => 'bi-box-seam',          'color' => 'secondary'],
        'general'         => ['label' => 'General',          'icon' => 'bi-envelope',          'color' => 'dark'],
    ],

    // Per-customer recipient lists (TO + optional CC) shown on the customer profile
    // and the External settings tab. Subset of external — no 'general'.
    'customer' => [
        'estimate'        => ['label' => 'Repair Estimate Emails', 'icon' => 'bi-file-earmark-text', 'color' => 'primary'],
        'invoice'         => ['label' => 'Invoice Emails',         'icon' => 'bi-receipt',           'color' => 'success'],
        'movement_report' => ['label' => 'Movement Report Emails', 'icon' => 'bi-truck',             'color' => 'info'],
    ],

    // Internal staff-only notification recipient lists (internal_notification_emails).
    'internal' => [
        'estimate_approval' => ['label' => 'Estimate Approval / Rejection', 'icon' => 'bi-file-earmark-check', 'color' => 'primary'],
        'invoice'           => ['label' => 'Invoice Notifications',         'icon' => 'bi-receipt',            'color' => 'success'],
        'movement_report'   => ['label' => 'Movement Reports',             'icon' => 'bi-truck',              'color' => 'info'],
        'general'           => ['label' => 'General Notifications',         'icon' => 'bi-bell',               'color' => 'secondary'],
    ],

];
