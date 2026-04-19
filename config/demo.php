<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, a "Continue as demo" link appears on the login page that
    | logs the visitor straight into the configured demo account.  The demo
    | account has its password-change and 2FA options disabled so the shelf
    | remains accessible to subsequent visitors.
    |
    */

    'enabled' => env('DEMO_ENABLED', false),

    'email' => env('DEMO_EMAIL', 'demo@litstack.app'),

    'max_books' => env('DEMO_MAX_BOOKS', 50),

];
