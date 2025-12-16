<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Acebu Dental</title>
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
                        // Validate form
                        if (!this.formData.username || !this.formData.password) {
                            this.error = 'Please fill in all fields';
                            this.isSubmitting = false;
                            return;
                        }

                        localStorage.removeItem('token');
                        localStorage.removeItem('adminToken');
                        localStorage.removeItem('isAdmin');
                        localStorage.removeItem('currentUser');

                        // Ensure username and password are not empty
                        const username = this.formData.username.trim();
                        const password = this.formData.password;
                        
                        if (!username || !password) {
                            this.error = 'Please enter both username and password';
                            this.isSubmitting = false;
                            return;
                        }
                        
                        const response = await fetch('{{ url('/api/admin/login') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                username: username,
                                password: password
                            }),
                        });

                        // Check if response is OK and parse JSON
                        let data;
                        const contentType = response.headers.get('content-type');
                        if (contentType && contentType.includes('application/json')) {
                            data = await response.json();
                        } else {
                            const text = await response.text();
                            this.error = text || 'Server returned an invalid response';
                            this.isSubmitting = false;
                            return;
                        }

                        if (!response.ok) {
                            // Specific message for invalid username/password
                            if (response.status === 401) {
                                this.error = 'Invalid username or password';
                                this.isSubmitting = false;
                                return;
                            }

                            // Handle validation errors
                            if (data.errors) {
                                const errorMessages = Object.values(data.errors).flat();
                                this.error = errorMessages.join(', ') || data.message || 'Login failed';
                                this.isSubmitting = false;
                                return;
                            }

                            this.error = data.message || 'Login failed';
                            this.isSubmitting = false;
                            return;
                        }

                        // Store the admin token
                        if (data.token) {
                            localStorage.setItem('adminToken', data.token);
                            localStorage.setItem('isAdmin', 'true');
                            localStorage.setItem('token', data.token);
                            
                            // Store admin info if available
                            if (data.admin) {
                                localStorage.setItem('currentUser', JSON.stringify(data.admin));
                            }

                            window.location.href = '/admin/admindashboard';
                        } else {
                            this.error = 'No token received from server';
                            this.isSubmitting = false;
                            return;
                        }

                    } catch (err) {
                        console.error('Login error:', err);
                        // Show generic invalid message for network/server errors
                        this.error = 'Invalid username or password';
                    } finally {
                        this.isSubmitting = false;
                    }
                }
             }"
             x-init="setTimeout(() => lucide.createIcons(), 100)">
        
            <!-- Inline Error Notification (shown on login failures, placed in form below) -->
        
            <div class="w-full max-w-md bg-white rounded-lg shadow-xl overflow-hidden">
                <!-- Header Section -->
                <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 text-white">
                    <div class="text-center">
                        <div class="flex justify-center mb-2">
                            <div class="bg-white/20 p-2 rounded-full">
                                <i data-lucide="shield-check" class="h-8 w-8 text-white"></i>
                            </div>
                        </div>

                        <!-- Inline error notification will be shown inside the form (below) -->
                        <h1 class="text-2xl font-bold mb-1">Admin Access</h1>
                        <p class="text-purple-100 text-sm">Sign in to manage the clinic</p>
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
                                    class="appearance-none block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 bg-white hover:border-gray-400 transition-all"
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
                                    class="appearance-none block w-full pl-10 pr-10 py-2.5 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 bg-white hover:border-gray-400 transition-all"
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
                                    class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded"
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
                                class="w-full py-2.5 px-4 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-semibold rounded-md focus:outline-none focus:ring-4 focus:ring-purple-300 transition-all duration-200 flex items-center justify-center gap-2">
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
                                <span class="px-3 bg-white text-gray-500">Admin access only</span>
                            </div>
                        </div>
                    </div>

                    <!-- Back to Home -->
                    <div class="mt-4">
                        <a href="{{ route('home') }}"
                            class="w-full flex justify-center items-center gap-2 py-2 px-4 border-2 border-purple-500 rounded-md text-sm font-medium text-purple-600 bg-white hover:bg-purple-50 hover:border-purple-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-300 transition-all">
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
