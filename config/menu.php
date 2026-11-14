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
    ['label' => 'Invoicing', 'icon' => 'file-invoice-dollar', 'can' => 'invoices.view', 'children' => [
        ['label' => 'All Invoices', 'route' => 'invoices.index', 'active' => 'invoices.*'],
        ['label' => 'Create Invoice', 'route' => 'invoices.create', 'can' => 'invoices.manage'],
        ['label' => 'Credit Notes', 'route' => 'invoices.index', 'params' => ['type' => 'credit_note'], 'active' => 'invoices.*'],
    ]],

    ['heading' => 'Business Operations'],
    ['label' => 'Products & Services', 'icon' => 'boxes-stacked', 'module' => 'products', 'can' => 'products.view', 'children' => [
        ['label' => 'All Products', 'route' => 'products.index', 'params' => ['type' => 'product'], 'active' => 'products.*'],
        ['label' => 'Services', 'route' => 'products.index', 'params' => ['type' => 'service'], 'active' => 'products.*'],
        ['label' => 'Categories', 'route' => 'product-categories.index', 'active' => 'product-categories.*'],
        ['label' => 'Units', 'route' => 'units.index', 'active' => 'units.*'],
    ]],
    ['label' => 'Purchasing', 'icon' => 'cart-flatbed', 'module' => 'purchasing', 'can' => 'purchases.view', 'children' => [
        ['label' => 'Purchase Orders', 'route' => 'purchases.index', 'active' => 'purchases.*'],
        ['label' => 'New Purchase Order', 'route' => 'purchases.create', 'can' => 'purchases.manage'],
    ]],
    ['label' => 'Inventory', 'icon' => 'warehouse', 'module' => 'inventory', 'can' => 'inventory.view', 'children' => [
        ['label' => 'Stock Overview', 'route' => 'inventory.overview'],
        ['label' => 'Stock Movements', 'route' => 'inventory.movements'],
        ['label' => 'Stock Transfers', 'route' => 'inventory.change', 'params' => ['mode' => 'transfer'], 'can' => 'inventory.manage'],
        ['label' => 'Stock Adjustments', 'route' => 'inventory.change', 'params' => ['mode' => 'adjust'], 'can' => 'inventory.manage'],
        ['label' => 'Physical Stock Counts', 'route' => 'inventory.change', 'params' => ['mode' => 'count'], 'can' => 'inventory.manage'],
        ['label' => 'Warehouses', 'route' => 'warehouses.index', 'active' => 'warehouses.*'],
    ]],
    ['label' => 'Sales Management', 'icon' => 'file-signature', 'module' => 'sales', 'can' => 'sales.view', 'children' => [
        ['label' => 'Quotations', 'route' => 'sales.index', 'params' => ['doc' => 'quotation'], 'active' => 'sales.*'],
        ['label' => 'Sales Orders', 'route' => 'sales.index', 'params' => ['doc' => 'order'], 'active' => 'sales.*'],
    ]],
    ['label' => 'Point of Sale', 'icon' => 'cash-register', 'module' => 'pos', 'can' => 'pos.use', 'children' => [
        ['label' => 'Open POS', 'route' => 'pos.index'],
        ['label' => 'Cashier Sessions', 'route' => 'pos.sessions', 'active' => 'pos.session*'],
        ['label' => 'POS Sales', 'route' => 'pos.sales', 'active' => 'pos.sale*'],
        ['label' => 'POS Returns', 'route' => 'pos.sales', 'params' => ['status' => 'refunded'], 'active' => 'pos.sale*'],
        ['label' => 'Registers', 'route' => 'pos.registers.index', 'active' => 'pos.registers.*', 'can' => 'settings.manage'],
    ]],
    ['label' => 'Fixed Assets', 'icon' => 'building', 'module' => 'fixed_assets', 'can' => 'assets.view', 'children' => [
        ['label' => 'Asset Register', 'route' => 'assets.index', 'active' => 'assets.*'],
        ['label' => 'Asset Categories', 'route' => 'asset-categories.index', 'active' => 'asset-categories.*'],
    ]],
    ['label' => 'Payroll', 'icon' => 'users-gear', 'module' => 'payroll', 'can' => 'payroll.view', 'children' => [
        ['label' => 'Employees', 'route' => 'employees.index', 'active' => 'employees.*'],
        ['label' => 'Departments', 'route' => 'departments.index', 'active' => 'departments.*'],
        ['label' => 'Payroll Runs', 'route' => 'payroll.index', 'active' => 'payroll.show'],
        ['label' => 'Salary Advances', 'route' => 'payroll.advances'],
    ]],

    ['heading' => 'Management'],
    ['label' => 'General Journal', 'icon' => 'book', 'route' => 'journals.index', 'active' => 'journals.*', 'can' => 'journals.view'],
    ['label' => 'Reports', 'icon' => 'chart-column', 'route' => 'reports.index', 'active' => 'reports.*', 'can' => 'reports.view'],
    ['label' => 'Settings', 'icon' => 'gear', 'children' => [
        ['label' => 'Company Profile', 'route' => 'settings.company', 'can' => 'settings.manage'],
        ['label' => 'Users & Roles', 'route' => 'settings.users', 'can' => 'settings.manage'],
        ['label' => 'Modules', 'route' => 'settings.modules', 'can' => 'settings.manage'],
        ['label' => 'Currencies', 'route' => 'settings.currencies', 'can' => 'settings.manage'],
        ['label' => 'Subscription & Billing', 'route' => 'billing.show'],
    ]],
];
