<?php

return [

    /*
    | The LLM provider every AI feature talks to (gemini | deepseek). Business logic only
    | knows App\Support\Ai\LlmClient, so switching is a .env change. Keys never live in code.
    */
    'provider' => env('AI_PROVIDER', 'gemini'),

    // Reading scanned pages needs a model that accepts files; DeepSeek's API does not.
    'vision_provider' => env('AI_VISION_PROVIDER', 'gemini'),

    'timeout' => (int) env('AI_TIMEOUT', 120),

    'providers' => [
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-3.5-flash'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        ],
        'deepseek' => [
            'api_key' => env('DEEPSEEK_API_KEY'),
            'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
            'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
        ],
    ],

    /*
    | "Bandingkan 2 Dokumen".
    */
    'comparison' => [
        'disk' => env('COMPARISON_DISK', 'comparison'),
        'max_file_kb' => (int) env('COMPARISON_MAX_FILE_KB', 20480),
        'max_pages' => (int) env('COMPARISON_MAX_PAGES', 100),
        'max_paste_chars' => (int) env('COMPARISON_MAX_PASTE_CHARS', 400000),
        // A page with fewer characters than this in its text layer is treated as a scan and OCR'd.
        'min_chars_per_page' => (int) env('COMPARISON_MIN_CHARS_PER_PAGE', 40),
        'ocr_pages_per_call' => (int) env('COMPARISON_OCR_PAGES_PER_CALL', 8),
        // Characters of article text sent to the model in one analysis call.
        'analysis_batch_chars' => (int) env('COMPARISON_ANALYSIS_BATCH_CHARS', 24000),
        'retention_days' => (int) env('COMPARISON_RETENTION_DAYS', 90),
        'pdftotext_binary' => env('PDFTOTEXT_BINARY', 'pdftotext'),
        'pdfinfo_binary' => env('PDFINFO_BINARY', 'pdfinfo'),
        'pdftoppm_binary' => env('PDFTOPPM_BINARY', 'pdftoppm'),
    ],

];
