<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    @if (! empty($retryAfter))
        <meta http-equiv="Retry-After" content="{{ $retryAfter }}">
    @endif
    <meta name="theme-color" content="#141416">
    <title>Обновление — {{ config('app.name', 'SkyDesk') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Manrope:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-0: #0f0f12;
            --bg-1: #1a1630;
            --ink: #f4f2ff;
            --muted: rgba(244, 242, 255, 0.68);
            --accent: #8b7cf7;
            --accent-soft: rgba(105, 87, 238, 0.28);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            min-height: 100%;
        }

        body {
            font-family: Manrope, system-ui, sans-serif;
            color: var(--ink);
            background:
                radial-gradient(ellipse 80% 55% at 15% 10%, var(--accent-soft), transparent 55%),
                radial-gradient(ellipse 70% 50% at 90% 85%, rgba(88, 60, 200, 0.22), transparent 50%),
                linear-gradient(160deg, var(--bg-0), var(--bg-1) 55%, #121018);
            display: grid;
            place-items: center;
            padding: 2rem 1.25rem;
        }

        main {
            width: min(100%, 28rem);
            text-align: center;
        }

        .brand {
            font-family: Fraunces, Georgia, serif;
            font-size: clamp(2.4rem, 8vw, 3.2rem);
            font-weight: 700;
            letter-spacing: -0.03em;
            line-height: 1.05;
            margin-bottom: 1.75rem;
        }

        .brand span {
            color: var(--accent);
        }

        .pulse {
            width: 3rem;
            height: 3rem;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            border: 2px solid rgba(139, 124, 247, 0.35);
            border-top-color: var(--accent);
            animation: spin 0.9s linear infinite;
        }

        h1 {
            font-family: Fraunces, Georgia, serif;
            font-size: clamp(1.35rem, 4.5vw, 1.65rem);
            font-weight: 600;
            letter-spacing: -0.02em;
            margin-bottom: 0.75rem;
        }

        p {
            font-size: 1rem;
            line-height: 1.55;
            color: var(--muted);
            font-weight: 500;
        }

        .hint {
            margin-top: 1.75rem;
            font-size: 0.875rem;
            color: rgba(244, 242, 255, 0.45);
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (prefers-reduced-motion: reduce) {
            .pulse { animation: none; border-color: var(--accent); }
        }
    </style>
</head>
<body>
<main>
    <div class="brand">Sky<span>Desk</span></div>
    <div class="pulse" aria-hidden="true"></div>
    <h1>Идёт обновление</h1>
    <p>Собираем интерфейс и применяем изменения. Обычно это занимает пару минут — страница обновится сама.</p>
    <p class="hint">Можно закрыть вкладку и зайти чуть позже.</p>
</main>
</body>
</html>
