<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#6957EE" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#141416" media="(prefers-color-scheme: dark)">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Natassg') }}">
    <meta name="application-name" content="{{ config('app.name', 'Natassg') }}">
    <meta name="description" content="Личный помощник: поручения, календарь и финансы">
    <title inertia>{{ config('app.name', 'Natassg') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>
@inertia
</body>
</html>
