<?php

return [
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL') . '/auth/google/callback'),
    ],
    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI', env('APP_URL') . '/auth/microsoft/callback'),
        'proxy' => env('PROXY'),
    ],
    'whatsapp' => [
        'number' => env('WHATSAPP_NUMBER', ''),
        'message' => env('WHATSAPP_MESSAGE', 'Hello, I need help with file conversion.'),
    ],
    'openai' => [
        'api_key' => env('OPENAI_API_KEY', ''),
        'model' => env('OPENAI_MODEL', 'gpt-5.2'),
        'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 450),
    ],
];
