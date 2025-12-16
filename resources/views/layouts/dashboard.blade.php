<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') - Acebu Dental</title>
    @vite(['resources/css/app.css'])
    <script defer src="{{ asset('libs/alpine.min.js') }}"></script>
    <style>[x-cloak] { display: none !important; }</style>
    <!-- Chart.js for data visualization -->
    <script src="{{ asset('libs/chart.min.js') }}"></script>
    <!-- Lucide Icons -->
    <script src="{{ asset('libs/lucide.min.js') }}"></script>
    <!-- API Utils for optimized requests -->
    <script src="{{ asset('js/api-utils.js') }}"></script>
    <!-- Medical Dashboard Styles -->
    <link rel="stylesheet" href="{{ asset('css/medical-dashboard.css') }}">
    @stack('styles')
</head>
<body class="antialiased"
      x-data="{
          isAdmin: false,
          init() {
              // Check localStorage to determine user role
              const adminStatus = localStorage.getItem('isAdmin');
              this.isAdmin = adminStatus === 'true';
              
              // Redirect if no authentication
              const token = localStorage.getItem('token');
              if (!token) {
                  window.location.href = '{{ route('home') }}';
                  return;
              }
              
              // Redirect to correct dashboard if on wrong one
              const currentPath = window.location.pathname;
              if (this.isAdmin && !currentPath.includes('/admin/')) {
                  window.location.href = '/admin/admindashboard';
              } else if (!this.isAdmin && !currentPath.includes('/patient/')) {
                  window.location.href = '/patient/patientdashboard';
              }
          }
      }">
    <div class="h-screen flex flex-col">
        {{-- Modern Header with Search --}}
        <div x-show="isAdmin" x-cloak>
            @include('partials.dashboard.modern-header', ['isAdmin' => true])
        </div>
        <div x-show="!isAdmin" x-cloak>
            @include('partials.dashboard.modern-header', ['isAdmin' => false])
        </div>

        <div class="flex-1 flex overflow-hidden">
            {{-- Sidebar with dynamic isAdmin --}}
            <div x-show="isAdmin" x-cloak>
                @include('partials.dashboard.sidebar', ['isAdmin' => true])
            </div>
            <div x-show="!isAdmin" x-cloak>
                @include('partials.dashboard.sidebar', ['isAdmin' => false])
            </div>
            
            <main class="flex-1 overflow-y-auto bg-gray-50 p-6" style="transition: opacity 120ms ease-out;">
                @yield('content')
            </main>
        </div>
    </div>

    <!-- Medical Dashboard JS -->
    <script src="{{ asset('js/medical-dashboard.js') }}"></script>
    <script>
        lucide.createIcons();
        // Re-initialize icons when Alpine updates
        document.addEventListener('alpine:init', () => {
            Alpine.effect(() => {
                setTimeout(() => lucide.createIcons(), 100);
            });
        });
    </script>
    @stack('scripts')
</body>
</html>

