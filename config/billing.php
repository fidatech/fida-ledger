<?php

/*
| Subscriptions. The two plans and their prices are rows of the "plans" table (seeded from
| 'plans' below, then changed by the system administrators). There is no free plan and no trial.
*/
return [
    'registration' => (bool) env('REGISTRATION_OPEN', true),
    'currency' => 'USD',
    'instructions' => env('BILLING_INSTRUCTIONS', 'Pay to our account, then send the receipt here.'),
    'email' => env('BILLING_EMAIL'),

    // a subscription runs for this many months per payment
    'months' => 12,
    // days before the end from which a renewal can be paid
    'renew_days' => 30,

    'methods' => ['hesabpay' => 'HesabPay', 'bank' => 'Bank transfer', 'cash' => 'Cash'],

    'reject_reasons' => [
        'wrong_receipt' => 'The receipt is not for this payment',
        'smaller_amount' => 'The amount paid is smaller than the amount due',
        'not_received' => 'The money has not arrived',
        'unreadable' => 'The receipt cannot be read',
        'duplicate' => 'This payment was already used',
    ],

    // what every plan includes; shown beside the Business modules (config/modules.php) when a version is chosen
    'core_modules' => [
        'Financial dashboard', 'Money In and Money Out', 'Accounts: customers, suppliers, sarafs, cash, bank, personal, loans',
        'Chart of accounts and double-entry general ledger', 'Transfers and currency exchange', 'Multi-currency accounting',
        'Invoices, receipts and credit notes', 'Account statements and financial reports', 'Users and roles, company settings',
    ],

    'plans' => [
        ['code' => 'starter', 'name' => 'Starter', 'description' => 'Complete accounting: Money In and Out, accounts, multi-currency, invoices and financial reports.', 'setup_fee' => '200.00', 'annual_fee' => '100.00', 'sort' => 1],
        ['code' => 'business', 'name' => 'Business', 'description' => 'Everything in Starter, plus products, purchasing, inventory, sales, point of sale, fixed assets and payroll.', 'setup_fee' => '400.00', 'annual_fee' => '200.00', 'sort' => 2],
    ],
];
