<?php

/**
 * Module Registry — single source of truth for all access-controlled modules.
 *
 * Each entry defines:
 *   label   — human-readable name used in the Access Control UI
 *   section — sidebar/grouping label for the permission matrix UI
 *   actions — ordered list of actions available on the module
 *
 * Running `php artisan permissions:sync` will create any missing Permission
 * records and leave existing assignments untouched.
 *
 * ADDING A NEW MODULE:
 *   1. Add an entry here.
 *   2. Run: php artisan permissions:sync
 *   3. Assign the new permissions to roles in the Access Control UI.
 */

return [

    // ── Billing ───────────────────────────────────────────────────────────────

    'billing.storage' => [
        'label'   => 'Storage Billing',
        'section' => 'Billing',
        'actions' => ['view', 'create', 'edit', 'delete', 'approve', 'pdf', 'email'],
    ],

    'billing.storage-handling' => [
        'label'   => 'Storage & Handling Billing',
        'section' => 'Billing',
        'actions' => ['view', 'create', 'edit', 'delete', 'approve', 'pdf'],
    ],

    'billing.reefer' => [
        'label'   => 'Reefer Electricity Billing',
        'section' => 'Billing',
        'actions' => ['view', 'create', 'edit', 'delete', 'approve', 'pdf'],
    ],

    'billing.repair' => [
        'label'   => 'Repair Invoices',
        'section' => 'Billing',
        'actions' => ['view', 'create', 'edit', 'delete', 'approve', 'pdf'],
    ],

    // ── Yard Operations ───────────────────────────────────────────────────────

    'yard' => [
        'label'   => 'Yard Gate Operations',
        'section' => 'Yard',
        'actions' => ['view', 'gate-in', 'gate-out', 'movement-edit', 'movement-delete', 'backdate'],
    ],

    'yard.jobs' => [
        'label'   => 'Yard Jobs',
        'section' => 'Yard',
        'actions' => ['view', 'edit'],
    ],

    'yard.reefer' => [
        'label'   => 'Reefer Sessions',
        'section' => 'Yard',
        'actions' => ['view', 'plug-in', 'plug-out', 'temp-log'],
    ],

    // ── Operations ────────────────────────────────────────────────────────────

    'surveys' => [
        'label'   => 'Surveys & Inquiries',
        'section' => 'Operations',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    'estimates' => [
        'label'   => 'Repair Estimates',
        'section' => 'Operations',
        'actions' => ['view', 'create', 'edit', 'delete', 'approve'],
    ],

    'work-orders' => [
        'label'   => 'Work Orders',
        'section' => 'Operations',
        'actions' => ['view', 'create', 'edit', 'delete', 'approve'],
    ],

    'approvals' => [
        'label'   => 'Approvals',
        'section' => 'Operations',
        'actions' => ['view', 'approve', 'reject'],
    ],

    'guard-post' => [
        'label'   => 'Guard Post',
        'section' => 'Operations',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    // ── Customers & Containers ────────────────────────────────────────────────

    'customers' => [
        'label'   => 'Customers',
        'section' => 'Customers & Containers',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    'containers' => [
        'label'   => 'Containers',
        'section' => 'Customers & Containers',
        'actions' => ['view', 'create', 'edit', 'delete', 'hold'],
    ],

    'container-bookings' => [
        'label'   => 'Container Bookings',
        'section' => 'Customers & Containers',
        'actions' => ['view', 'create', 'edit', 'allocate', 'cancel', 'delete'],
    ],

    // ── Masters — Tariffs ─────────────────────────────────────────────────────

    'masters.reefer-tariff' => [
        'label'   => 'Reefer Electricity Tariff',
        'section' => 'Masters — Tariffs',
        'actions' => ['view', 'create', 'edit', 'delete', 'toggle'],
    ],

    'masters.storage-tariff' => [
        'label'   => 'Storage Tariff',
        'section' => 'Masters — Tariffs',
        'actions' => ['view', 'create', 'edit', 'delete', 'toggle'],
    ],

    'masters.handling-tariff' => [
        'label'   => 'Handling Tariff',
        'section' => 'Masters — Tariffs',
        'actions' => ['view', 'create', 'edit', 'delete', 'toggle'],
    ],

    'masters.mr-tariff' => [
        'label'   => 'M&R Tariff',
        'section' => 'Masters — Tariffs',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    // ── Masters — Operations ─────────────────────────────────────────────────

    'masters.job-types' => [
        'label'   => 'Gate-In Job Types',
        'section' => 'Masters — Operations',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    // ── Masters — Reference Data ──────────────────────────────────────────────

    'masters.charge-codes' => [
        'label'   => 'Charge Codes',
        'section' => 'Masters — Reference',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    'masters.tax-codes' => [
        'label'   => 'Tax Codes',
        'section' => 'Masters — Reference',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    'masters.currencies' => [
        'label'   => 'Currencies',
        'section' => 'Masters — Reference',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    'masters.banks' => [
        'label'   => 'Banks',
        'section' => 'Masters — Reference',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    'masters.exchange-rates' => [
        'label'   => 'Exchange Rates',
        'section' => 'Masters — Reference',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    'masters.equipment-types' => [
        'label'   => 'Equipment Types',
        'section' => 'Masters — Reference',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    'masters.container-grades' => [
        'label'   => 'Container Grades',
        'section' => 'Masters — Reference',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    'masters.storage-zones' => [
        'label'   => 'Storage Zones & Slots',
        'section' => 'Masters — Reference',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    'masters.customer-types' => [
        'label'   => 'Customer Types',
        'section' => 'Masters — Reference',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    'masters.mr-codes' => [
        'label'   => 'M&R Codes',
        'section' => 'Masters — Reference',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    'masters.repair-categories' => [
        'label'   => 'Repair Categories',
        'section' => 'Masters — Reference',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    'masters.damage-rules' => [
        'label'   => 'Damage Assessment Rules',
        'section' => 'Masters — Reference',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    'masters.checklist-items' => [
        'label'   => 'Checklist Items',
        'section' => 'Masters — Reference',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    'masters.countries' => [
        'label'       => 'Countries & States',
        'section'     => 'Masters — Reference',
        'system_only' => true,
        'actions'     => ['view', 'create', 'edit', 'delete'],
    ],

    // ── Finance ───────────────────────────────────────────────────────────────

    'finance.setup' => [
        'label'   => 'Finance Setup (Fiscal Years)',
        'section' => 'Finance',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    'finance.periods' => [
        'label'   => 'Accounting Periods',
        'section' => 'Finance',
        'actions' => ['view', 'close', 'reopen'],
    ],

    'finance.coa' => [
        'label'   => 'Chart of Accounts',
        'section' => 'Finance',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    'finance.mappings' => [
        'label'   => 'Account Mappings',
        'section' => 'Finance',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    'finance.gl' => [
        'label'   => 'General Ledger',
        'section' => 'Finance',
        'actions' => ['view', 'create', 'post', 'void'],
    ],

    'finance.ar' => [
        'label'   => 'AR / Invoice Posting',
        'section' => 'Finance',
        'actions' => ['view', 'post', 'void'],
    ],

    'finance.receipts' => [
        'label'   => 'Receipts',
        'section' => 'Finance',
        'actions' => ['view', 'create', 'edit', 'confirm', 'void', 'delete', 'pdf', 'email'],
    ],

    'finance.vouchers' => [
        'label'   => 'Payment Vouchers',
        'section' => 'Finance',
        'actions' => ['view', 'create', 'edit', 'confirm', 'void', 'pdf', 'email'],
    ],

    'finance.ar-credit-notes' => [
        'label'   => 'AR Credit Notes',
        'section' => 'Finance',
        'actions' => ['view', 'create', 'edit', 'approve', 'delete', 'pdf', 'email'],
    ],

    'finance.ap-credit-notes' => [
        'label'   => 'AP Credit Notes',
        'section' => 'Finance',
        'actions' => ['view', 'create', 'edit', 'approve', 'delete', 'pdf', 'email'],
    ],

    'finance.ap' => [
        'label'   => 'AP / Supplier Invoices',
        'section' => 'Finance',
        'actions' => ['view', 'create', 'post', 'void', 'delete'],
    ],

    'finance.bank-reconciliation' => [
        'label'   => 'Bank Reconciliation',
        'section' => 'Finance',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    // ── Reports ───────────────────────────────────────────────────────────────

    'reports' => [
        'label'   => 'Reports',
        'section' => 'Reports',
        'actions' => ['view'],
    ],

    'container-inquiry' => [
        'label'   => 'Container Inquiry',
        'section' => 'Reports',
        'actions' => ['view'],
    ],

    // ── Settings ──────────────────────────────────────────────────────────────

    'settings.users' => [
        'label'   => 'User Management',
        'section' => 'Settings',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    'settings.company' => [
        'label'       => 'Company Settings',
        'section'     => 'Settings',
        'system_only' => true,
        'actions'     => ['view', 'edit'],
    ],

    'settings.cloud-storage' => [
        'label'       => 'Document Storage',
        'section'     => 'Settings',
        'system_only' => true,
        'actions'     => ['view', 'edit'],
    ],

    'settings.approval-workflows' => [
        'label'   => 'Approval Workflows',
        'section' => 'Settings',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    'access-control' => [
        'label'   => 'Access Control',
        'section' => 'Settings',
        'actions' => ['view', 'create', 'edit', 'delete'],
    ],

    'audit-log' => [
        'label'   => 'Audit Log',
        'section' => 'Settings',
        'actions' => ['view'],
    ],

];
