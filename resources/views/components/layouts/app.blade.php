@props([
    'title' => null,
    'description' => null,
])

@php
    $site = config('site');
    $pageTitle = $title
        ? $title.' — '.$site['company']['name']
        : $site['company']['name'].' — '.$site['company']['sector'];
    $metaDescription = $description ?? $site['company']['description'];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $pageTitle }}</title>
        <meta name="description" content="{{ $metaDescription }}">

        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $site['company']['name'] }}">
        <meta property="og:title" content="{{ $pageTitle }}">
        <meta property="og:description" content="{{ $metaDescription }}">

        <link rel="icon" href="{{ asset('images/logo-mark.png') }}" type="image/png">
        <link rel="apple-touch-icon" href="{{ asset('images/logo-mark.png') }}">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="flex min-h-screen flex-col bg-white font-sans text-ink-900 antialiased">
        <x-site.header :site="$site" />

        <main class="flex-1">
            {{ $slot }}
        </main>

        <x-site.footer :site="$site" />

        @livewireScripts
    </body>
</html>
