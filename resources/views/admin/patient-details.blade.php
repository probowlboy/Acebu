    @extends('layouts.dashboard')

    @section('title', 'Patient Account Dashboard')

    @section('content')
    <div class="min-h-screen bg-gray-50"
        x-data="{
            patientId: {{ $patientId }},
            patient: null,
            appointments: [],
            filteredAppointments: [],
            stats: { total: 0, completed: 0, upcoming: 0 },
            isLoading: true,
            appointmentLoading: true,
            saving: false,
            error: null,
            successMessage: null,
            profileImage: null,
            profileImagePreview: null,
            isDragging: false,
            searchQuery: '',
            activeTab: 'settings',
            editForm: {
                first_name: '',
                middle_name: '',
                last_name: '',
                username: '',
                email: '',
                password: '',
                confirm_password: '',
                birthday: '',
                phone: '',
                country: '',
                address: '',
                province: '',
                state: '',
                zip_code: '',
                zone_street: '',
                gender: '',
                medical_record_number: ''
            },
            passwordForm: {
                new_password: '',
                confirm_password: ''
            },
            passwordSaving: false,
            async init() {
                const isAdmin = localStorage.getItem('isAdmin') === 'true';
                if (!isAdmin) {
                    window.location.href = '/patient/patientdashboard';
                    return;
                }
                await this.refreshData();
            },
            async refreshData() {
                this.error = null;
                await Promise.all([this.fetchPatient(), this.fetchAppointments()]);
            },
            async fetchPatient() {
                try {
                    this.isLoading = true;
                    const token = localStorage.getItem('token');
                    if (!token) {
                        window.location.href = '{{ route('admin.login') }}';
                        return;
                    }
                    const response = await window.apiUtils.fetch(`{{ url('/api/admin/patients') }}/${this.patientId}`, {
                        method: 'GET',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Accept': 'application/json'
                        }
                    });
                    if (!response || !response.ok) {
                        throw new Error('Unable to load patient details.');
                    }
                    const data = await response.json();
                    this.patient = data.patient;
                    
                    const nameParts = (data.patient.name || '').split(' ');
                    const firstName = nameParts[0] || '';
                    const middleName = nameParts.length > 2 ? nameParts.slice(1, -1).join(' ') : '';
                    const lastName = nameParts.length > 1 ? nameParts[nameParts.length - 1] : '';
                    
                    this.editForm = {
                        first_name: firstName,
                        middle_name: middleName,
                        last_name: lastName,
                        username: data.patient.username || '',
                        email: data.patient.email || '',
                        password: '',
                        confirm_password: '',
                        birthday: data.patient.birthday ? data.patient.birthday.split('T')[0] : '',
                        phone: data.patient.phone || '',
                        country: data.patient.country || '',
                        address: data.patient.municipality || data.patient.barangay || '',
                        province: data.patient.province || '',
                        state: data.patient.province || '',
                        zip_code: data.patient.zip_code || '',
                        zone_street: data.patient.zone_street || '',
                        gender: data.patient.gender || '',
                        medical_record_number: data.patient.medical_record_number || ''
                    };
                    
                    if (data.patient.profile_photo_url) {
                        this.profileImagePreview = data.patient.profile_photo_url;
                    }
                } catch (error) {
                    console.error(error);
                    this.error = error.message || 'Failed to load patient.';
                } finally {
                    this.isLoading = false;
                }
            },
            async fetchAppointments() {
                try {
                    this.appointmentLoading = true;
                    const token = localStorage.getItem('token');
                    if (!token) {
                        window.location.href = '{{ route('admin.login') }}';
                        return;
                    }
                    const response = await window.apiUtils.fetch(`{{ url('/api/admin/patients') }}/${this.patientId}/history`, {
                        method: 'GET',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Accept': 'application/json'
                        }
                    });
                    if (!response || !response.ok) {
                        throw new Error('Unable to load appointment history.');
                    }
                    const data = await response.json();
                    this.appointments = data.appointments || [];
                    this.filteredAppointments = this.appointments;
                    this.computeStats();
                } catch (error) {
                    console.error(error);
                    this.error = error.message || 'Failed to load appointment history.';
                } finally {
                    this.appointmentLoading = false;
                }
            },
            computeStats() {
                const stats = { total: 0, completed: 0, upcoming: 0 };
                const now = new Date();
                this.appointments.forEach(appointment => {
                    stats.total += 1;
                    if (appointment.status === 'completed') {
                        stats.completed += 1;
                    } else if (appointment.status === 'pending' || appointment.status === 'confirmed') {
                        const apptDate = this.parseLocalISO ? this.parseLocalISO(appointment.appointment_date) : new Date(appointment.appointment_date);
                        if (apptDate && apptDate > now) {
                            stats.upcoming += 1;
                        }
                    }
                });
                this.stats = stats;
            },
            parseLocalISO(dateString) {
                if (!dateString) return null;
                try {
                    const parts = String(dateString).split('T');
                    const [datePart, timePart] = parts;
                    const [y, m, d] = (datePart || '').split('-').map(v => parseInt(v, 10));
                    if (!y || !m || !d) return null;
                    const [hh = '0', mm = '0', ss = '0'] = (timePart || '').split(':');
                    return new Date(y, parseInt(m,10) - 1, parseInt(d,10), parseInt(hh,10), parseInt(mm,10), parseInt((ss||'0').split('.')[0],10));
                } catch (e) {
                    console.error('parseLocalISO error', e, dateString);
                    return null;
                }
            },
            filterAppointments() {
                if (!this.searchQuery.trim()) {
                    this.filteredAppointments = this.appointments;
                    return;
                }
                const query = this.searchQuery.toLowerCase();
                this.filteredAppointments = this.appointments.filter(appointment => {
                    return (
                        appointment.service_name?.toLowerCase().includes(query) ||
                        appointment.status?.toLowerCase().includes(query) ||
                        appointment.dentist_name?.toLowerCase().includes(query) ||
                        appointment.notes?.toLowerCase().includes(query)
                    );
                });
            },
            handleFileSelect(event) {
                const file = event.target.files[0];
                if (file) {
                    this.handleFile(file);
                }
            },
            async handleFile(file) {
                if (!file.type.startsWith('image/')) {
                    this.error = 'Please select an image file.';
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    this.error = 'Image size must be less than 5MB.';
                    return;
                }
                this.profileImage = file;
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.profileImagePreview = e.target.result;
                };
                reader.readAsDataURL(file);
                
                // Upload image immediately
                await this.uploadProfilePhoto(file);
            },
            async uploadProfilePhoto(file) {
                try {
                    const token = localStorage.getItem('token');
                    if (!token) {
                        window.location.href = '{{ route('admin.login') }}';
                        return;
                    }
                    
                    const formData = new FormData();
                    formData.append('photo', file);
                    
                    const response = await fetch(`{{ url('/api/admin/patients') }}/${this.patientId}/photo`, {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Accept': 'application/json'
                        },
                        body: formData
                    });
                    
                    if (!response.ok) {
                        const errorData = await response.json();
                        throw new Error(errorData.message || 'Failed to upload photo.');
                    }
                    
                    const data = await response.json();
                    if (data.patient && data.patient.profile_photo_url) {
                        this.profileImagePreview = data.patient.profile_photo_url;
                        // Update patient data
                        if (this.patient) {
                            this.patient.profile_photo_url = data.patient.profile_photo_url;
                        }
                    }
                    // Refresh patient data to get updated photo URL
                    await this.fetchPatient();
                    this.successMessage = 'Profile photo updated successfully.';
                    setTimeout(() => this.successMessage = null, 3000);
                } catch (error) {
                    console.error(error);
                    this.error = error.message || 'Failed to upload photo.';
                }
            },
            handleDrop(event) {
                event.preventDefault();
                this.isDragging = false;
                const file = event.dataTransfer.files[0];
                if (file) {
                    this.handleFile(file);
                }
            },
            handleDragOver(event) {
                event.preventDefault();
                this.isDragging = true;
            },
            handleDragLeave() {
                this.isDragging = false;
            },
            async updatePatient() {
                try {
                    this.error = null;
                    this.successMessage = null;
                    this.saving = true;
                    const token = localStorage.getItem('token');
                    if (!token) {
                        window.location.href = '{{ route('admin.login') }}';
                        return;
                    }
                    
                    const fullName = `${this.editForm.first_name} ${this.editForm.middle_name ? this.editForm.middle_name + ' ' : ''}${this.editForm.last_name}`.trim();
                    
                    const payload = {
                        name: fullName,
                        username: this.editForm.username,
                        email: this.editForm.email,
                        phone: this.editForm.phone,
                        birthday: this.editForm.birthday,
                        gender: this.editForm.gender,
                        municipality: this.editForm.address,
                        province: this.editForm.province,
                        country: this.editForm.country,
                        zip_code: this.editForm.zip_code,
                        zone_street: this.editForm.zone_street,
                        medical_record_number: this.editForm.medical_record_number
                    };
                    
                    const response = await window.apiUtils.fetch(`{{ url('/api/admin/patients') }}/${this.patientId}`, {
                        method: 'PUT',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });
                    
                    if (!response || !response.ok) {
                        const errorData = await response.json();
                        throw new Error(errorData.message || 'Failed to update patient.');
                    }
                    
                    await this.fetchPatient();
                    this.successMessage = 'Patient information updated successfully.';
                    setTimeout(() => this.successMessage = null, 5000);
                } catch (error) {
                    console.error(error);
                    this.error = error.message || 'Unable to update patient.';
                } finally {
                    this.saving = false;
                }
            },
            formatDate(dateString) {
                if (!dateString) return 'N/A';
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            },
            getStatusColor(status) {
                const colors = {
                    'confirmed': 'bg-green-100 text-green-700 border-green-200',
                    'pending': 'bg-yellow-100 text-yellow-700 border-yellow-200',
                    'completed': 'bg-blue-100 text-blue-700 border-blue-200',
                    'cancelled': 'bg-red-100 text-red-700 border-red-200'
                };
                return colors[status] || 'bg-gray-100 text-gray-700 border-gray-200';
            },
            async changePassword() {
                if (!this.passwordForm.new_password || this.passwordForm.new_password.length < 8) {
                    this.error = 'New password must be at least 8 characters.';
                    return;
                }
                if (this.passwordForm.new_password !== this.passwordForm.confirm_password) {
                    this.error = 'Passwords do not match.';
                    return;
                }
                try {
                    this.error = null;
                    this.successMessage = null;
                    this.passwordSaving = true;
                    const token = localStorage.getItem('token');
                    if (!token) {
                        window.location.href = '{{ route('admin.login') }}';
                        return;
                    }
                    const response = await fetch(`{{ url('/api/admin/patients') }}/${this.patientId}/password`, {
                        method: 'PUT',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            new_password: this.passwordForm.new_password
                        })
                    });
                    if (!response || !response.ok) {
                        const errorData = await response.json();
                        throw new Error(errorData.message || 'Failed to change password.');
                    }
                    this.passwordForm.new_password = '';
                    this.passwordForm.confirm_password = '';
                    this.successMessage = 'Password updated successfully.';
                    setTimeout(() => this.successMessage = null, 5000);
                } catch (error) {
                    console.error(error);
                    this.error = error.message || 'Unable to change password.';
                } finally {
                    this.passwordSaving = false;
                }
            }
        }"
        x-cloak>

        <div class="flex h-full">
            <!-- Left Sidebar -->
            <aside class="w-64 bg-white border-r border-gray-200 flex-shrink-0">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center gap-3 mb-6">
                        <a href="{{ route('admin.users') }}" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                            <i data-lucide="arrow-left" class="h-5 w-5 text-gray-600"></i>
                        </a>
                        <h2 class="text-lg font-semibold text-gray-900">Patient Dashboard</h2>
                    </div>

                    <!-- Sidebar Profile Card with Upload -->
                    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                        <!-- Profile Picture Section -->
                        <div class="bg-gradient-to-br from-teal-500 to-blue-600 p-4">
                            <div class="flex justify-center mb-3">
                                <div class="relative group">
                                    <div 
                                        @drop.prevent="handleDrop"
                                        @dragover.prevent="handleDragOver"
                                        @dragleave.prevent="handleDragLeave"
                                        :class="isDragging ? 'ring-4 ring-teal-300 scale-105' : 'ring-2 ring-white/50'"
                                        class="w-20 h-20 rounded-full overflow-hidden border-4 border-white shadow-xl cursor-pointer transition-all">
                                        
                                        <img x-show="profileImagePreview" 
                                            :src="profileImagePreview" 
                                            alt="Profile"
                                            class="w-full h-full object-cover">
                                            
                                        <div x-show="!profileImagePreview" class="flex flex-col items-center justify-center w-full h-full bg-white/20 backdrop-blur-sm">
                                            <i data-lucide="user" class="h-8 w-8 text-white mb-1"></i>
                                        </div>

                                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-all rounded-full flex items-center justify-center">
                                            <i data-lucide="camera" class="h-5 w-5 text-white opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                        </div>

                                        <input type="file" 
                                            accept="image/*" 
                                            @change="handleFileSelect"
                                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Patient Info Section -->
                        <div class="p-4">
                            <div class="text-center mb-4">
                                <h3 class="text-base font-bold text-gray-900 mb-1" x-text="patient?.name || 'Loading...'"></h3>
                                <p class="text-xs text-gray-500 mb-2 truncate" x-text="patient?.email || ''"></p>
                                <div class="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 rounded-full">
                                    <i data-lucide="hash" class="h-3 w-3 text-gray-500"></i>
                                    <span class="text-xs font-medium text-gray-700" x-text="patient?.id || ''"></span>
                                </div>
                            </div>

                            <!-- Quick Stats -->
                            <div class="grid grid-cols-3 gap-2 pt-4 border-t border-gray-200">
                                <div class="text-center p-2 bg-blue-50 rounded-lg">
                                    <p class="text-xl font-bold text-blue-600 mb-0.5" x-text="stats.total"></p>
                                    <p class="text-xs font-medium text-gray-600">Visits</p>
                                </div>
                                <div class="text-center p-2 bg-green-50 rounded-lg">
                                    <p class="text-xl font-bold text-green-600 mb-0.5" x-text="stats.completed"></p>
                                    <p class="text-xs font-medium text-gray-600">Done</p>
                                </div>
                                <div class="text-center p-2 bg-purple-50 rounded-lg">
                                    <p class="text-xl font-bold text-purple-600 mb-0.5" x-text="stats.upcoming"></p>
                                    <p class="text-xs font-medium text-gray-600">Upcoming</p>
                                </div>
                            </div>

                            <!-- Password Change Section -->
                            <div class="pt-4 border-t border-gray-200 mt-4">
                                <h4 class="text-xs font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                    <i data-lucide="key" class="h-3 w-3 text-teal-600"></i>
                                    Change Password
                                </h4>
                                <form @submit.prevent="changePassword" class="space-y-2">
                                    <div>
                                        <input type="password" 
                                               x-model="passwordForm.new_password" 
                                               placeholder="New Password"
                                               minlength="8"
                                               class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                                    </div>
                                    <div>
                                        <input type="password" 
                                               x-model="passwordForm.confirm_password" 
                                               placeholder="Confirm Password"
                                               minlength="8"
                                               class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                                    </div>
                                    <button type="submit"
                                            :disabled="passwordSaving"
                                            :class="passwordSaving ? 'opacity-50 cursor-not-allowed' : ''"
                                            class="w-full px-3 py-1.5 bg-teal-500 hover:bg-teal-600 text-white text-xs font-medium rounded-lg transition-colors flex items-center justify-center gap-1">
                                        <i data-lucide="save" class="h-3 w-3" x-show="!passwordSaving"></i>
                                        <i data-lucide="loader" class="h-3 w-3 animate-spin" x-show="passwordSaving" x-cloak></i>
                                        <span x-text="passwordSaving ? 'Updating...' : 'Update Password'"></span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Tabs -->
                <nav class="p-4 space-y-1">
                    <button @click="activeTab = 'settings'" 
                            :class="activeTab === 'settings' ? 'bg-teal-50 text-teal-700 border-teal-200' : 'text-gray-700 hover:bg-gray-50'"
                            class="w-full flex items-center gap-3 px-4 py-2.5 rounded-lg font-medium transition-all border">
                        <i data-lucide="user-cog" class="h-5 w-5"></i>
                        <span>Account Settings</span>
                    </button>
                    <button @click="activeTab = 'history'" 
                            :class="activeTab === 'history' ? 'bg-teal-50 text-teal-700 border-teal-200' : 'text-gray-700 hover:bg-gray-50'"
                            class="w-full flex items-center gap-3 px-4 py-2.5 rounded-lg font-medium transition-all border">
                        <i data-lucide="history" class="h-5 w-5"></i>
                        <span>History</span>
                    </button>
                </nav>
            </aside>

            <!-- Main Content -->
            <main class="flex-1 overflow-y-auto bg-gray-50">
                <div class="p-6">
                    <!-- Error & Success Messages -->
                    <div x-show="error" x-transition class="mb-4 bg-red-50 border-l-4 border-red-400 text-red-700 p-3 rounded-lg">
                        <div class="flex items-center">
                            <i data-lucide="alert-circle" class="h-4 w-4 mr-2"></i>
                            <p class="text-sm" x-text="error"></p>
                        </div>
                    </div>

                    <div x-show="successMessage" x-transition class="mb-4 bg-green-50 border-l-4 border-green-400 text-green-700 p-3 rounded-lg">
                        <div class="flex items-center">
                            <i data-lucide="check-circle" class="h-4 w-4 mr-2"></i>
                            <p class="text-sm" x-text="successMessage"></p>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div x-show="isLoading" class="text-center py-20">
                        <i data-lucide="loader" class="h-10 w-10 animate-spin text-teal-600 mx-auto mb-4"></i>
                        <p class="text-gray-600">Loading patient profile...</p>
                    </div>

                    <!-- Account Settings Tab -->
                    <div x-show="!isLoading && patient && activeTab === 'settings'" class="space-y-6">
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-6">Account Settings</h3>
                            <form @submit.prevent="updatePatient" class="space-y-6">

                                <!-- Personal Info -->
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                        <i data-lucide="user" class="h-4 w-4 text-teal-600"></i>
                                        Personal Information
                                    </h4>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1.5">First Name *</label>
                                            <input type="text" x-model="editForm.first_name" required
                                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Middle Name</label>
                                            <input type="text" x-model="editForm.middle_name"
                                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Last Name *</label>
                                            <input type="text" x-model="editForm.last_name" required
                                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Username *</label>
                                            <input type="text" x-model="editForm.username" required
                                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Date of Birth *</label>
                                            <input type="date" x-model="editForm.birthday" required
                                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Gender *</label>
                                            <select x-model="editForm.gender" required
                                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                                                <option value="">Select</option>
                                                <option value="male">Male</option>
                                                <option value="female">Female</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Contact Info -->
                                <div class="pt-4 border-t border-gray-200">
                                    <h4 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                        <i data-lucide="phone" class="h-4 w-4 text-blue-600"></i>
                                        Contact Information
                                    </h4>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Email Address *</label>
                                            <input type="email" x-model="editForm.email" required
                                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Phone Number *</label>
                                            <input type="tel" x-model="editForm.phone" required
                                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                                        </div>
                                    </div>
                                </div>

                                <!-- Address Info -->
                                <div class="pt-4 border-t border-gray-200">
                                    <h4 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                        <i data-lucide="map-pin" class="h-4 w-4 text-green-600"></i>
                                        Address Information
                                    </h4>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Country *</label>
                                            <input type="text" x-model="editForm.country" required
                                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Address *</label>
                                            <input type="text" x-model="editForm.address" required
                                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Province *</label>
                                            <input type="text" x-model="editForm.province" required
                                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1.5">State *</label>
                                            <input type="text" x-model="editForm.state" required
                                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Zip Code *</label>
                                            <input type="text" x-model="editForm.zip_code" required
                                                maxlength="4"
                                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Zone/Street *</label>
                                            <input type="text" x-model="editForm.zone_street" required
                                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Medical Record Number</label>
                                            <input type="text" x-model="editForm.medical_record_number"
                                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-4">
                                    <button type="submit" :disabled="saving"
                                            class="px-6 py-2 bg-teal-600 text-white font-medium rounded-lg hover:bg-teal-700 disabled:opacity-50">
                                        <span x-show="!saving">Save Changes</span>
                                        <span x-show="saving" class="flex items-center gap-2">
                                            <i data-lucide="loader" class="animate-spin h-4 w-4"></i> Saving...
                                        </span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- History Tab -->
                    <div x-show="!isLoading && activeTab === 'history'" class="space-y-4">
                        <div class="mb-4">
                            <input type="text" placeholder="Search appointments..." x-model="searchQuery" 
                                @input="filterAppointments"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-sm">
                        </div>
                        <template x-if="appointmentLoading">
                            <div class="text-center py-20 text-gray-500">
                                <i data-lucide="loader" class="h-10 w-10 animate-spin mx-auto mb-4"></i>
                                Loading appointments...
                            </div>
                        </template>
                        <template x-if="!appointmentLoading && filteredAppointments.length === 0">
                            <p class="text-gray-500 text-center py-10">No appointments found.</p>
                        </template>
                        <div class="space-y-4" x-show="filteredAppointments.length > 0">
                            <template x-for="appointment in filteredAppointments" :key="appointment.id">
                                <div class="bg-white border border-gray-200 rounded-lg p-4 flex justify-between items-center">
                                    <div>
                                        <h5 class="text-sm font-semibold text-gray-900" x-text="appointment.service_name"></h5>
                                        <p class="text-xs text-gray-500" x-text="formatDate(appointment.appointment_date)"></p>
                                    </div>
                                    <span :class="`px-2 py-1 text-xs rounded-full border ${getStatusColor(appointment.status)}`" 
                                        x-text="appointment.status"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>
    @endsection
