<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} | Feminique</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.5rem;
            background: #E7D9C4;
            color: #4E3727;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            text-align: center;
            -webkit-font-smoothing: antialiased;
        }
        .box { max-width: 32rem; }
        .brand {
            font-family: Georgia, 'Times New Roman', serif;
            font-size: 1.25rem;
            letter-spacing: 0.35em;
            text-transform: uppercase;
        }
        .code {
            margin-top: 3rem;
            font-family: Georgia, 'Times New Roman', serif;
            font-size: 5rem;
            font-weight: 300;
            letter-spacing: 0.2em;
            color: rgba(78, 55, 39, 0.3);
            line-height: 1;
        }
        h1 {
            margin-top: 1.5rem;
            font-family: Georgia, 'Times New Roman', serif;
            font-size: 2rem;
            font-weight: 400;
            line-height: 1.2;
        }
        p.text {
            margin-top: 1.25rem;
            font-size: 0.9rem;
            line-height: 1.7;
            color: rgba(78, 55, 39, 0.85);
        }
        a.btn {
            display: inline-block;
            margin-top: 2.5rem;
            padding: 1rem 2rem;
            border: 1px solid #4E3727;
            background: #4E3727;
            color: #E7D9C4;
            font-size: 0.75rem;
            letter-spacing: 0.25em;
            text-transform: uppercase;
            text-decoration: none;
        }
        a.btn:hover { background: transparent; color: #4E3727; }
    </style>
</head>
<body>
    <div class="box">
        <p class="brand">Feminique</p>
        <p class="code">{{ $code }}</p>
        <h1>{{ $title }}</h1>
        <p class="text">{{ $message }}</p>
        <a class="btn" href="{{ url('/') }}">{{ $button }}</a>
    </div>
</body>
</html>