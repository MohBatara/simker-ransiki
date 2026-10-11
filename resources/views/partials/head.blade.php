<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<meta name="theme-color" content="#1F5A47">
@if ($noindex ?? false)
    <meta name="robots" content="noindex, nofollow">
@endif
@vite(['resources/css/app.css', 'resources/js/app.js'])
