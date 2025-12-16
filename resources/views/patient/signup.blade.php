    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Sign Up - Acebu Dental</title>
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
                formData: {
                firstName: '',
                middleName: '',
                lastName: '',
                username: '',
                password: '',
                confirmPassword: '',
                birthday: '',
                phone: '',
                country: '',
                address: '',
                province: '',
                state: '',
                zipCode: '',
                zoneStreet: '',
                gender: ''
            },
             age: null,
             errors: {},
             showPassword: false,
             showConfirmPassword: false,
             isSubmitting: false,
             notification: { show: false, message: '', type: 'success' },
             
             formatPhoneNumber(value) {
                 let cleaned = value.replace(/\D/g, '');
                 if (cleaned.startsWith('0')) {
                     cleaned = cleaned.substring(1);
                 }
                 if (cleaned.startsWith('63')) {
                     cleaned = cleaned.substring(2);
                 }
                 cleaned = cleaned.substring(0, 10);
                 if (cleaned.length === 0) return '';
                 let formatted = '+63 ';
                 if (cleaned.length > 0) {
                     formatted += cleaned.substring(0, 1);
                 }
                 if (cleaned.length > 1) {
                     formatted += cleaned.substring(1, 4);
                 }
                 if (cleaned.length > 4) {
                     formatted += ' ' + cleaned.substring(4, 7);
                 }
                 if (cleaned.length > 7) {
                     formatted += ' ' + cleaned.substring(7, 10);
                 }
                 return formatted.trim();
             },

             showNotification(message, type = 'success') {
                 try {
                     this.notification = { show: true, message: message || '', type: type || 'success' };
                     // auto-hide
                     setTimeout(() => { try { this.notification.show = false; } catch(e){} }, 3000);
                 } catch (e) { console.error('showNotification error', e); }
             },
             
             calculateAge() {
                 if (this.formData.birthday) {
                     const birthDate = new Date(this.formData.birthday);
                     const today = new Date();
                     let calculatedAge = today.getFullYear() - birthDate.getFullYear();
                     const monthDiff = today.getMonth() - birthDate.getMonth();
                     if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                         calculatedAge--;
                     }
                     this.age = calculatedAge;
                 } else {
                     this.age = null;
                 }
             },
             
             handleChange(e) {
                 const name = e.target.name;
                 let value = e.target.value;
                 
                 if (name === 'phone') {
                     if (/^\d*$/.test(value.replace(/\D/g, ''))) {
                         value = this.formatPhoneNumber(value);
                     } else {
                         return;
                     }
                 } else if (name === 'zipCode') {
                     if (!/^\d{0,4}$/.test(value)) {
                         return;
                     }
                 } else if (['firstName', 'middleName', 'lastName', 'province', 'address', 'country'].includes(name)) {
                     if (/\d/.test(value)) {
                         return;
                     }
                 }
                 
                 this.formData[name] = value;
                 
                 if (name === 'birthday') {
                     this.calculateAge();
                 }
                 
                 if (this.errors[name]) {
                     delete this.errors[name];
                 }
             },
             
             validate() {
                 this.errors = {};
                 
                 if (!this.formData.firstName.trim()) {
                     this.errors.firstName = 'First name is required';
                 }
                 if (!this.formData.lastName.trim()) {
                     this.errors.lastName = 'Last name is required';
                 }
                 if (!this.formData.username.trim()) {
                     this.errors.username = 'Username (email) is required';
                 } else if (!/\S+@\S+\.\S+/.test(this.formData.username)) {
                     this.errors.username = 'Username must be a valid email address';
                 }
                 if (!this.formData.password) {
                     this.errors.password = 'Password is required';
                 } else if (this.formData.password.length < 8) {
                     this.errors.password = 'Password must be at least 8 characters';
                 }
                 if (!this.formData.confirmPassword) {
                     this.errors.confirmPassword = 'Please confirm your password';
                 } else if (this.formData.confirmPassword !== this.formData.password) {
                     this.errors.confirmPassword = 'Passwords do not match';
                 }
                 if (!this.formData.birthday) {
                     this.errors.birthday = 'Birthday is required';
                 } else {
                     const birthdayDate = new Date(this.formData.birthday);
                     const today = new Date();
                     if (birthdayDate > today) {
                         this.errors.birthday = 'Birthday cannot be in the future';
                     }
                 }
                 if (!this.formData.phone.trim()) {
                     this.errors.phone = 'Phone number is required';
                 }
                 if (!this.formData.country.trim()) {
                     this.errors.country = 'Country is required';
                 }
                 if (!this.formData.address.trim()) {
                     this.errors.address = 'Municipality is required';
                 }
                 if (!this.formData.province.trim()) {
                     this.errors.province = 'Province is required';
                 }
                 if (!this.formData.state.trim()) {
                     this.errors.state = 'Barangay is required';
                 }
                 if (!this.formData.zipCode.trim()) {
                     this.errors.zipCode = 'ZIP code is required';
                 }
                 if (!this.formData.zoneStreet.trim()) {
                     this.errors.zoneStreet = 'Zone/Street is required';
                 }
                 if (!this.formData.gender.trim()) {
                     this.errors.gender = 'Gender is required';
                 } else if (!['Female','Male'].includes(this.formData.gender)) {
                     this.errors.gender = 'Invalid gender selected';
                 }

                 // return whether form is valid
                 return Object.keys(this.errors).length === 0;
             },
             
             async handleSubmit(e) {
                 e.preventDefault();
                 if (this.validate()) {
                     this.isSubmitting = true;
                     try {
                         const response = await fetch('{{ url('/api/patients/register') }}', {
                             method: 'POST',
                             headers: {
                                 'Content-Type': 'application/json',
                                 'Accept': 'application/json',
                                 'X-CSRF-TOKEN': '{{ csrf_token() }}'
                             },
                             body: JSON.stringify({
                                 name: `${this.formData.firstName} ${this.formData.middleName} ${this.formData.lastName}`,
                                 email: this.formData.username,
                                 password: this.formData.password,
                                 username: this.formData.username,
                                 birthday: this.formData.birthday,
                                 phone: this.formData.phone,
                                 country: this.formData.country,
                                 municipality: this.formData.address,
                                 province: this.formData.province,
                                 barangay: this.formData.state,
                                 zip_code: this.formData.zipCode,
                                 zone_street: this.formData.zoneStreet,
                                 gender: this.formData.gender,
                             }),
                         });
                         
                         let data = null;
                         try { data = await response.json(); } catch(e) { data = null; }
                         
                         if (response && response.ok) {
                             this.showNotification('Account created successfully! Please login to continue.', 'success');
                             // Reset form
                             Object.keys(this.formData).forEach(key => {
                                 this.formData[key] = '';
                             });
                             setTimeout(() => {
                                 window.location.href = '{{ route('patient.login') }}';
                             }, 2000);
                         } else {
                             this.showNotification((data && data.message) ? data.message : 'Registration failed. Please try again.', 'error');
                         }
                     } catch (error) {
                         console.error('Signup error', error);
                         this.showNotification('Registration failed. Please try again.', 'error');
                     } finally {
                         this.isSubmitting = false;
                     }
                 }
             }
         }"
         x-init="calculateAge()">
        
        <!-- Notification -->
        <div x-show="notification.show" x-cloak 
             :class="notification.type === 'success' ? 'bg-green-50 border-green-400 text-green-700' : 'bg-red-50 border-red-400 text-red-700'"
             class="fixed top-4 right-4 border px-4 py-3 rounded-lg shadow-xl z-50 flex items-center gap-2">
            <i :data-lucide="notification.type === 'success' ? 'check-circle' : 'alert-circle'" class="h-5 w-5"></i>
            <span x-text="notification.message"></span>
        </div>
        
        <div class="w-full max-w-xl bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Header Section -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-3 py-2 text-white">
                <div class="text-center">
                    <h1 class="text-lg font-bold mb-0.5">Create an account</h1>
                    <p class="text-blue-100 text-xs">Sign up to get started with Acebu Dental</p>
                </div>
            </div>
            
            <div class="p-3">
            <form @submit.prevent="handleSubmit" class="space-y-2.5">
                <!-- Personal Information Section -->
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 p-2.5 rounded-md border border-blue-100 space-y-2.5">
                    <div class="flex items-center gap-1.5 pb-1.5 border-b border-blue-200">
                        <div class="bg-blue-600 p-0.5 rounded">
                            <i data-lucide="user" class="h-2.5 w-2.5 text-white"></i>
                        </div>
                        <h2 class="text-sm font-bold text-gray-800">Personal Information</h2>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-1.5">
                        <div>
                            <label for="firstName" class="block text-xs font-semibold text-gray-700 mb-0.5">First Name <span class="text-red-500">*</span></label>
                            <div class="relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-1.5 flex items-center pointer-events-none">
                                    <i data-lucide="user" class="h-3 w-3 text-gray-500"></i>
                                </div>
                                <input
                                    type="text"
                                    name="firstName"
                                    id="firstName"
                                    x-model="formData.firstName"
                                    @input="handleChange"
                                    placeholder="First Name"
                                    pattern="[A-Za-z\s]*"
                                    class="appearance-none block w-full pl-6 pr-1.5 py-1 border rounded-md shadow-sm placeholder-gray-400 focus:outline-none text-xs transition-all"
                                    :class="errors.firstName ? 'border-red-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-red-50' : 'border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white hover:border-gray-400'"
                                />
                            </div>
                            <p x-show="errors.firstName" x-cloak class="mt-0.5 text-xs text-red-600" x-text="errors.firstName"></p>
                        </div>
                        <div>
                            <label for="middleName" class="block text-xs font-semibold text-gray-700 mb-0.5">Middle Name</label>
                            <div class="relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-1.5 flex items-center pointer-events-none">
                                    <i data-lucide="user" class="h-3 w-3 text-gray-500"></i>
                                </div>
                                <input
                                    type="text"
                                    name="middleName"
                                    id="middleName"
                                    x-model="formData.middleName"
                                    @input="handleChange"
                                    placeholder="Middle Name"
                                    pattern="[A-Za-z\s]*"
                                    class="appearance-none block w-full pl-6 pr-1.5 py-1 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs bg-white hover:border-gray-400 transition-all"
                                />
                            </div>
                        </div>
                        <div>
                            <label for="lastName" class="block text-xs font-semibold text-gray-700 mb-0.5">Last Name <span class="text-red-500">*</span></label>
                            <div class="relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-1.5 flex items-center pointer-events-none">
                                    <i data-lucide="user" class="h-3 w-3 text-gray-500"></i>
                                </div>
                                <input
                                    type="text"
                                    name="lastName"
                                    id="lastName"
                                    x-model="formData.lastName"
                                    @input="handleChange"
                                    placeholder="Last Name"
                                    pattern="[A-Za-z\s]*"
                                    class="appearance-none block w-full pl-6 pr-1.5 py-1 border rounded-md shadow-sm placeholder-gray-400 focus:outline-none text-xs transition-all"
                                    :class="errors.lastName ? 'border-red-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-red-50' : 'border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white hover:border-gray-400'"
                                />
                            </div>
                            <p x-show="errors.lastName" x-cloak class="mt-0.5 text-xs text-red-600" x-text="errors.lastName"></p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-1.5">
                        <div>
                            <label for="birthday" class="block text-xs font-semibold text-gray-700 mb-0.5">Birthday <span class="text-red-500">*</span></label>
                            <div class="relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-1.5 flex items-center pointer-events-none">
                                    <i data-lucide="calendar" class="h-3 w-3 text-gray-500"></i>
                                </div>
                                <input
                                    type="date"
                                    name="birthday"
                                    id="birthday"
                                    x-model="formData.birthday"
                                    @input="handleChange"
                                    class="appearance-none block w-full pl-6 pr-1.5 py-1 border rounded-md shadow-sm placeholder-gray-400 focus:outline-none text-xs transition-all"
                                    :class="errors.birthday ? 'border-red-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-red-50' : 'border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white hover:border-gray-400'"
                                />
                            </div>
                            <p x-show="errors.birthday" x-cloak class="mt-0.5 text-xs text-red-600" x-text="errors.birthday"></p>
                            <p x-show="age !== null" class="text-xs text-gray-600 mt-0.5">
                                Age: <span x-text="age"></span> years old
                            </p>
                        </div>
                        <div>
                            <label for="phone" class="block text-xs font-semibold text-gray-700 mb-0.5">Phone Number <span class="text-red-500">*</span></label>
                            <div class="relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-1.5 flex items-center pointer-events-none">
                                    <i data-lucide="phone" class="h-3 w-3 text-gray-500"></i>
                                </div>
                                <input
                                    type="tel"
                                    name="phone"
                                    id="phone"
                                    x-model="formData.phone"
                                    @input="handleChange"
                                    placeholder="Enter phone number"
                                    class="appearance-none block w-full pl-6 pr-1.5 py-1 border rounded-md shadow-sm placeholder-gray-400 focus:outline-none text-xs transition-all"
                                    :class="errors.phone ? 'border-red-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-red-50' : 'border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white hover:border-gray-400'"
                                />
                            </div>
                            <p x-show="errors.phone" x-cloak class="mt-0.5 text-xs text-red-600" x-text="errors.phone"></p>
                        </div>
                        <div>
                                <label for="gender" class="block text-xs font-semibold text-gray-700 mb-0.5">Gender <span class="text-red-500">*</span></label>
                                <div class="relative rounded-md shadow-sm">
                                    <select
                                        name="gender"
                                        id="gender"
                                        x-model="formData.gender"
                                        @change="handleChange"
                                        class="appearance-none block w-full pl-2 pr-1.5 py-1 border rounded-md shadow-sm placeholder-gray-400 focus:outline-none text-xs transition-all"
                                        :class="errors.gender ? 'border-red-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-red-50' : 'border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white hover:border-gray-400'"
                                    >
                                        <option value="" disabled selected>Select gender</option>
                                        <option value="Female">Female</option>
                                        <option value="Male">Male</option>
                                    </select>
                                </div>
                                <p x-show="errors.gender" x-cloak class="mt-0.5 text-xs text-red-600" x-text="errors.gender"></p>
                        </div>
                    </div>
                </div>
                
                <!-- Address Section -->
                <div class="bg-gradient-to-br from-green-50 to-emerald-50 p-2.5 rounded-md border border-green-100 space-y-2.5">
                    <div class="flex items-center gap-1.5 pb-1.5 border-b border-green-200">
                        <div class="bg-green-600 p-0.5 rounded">
                            <i data-lucide="map-pin" class="h-2.5 w-2.5 text-white"></i>
                        </div>
                        <h2 class="text-sm font-bold text-gray-800">Address Information</h2>
                    </div>
                    <!-- Row 1: Country | Province -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-1.5">
                        <div>
                            <label for="country" class="block text-xs font-semibold text-gray-700 mb-0.5">Country <span class="text-red-500">*</span></label>
                            <div class="relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-1.5 flex items-center pointer-events-none">
                                    <i data-lucide="globe" class="h-3 w-3 text-gray-500"></i>
                                </div>
                                <input
                                    type="text"
                                    name="country"
                                    id="country"
                                    x-model="formData.country"
                                    @input="handleChange"
                                    placeholder="Country"
                                    pattern="[A-Za-z\s]*"
                                    class="appearance-none block w-full pl-6 pr-1.5 py-1 border rounded-md shadow-sm placeholder-gray-400 focus:outline-none text-xs transition-all"
                                    :class="errors.country ? 'border-red-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-red-50' : 'border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white hover:border-gray-400'"
                                />
                            </div>
                            <p x-show="errors.country" x-cloak class="mt-0.5 text-xs text-red-600" x-text="errors.country"></p>
                        </div>
                        <div>
                            <label for="province" class="block text-xs font-semibold text-gray-700 mb-0.5">Province <span class="text-red-500">*</span></label>
                            <div class="relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-1.5 flex items-center pointer-events-none">
                                    <i data-lucide="map-pin" class="h-3 w-3 text-gray-500"></i>
                                </div>
                                <input
                                    type="text"
                                    name="province"
                                    id="province"
                                    x-model="formData.province"
                                    @input="handleChange"
                                    placeholder="Province"
                                    pattern="[A-Za-z\s]*"
                                    class="appearance-none block w-full pl-6 pr-1.5 py-1 border rounded-md shadow-sm placeholder-gray-400 focus:outline-none text-xs transition-all"
                                    :class="errors.province ? 'border-red-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-red-50' : 'border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white hover:border-gray-400'"
                                />
                            </div>
                            <p x-show="errors.province" x-cloak class="mt-0.5 text-xs text-red-600" x-text="errors.province"></p>
                        </div>
                    </div>
                    <!-- Row 2: Municipality | Barangay -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-1.5">
                        <div>
                            <label for="address" class="block text-xs font-semibold text-gray-700 mb-0.5">Municipality <span class="text-red-500">*</span></label>
                            <div class="relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-1.5 flex items-center pointer-events-none">
                                    <i data-lucide="building" class="h-3 w-3 text-gray-500"></i>
                                </div>
                                <input
                                    type="text"
                                    name="address"
                                    id="address"
                                    x-model="formData.address"
                                    @input="handleChange"
                                    placeholder="Municipality"
                                    pattern="[A-Za-z\s]*"
                                    class="appearance-none block w-full pl-6 pr-1.5 py-1 border rounded-md shadow-sm placeholder-gray-400 focus:outline-none text-xs transition-all"
                                    :class="errors.address ? 'border-red-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-red-50' : 'border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white hover:border-gray-400'"
                                />
                            </div>
                            <p x-show="errors.address" x-cloak class="mt-0.5 text-xs text-red-600" x-text="errors.address"></p>
                        </div>
                        <div>
                            <label for="state" class="block text-xs font-semibold text-gray-700 mb-0.5">Barangay <span class="text-red-500">*</span></label>
                            <div class="relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-1.5 flex items-center pointer-events-none">
                                    <i data-lucide="home" class="h-3 w-3 text-gray-500"></i>
                                </div>
                                <input
                                    type="text"
                                    name="state"
                                    id="state"
                                    x-model="formData.state"
                                    @input="handleChange"
                                    placeholder="Barangay"
                                    class="appearance-none block w-full pl-6 pr-1.5 py-1 border rounded-md shadow-sm placeholder-gray-400 focus:outline-none text-xs transition-all"
                                    :class="errors.state ? 'border-red-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-red-50' : 'border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white hover:border-gray-400'"
                                />
                            </div>
                            <p x-show="errors.state" x-cloak class="mt-0.5 text-xs text-red-600" x-text="errors.state"></p>
                        </div>
                    </div>
                    <!-- Row 3: ZIP Code | Zone/Street -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-1.5">
                        <div>
                            <label for="zipCode" class="block text-xs font-semibold text-gray-700 mb-0.5">ZIP Code <span class="text-red-500">*</span></label>
                            <div class="relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-1.5 flex items-center pointer-events-none">
                                    <i data-lucide="hash" class="h-3 w-3 text-gray-500"></i>
                                </div>
                                <input
                                    type="text"
                                    name="zipCode"
                                    id="zipCode"
                                    x-model="formData.zipCode"
                                    @input="handleChange"
                                    placeholder="Enter ZIP code"
                                    maxlength="4"
                                    class="appearance-none block w-full pl-6 pr-1.5 py-1 border rounded-md shadow-sm placeholder-gray-400 focus:outline-none text-xs transition-all"
                                    :class="errors.zipCode ? 'border-red-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-red-50' : 'border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white hover:border-gray-400'"
                                />
                            </div>
                            <p x-show="errors.zipCode" x-cloak class="mt-0.5 text-xs text-red-600" x-text="errors.zipCode"></p>
                    </div>
                    <div>
                        <label for="zoneStreet" class="block text-xs font-semibold text-gray-700 mb-0.5">Zone/Street <span class="text-red-500">*</span></label>
                        <div class="relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-1.5 flex items-center pointer-events-none">
                                <i data-lucide="map-pin" class="h-3 w-3 text-gray-500"></i>
                            </div>
                            <input
                                type="text"
                                name="zoneStreet"
                                id="zoneStreet"
                                x-model="formData.zoneStreet"
                                @input="handleChange"
                                placeholder="Zone/Street Address"
                                class="appearance-none block w-full pl-6 pr-1.5 py-1 border rounded-md shadow-sm placeholder-gray-400 focus:outline-none text-xs transition-all"
                                :class="errors.zoneStreet ? 'border-red-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-red-50' : 'border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white hover:border-gray-400'"
                            />
                        </div>
                        <p x-show="errors.zoneStreet" x-cloak class="mt-0.5 text-xs text-red-600" x-text="errors.zoneStreet"></p>
                        </div>
                    </div>
                </div>
                
                <!-- Account Information Section -->
                <div class="bg-gradient-to-br from-purple-50 to-pink-50 p-2.5 rounded-md border border-purple-100 space-y-2.5">
                    <div class="flex items-center gap-1.5 pb-1.5 border-b border-purple-200">
                        <div class="bg-purple-600 p-0.5 rounded">
                            <i data-lucide="lock" class="h-2.5 w-2.5 text-white"></i>
                        </div>
                        <h2 class="text-sm font-bold text-gray-800">Account Information</h2>
                    </div>
                    <div>
                        <label for="username" class="block text-xs font-semibold text-gray-700 mb-0.5">Username <span class="text-red-500">*</span></label>
                        <div class="relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-1.5 flex items-center pointer-events-none">
                                <i data-lucide="user" class="h-3 w-3 text-gray-500"></i>
                            </div>
                            <input
                                type="text"
                                name="username"
                                id="username"
                                x-model="formData.username"
                                @input="handleChange"
                                placeholder="email@example.com"
                                class="appearance-none block w-full pl-6 pr-1.5 py-1 border rounded-md shadow-sm placeholder-gray-400 focus:outline-none text-xs transition-all"
                                :class="errors.username ? 'border-red-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-red-50' : 'border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white hover:border-gray-400'"
                            />
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">Your email address will be used as your username.</p>
                        <p x-show="errors.username" x-cloak class="mt-0.5 text-xs text-red-600" x-text="errors.username"></p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-1.5">
                        <div>
                            <label for="password" class="block text-xs font-semibold text-gray-700 mb-0.5">Password <span class="text-red-500">*</span></label>
                            <div class="relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-1.5 flex items-center pointer-events-none">
                                    <i data-lucide="lock" class="h-3 w-3 text-gray-500"></i>
                                </div>
                                <input
                                    :type="showPassword ? 'text' : 'password'"
                                    name="password"
                                    id="password"
                                    x-model="formData.password"
                                    @input="handleChange"
                                    placeholder="••••••••"
                                    class="appearance-none block w-full pl-6 pr-6 py-1 border rounded-md shadow-sm placeholder-gray-400 focus:outline-none text-xs transition-all"
                                    :class="errors.password ? 'border-red-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-red-50' : 'border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white hover:border-gray-400'"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 pr-2 flex items-center"
                                >
                                    <i x-show="showPassword" x-cloak data-lucide="eye-off" class="h-3 w-3 text-gray-500 cursor-pointer"></i>
                                    <i x-show="!showPassword" data-lucide="eye" class="h-3 w-3 text-gray-500 cursor-pointer"></i>
                                </button>
                            </div>
                            <p x-show="errors.password" x-cloak class="mt-0.5 text-xs text-red-600" x-text="errors.password"></p>
                        </div>
                        <div>
                            <label for="confirmPassword" class="block text-xs font-semibold text-gray-700 mb-0.5">Confirm Password <span class="text-red-500">*</span></label>
                            <div class="relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-1.5 flex items-center pointer-events-none">
                                    <i data-lucide="lock" class="h-3 w-3 text-gray-500"></i>
                                </div>
                                <input
                                    :type="showConfirmPassword ? 'text' : 'password'"
                                    name="confirmPassword"
                                    id="confirmPassword"
                                    x-model="formData.confirmPassword"
                                    @input="handleChange"
                                    placeholder="••••••••"
                                    class="appearance-none block w-full pl-6 pr-6 py-1 border rounded-md shadow-sm placeholder-gray-400 focus:outline-none text-xs transition-all"
                                    :class="errors.confirmPassword ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'"
                                />
                                <button
                                    type="button"
                                    @click="showConfirmPassword = !showConfirmPassword"
                                    class="absolute inset-y-0 right-0 pr-2 flex items-center"
                                >
                                    <i x-show="showConfirmPassword" x-cloak data-lucide="eye-off" class="h-3 w-3 text-gray-500 cursor-pointer"></i>
                                    <i x-show="!showConfirmPassword" data-lucide="eye" class="h-3 w-3 text-gray-500 cursor-pointer"></i>
                                </button>
                            </div>
                            <p x-show="errors.confirmPassword" x-cloak class="mt-0.5 text-xs text-red-600" x-text="errors.confirmPassword"></p>
                        </div>
                    </div>
                </div>
                
                <!-- Submit Section -->
                <div class="pt-2 border-t border-gray-200">
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        :class="isSubmitting ? 'opacity-70 cursor-not-allowed' : 'hover:shadow-lg transform hover:-translate-y-0.5'"
                        class="w-full py-1.5 px-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold rounded-md focus:outline-none focus:ring-4 focus:ring-blue-300 transition-all duration-200 flex items-center justify-center gap-1.5 text-xs"
                    >
                        <i x-show="!isSubmitting" data-lucide="user-plus" class="h-3 w-3"></i>
                        <i x-show="isSubmitting" x-cloak data-lucide="loader" class="h-3 w-3 animate-spin"></i>
                        <span x-text="isSubmitting ? 'Creating Account...' : 'Create Account'"></span>
                    </button>
                    <div class="mt-1.5">
                        <a
                            href="{{ route('home') }}"
                            class="w-full flex justify-center items-center gap-1.5 py-1 px-2.5 border-2 border-gray-300 rounded-md text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-300 transition-all"
                        >
                            <i data-lucide="arrow-left" class="h-2.5 w-2.5"></i>
                            Go back to home
                        </a>
                    </div>
                </div>
            </form>
            <div class="mt-2 pt-2 border-t border-gray-200 text-center">
                <p class="text-xs text-gray-600">
                    Already have an account? 
                    <a href="{{ route('patient.login') }}" class="text-blue-600 hover:text-blue-800 font-semibold hover:underline transition-colors">
                        Sign in here
                    </a>
                </p>
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

