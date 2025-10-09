<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- ==============================
         Local Fonts (Figtree)
         ============================== -->
    <link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/figtree/figtree-v9-latin-400.woff2') }}" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/figtree/figtree-v9-latin-500.woff2') }}" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/figtree/figtree-v9-latin-600.woff2') }}" crossorigin>

    <style>
        /* =========================
           Local Figtree Font Faces
           ========================= */
        @font-face {
            font-family: "Figtree";
            src: url("{{ asset('fonts/figtree/figtree-v9-latin-400.woff2') }}") format("woff2");
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: "Figtree";
            src: url("{{ asset('fonts/figtree/figtree-v9-latin-500.woff2') }}") format("woff2");
            font-weight: 500;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: "Figtree";
            src: url("{{ asset('fonts/figtree/figtree-v9-latin-600.woff2') }}") format("woff2");
            font-weight: 600;
            font-style: normal;
            font-display: swap;
        }

        html {
            font-family: "Figtree", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
        }
    </style>

    <!-- =========================
         Scripts & Styles (Vite)
         ========================= -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- =========================
         Theme Initialization
         ========================= -->
    <script>
        // Init theme on first paint
        (function() {
            const saved = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = saved ?? (prefersDark ? 'dark' : 'light');
            if (theme === 'dark') document.documentElement.classList.add('dark');
        })();

        // Toggle between dark/light
        function toggleTheme() {
            const root = document.documentElement;
            const nowDark = !root.classList.contains('dark');
            root.classList.toggle('dark', nowDark);
            localStorage.setItem('theme', nowDark ? 'dark' : 'light');
        }
    </script>
</head>

<body class="font-sans antialiased bg-gray-100 dark:bg-gray-900">
    <div class="min-h-screen flex flex-col">
        {{-- Top navigation (Breeze default) --}}
        @include('layouts.navigation')

        {{-- Page heading with Dark/Light switch --}}
        @isset($header)
            <header class="bg-white dark:bg-gray-800 shadow">
                <div class="w-full px-4 sm:px-6 lg:px-8 py-6 flex items-center justify-between">
                    <div class="text-gray-900 dark:text-gray-100">
                        {{ $header }}
                    </div>

                    <button type="button"
                            onclick="toggleTheme()"
                            class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm
                                   bg-white border-gray-300 text-gray-700 hover:bg-gray-50
                                   dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M21.64 13.01A9 9 0 1 1 11 2.36a7 7 0 1 0 10.64 10.65z"/>
                        </svg>
                        <span>Theme</span>
                    </button>
                </div>
            </header>
        @endisset

        {{-- Main layout with sidebar + content --}}
        <div class="flex flex-1 w-full px-4 sm:px-6 lg:px-8 py-6 gap-6">
            {{-- Sidebar --}}
            @include('layouts.partials.sidebar')

            {{-- Page Content --}}
            <main class="flex-1">
                <div class="w-full h-full bg-white dark:bg-gray-800 dark:text-gray-100 rounded-md shadow p-6">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
</body>
</html>
