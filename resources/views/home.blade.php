<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Acebu Dental - Home</title>
	@vite(['resources/css/app.css'])
	<script defer src="{{ asset('libs/alpine.min.js') }}"></script>
	<script src="{{ asset('libs/lucide.min.js') }}"></script>
	<style>[x-cloak] { display: none !important; }</style>
</head>
<body class="antialiased bg-white text-gray-900">
	@include('partials.header')
	<main class="pt-16">
		@include('partials.home-section')
		@include('partials.about-section')
		@include('partials.dental-section')
		@include('partials.contact-section')
	</main>

	<footer class="border-t border-gray-100 bg-sky-500">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-4 text-sm text-white text-center">
    <p>&copy; {{ date('Y') }} Acebu Dental. All rights reserved.</p>
  </div>
</footer>

<script>
	lucide.createIcons();
</script>
</body>
</html>
