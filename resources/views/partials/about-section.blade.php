<section id="about" 
	x-data="{
		visible: false,
		init() {
			const observer = new IntersectionObserver((entries) => {
				entries.forEach(entry => {
					this.visible = entry.isIntersecting;
				});
			}, { 
				threshold: 0.05,
				rootMargin: '0px'
			});
			observer.observe(this.$el);
		}
	}"
	x-bind:class="visible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-10'"
	class="relative overflow-hidden bg-white transition-all duration-500 ease-out">
	<div class="relative min-h-screen">
		<div class="absolute inset-0">
			<div class="h-full w-full bg-cover bg-center" style="background-image: url('{{ asset('images/bg2.jpg') }}')"></div>
		</div>
		<div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-24 sm:py-32 lg:py-40 min-h-screen flex flex-col justify-center">
			<div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
				<!-- Text Section -->
				<div>
					<h2 class="text-4xl sm:text-5xl font-extrabold tracking-tight text-gray-900">
						About Our Clinic
					</h2>
					<p class="mt-6 text-lg sm:text-xl leading-8 text-gray-700">
						We are dedicated to delivering exceptional dental care for the whole family. 
						Our friendly team combines expertise with the latest technology to ensure a 
						comfortable experience and lasting results.
					</p>
					<ul class="mt-6 space-y-4 text-gray-800 text-lg sm:text-xl">
						<li class="flex items-start">
							<span class="mr-3 text-indigo-600 text-2xl">✓</span> 
							<span>Personalized treatment plans</span>
						</li>
						<li class="flex items-start">
							<span class="mr-3 text-indigo-600 text-2xl">✓</span> 
							<span>Modern, gentle procedures</span>
						</li>
						<li class="flex items-start">
							<span class="mr-3 text-indigo-600 text-2xl">✓</span> 
							<span>Flexible scheduling</span>
						</li>
					</ul>
				</div>

				<!-- Carousel Section -->
				<div class="relative" 
					x-data="{
						currentSlide: 0,
						autoPlayInterval: null,
						slides: [
							'{{ asset('images/Carousel1.jpg') }}',
							'{{ asset('images/Carousel2.jpg') }}',
							'{{ asset('images/Carousel3.jpg') }}',
							'{{ asset('images/Carousel4.jpg') }}',
							'{{ asset('images/Carousel5.jpg') }}'
						],
						init() {
							this.startAutoPlay();
						},
						startAutoPlay() {
							this.autoPlayInterval = setInterval(() => {
								this.nextSlide();
							}, 4000);
						},
						stopAutoPlay() {
							if (this.autoPlayInterval) {
								clearInterval(this.autoPlayInterval);
								this.autoPlayInterval = null;
							}
						},
						nextSlide() {
							this.currentSlide = (this.currentSlide + 1) % this.slides.length;
						},
						prevSlide() {
							this.currentSlide = (this.currentSlide - 1 + this.slides.length) % this.slides.length;
						},
						goToSlide(index) {
							this.currentSlide = index;
						}
					}" 
					@mouseenter="stopAutoPlay()" 
					@mouseleave="startAutoPlay()">
					
					<div class="relative h-96 md:h-[550px] lg:h-[600px] w-full rounded-2xl overflow-hidden shadow-lg">
						<!-- Carousel Images -->
						<template x-for="(slide, index) in slides" :key="index">
							<div x-show="currentSlide === index" 
								x-transition:enter="transition ease-out duration-300"
								x-transition:enter-start="opacity-0"
								x-transition:enter-end="opacity-100"
								x-transition:leave="transition ease-in duration-300"
								x-transition:leave-start="opacity-100"
								x-transition:leave-end="opacity-0"
								class="absolute inset-0 bg-cover bg-center"
								:style="`background-image: url('${slide}')`">
							</div>
						</template>

						<!-- Navigation Arrows -->
						<button @click="prevSlide()" 
							class="absolute left-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white text-gray-800 rounded-full p-3 shadow-md transition-colors z-10">
							<svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
							</svg>
						</button>
						<button @click="nextSlide()" 
							class="absolute right-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white text-gray-800 rounded-full p-3 shadow-md transition-colors z-10">
							<svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
							</svg>
						</button>

						<!-- Indicators -->
						<div class="absolute bottom-5 left-1/2 -translate-x-1/2 flex space-x-3 z-10">
							<template x-for="(slide, index) in slides" :key="index">
								<button @click="goToSlide(index)" 
									:class="currentSlide === index ? 'bg-white w-3 h-3' : 'bg-white/50 w-2 h-2'"
									class="rounded-full transition-all duration-300"></button>
							</template>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>
