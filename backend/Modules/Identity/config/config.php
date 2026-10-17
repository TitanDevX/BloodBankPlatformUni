<?php

return [
    'name' => 'Identity',
    'token_lifetime_minutes' => (int) env('IDENTITY_TOKEN_LIFETIME_MINUTES', 720),
    'demo_admin_enabled' => (bool) env('DEMO_ADMIN_ENABLED', true),
    'demo_admin_password' => env('DEMO_ADMIN_PASSWORD', '123456'),
];
