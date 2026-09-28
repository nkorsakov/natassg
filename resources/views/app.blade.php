<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#6957EE" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#141416" media="(prefers-color-scheme: dark)">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'SkyDesk') }}">
    <meta name="application-name" content="{{ config('app.name', 'SkyDesk') }}">
    @php
        $seo = $seo ?? null;
        $seoTitle = $seo['title'] ?? null;
        $seoDescription = $seo['description'] ?? 'SkyDesk — поручения, календарь и финансы для личного помощника';
        $seoUrl = $seo['url'] ?? null;
        $seoImage = $seo['image'] ?? null;
    @endphp
    <meta name="description" content="{{ $seoDescription }}">
    @if ($seoTitle)
        <title>{{ $seoTitle }}</title>
        <meta property="og:type" content="website">
        <meta property="og:locale" content="ru_RU">
        <meta property="og:site_name" content="SkyDesk">
        <meta property="og:title" content="{{ $seoTitle }}">
        <meta property="og:description" content="{{ $seoDescription }}">
        @if ($seoUrl)
            <meta property="og:url" content="{{ $seoUrl }}">
            <link rel="canonical" href="{{ $seoUrl }}">
        @endif
        @if ($seoImage)
            <meta property="og:image" content="{{ $seoImage }}">
        @endif
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $seoTitle }}">
        <meta name="twitter:description" content="{{ $seoDescription }}">
        @if (!empty($seo['json_ld']))
            <script type="application/ld+json">{!! json_encode($seo['json_ld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
        @endif
    @else
        <title inertia>{{ config('app.name', 'SkyDesk') }}</title>
    @endif
    <link rel="manifest" href="/build/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Manrope:wght@500;600;700&display=swap" rel="stylesheet">
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>
@inertia
<!-- Yandex.Metrika counter -->
<script type="text/javascript">
    window.dataLayer = window.dataLayer || [];
    (function(m,e,t,r,i,k,a){
        m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
        m[i].l=1*new Date();
        for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
        k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
    })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=111424783', 'ym');

    ym(111424783, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/111424783" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->
</body>
</html>
