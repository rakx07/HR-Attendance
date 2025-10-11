<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HR Attendance Monitoring System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --green1: #0b5d22;
            --green2: #1b7a2e;
            --gold:   #e4b200;
            --white:  #ffffff;
        }

        body {
            margin: 0;
            font-family: 'Figtree', sans-serif;
            background: linear-gradient(135deg, var(--green1) 0%, var(--green2) 50%, var(--gold) 100%);
            color: #222;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        header {
            text-align: center;
            padding: 2rem 1rem 1rem;
        }

        header img {
            display: block;
            margin: 0 auto 0.75rem auto;   /* centers horizontally */
            width: 110px;
            height: auto;
        }

        header h1 {
            font-size: 1.9rem;
            font-weight: 700;
            color: var(--white);
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            margin-top: 0.25rem;
        }

        main {
            max-width: 900px;
            margin: 0 auto;
            background: rgba(255,255,255,0.95);
            border-radius: 16px;
            padding: 2rem 3rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        h2 {
            color: var(--green1);
            font-weight: 700;
            margin-bottom: .75rem;
        }

        p {
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        ul {
            list-style-type: disc;
            padding-left: 1.5rem;
            color: #333;
        }

        .btn-login {
            display: inline-block;
            background: var(--green1);
            color: var(--white);
            font-weight: 600;
            padding: .7rem 1.6rem;
            border-radius: 8px;
            text-decoration: none;
            margin-top: 1.5rem;
            transition: background 0.2s;
        }

        .btn-login:hover {
            background: #0e742c;
        }

        footer {
            text-align: center;
            font-size: .85rem;
            color: rgba(255,255,255,0.9);
            padding: 1.2rem;
        }

        @media (max-width: 640px) {
            main {
                padding: 1.5rem;
                margin: 0 1rem;
            }
            header img {
                width: 80px;
            }
            header h1 {
                font-size: 1.4rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <img src="{{ asset('images/school-logo.png') }}" alt="School Logo">
        <h1>HR Attendance Monitoring System</h1>
    </header>

    <main>
        <h2>Welcome!</h2>
        <p>
            The <strong>HR Attendance Monitoring System</strong> is an internal platform designed to streamline and
            automate employee attendance tracking within the institution. It integrates biometric logs, shift schedules,
            and attendance reports to ensure accuracy, transparency, and efficiency.
        </p>

        <h2>Key Features</h2>
        <ul>
            <li>🔹 Real-time biometric data synchronization from ZKTeco devices.</li>
            <li>🔹 Employee attendance dashboard with daily and monthly summaries.</li>
            <li>🔹 Automated shift schedule management and holiday calendars.</li>
            <li>🔹 Comprehensive attendance reporting with PDF and Excel exports.</li>
            <li>🔹 Role-based access for HR Officers, IT Admins, and Department Heads.</li>
            <li>🔹 Audit trail for all attendance edits and approvals.</li>
        </ul>

        <h2>How It Helps</h2>
        <p>
            This system minimizes manual errors, saves HR processing time, and improves compliance with institutional
            policies. It also empowers employees to view their own attendance logs and promotes accountability across all departments.
        </p>

        <div style="text-align:center;">
            <a href="{{ route('login') }}" class="btn-login">Log In to Continue</a>
        </div>
    </main>

    <footer>
        &copy; {{ date('Y') }} Notre Dame of Marbel University — HRMIS Department. All Rights Reserved.
    </footer>
</body>
</html>
