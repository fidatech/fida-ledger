<?php

/*
| The sidebar of a company.
|   'can'     the permission the user's role needs (config/ledger.php 'roles')
|   'module'  the Business module that must be switched on (config/modules.php)
|   'active'  the route pattern that opens the entry; 'params' tell apart entries that share a route
| Only screens that exist are listed: a module's entries are added here together with the module.
*/
return [
    ['heading' => 'Overview & Finance'],
    ['label' => 'Dashboard', 'icon' => 'gauge-high', 'route' => 'dashboard'],
    ['label' => 'Money In', 'icon' => 'arrow-down', 'can' => 'money.view', 'children' => [
        ['label' => 'Record Money In', 'route' => 'money.create', 'params' => ['direction' => 'in'], 'can' => 'money.create'],
        ['label' => 'Money In History', 'route' => 'money.index', 'params' => ['direction' => 'in'], 'active' => 'money.*'],
    ]],
    ['label' => 'Money Out', 'icon' => 'arrow-up', 'can' => 'money.view', 'children' => [
        ['label' => 'Record Money Out', 'route' => 'money.create', 'params' => ['direction' => 'out'], 'can' => 'money.create'],
        ['label' => 'Money Out History', 'route' => 'money.index', 'params' => ['direction' => 'out'], 'active' => 'money.*'],
    ]],
    ['label' => 'Accounts', 'icon' => 'address-book', 'can' => 'accounts.view', 'children' => [
        ['label' => 'All Accounts', 'route' => 'accounts.index', 'active' => 'accounts.*'],
        ['label' => 'Customers', 'route' => 'accounts.index', 'params' => ['kind' => 'customer'], 'active' => 'accounts.*'],
        ['label' => 'Suppliers', 'route' => 'accounts.index', 'params' => ['kind' => 'supplier'], 'active' => 'accounts.*'],
        ['label' => 'Sarafs', 'route' => 'accounts.index', 'params' => ['kind' => 'saraf'], 'active' => 'accounts.*'],
        ['label' => 'Cash', 'route' => 'accounts.index', 'params' => ['kind' => 'cash'], 'active' => 'accounts.*'],
        ['label' => 'Bank accounts', 'route' => 'accounts.index', 'params' => ['kind' => 'bank'], 'active' => 'accounts.*'],
        ['label' => 'Personal Accounts', 'route' => 'accounts.index', 'params' => ['kind' => 'personal'], 'active' => 'accounts.*'],
        ['label' => 'Loans given', 'route' => 'accounts.index', 'params' => ['kind' => 'loan_receivable'], 'active' => 'accounts.*'],
        ['label' => 'Loans taken', 'route' => 'accounts.index', 'params' => ['kind' => 'loan_payable'], 'active' => 'accounts.*'],
        ['label' => 'Chart of Accounts', 'route' => 'accounts.chart'],
    ]],
    ['label' => 'Transfers & Exchange', 'icon' => 'right-left', 'can' => 'money.view', 'children' => [
        ['label' => 'Account Transfer', 'route' => 'transfers.create', 'can' => 'transfers.create'],
        ['label' => 'Currency Exchange', 'route' => 'transfers.create', 'params' => ['type' => 'exchange'], 'can' => 'transfers.create'],
        ['label' => 'Exchange Rates', 'route' => 'settings.rates', 'can' => 'settings.manage'],
        ['label' => 'Transfer & Exchange History', 'route' => 'transfers.index'],
    ]],

    ['heading' => 'Management'],
    ['label' => 'General Journal', 'icon' => 'book', 'route' => 'journals.index', 'active' => 'journals.*', 'can' => 'journals.view'],
    ['label' => 'Reports', 'icon' => 'chart-column', 'route' => 'reports.index', 'active' => 'reports.*', 'can' => 'reports.view'],
    ['label' => 'Settings', 'icon' => 'gear', 'children' => [
        ['label' => 'Modules', 'route' => 'settings.modules', 'can' => 'settings.manage'],
        ['label' => 'Subscription & Billing', 'route' => 'billing.show'],
    ]],
];
