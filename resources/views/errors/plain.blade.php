<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} | {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.5rem;
            background: #FFFFFF;
            color: #252A27;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            text-align: center;
            -webkit-font-smoothing: antialiased;
        }
        .box { max-width: 32rem; }
        .brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: #07502F;
        }
        .code {
            margin-top: 3rem;
            font-size: 5rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            color: #C5A46D;
            line-height: 1;
        }
        h1 {
            margin-top: 1.5rem;
            font-size: 1.75rem;
            font-weight: 600;
            line-height: 1.25;
            color: #07502F;
        }
        p.text {
            margin-top: 1.25rem;
            font-size: 1rem;
            line-height: 1.7;
            color: #252A27;
        }
        a.btn {
            display: inline-block;
            margin-top: 2.5rem;
            padding: 0.9rem 2rem;
            border: 1px solid #07502F;
            border-radius: 0.5rem;
            background: #07502F;
            color: #FFFFFF;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
        }
        a.btn:hover { background: #053D24; border-color: #053D24; }
    </style>
</head>
<body>
    <div class="box">
        <p class="brand">{{ config('app.name') }}</p>
        <p class="code">{{ $code }}</p>
        <h1>{{ $title }}</h1>
        <p class="text">{{ $message }}</p>
        <a class="btn" href="{{ url('/') }}">{{ $button }}</a>
    </div>
</body>
</html>