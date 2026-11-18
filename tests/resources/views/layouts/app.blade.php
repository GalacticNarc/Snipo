<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <style>
        :root {
            --bg: #f6f6f9; --surface: #ffffff; --surface-2: #f0f0f5; --border: #e3e3ea;
            --text: #17171c; --muted: #6b6b78; --accent: #5b5bd6; --accent-hover: #4b4bc4;
            --accent-text: #ffffff; --danger: #d6455b; --rec: #e5484d; --ok: #2f9e6b;
            --shadow: 0 1px 2px rgba(20,20,40,.06), 0 4px 16px rgba(20,20,40,.06);
            --radius: 12px;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #111114; --surface: #1a1a1f; --surface-2: #232329; --border: #2e2e36;
                --text: #ececf1; --muted: #9a9aa8; --accent: #7c7cf0; --accent-hover: #8f8ff5;
                --shadow: 0 1px 2px rgba(0,0,0,.4), 0 4px 16px rgba(0,0,0,.3);
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text);
               font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        a { color: inherit; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 12px;
                  padding: 14px 24px; border-bottom: 1px solid var(--border); background: var(--surface); }
        .brand { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 17px; text-decoration: none; }
        .brand-dot { width: 22px; height: 22px; border-radius: 50%; background: var(--rec);
                     box-shadow: inset 0 0 0 5px var(--surface), 0 0 0 2px var(--rec); }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 28px 24px 60px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: 1px solid var(--border);
               background: var(--surface); color: var(--text); padding: 9px 16px; border-radius: 10px;
               font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; white-space: nowrap; }
        .btn:hover { background: var(--surface-2); }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        .btn-primary { background: var(--accent); border-color: var(--accent); color: var(--accent-text); }
        .btn-primary:hover { background: var(--accent-hover); }
        .btn-rec { background: var(--rec); border-color: var(--rec); color: #fff; }
        .btn-rec:hover { filter: brightness(1.08); background: var(--rec); }
        .btn-danger { color: var(--danger); }
        .btn-lg { padding: 13px 26px; font-size: 16px; border-radius: 12px; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); }
        .muted { color: var(--muted); }
        .flash { padding: 10px 14px; border-radius: 10px; background: var(--surface-2); margin-bottom: 18px; }
        h1 { font-size: 24px; margin: 0; }
        @media (max-width: 600px) { .topbar, .wrap { padding-left: 16px; padding-right: 16px; } }
    </style>
    @stack('head')
</head>
<body>
<header class="topbar">
    <a class="brand" href="{{ route('videos.index') }}"><span class="brand-dot"></span>{{ config('app.name') }}</a>
    <a class="btn btn-rec" href="{{ route('videos.record') }}">● New recording</a>
</header>
<main class="wrap">
    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif
    @yield('content')
</main>
@stack('scripts')
</body>
</html>
