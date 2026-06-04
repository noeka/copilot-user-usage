<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Copilot Usage') — {{ config('copilot.org') }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #0d1117;
            --surface: #161b22;
            --border: #30363d;
            --text: #e6edf3;
            --muted: #8b949e;
            --accent: #58a6ff;
            --green: #3fb950;
            --purple: #bc8cff;
            --orange: #d29922;
            --radius: 6px;
            --shadow: 0 1px 3px rgba(0,0,0,.4);
        }

        body { background: var(--bg); color: var(--text); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; font-size: 14px; line-height: 1.5; }

        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }

        .navbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            height: 56px;
        }
        .navbar-brand { font-weight: 600; font-size: 15px; color: var(--text); display: flex; align-items: center; gap: 8px; }
        .navbar-brand svg { fill: var(--text); }
        .navbar-nav { display: flex; gap: 4px; flex: 1; }
        .navbar-nav a { padding: 6px 12px; border-radius: var(--radius); color: var(--muted); font-size: 13px; }
        .navbar-nav a:hover, .navbar-nav a.active { background: rgba(255,255,255,.06); color: var(--text); text-decoration: none; }
        .navbar-user { display: flex; align-items: center; gap: 10px; margin-left: auto; }
        .navbar-user img { width: 28px; height: 28px; border-radius: 50%; }
        .navbar-user .login { font-size: 13px; color: var(--muted); }

        .container { max-width: 1200px; margin: 0 auto; padding: 24px 24px; }

        .page-header { margin-bottom: 24px; }
        .page-header h1 { font-size: 20px; font-weight: 600; }
        .page-header p { color: var(--muted); margin-top: 4px; font-size: 13px; }

        .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); }
        .card-header { padding: 14px 16px; border-bottom: 1px solid var(--border); font-weight: 600; font-size: 13px; display: flex; align-items: center; justify-content: space-between; }
        .card-body { padding: 16px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 24px; }
        .stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px; }
        .stat-card .label { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); margin-bottom: 8px; }
        .stat-card .value { font-size: 28px; font-weight: 600; line-height: 1; }
        .stat-card .sub { font-size: 12px; color: var(--muted); margin-top: 6px; }
        .stat-card .sparkline { margin-top: 8px; }

        .charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
        @media (max-width: 768px) { .charts-grid { grid-template-columns: 1fr; } }

        .chart-wrap { overflow-x: auto; }
        .chart-wrap svg { display: block; max-width: 100%; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 8px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); border-bottom: 1px solid var(--border); }
        td { padding: 10px 12px; border-bottom: 1px solid rgba(48,54,61,.6); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: rgba(255,255,255,.03); }

        .avatar { width: 24px; height: 24px; border-radius: 50%; vertical-align: middle; margin-right: 6px; }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-green { background: rgba(63,185,80,.15); color: var(--green); }
        .badge-blue { background: rgba(88,166,255,.15); color: var(--accent); }

        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: var(--radius); font-size: 13px; font-weight: 500; cursor: pointer; border: 1px solid var(--border); background: var(--surface); color: var(--text); text-decoration: none; }
        .btn:hover { background: rgba(255,255,255,.06); text-decoration: none; }
        .btn-sm { padding: 4px 10px; font-size: 12px; }

        .logout-form { display: inline; }

        .empty-state { text-align: center; padding: 60px 24px; color: var(--muted); }
        .empty-state h3 { font-size: 16px; color: var(--text); margin-bottom: 8px; }
        .empty-state p { font-size: 13px; }

        .progress-bar { height: 6px; background: var(--border); border-radius: 3px; overflow: hidden; }
        .progress-bar-fill { height: 100%; background: var(--accent); border-radius: 3px; }
    </style>
</head>
<body>
<nav class="navbar">
    <a href="{{ route('dashboard') }}" class="navbar-brand">
        <svg width="20" height="20" viewBox="0 0 16 16"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>
        Copilot Usage
    </a>

    <div class="navbar-nav">
        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">My Usage</a>
        @if(auth()->user()?->is_admin)
            <a href="{{ route('org.index') }}" class="{{ request()->routeIs('org.*') ? 'active' : '' }}">Organisation</a>
        @endif
    </div>

    @auth
    <div class="navbar-user">
        @if(auth()->user()->avatar_url)
            <img src="{{ auth()->user()->avatar_url }}" alt="">
        @endif
        <span class="login">{{ auth()->user()->github_login }}</span>
        <form class="logout-form" action="{{ route('auth.logout') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-sm">Sign out</button>
        </form>
    </div>
    @endauth
</nav>

<div class="container">
    @yield('content')
</div>
</body>
</html>
