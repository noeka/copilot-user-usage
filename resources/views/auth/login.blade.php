<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in — Copilot Usage</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --bg:#0d1117; --surface:#161b22; --border:#30363d; --text:#e6edf3; --muted:#8b949e; --accent:#58a6ff; --radius:6px; }
        body { background:var(--bg); color:var(--text); font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; display:flex; align-items:center; justify-content:center; min-height:100vh; }
        .login-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:40px; max-width:360px; width:100%; text-align:center; }
        .login-card h1 { font-size:18px; margin-bottom:8px; }
        .login-card p { color:var(--muted); font-size:13px; margin-bottom:28px; }
        .btn-github { display:flex; align-items:center; justify-content:center; gap:10px; width:100%; padding:10px 18px; background:#21262d; border:1px solid var(--border); border-radius:var(--radius); color:var(--text); font-size:14px; font-weight:500; text-decoration:none; cursor:pointer; }
        .btn-github:hover { background:#30363d; }
        .btn-github svg { fill:var(--text); }
        .error { background:rgba(248,81,73,.1); border:1px solid rgba(248,81,73,.4); border-radius:var(--radius); padding:10px 14px; color:#f85149; font-size:13px; margin-bottom:20px; text-align:left; }
    </style>
</head>
<body>
<div class="login-card">
    <h1>Copilot Usage Dashboard</h1>
    <p>Sign in with your GitHub account to view Copilot usage for {{ config('copilot.org') }}.</p>

    @if($errors->any())
        <div class="error">{{ $errors->first('github') }}</div>
    @endif

    <a href="{{ route('auth.github') }}" class="btn-github">
        <svg width="20" height="20" viewBox="0 0 16 16"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>
        Continue with GitHub
    </a>
</div>
</body>
</html>
