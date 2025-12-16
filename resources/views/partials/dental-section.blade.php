<section 
  id="dental" 
  class="relative bg-cover bg-center bg-no-repeat transition-all duration-500 ease-out"
  style="background-image: url('{{ asset('images/pexels-cottonbro-6502739.jpg') }}');"
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
  x-bind:class="{ 'opacity-100 translate-y-0': visible, 'opacity-0 translate-y-10': !visible }"
>
  <!-- Blue-tinted overlay for readability -->
  <div class="absolute inset-0 bg-blue-900/40 backdrop-blur-[2px]"></div>

  <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-24 sm:py-32 lg:py-40 min-h-screen flex flex-col justify-center text-white">
    <div class="text-center">
      <h2 class="text-4xl font-bold tracking-tight sm:text-5xl text-white">Dental Services</h2>
      <p class="mt-6 max-w-2xl mx-auto text-lg text-gray-100">
        At Acebu Dental Clinic, we provide comprehensive dental care tailored to meet your needs — from prevention and restoration to aesthetic enhancements. 
        Our goal is to give you a healthy, confident smile that lasts a lifetime.
      </p>
    </div>

    <div class="mt-16 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-10">
      <div class="p-8 shadow-lg ring-1 ring-white/20 transition-transform duration-300 hover:-translate-y-2 hover:shadow-2xl">
        <h3 class="text-2xl font-semibold">Preventative Care</h3>
        <p class="mt-3 text-gray-100">
          Regular checkups, dental cleanings, and digital x-rays help detect and prevent oral health issues early. 
          Our team educates patients on proper hygiene practices to maintain strong teeth and gums.
        </p>
      </div>

      <div class="p-8 shadow-lg ring-1 ring-white/20 transition-transform duration-300 hover:-translate-y-2 hover:shadow-2xl">
        <h3 class="text-2xl font-semibold">Restorative Dentistry</h3>
        <p class="mt-3 text-gray-100">
          Whether you need fillings, crowns, bridges, or root canals, we restore your teeth to full function and appearance 
          using durable and natural-looking materials.
        </p>
      </div>

      <div class="p-8 shadow-lg ring-1 ring-white/20 transition-transform duration-300 hover:-translate-y-2 hover:shadow-2xl">
        <h3 class="text-2xl font-semibold">Cosmetic Dentistry</h3>
        <p class="mt-3 text-gray-100">
          Transform your smile with our cosmetic treatments, including teeth whitening, porcelain veneers, and clear aligners.
          Enhance both your confidence and appearance.
        </p>
      </div>

      <div class="p-8 shadow-lg ring-1 ring-white/20 transition-transform duration-300 hover:-translate-y-2 hover:shadow-2xl">
        <h3 class="text-2xl font-semibold">Oral Surgery</h3>
        <p class="mt-3 text-gray-100">
          From simple extractions to wisdom teeth removal, our experienced dentists ensure a safe and comfortable procedure 
          with advanced techniques and gentle care.
        </p>
      </div>

      <div class="p-8 shadow-lg ring-1 ring-white/20 transition-transform duration-300 hover:-translate-y-2 hover:shadow-2xl">
        <h3 class="text-2xl font-semibold">Pediatric Dentistry</h3>
        <p class="mt-3 text-gray-100">
          We make dental visits fun and stress-free for children. Our gentle approach helps kids develop healthy habits 
          and a positive attitude toward oral health from an early age.
        </p>
      </div>

      <div class="p-8 shadow-lg ring-1 ring-white/20 transition-transform duration-300 hover:-translate-y-2 hover:shadow-2xl">
        <h3 class="text-2xl font-semibold">Orthodontics</h3>
        <p class="mt-3 text-gray-100">
          Correct misaligned teeth and bite issues with modern orthodontic solutions. We offer both traditional braces 
          and invisible aligners for a perfect smile alignment.
        </p>
      </div>
    </div>
  </div>
</section>
