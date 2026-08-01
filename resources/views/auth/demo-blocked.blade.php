<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Demo boundary — Authzio</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: "Plus Jakarta Sans", system-ui, sans-serif;
            background: #f4f7f6;
            color: #14201e;
            padding: 24px;
        }
        .card {
            width: min(100%, 440px);
            background: #fff;
            border: 1px solid rgba(20, 32, 30, 0.12);
            padding: 32px;
        }
        h1 { margin: 0 0 12px; font-size: 1.4rem; }
        p { margin: 0; color: rgba(20, 32, 30, 0.7); line-height: 1.5; }
        a {
            display: inline-block;
            margin-top: 20px;
            color: #0F766E;
            font-weight: 650;
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="card">
    <h1>Demo account boundary</h1>
    <p>{{ $message }}</p>
    <p style="margin-top:12px">Sign up for your own organization to use hosted login and make permanent changes.</p>
    <a href="{{ url('/console/register') }}">Create a free account</a>
</div>
</body>
</html>
