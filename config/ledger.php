<?php

/*
| Accounting rules of Fida Ledger. See docs/ARCHITECTURE.md for the reasons behind them.
*/
return [
    // decimals kept in the database for amounts and for exchange rates
    'scale' => 4,
    'rate_scale' => 8,

    // currencies every new company can pick from: code => [name, symbol]
    'currencies' => [
        'AFN' => ['Afghani', '؋'],
        'USD' => ['US Dollar', '$'],
        'PKR' => ['Pakistani Rupee', '₨'],
        'EUR' => ['Euro', '€'],
    ],

    'company_types' => [
        'individual' => 'Individual or freelancer',
        'trading' => 'Trading company',
        'retail' => 'Retail shop',
        'wholesale' => 'Wholesale business',
        'saraf' => 'Saraf / money exchange',
        'services' => 'Service provider',
        'other' => 'Other',
    ],

    // the five classes and the side on which each one grows
    'classes' => [
        'asset' => 'debit',
        'liability' => 'credit',
        'equity' => 'credit',
        'income' => 'credit',
        'expense' => 'debit',
    ],

    /*
    | Kinds of account a user can open. 'group' is the code of the group account in the chart
    | the new account is placed under; 'currency' => true means the account holds one currency only.
    */
    'kinds' => [
        'cash' => ['label' => 'Cash', 'plural' => 'Cash', 'class' => 'asset', 'group' => '1100', 'currency' => true, 'icon' => 'money-bill-wave'],
        'bank' => ['label' => 'Bank account', 'plural' => 'Bank accounts', 'class' => 'asset', 'group' => '1200', 'currency' => true, 'icon' => 'building-columns'],
        'customer' => ['label' => 'Customer', 'plural' => 'Customers', 'class' => 'asset', 'group' => '1300', 'icon' => 'user-tag'],
        'saraf' => ['label' => 'Saraf', 'plural' => 'Sarafs', 'class' => 'asset', 'group' => '1350', 'icon' => 'coins'],
        'loan_receivable' => ['label' => 'Loan given', 'plural' => 'Loans given', 'class' => 'asset', 'group' => '1400', 'icon' => 'hand-holding-dollar'],
        'personal' => ['label' => 'Personal account', 'plural' => 'Personal accounts', 'class' => 'asset', 'group' => '1450', 'icon' => 'user'],
        'supplier' => ['label' => 'Supplier', 'plural' => 'Suppliers', 'class' => 'liability', 'group' => '2100', 'icon' => 'truck-field'],
        'loan_payable' => ['label' => 'Loan taken', 'plural' => 'Loans taken', 'class' => 'liability', 'group' => '2200', 'icon' => 'handshake'],
        'capital' => ['label' => 'Capital account', 'plural' => 'Capital accounts', 'class' => 'equity', 'group' => '3000', 'icon' => 'landmark'],
        'income' => ['label' => 'Income account', 'plural' => 'Income accounts', 'class' => 'income', 'group' => '4000', 'icon' => 'arrow-trend-up'],
        'expense' => ['label' => 'Expense account', 'plural' => 'Expense accounts', 'class' => 'expense', 'group' => '5000', 'icon' => 'receipt'],
    ],

    /*
    | The chart a new company starts with: [code, name, class, parent code, kind, system key].
    | A row with children is a group (nothing is posted to it). The system key lets the code find
    | an account whatever the company renames it to.
    */
    'chart' => [
        ['1000', 'Assets', 'asset', null, null, null],
        ['1100', 'Cash', 'asset', '1000', null, null],
        ['1200', 'Bank accounts', 'asset', '1000', null, null],
        ['1300', 'Customers (receivable)', 'asset', '1000', null, null],
        ['1350', 'Sarafs', 'asset', '1000', null, null],
        ['1400', 'Loans given', 'asset', '1000', null, null],
        ['1450', 'Personal accounts', 'asset', '1000', null, null],
        ['1460', 'Employee advances', 'asset', '1000', 'employee_advance', 'employee_advances'],
        ['1500', 'Inventory', 'asset', '1000', 'inventory', 'inventory'],
        ['1600', 'Fixed assets', 'asset', '1000', 'fixed_asset', 'fixed_assets'],
        ['1690', 'Accumulated depreciation', 'asset', '1000', 'accumulated_depreciation', 'accumulated_depreciation'],
        ['2000', 'Liabilities', 'liability', null, null, null],
        ['2100', 'Suppliers (payable)', 'liability', '2000', null, null],
        ['2150', 'Goods received not invoiced', 'liability', '2000', 'accrual', 'grni'],
        ['2200', 'Loans taken', 'liability', '2000', null, null],
        ['2300', 'Payroll liabilities', 'liability', '2000', 'payroll_liability', 'payroll_liabilities'],
        ['2310', 'Payroll deductions payable', 'liability', '2000', 'payroll_liability', 'payroll_deductions'],
        ['2400', 'Taxes payable', 'liability', '2000', 'tax', 'taxes_payable'],
        ['3000', 'Equity', 'equity', null, null, null],
        ['3100', 'Owner capital', 'equity', '3000', 'capital', 'capital'],
        ['3200', 'Owner drawings', 'equity', '3000', 'capital', 'drawings'],
        ['3900', 'Opening balances', 'equity', '3000', 'equity', 'opening_balances'],
        ['4000', 'Income', 'income', null, null, null],
        ['4100', 'Sales', 'income', '4000', 'income', 'sales'],
        ['4200', 'Service income', 'income', '4000', 'income', 'service_income'],
        ['4900', 'Other income', 'income', '4000', 'income', 'other_income'],
        ['4950', 'Exchange gain or loss', 'income', '4000', 'income', 'exchange_difference'],
        ['4960', 'Gain or loss on asset disposal', 'income', '4000', 'income', 'asset_disposal'],
        ['5000', 'Expenses', 'expense', null, null, null],
        ['5100', 'Cost of goods sold', 'expense', '5000', 'expense', 'cogs'],
        ['5150', 'Inventory adjustments', 'expense', '5000', 'expense', 'inventory_adjustments'],
        ['5160', 'Purchases not kept in stock', 'expense', '5000', 'expense', 'purchase_expenses'],
        ['5200', 'Salaries', 'expense', '5000', 'expense', 'salaries'],
        ['5300', 'Rent', 'expense', '5000', 'expense', null],
        ['5400', 'Utilities', 'expense', '5000', 'expense', null],
        ['5500', 'Depreciation', 'expense', '5000', 'expense', 'depreciation'],
        ['5900', 'Other expenses', 'expense', '5000', 'expense', 'other_expenses'],
    ],

    /*
    | What a Money In or Money Out can be. The user picks one; the pick decides which accounts
    | can stand on the other side, and the class of that account decides the accounting:
    | money from a customer lowers what he owes, a loan received raises a liability, and only an
    | income or expense account touches profit. 'kinds' => null allows every account that is not
    | cash or bank.
    */
    'money' => [
        'in' => [
            'customer_receipt' => ['label' => 'Received from a customer', 'kinds' => ['customer']],
            'income' => ['label' => 'Other income', 'kinds' => ['income']],
            'capital' => ['label' => 'Capital put in by the owner', 'kinds' => ['capital']],
            'loan_received' => ['label' => 'Loan received', 'kinds' => ['loan_payable']],
            'loan_repaid_to_us' => ['label' => 'Repayment of a loan we gave', 'kinds' => ['loan_receivable']],
            'refund_received' => ['label' => 'Refund from a supplier', 'kinds' => ['supplier']],
            'from_saraf' => ['label' => 'Received from a saraf', 'kinds' => ['saraf']],
            'from_person' => ['label' => 'Received from a personal account', 'kinds' => ['personal']],
            'other' => ['label' => 'Other money in', 'kinds' => null],
        ],
        'out' => [
            'supplier_payment' => ['label' => 'Paid to a supplier', 'kinds' => ['supplier']],
            'expense' => ['label' => 'Expense', 'kinds' => ['expense']],
            'loan_repayment' => ['label' => 'Repayment of a loan we took', 'kinds' => ['loan_payable']],
            'money_lent' => ['label' => 'Loan given', 'kinds' => ['loan_receivable']],
            'capital_withdrawal' => ['label' => 'Taken out by the owner', 'kinds' => ['capital']],
            'refund_paid' => ['label' => 'Refund to a customer', 'kinds' => ['customer']],
            'to_saraf' => ['label' => 'Paid to a saraf', 'kinds' => ['saraf']],
            'to_person' => ['label' => 'Paid to a personal account', 'kinds' => ['personal']],
            'other' => ['label' => 'Other money out', 'kinds' => null],
        ],
    ],

    /*
    | Roles of a company and what each may do. The owner may do everything. Permissions are
    | checked in the routes (middleware "can.do:<permission>"), never only by hiding a menu.
    */
    'roles' => [
        'owner' => ['label' => 'Company owner', 'can' => ['*']],
        'admin' => ['label' => 'Administrator', 'can' => ['*']],
        'accountant' => ['label' => 'Accountant', 'can' => ['accounts.view', 'accounts.manage', 'money.view', 'money.create', 'money.void', 'transfers.create', 'invoices.view', 'invoices.manage', 'products.view', 'products.manage', 'journals.view', 'reports.view', 'reports.export',
            'inventory.view', 'inventory.manage', 'purchases.view', 'purchases.manage', 'purchases.approve', 'sales.view', 'sales.manage', 'pos.use', 'pos.close', 'assets.view', 'assets.manage']],
        'cashier' => ['label' => 'Cashier', 'can' => ['accounts.view', 'money.view', 'money.create', 'transfers.create', 'invoices.view', 'products.view', 'pos.use', 'pos.close']],
        'salesperson' => ['label' => 'Salesperson', 'can' => ['accounts.view', 'money.view', 'invoices.view', 'invoices.manage', 'products.view', 'inventory.view', 'sales.view', 'sales.manage', 'pos.use']],
        'storekeeper' => ['label' => 'Storekeeper', 'can' => ['products.view', 'products.manage', 'inventory.view', 'inventory.manage', 'purchases.view', 'sales.view']],
        'payroll' => ['label' => 'HR / payroll manager', 'can' => ['payroll.view', 'payroll.manage']],
        'viewer' => ['label' => 'Viewer', 'can' => ['accounts.view', 'money.view', 'invoices.view', 'products.view', 'inventory.view', 'purchases.view', 'sales.view', 'assets.view', 'journals.view', 'reports.view']],
    ],
];
