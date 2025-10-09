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
</head>

<body class="font-sans text-gray-900 antialiased">
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
        <div>
            <a href="/">
                <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
            </a>
        </div>

        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
