{{-- resources/views/layouts/guest.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'HR Attendance Monitoring System') }}</title>

    {{-- Vite assets --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --green1: #0b5d22;   /* deep green */
            --green2: #1b7a2e;   /* mid green */
            --gold:   #e4b200;   /* gold */
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Figtree', sans-serif;
            background: linear-gradient(135deg, var(--green1) 0%, var(--green2) 50%, var(--gold) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #222;
        }

        .auth-wrapper {
            width: 100%;
            max-width: 420px;
            background: rgba(255,255,255,0.94);
            border-radius: 18px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.18);
            padding: 2rem 2.5rem;
            text-align: center;
            backdrop-filter: blur(8px);
        }

        .school-logo {
            width: 80px;
            margin: 0 auto 0.75rem;
            display: block;
        }

        .app-title {
            font-weight: 700;
            font-size: 1.25rem;
            color: #0b5d22;
            margin-bottom: 0.5rem;
        }

        .sub-title {
            font-weight: 600;
            font-size: 1rem;
            color: #166534;
            margin-bottom: 1.5rem;
        }
    </style>
</head>

<body>
    <div class="auth-wrapper">
        {{-- Replace with your school logo --}}
        <img src="{{ asset('images/school-logo.png') }}" alt="School Logo" class="school-logo">

        {{-- System title --}}
        <div class="app-title">HR Attendance Monitoring System</div>
        <div class="sub-title">Sign In</div>

        {{-- Auth content from the slot --}}
        <div>
            {{ $slot }}
        </div>
    </div>
</body>
</html>
