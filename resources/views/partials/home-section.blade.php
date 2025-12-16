<section id="home" class="relative overflow-hidden transition-all duration-500 ease-out"
    x-data="{
        visible: false,
        init() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    this.visible = entry.isIntersecting;
                });
            }, { threshold: 0.05 });
            observer.observe(this.$el);
        }
    }"
    x-bind:class="{ 'opacity-100 translate-y-0': visible, 'opacity-0 translate-y-10': !visible }">
    <div class="relative bg-indigo-50">
        <div class="absolute inset-0">
            <div class="h-full w-full bg-cover bg-center" style="background-image: url('{{ asset('images/pexels-cottonbro-6502739.jpg') }}')"></div>
        </div>
        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-24 sm:py-32 lg:py-40 min-h-screen flex flex-col justify-center">
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="text-5xl font-bold tracking-tight text-white sm:text-6xl lg:text-7xl whitespace-nowrap">
                    Your Smile, Our Priority
                </h1>
                <p class="mt-6 text-xl leading-8 text-white sm:text-2xl">
                    Experience compassionate, high-quality dental care with modern technology and a gentle touch.
                </p>
                <div class="mt-8 flex items-center justify-center gap-x-4">
                    <a href="#dental" class="inline-flex items-center rounded-md border-2 border-blue-500 bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow hover:bg-indigo-500">
                        View Services
                    </a>
                    <a href="{{ route('patient.login') }}" class="inline-flex items-center rounded-md border-2 border-blue-500 px-5 py-3 text-sm font-semibold leading-6 text-white hover:text-gray-200">
                        Book Now<span aria-hidden="true"> →</span>
                    </a>
                </div>
            </div>
            
            <!-- Images Section -->
            <div class="mt-16 flex flex-col justify-center">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="h-[200px] sm:h-[250px] md:h-[280px] w-full overflow-hidden rounded-3xl shadow-2xl border-2 border-white/40">
                        <img 
                            src="{{ asset('images/Carousel1.jpg') }}" 
                            alt="Dental Care Image 1"
                            class="h-full w-full object-cover"
                            loading="lazy">
                    </div>
                    <div class="h-[200px] sm:h-[250px] md:h-[280px] w-full overflow-hidden rounded-3xl shadow-2xl border-2 border-white/40">
                        <img 
                            src="{{ asset('images/Carousel2.jpg') }}" 
                            alt="Dental Care Image 2"
                            class="h-full w-full object-cover"
                            loading="lazy">
                    </div>
                    <div class="h-[200px] sm:h-[250px] md:h-[280px] w-full overflow-hidden rounded-3xl shadow-2xl border-2 border-white/40">
                        <img 
                            src="{{ asset('images/Carousel3.jpg') }}" 
                            alt="Dental Care Image 3"
                            class="h-full w-full object-cover"
                            loading="lazy">
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
