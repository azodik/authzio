{{-- Standalone error shell: no Vite/DB so 500 pages still render. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0A6565">
    <title>@yield('title') — Authzio</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('images/favicon.svg') }}" type="image/svg+xml">
    <style>
        :root {
            --ink: #0a1210;
            --ink-soft: #24312c;
            --paper: #f5f6f4;
            --paper-elevated: #fcfcfb;
            --mist: #d5dbd6;
            --teal: #0a6565;
            --teal-bright: #0e8585;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: "Source Sans 3", ui-sans-serif, system-ui, sans-serif;
            background:
                radial-gradient(ellipse 80% 50% at 50% -10%, color-mix(in srgb, var(--teal) 14%, transparent), transparent),
                var(--paper);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; text-decoration: none; }
        .wrap {
            width: min(40rem, calc(100% - 2rem));
            margin: auto;
            padding: 3rem 0;
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 0.65rem;
            margin-bottom: 2rem;
        }
        .brand img { width: 1.75rem; height: 1.75rem; }
        .brand span {
            font-family: Syne, ui-sans-serif, system-ui, sans-serif;
            font-weight: 700;
            font-size: 1.15rem;
            letter-spacing: -0.02em;
        }
        .panel {
            border: 1px solid var(--mist);
            background: var(--paper-elevated);
            padding: 2rem 1.75rem;
        }
        .code {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 0.75rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--teal);
            margin: 0 0 0.75rem;
        }
        h1 {
            font-family: Syne, ui-sans-serif, system-ui, sans-serif;
            font-size: clamp(1.6rem, 4vw, 2rem);
            font-weight: 700;
            letter-spacing: -0.03em;
            margin: 0 0 0.75rem;
            line-height: 1.2;
        }
        p {
            margin: 0;
            color: color-mix(in srgb, var(--ink-soft) 72%, transparent);
            line-height: 1.6;
            font-size: 1rem;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1.75rem;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.65rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            border: 1px solid transparent;
        }
        .btn-primary {
            background: var(--ink);
            color: var(--paper);
        }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-ghost {
            border-color: var(--mist);
            background: transparent;
            color: var(--ink);
        }
        .btn-ghost:hover { background: color-mix(in srgb, var(--mist) 35%, transparent); }
    </style>
</head>
<body>
    <main class="wrap">
        <a href="{{ url('/') }}" class="brand" aria-label="Authzio home">
            <img src="{{ asset('images/logo.svg') }}" alt="" width="40" height="40">
            <span>Authzio</span>
        </a>
        <div class="panel">
            @yield('content')
        </div>
    </main>
</body>
</html>
