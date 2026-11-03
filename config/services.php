<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'mcp' => [
        'api_token' => env('MCP_API_TOKEN'),
    ],

    'citizen_matching' => [
        'key' => env('CITIZEN_MATCHING_KEY'),
    ],

    'foundry' => [
        'project_endpoint' => env('FOUNDRY_PROJECT_ENDPOINT'),
        'agent_name' => env('FOUNDRY_AGENT_NAME'),
        'model_deployment' => env('FOUNDRY_MODEL_DEPLOYMENT'),
        'api_version' => env('FOUNDRY_API_VERSION', 'v1'),
        'timeout' => (int) env('FOUNDRY_TIMEOUT', 45),
        'identity_endpoint' => env('IDENTITY_ENDPOINT'),
        'identity_header' => env('IDENTITY_HEADER'),
        'azure_cli_config_dir' => env('AZURE_CONFIG_DIR') ?: (
            PHP_OS_FAMILY === 'Windows' && env('USERPROFILE')
                ? rtrim((string) env('USERPROFILE'), '\\/') . DIRECTORY_SEPARATOR . '.azure'
                : storage_path('app/private/azure-cli')
        ),
    ],

];
