<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <title>{{ !empty($title) ? $title . ' - ' : '' }}{{ config('app.name') }}</title>
  <meta name="robots" content="@yield('robots', 'all')">
  <meta name="color-scheme" content="light dark">
  @vite('resources/css/app.css')
  @vite('resources/js/app.js')
  @include('parts/icons')
  @include('parts/opengraph')
  @if (config('app.plausible_domain'))
    <script defer
      data-domain="{{ config('app.plausible_domain') }}"
      src="{{ config('app.plausible_script') }}"
    ></script>
  @endif
</head>
<body>
<header class="container">
  <nav>
    <a href="{{ url('/') }}" class="site-name">
      {{ config('app.name') }}
    </a>
  </nav>
</header>
<main>
  {{ $slot }}
</main>
<footer class="container">
  <p>A little project from <a href="https://mattstein.com">Matt Stein</a>.</p>
</footer>
</body>
</html>
