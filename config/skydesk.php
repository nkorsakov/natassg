<?php

return [
    /*
    | Soft gate for public manager surfaces (report accept + live cashflow).
    */
    'public_pin' => env('SKYDESK_PUBLIC_PIN', '4608'),

    /*
    | Whose live finance is shown on /cashflow after PIN unlock.
    | Prefer explicit id; otherwise resolve by email; else first non-admin user.
    */
    'public_finance_user_id' => env('SKYDESK_PUBLIC_FINANCE_USER_ID'),
    'public_finance_user_email' => env('SKYDESK_PUBLIC_FINANCE_USER_EMAIL', 'nataliya@skydesk.local'),
];
