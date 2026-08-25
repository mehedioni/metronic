<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
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

        {{-- Inline style so the page paints the themed background before
             app.css loads. Must match --background in resources/css/app.css. --}}
        <style>
            html {
                background-color: #ffffff;
            }

            html.dark {
                background-color: #09090b;
            }
        </style>

        <title inertia>{{ config('app.name') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">


        {{-- Page components are code-split by Inertia's glob resolver (see
             resources/js/app.ts), which also serves module-owned pages under
             modules/<Module>/Resources/js/pages. --}}
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        <x-inertia::head />
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
