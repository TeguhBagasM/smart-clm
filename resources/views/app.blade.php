<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Boot background shown before app.css loads. It cannot use the theme variables yet, so the two values below must equal --background in :root and .dark of app.css (a test enforces this). --}}
        <style>
            html {
                background-color: #ffffff;
            }

            html.dark {
                background-color: #0b1220;
            }
        </style>

        @php($companyLogo = app(\App\Support\Branding\CompanyBranding::class)->logoUrl(auth()->user()))
        @if ($companyLogo)
            <link rel="icon" href="{{ $companyLogo }}">
            <link rel="apple-touch-icon" href="{{ $companyLogo }}">
        @else
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @endif

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
