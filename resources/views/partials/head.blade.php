<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />

@php
    $pageTitle       = filled($title ?? null) ? config('app.name').' - '.$title : config('app.name');
    $pageDescription = $description ?? __('A personal book tracking app. Search the Open Library catalogue, add books to your shelf, track reading progress, and leave reviews.');
    $pageUrl         = url()->current();
    $ogImage         = config('app.url').'/og-image.png';
@endphp

<title>{{ $pageTitle }}</title>

<meta name="description" content="{{ $pageDescription }}">
<link rel="canonical" href="{{ $pageUrl }}">

{{-- Open Graph --}}
<meta property="og:type"        content="website">
<meta property="og:site_name"   content="{{ config('app.name') }}">
<meta property="og:title"       content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $pageDescription }}">
<meta property="og:url"         content="{{ $pageUrl }}">
<meta property="og:image"       content="{{ $ogImage }}">
<meta property="og:image:width"  content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt"   content="{{ config('app.name') }}">

{{-- Twitter / X --}}
<meta name="twitter:card"        content="summary_large_image">
<meta name="twitter:title"       content="{{ $pageTitle }}">
<meta name="twitter:description" content="{{ $pageDescription }}">
<meta name="twitter:image"       content="{{ $ogImage }}">

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">

@vite(['resources/css/app.css', 'resources/js/app.js'])
