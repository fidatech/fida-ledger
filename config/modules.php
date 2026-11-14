<?php

/*
| The optional Business modules. The key is the stable identifier of a module: it is stored
| in company_modules, used by the "module:<key>" middleware and by the menu.
|
| 'requires'  modules that must be active before this one can be switched on. A module cannot
|             be switched off while an active module requires it.
|
| Core accounting (dashboard, accounts, Money In / Out, transfers, exchange, invoicing and the
| financial reports) is not listed here: it is part of every plan and cannot be switched off.
| Which plans may use a module is kept in the "modules" table, so the system administrators
| can change it without a new release.
*/
return [
    'products' => [
        'name' => 'Products & Services',
        'description' => 'One catalogue of products and services with units, variants, price lists and barcodes.',
        'icon' => 'boxes-stacked',
        'requires' => [],
    ],
    'purchasing' => [
        'name' => 'Purchasing',
        'description' => 'Purchase orders, goods receipts, supplier bills, returns and landed costs.',
        'icon' => 'cart-flatbed',
        'requires' => ['products'],
    ],
    'inventory' => [
        'name' => 'Inventory',
        'description' => 'Warehouses, stock movements, transfers, adjustments, counts and valuation.',
        'icon' => 'warehouse',
        'requires' => ['products'],
    ],
    'sales' => [
        'name' => 'Sales Management',
        'description' => 'Quotations, sales orders, deliveries and customer returns.',
        'icon' => 'file-invoice-dollar',
        'requires' => ['products'],
    ],
    'pos' => [
        'name' => 'Point of Sale',
        'description' => 'A fast selling screen for shops with registers, cashier sessions and cash closing.',
        'icon' => 'cash-register',
        'requires' => ['products', 'sales', 'inventory'],
    ],
    'fixed_assets' => [
        'name' => 'Fixed Assets',
        'description' => 'Asset register, depreciation, maintenance, transfers and disposals.',
        'icon' => 'building',
        'requires' => [],
    ],
    'payroll' => [
        'name' => 'Payroll',
        'description' => 'Employees, salary structures, payroll runs, payslips and salary advances.',
        'icon' => 'users-gear',
        'requires' => [],
    ],
];
