<section id="contact" class="relative transition-all duration-500 ease-out"
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
  <!-- Background Image -->
  <div 
    class="absolute inset-0 h-full w-full bg-cover bg-center" 
    style="background-image: url('{{ asset('images/bg3.jpg') }}')">
  </div>

  <!-- Dark overlay for readability -->
  <div class="absolute inset-0 bg-black/50"></div>

  <!-- Content -->
  <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-24 sm:py-32 lg:py-40 min-h-screen flex flex-col justify-center text-white">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-16">

      <!-- Contact Form -->
      <div class="flex flex-col justify-center">
        <h2 class="text-4xl sm:text-5xl font-extrabold tracking-tight drop-shadow-lg">Get in Touch</h2>
        <p class="mt-4 text-lg sm:text-xl text-gray-200 drop-shadow-sm">
          Have questions or want to book an appointment? Send us a message, and we’ll respond as soon as possible.
        </p>

        <form action="{{ route('contact.send') }}" method="POST" class="mt-10 space-y-6" id="contact-form">
          @csrf
          
          @if(session('success'))
            <div class="bg-green-500 text-white px-4 py-3 rounded-md shadow-md mb-4" id="success-message">
              {{ session('success') }}
            </div>
          @endif

          @if(session('error'))
            <div class="bg-red-500 text-white px-4 py-3 rounded-md shadow-md mb-4" id="error-message">
              {{ session('error') }}
            </div>
          @endif

          @if($errors->any())
            <div class="bg-red-500 text-white px-4 py-3 rounded-md shadow-md mb-4">
              <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <input type="text" name="name" placeholder="Your Name" value="{{ old('name') }}" required
              class="w-full rounded-md bg-white/90 text-gray-900 shadow-md border border-gray-200 placeholder-gray-500 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
            <input type="email" name="email" placeholder="Email Address" value="{{ old('email') }}" required
              class="w-full rounded-md bg-white/90 text-gray-900 shadow-md border border-gray-200 placeholder-gray-500 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
          </div>

          <input type="text" name="subject" placeholder="Subject" value="{{ old('subject') }}" required
            class="w-full rounded-md bg-white/90 text-gray-900 shadow-md border border-gray-200 placeholder-gray-500 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">

          <textarea name="message" rows="6" placeholder="Message" required
            class="w-full rounded-md bg-white/90 text-gray-900 shadow-md border border-gray-200 placeholder-gray-500 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">{{ old('message') }}</textarea>

          <button type="submit" id="submit-btn"
            class="w-full sm:w-auto inline-flex justify-center items-center rounded-lg bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 transition px-6 py-3 text-lg font-semibold shadow-lg text-white disabled:opacity-50 disabled:cursor-not-allowed">
            <span id="submit-text">Send Message</span>
            <span id="submit-spinner" class="hidden ml-2">
              <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </span>
          </button>
        </form>
      </div>

      <!-- Map and Contact Info -->
      <div class="flex flex-col justify-center space-y-8">
        <div class="h-[500px] w-full overflow-hidden rounded-3xl shadow-2xl border-2 border-white/40 bg-gray-200">
          <iframe
            src="https://www.google.com/maps?q=Zone%203%20Bugo%2C%20Cagayan%20de%20Oro&output=embed"
            width="100%"
            height="100%"
            allowfullscreen=""
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            class="h-full w-full border-0">
          </iframe>
        </div>

        <div class="space-y-3 text-gray-100 text-lg">
          <p><span class="font-semibold">Phone:</span> 0966-334-1022</p>
          <p><span class="font-semibold">Email:</span> acebudentalsolutions@gmail.com</p>
          <p><span class="font-semibold">Address:</span> Zone 3, Brgy. Bugo, Cagayan de Oro City</p>
        </div>
      </div>

    </div>
  </div>
</section>

<script>
  /**
   * Function to successfully submit contact form message
   * Messages are saved to database and admins are notified
   * This function handles form validation, submission, and user feedback
   */
  function sendContactEmail() {
    const form = document.getElementById('contact-form');
    const submitBtn = document.getElementById('submit-btn');
    const submitText = document.getElementById('submit-text');
    const submitSpinner = document.getElementById('submit-spinner');
    
    if (!form || !submitBtn) return;
    
    // Validate form fields before submission
    const name = form.querySelector('input[name="name"]').value.trim();
    const email = form.querySelector('input[name="email"]').value.trim();
    const subject = form.querySelector('input[name="subject"]').value.trim();
    const message = form.querySelector('textarea[name="message"]').value.trim();
    
    // Client-side validation
    if (!name || !email || !subject || !message) {
      showMessage('Please fill in all required fields.', 'error');
      return false;
    }
    
    // Email format validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      showMessage('Please enter a valid email address.', 'error');
      return false;
    }
    
    // Show loading state
    submitBtn.disabled = true;
    submitText.textContent = 'Sending...';
    submitSpinner.classList.remove('hidden');
    
    // Remove any existing error messages
    removeMessages();
    
    // Allow form to submit normally (don't prevent default)
    // The form will submit and save message to database via ContactController
    // Admin will be notified in the dashboard
    return true;
  }
  
  /**
   * Show success or error message
   */
  function showMessage(message, type = 'success') {
    removeMessages();
    
    const messageDiv = document.createElement('div');
    messageDiv.className = type === 'success' 
      ? 'bg-green-500 text-white px-4 py-3 rounded-md shadow-md mb-4' 
      : 'bg-red-500 text-white px-4 py-3 rounded-md shadow-md mb-4';
    messageDiv.id = type === 'success' ? 'success-message' : 'error-message';
    messageDiv.textContent = message;
    
    const form = document.getElementById('contact-form');
    if (form) {
      form.insertBefore(messageDiv, form.firstChild.nextSibling);
    }
    
    // Scroll to message
    messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
  
  /**
   * Remove existing messages
   */
  function removeMessages() {
    const successMsg = document.getElementById('success-message');
    const errorMsg = document.getElementById('error-message');
    if (successMsg) successMsg.remove();
    if (errorMsg) errorMsg.remove();
  }
  
  /**
   * Reset form button state
   */
  function resetSubmitButton() {
    const submitBtn = document.getElementById('submit-btn');
    const submitText = document.getElementById('submit-text');
    const submitSpinner = document.getElementById('submit-spinner');
    
    if (submitBtn && submitText && submitSpinner) {
      submitBtn.disabled = false;
      submitText.textContent = 'Send Message';
      submitSpinner.classList.add('hidden');
    }
  }
  
  // Initialize on page load
  document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('contact-form');
    
    if (form) {
      // Attach submit handler - validate but allow form to submit
      form.addEventListener('submit', function(e) {
        // Get form values for validation
        const name = form.querySelector('input[name="name"]').value.trim();
        const email = form.querySelector('input[name="email"]').value.trim();
        const subject = form.querySelector('input[name="subject"]').value.trim();
        const message = form.querySelector('textarea[name="message"]').value.trim();
        
        // Client-side validation
        if (!name || !email || !subject || !message) {
          e.preventDefault();
          showMessage('Please fill in all required fields.', 'error');
          return false;
        }
        
        // Email format validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
          e.preventDefault();
          showMessage('Please enter a valid email address.', 'error');
          return false;
        }
        
        // If validation passes, show loading state and allow form to submit
        const submitBtn = document.getElementById('submit-btn');
        const submitText = document.getElementById('submit-text');
        const submitSpinner = document.getElementById('submit-spinner');
        
        if (submitBtn && submitText && submitSpinner) {
          submitBtn.disabled = true;
          submitText.textContent = 'Sending...';
          submitSpinner.classList.remove('hidden');
        }
        
        // Remove any existing error messages
        removeMessages();
        
        // Form will submit normally (no preventDefault)
      });
      
      // Scroll to contact section if there's a message
      @if(session('success') || session('error') || $errors->any())
        const contactSection = document.getElementById('contact');
        if (contactSection) {
          setTimeout(() => {
            contactSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            
            // Highlight the message briefly
            const messageElement = document.getElementById('success-message') || document.getElementById('error-message');
            if (messageElement) {
              messageElement.style.animation = 'pulse 2s ease-in-out';
            }
          }, 100);
        }
      @endif
    }
  });
</script>
