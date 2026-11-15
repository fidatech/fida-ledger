<?php

/*
| WhatsApp messages go out through RelayWA (https://relaywa.com). Every company connects its
| own number with its own session key (Settings > WhatsApp); nothing here is a secret.
| RelayWA links a number by QR code like WhatsApp Web. It is not the official Meta Cloud API.
*/
return [
    'base_url' => rtrim(env('RELAYWA_URL', 'https://relaywa.com/api'), '/'),
    'timeout' => (int) env('RELAYWA_TIMEOUT', 20),

    // The texts a company starts with. Words in {braces} are filled in when the message is written.
    'templates' => [
        'invoice' => [
            'label' => 'Invoice',
            'fields' => ['customer', 'company', 'code', 'date', 'due_date', 'total', 'due'],
            'body' => "Dear {customer},\nInvoice {code} of {date} from {company}.\nTotal: {total}\nStill to pay: {due}\nThank you.",
        ],
        'receipt' => [
            'label' => 'Payment receipt',
            'fields' => ['customer', 'company', 'code', 'date', 'amount'],
            'body' => "Dear {customer},\n{company} received {amount} from you on {date}.\nReceipt: {code}\nThank you.",
        ],
        'statement' => [
            'label' => 'Account balance',
            'fields' => ['customer', 'company', 'date', 'balance'],
            'body' => "Dear {customer},\nYour account with {company} on {date}:\n{balance}",
        ],
    ],
];
