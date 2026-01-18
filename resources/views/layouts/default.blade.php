<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', App::currentLocale()) }}">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1">
	<title>@yield('title') - {{ config('app.name') }}</title>

	<link type="image/svg+xml" href="/favicon.svg?v={{ filemtime(public_path('/favicon.svg')) }}" rel="icon" />
	<link href="https://fonts.googleapis.com" rel="preconnect" />
	<link href="https://fonts.gstatic.com" rel="preconnect" crossorigin />
	<link href="https://fonts.googleapis.com/css2?family=Noto+Sans&display=swap" rel="stylesheet" />
	<link type="font/woff2" href="/fonts/QuikscriptSans.woff2" rel="preload" as="font" crossorigin />

	@vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/datetime-absolute.js', 'resources/js/datetime-relative.js'])

	{!! config('app.extra-head-html') !!}

	<style>
		@font-face {
			font-display: swap;
			font-family: "Quikscript Sans";
			src: url("/fonts/QuikscriptSans.woff2");
		}
	</style>
</head>

<body>
	<hidden>
		<x-spinner id="spinner" />
	</hidden>
	<header>
		<nav>
			@auth
				<a href="/">@t('Dashboard')</a>
				<a href="/c">@t('Cars')</a>
			@endauth
			@can('isAdmin')
				<a href="/u">@t('Users')</a>
			@endcan
			@auth
				<a class="ml-auto" href="/export">@t('Export')</a>
				<a href="/profile">@t('Profile')</a>
				<a href="/logout">@t('Logout')</a>
			@endauth
		</nav>
	</header>
	<main>
		<h1>@yield('title')</h1>
		@include('blocks.successes')
		@include('blocks.errors')
		@yield('content')
	</main>
	<footer>
		<a href="https://github.com/jkoop/owing" target="_blank">GitHub</a>
		<span>@t('Page took {:time}s', ['time' => number_format(microtime(true) - LARAVEL_START, 2)])</span>
		<span>
			@auth
				<a href="/profile">{{ Auth::user()->name }}</a>
			@endauth
		</span>
	</footer>
</body>

</html>
