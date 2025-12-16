<header x-data="{ open: false }" class="w-full bg-[#0c4c8a] border-b border-[#0a3d6f] shadow-md fixed top-0 left-0 z-50">
	<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
		<div class="flex h-16 items-center justify-between">
			<div class="flex items-center">
				<a href="{{ route('home') }}" class="flex items-center space-x-2">
					<img src="{{ asset('images/bg.png') }}" alt="Logo" class="h-15 w-10">
					<span class="font-semibold text-lg text-white">Acebu Dental</span>
				</a>
			</div>
			<nav class="hidden md:flex items-center space-x-6 mr-8">
				@php
					$nav = [
						[ 'label' => 'Home', 'href' => route('home') . '#home', 'match' => request()->routeIs('home') ],
						[ 'label' => 'About', 'href' => route('home') . '#about', 'match' => request()->routeIs('home') ],
						[ 'label' => 'Service', 'href' => route('home') . '#dental', 'match' => request()->routeIs('home') ],
						[ 'label' => 'Contact', 'href' => route('home') . '#contact', 'match' => request()->routeIs('home') ],
					];
				@endphp
				@foreach ($nav as $item)
					<a href="{{ $item['href'] }}"
						class="text-sm font-medium transition-colors {{ $item['match'] ? 'text-white font-semibold' : 'text-gray-200 hover:text-white' }}">
						{{ $item['label'] }}
					</a>
				@endforeach
				<a href="{{ route('patient.login') }}" class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-[#0c4c8a] shadow hover:bg-gray-100">
					Book Now
				</a>
			</nav>
			<div class="md:hidden">
				<button @click="open = !open" type="button" class="inline-flex items-center justify-center rounded-md p-2 text-white hover:bg-[#0a3d6f] focus:outline-none focus:ring-2 focus:ring-white">
					<span class="sr-only">Open main menu</span>
					<svg x-show="!open" x-cloak class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
					</svg>
					<svg x-show="open" x-cloak class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
					</svg>
				</button>
			</div>
		</div>
	</div>
	<div x-show="open" x-cloak class="md:hidden border-t border-[#0a3d6f] bg-[#0c4c8a]">
		<div class="space-y-1 px-4 py-3">
			<a href="{{ route('home') }}#home" class="block rounded px-3 py-2 text-base font-medium {{ request()->routeIs('home') ? 'text-white font-semibold bg-[#0a3d6f]' : 'text-gray-200 hover:bg-[#0a3d6f] hover:text-white' }}">Home</a>
			<a href="{{ route('home') }}#about" class="block rounded px-3 py-2 text-base font-medium text-gray-200 hover:bg-[#0a3d6f] hover:text-white">About</a>
			<a href="{{ route('home') }}#dental" class="block rounded px-3 py-2 text-base font-medium text-gray-200 hover:bg-[#0a3d6f] hover:text-white">Service</a>
			<a href="{{ route('home') }}#contact" class="block rounded px-3 py-2 text-base font-medium text-gray-200 hover:bg-[#0a3d6f] hover:text-white">Contact</a>
			<div class="pt-2">
				<a href="{{ route('patient.login') }}" class="block w-full text-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-[#0c4c8a] shadow hover:bg-gray-100">Book Now</a>
			</div>
		</div>
	</div>
</header>


