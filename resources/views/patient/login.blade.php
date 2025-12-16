<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Login - Acebu Dental</title>
    @vite(['resources/css/app.css'])
    <script defer src="{{ asset('libs/alpine.min.js') }}"></script>
    <style>[x-cloak] { display: none !important; }</style>
    <!-- Lucide Icons -->
    <script src="{{ asset('libs/lucide.min.js') }}"></script>
</head>
<body class="antialiased">
    <div class="relative min-h-screen w-full">
        <div class="fixed inset-0 h-full w-full bg-cover bg-center"
             style="background-image: url('{{ asset('images/signupbg.jpg') }}'); filter: brightness(0.65);">
        </div>
        <div class="relative min-h-screen w-full flex flex-col justify-center items-center py-4 px-4"
             x-data="{
                 formData: { username: '', password: '' },
                 error: '',
                 showPassword: false,
                 isSubmitting: false,
                 async handleSubmit(e) {
                     e.preventDefault();
                     this.error = '';
                     this.isSubmitting = true;

                     try {
                         localStorage.removeItem('token');
                         localStorage.removeItem('adminToken');
                         localStorage.removeItem('isAdmin');
                         localStorage.removeItem('currentUser');

                         const response = await fetch('{{ url('/api/patients/login') }}', {
                             method: 'POST',
                             headers: {
                                 'Content-Type': 'application/json',
                                 'Accept': 'application/json',
                                 'X-CSRF-TOKEN': '{{ csrf_token() }}'
                             },
                             body: JSON.stringify(this.formData),
                         });

                         const data = await response.json();

                         if (!response.ok) {
                             // Specific message for invalid username/password
                             if (response.status === 401) {
                                 this.error = 'Invalid username or password';
                                 this.isSubmitting = false;
                                 return;
                             }

                             // Validation errors from API
                             if (data.errors) {
                                 const errorMessages = Object.values(data.errors).flat();
                                 this.error = errorMessages.join(', ');
                                 this.isSubmitting = false;
                                 return;
                             }

                             this.error = data.message || 'Login failed';
                             this.isSubmitting = false;
                             return;
                         }

                         localStorage.setItem('currentUser', JSON.stringify(data.patient));
                         localStorage.setItem('token', data.token);
                         localStorage.setItem('isAdmin', 'false');

                         window.location.href = '/patient/patientdashboard';

                     } catch (err) {
                         console.error('Login error:', err);
                         this.error = 'Invalid username or password';
                     } finally {
                         this.isSubmitting = false;
                     }
                 }
             }"
             x-init="setTimeout(() => lucide.createIcons(), 100)">
        
            <!-- Remove fixed error notification. We'll render inline instead (inside the form above Remember Me). -->
        
            <div class="w-full max-w-md bg-white rounded-lg shadow-xl overflow-hidden">
                <!-- Header Section -->
                <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 text-white">
                    <div class="text-center">
                        <div class="flex justify-center mb-2">
                            <div class="bg-white/20 p-2 rounded-full">
                                <i data-lucide="user-circle" class="h-8 w-8 text-white"></i>
                            </div>
                        </div>
                        <h1 class="text-2xl font-bold mb-1">Welcome Back</h1>
                        <p class="text-blue-100 text-sm">Sign in to your account</p>
                    </div>
                </div>
                
                <div class="p-6">
                    <form @submit.prevent="handleSubmit" class="space-y-4">
                        <!-- Username Field -->
                        <div>
                            <label for="username" class="block text-sm font-semibold text-gray-700 mb-1.5">
                                Username <span class="text-red-500">*</span>
                            </label>
                            <div class="relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i data-lucide="user" class="h-4 w-4 text-gray-500"></i>
                                </div>
                                <input
                                    id="username"
                                    name="username"
                                    type="text"
                                    autocomplete="username"
                                    required
                                    x-model="formData.username"
                                    placeholder="Enter your username"
                                    class="appearance-none block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white hover:border-gray-400 transition-all"
                                />
                            </div>
                        </div>

                        <!-- Password Field -->
                        <div>
                            <label for="password" class="block text-sm font-semibold text-gray-700 mb-1.5">
                                Password <span class="text-red-500">*</span>
                            </label>
                            <div class="relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i data-lucide="lock" class="h-4 w-4 text-gray-500"></i>
                                </div>
                                <input
                                    id="password"
                                    name="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    autocomplete="current-password"
                                    required
                                    x-model="formData.password"
                                    placeholder="Enter your password"
                                    class="appearance-none block w-full pl-10 pr-10 py-2.5 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white hover:border-gray-400 transition-all"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center hover:opacity-70 transition-opacity"
                                >
                                    <i x-show="showPassword" x-cloak data-lucide="eye-off" class="h-4 w-4 text-gray-500 cursor-pointer"></i>
                                    <i x-show="!showPassword" data-lucide="eye" class="h-4 w-4 text-gray-500 cursor-pointer"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Error (visible above Remember Me on failed login) -->
                        <div x-show="error" x-cloak class="bg-red-50 border border-red-400 text-red-700 px-4 py-2 rounded-md shadow mb-2 flex items-center gap-2" role="alert">
                            <i data-lucide="alert-circle" class="h-4 w-4"></i>
                            <span x-text="error"></span>
                        </div>

                        <!-- Remember Me -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <input 
                                    id="remember-me"
                                    name="remember-me"
                                    type="checkbox"
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                >
                                <label for="remember-me" class="ml-2 text-sm text-gray-700">Remember me</label>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-2">
                            <button 
                                type="submit"
                                :disabled="isSubmitting"
                                :class="isSubmitting ? 'opacity-70 cursor-not-allowed' : 'hover:shadow-lg transform hover:-translate-y-0.5'"
                                class="w-full py-2.5 px-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold rounded-md focus:outline-none focus:ring-4 focus:ring-blue-300 transition-all duration-200 flex items-center justify-center gap-2">
                                <i x-show="!isSubmitting" data-lucide="log-in" class="h-4 w-4"></i>
                                <i x-show="isSubmitting" x-cloak data-lucide="loader" class="h-4 w-4 animate-spin"></i>
                                <span x-text="isSubmitting ? 'Signing in...' : 'Sign in'"></span>
                            </button>
                        </div>
                    </form>

                    <!-- Divider -->
                    <div class="mt-6">
                        <div class="relative">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-gray-300"></div>
                            </div>
                            <div class="relative flex justify-center text-sm">
                                <span class="px-3 bg-white text-gray-500">New to Acebu Dental?</span>
                            </div>
                        </div>
                    </div>

                    <!-- Sign Up Link -->
                    <div class="mt-4">
                        <a href="{{ route('patient.signup') }}"
                            class="w-full flex justify-center items-center gap-2 py-2 px-4 border-2 border-blue-500 rounded-md text-sm font-medium text-blue-600 bg-white hover:bg-blue-50 hover:border-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-300 transition-all">
                            <i data-lucide="user-plus" class="h-4 w-4"></i>
                            Create an account
                        </a>
                    </div>

                    <!-- Back to Home -->
                    <div class="mt-3">
                        <a href="{{ route('home') }}"
                            class="w-full flex justify-center items-center gap-2 py-2 px-4 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-300 transition-all">
                            <i data-lucide="arrow-left" class="h-4 w-4"></i>
                            Go back to home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        lucide.createIcons();
        // Re-initialize icons when Alpine updates
        document.addEventListener('alpine:init', () => {
            Alpine.effect(() => {
                setTimeout(() => lucide.createIcons(), 100);
            });
        });
    </script>
</body>
</html>
