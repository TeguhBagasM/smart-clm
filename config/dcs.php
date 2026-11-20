<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Legacy (Node) Session Bridge
    |--------------------------------------------------------------------------
    |
    | While the React frontend and the Node application are still in use, DCS
    | requests carry Node's `clm_token` cookie: an HS256 JWT signed with the
    | same JWT_SECRET. Laravel only verifies it; it never issues it. With no
    | secret configured every token is rejected.
    |
    */

    'node_jwt_secret' => env('DCS_NODE_JWT_SECRET'),

    'node_session_cookie' => 'clm_token',

    /*
    |--------------------------------------------------------------------------
    | Numbering Time Zone
    |--------------------------------------------------------------------------
    |
    | The {Year}, {Month} and {Day} of a document number come from the clock in
    | this zone (the application time zone when empty). The Node app used its server's local zone, so set it to that
    | zone to keep numbers around midnight on 1 January identical.
    |
    */

    'numbering_timezone' => env('DCS_NUMBERING_TIMEZONE'),

    /*
    |--------------------------------------------------------------------------
    | Document Files
    |--------------------------------------------------------------------------
    |
    | Disk holding the PDF renditions and flow images. Keep it private: files
    | are only ever served through the DCS API after the access checks, because
    | the clean master must not be downloadable without them.
    |
    */

    'disk' => env('DCS_DISK', 'dcs'),

    /*
    |--------------------------------------------------------------------------
    | AI Services
    |--------------------------------------------------------------------------
    |
    | Translation and metadata extraction call Gemini through the Node
    | application's internal endpoint, which keeps the API key and the prompts.
    | PDFs no longer go through Node: they are drawn inside Laravel (PhpPdfEngine).
    |
    */

    'ai' => [
        'url' => env('DCS_AI_URL'),
        'secret' => env('DCS_AI_SECRET'),
        'timeout' => (int) env('DCS_AI_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Appendix Uploads and Review Links
    |--------------------------------------------------------------------------
    |
    | Appendix files (Word/PDF) are linked from the printed document, so their
    | URL must work without logging in: they go to a public disk. The share base
    | URL is what external review links start with (the request's own host when
    | empty).
    |
    */

    'public_disk' => env('DCS_PUBLIC_DISK', 'public'),

    'share_base_url' => env('DCS_SHARE_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Legacy Import
    |--------------------------------------------------------------------------
    |
    | `dcs:import-legacy` reads the Node application's database (connection
    | `legacy`) and its files (disk `legacy`). Both are read-only.
    |
    */

    'legacy' => [
        'connection' => env('DCS_LEGACY_CONNECTION', 'legacy'),
        'disk' => env('DCS_LEGACY_DISK', 'legacy'),
    ],

];
