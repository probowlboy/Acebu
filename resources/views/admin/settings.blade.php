@extends('layouts.dashboard')

@section('title', 'Admin Settings')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
     x-data="{
         userData: null,
         error: null,
         success: null,
         loading: false,
         activeTab: 'profile',
         profileForm: {
             name: '',
             email: '',
             username: '',
             birthday: '',
             phone: '',
             country: '',
             municipality: '',
             province: '',
             barangay: '',
             zip_code: '',
             zone_street: ''
         },
         passwordForm: {
             current_password: '',
             new_password: '',
             confirm_password: ''
         },
         showCurrentPassword: false,
         showNewPassword: false,
         showConfirmPassword: false,
         async init() {
             // Check if user is patient and redirect
             const isAdmin = localStorage.getItem('isAdmin') === 'true';
             if (!isAdmin) {
                 window.location.href = '/patient/patientdashboard';
                 return;
             }
             
             await this.fetchUserData();
         },
         async fetchUserData() {
             try {
                 const token = localStorage.getItem('token');
                 
                 if (!token) {
                     window.location.href = '{{ route('admin.login') }}';
                     return;
                 }
                 
                 // Fetch full user data from API
                 const response = await fetch('{{ url('/api/user') }}', {
                     headers: {
                         'Authorization': `Bearer ${token}`,
                         'Accept': 'application/json'
                     },
                     credentials: 'include'
                 });
                 
                 if (response.ok) {
                     const user = await response.json();
                     this.userData = user;
                     this.profileForm = {
                         name: user.name || '',
                         email: user.email || '',
                         username: user.username || '',
                         birthday: user.birthday ? user.birthday.split('T')[0] : '',
                         phone: user.phone || '',
                         country: user.country || '',
                         municipality: user.municipality || '',
                         province: user.province || '',
                         barangay: user.barangay || '',
                         zip_code: user.zip_code || '',
                         zone_street: user.zone_street || ''
                     };
                 } else {
                     this.error = 'Failed to load user data';
                 }
                 
             } catch (error) {
                 console.error('Error fetching user data:', error);
                 this.error = 'Failed to load user data';
             }
         },
         async updateProfile() {
             this.loading = true;
             this.error = null;
             this.success = null;
             
             try {
                 const token = localStorage.getItem('token');
                 
                 const response = await fetch('{{ url('/api/admin/profile') }}', {
                     method: 'PUT',
                     headers: {
                         'Authorization': `Bearer ${token}`,
                         'Accept': 'application/json',
                         'Content-Type': 'application/json',
                         'X-CSRF-TOKEN': '{{ csrf_token() }}'
                     },
                     credentials: 'include',
                     body: JSON.stringify(this.profileForm)
                 });
                 
                 const data = await response.json();
                 
                 if (!response.ok) {
                     throw new Error(data.message || 'Failed to update profile');
                 }
                 
                 this.success = 'Profile updated successfully!';
                 setTimeout(() => this.success = null, 3000);
                 
             } catch (error) {
                 console.error('Error updating profile:', error);
                 this.error = error.message || 'Failed to update profile. Please try again.';
             } finally {
                 this.loading = false;
             }
         },
         async changePassword() {
             if (this.passwordForm.new_password !== this.passwordForm.confirm_password) {
                 this.error = 'New passwords do not match';
                 return;
             }
             
             if (this.passwordForm.new_password.length < 8) {
                 this.error = 'Password must be at least 8 characters';
                 return;
             }
             
             this.loading = true;
             this.error = null;
             this.success = null;
             
             try {
                 const token = localStorage.getItem('token');
                 
                 const response = await fetch('{{ url('/api/admin/password') }}', {
                     method: 'PUT',
                     headers: {
                         'Authorization': `Bearer ${token}`,
                         'Accept': 'application/json',
                         'Content-Type': 'application/json',
                         'X-CSRF-TOKEN': '{{ csrf_token() }}'
                     },
                     credentials: 'include',
                     body: JSON.stringify({
                         current_password: this.passwordForm.current_password,
                         new_password: this.passwordForm.new_password
                     })
                 });
                 
                 const data = await response.json();
                 
                 if (!response.ok) {
                     throw new Error(data.message || 'Failed to change password');
                 }
                 
                 this.success = 'Password changed successfully!';
                 this.passwordForm = {
                     current_password: '',
                     new_password: '',
                     confirm_password: ''
                 };
                 setTimeout(() => this.success = null, 3000);
                 
             } catch (error) {
                 console.error('Error changing password:', error);
                 this.error = error.message || 'Failed to change password. Please try again.';
             } finally {
                 this.loading = false;
             }
         }
     }">
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Admin Settings</h1>
                <p class="text-gray-600" x-show="userData">
                    Manage your account information, <span x-text="userData?.name"></span>
                </p>
            </div>
            <a href="{{ route('admin.dashboard') }}" 
               class="flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors">
                <i data-lucide="arrow-left" class="h-4 w-4 mr-2"></i>
                Back to Dashboard
            </a>
        </div>

        <!-- Error/Success Messages -->
        <div x-show="error" x-cloak 
             class="bg-red-50 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline" x-text="error"></span>
        </div>
        
        <div x-show="success" x-cloak 
             class="bg-green-50 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline" x-text="success"></span>
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px">
                    <button @click="activeTab = 'profile'"
                            :class="activeTab === 'profile' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="py-4 px-6 text-sm font-medium border-b-2 transition-colors">
                        <i data-lucide="user" class="h-4 w-4 inline mr-2"></i>
                        Profile Information
                    </button>
                    <button @click="activeTab = 'password'"
                            :class="activeTab === 'password' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="py-4 px-6 text-sm font-medium border-b-2 transition-colors">
                        <i data-lucide="lock" class="h-4 w-4 inline mr-2"></i>
                        Change Password
                    </button>
                </nav>
            </div>

            <!-- Profile Tab -->
            <div x-show="activeTab === 'profile'" class="p-6">
                <form @submit.prevent="updateProfile()" class="space-y-6">
                    <!-- Personal Information -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Personal Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                                <input type="text" 
                                       x-model="profileForm.name"
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                                <input type="email" 
                                       x-model="profileForm.email"
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Username <span class="text-red-500">*</span></label>
                                <input type="text" 
                                       x-model="profileForm.username"
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Birthday <span class="text-red-500">*</span></label>
                                <input type="date" 
                                       x-model="profileForm.birthday"
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone <span class="text-red-500">*</span></label>
                                <input type="text" 
                                       x-model="profileForm.phone"
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </div>

                    <!-- Address Information -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Address Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Country <span class="text-red-500">*</span></label>
                                <input type="text" 
                                       x-model="profileForm.country"
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Province <span class="text-red-500">*</span></label>
                                <input type="text" 
                                       x-model="profileForm.province"
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Municipality <span class="text-red-500">*</span></label>
                                <input type="text" 
                                       x-model="profileForm.municipality"
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Barangay <span class="text-red-500">*</span></label>
                                <input type="text" 
                                       x-model="profileForm.barangay"
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ZIP Code <span class="text-red-500">*</span></label>
                                <input type="text" 
                                       x-model="profileForm.zip_code"
                                       required
                                       maxlength="4"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Zone/Street <span class="text-red-500">*</span></label>
                                <input type="text" 
                                       x-model="profileForm.zone_street"
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="submit"
                                :disabled="loading"
                                :class="loading ? 'opacity-70 cursor-not-allowed' : ''"
                                class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                            <span x-show="!loading">Save Changes</span>
                            <span x-show="loading" x-cloak>Saving...</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Password Tab -->
            <div x-show="activeTab === 'password'" class="p-6">
                <form @submit.prevent="changePassword()" class="space-y-6">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Change Password</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Current Password <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input :type="showCurrentPassword ? 'text' : 'password'" 
                                           x-model="passwordForm.current_password"
                                           required
                                           class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <button type="button" 
                                            @click="showCurrentPassword = !showCurrentPassword"
                                            class="absolute right-3 top-2.5 text-gray-500 hover:text-gray-700">
                                        <i :data-lucide="showCurrentPassword ? 'eye-off' : 'eye'" class="h-5 w-5"></i>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">New Password <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input :type="showNewPassword ? 'text' : 'password'" 
                                           x-model="passwordForm.new_password"
                                           required
                                           minlength="8"
                                           class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <button type="button" 
                                            @click="showNewPassword = !showNewPassword"
                                            class="absolute right-3 top-2.5 text-gray-500 hover:text-gray-700">
                                        <i :data-lucide="showNewPassword ? 'eye-off' : 'eye'" class="h-5 w-5"></i>
                                    </button>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">Password must be at least 8 characters</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input :type="showConfirmPassword ? 'text' : 'password'" 
                                           x-model="passwordForm.confirm_password"
                                           required
                                           minlength="8"
                                           class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <button type="button" 
                                            @click="showConfirmPassword = !showConfirmPassword"
                                            class="absolute right-3 top-2.5 text-gray-500 hover:text-gray-700">
                                        <i :data-lucide="showConfirmPassword ? 'eye-off' : 'eye'" class="h-5 w-5"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="submit"
                                :disabled="loading"
                                :class="loading ? 'opacity-70 cursor-not-allowed' : ''"
                                class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                            <span x-show="!loading">Change Password</span>
                            <span x-show="loading" x-cloak>Changing...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();
</script>
@endsection

