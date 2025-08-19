<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenAI API Key
    |--------------------------------------------------------------------------
    |
    | Your OpenAI API key. You can get this from your OpenAI dashboard.
    | https://platform.openai.com/api-keys
    |
    */
    'api_key' => env('OPENAI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI Organization ID
    |--------------------------------------------------------------------------
    |
    | Your OpenAI organization ID (optional).
    |
    */
    'organization' => env('OPENAI_ORGANIZATION'),

    /*
    |--------------------------------------------------------------------------
    | Default Model
    |--------------------------------------------------------------------------
    |
    | The default model to use for chat completions.
    |
    */
    'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-3.5-turbo'),

    /*
    |--------------------------------------------------------------------------
    | Max Tokens
    |--------------------------------------------------------------------------
    |
    | Maximum number of tokens to generate in the response.
    |
    */
    'max_tokens' => env('OPENAI_MAX_TOKENS', 500),

    /*
    |--------------------------------------------------------------------------
    | Temperature
    |--------------------------------------------------------------------------
    |
    | Controls randomness in the response. Lower values are more deterministic.
    |
    */
    'temperature' => env('OPENAI_TEMPERATURE', 0.7),
];
