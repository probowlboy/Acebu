@extends('layouts.dashboard')

@section('title', 'Manage Appointments')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
     x-data="{
         appointments: [],
         upcomingAppointments: [],
         todayAppointments: [],
         pendingAppointments: [],
         ongoingAppointments: [],
         completedAppointments: [],
         cancelledAppointments: [],
         error: null,
         loading: true,
        isRefreshing: false,
        pollIntervalId: null,
        lastUpdated: null,
         filterStatus: 'pending',
         searchQuery: '',
         pastelColors: [
             { bg: 'bg-indigo-100', text: 'text-indigo-700' },
             { bg: 'bg-pink-100', text: 'text-pink-700' },
             { bg: 'bg-emerald-100', text: 'text-emerald-700' },
             { bg: 'bg-amber-100', text: 'text-amber-700' },
             { bg: 'bg-blue-100', text: 'text-blue-700' },
             { bg: 'bg-purple-100', text: 'text-purple-700' },
             { bg: 'bg-rose-100', text: 'text-rose-700' },
             { bg: 'bg-cyan-100', text: 'text-cyan-700' }
         ],
         services: [],
         _servicesFetchAttempted: false,
         async fetchServices() {
             try {
                 if (this._servicesFetchAttempted) return;
                 this._servicesFetchAttempted = true;
                 const token = localStorage.getItem('token');
                 if (!token) return;
                 const response = await window.apiUtils.fetch('{{ url('/api/admin/services') }}', {
                     method: 'GET',
                     headers: {
                         'Authorization': `Bearer ${token}`,
                         'Accept': 'application/json'
                     },
                     credentials: 'include'
                 });
                 if (response && response.ok) {
                     const data = await response.json();
                     this.services = Array.isArray(data) ? data : (data.services || []);
                 }
             } catch (e) {
                 console.error('Error fetching services:', e);
             }
         },
         getAvatarColor(id) {
             return this.pastelColors[id % this.pastelColors.length];
         },
         async init() {
             // Check if user is patient and redirect
             const isAdmin = localStorage.getItem('isAdmin') === 'true';
             if (!isAdmin) {
                 window.location.href = '/patient/patientdashboard';
                 return;
             }

             // Fetch both appointments and services so we can compute estimates from names
            await Promise.all([this.fetchAppointments(), this.fetchServices()]);
            try {
                // Periodic refresh to keep appointments dashboard in sync
                // Poll less frequently to reduce server load, pause when page is hidden
                const startPolling = () => {
                    if (this.pollIntervalId) clearInterval(this.pollIntervalId);
                    this.pollIntervalId = setInterval(() => {
                        if (!document.hidden) this.fetchAppointments();
                    }, 120000); // Increased to 2 minutes to reduce lag
                };
                const stopPolling = () => {
                    if (this.pollIntervalId) {
                        clearInterval(this.pollIntervalId);
                        this.pollIntervalId = null;
                    }
                };
                startPolling();
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) stopPolling();
                    else startPolling();
                });
                const self = this;
                window.addEventListener('beforeunload', function() { stopPolling.call(self); });
            } catch (e) { console.error('Failed to start appointments polling', e); }
         },
         async fetchAppointments() {
             try {
                 this.loading = true;
                 const token = localStorage.getItem('token');
                 
                 if (!token) {
                     window.location.href = '{{ route('admin.login') }}';
                     return;
                 }
                 
                 const response = await window.apiUtils.fetch('{{ url('/api/admin/appointments') }}', {
                     method: 'GET',
                     headers: {
                         'Authorization': `Bearer ${token}`,
                         'Accept': 'application/json'
                     },
                     credentials: 'include'
                 });
                if (!response) {
                    throw new Error('Network error. Failed to fetch appointments');
                }
                if (response.status === 401) {
                    // Unauthenticated - redirect to admin login
                    localStorage.removeItem('token');
                    window.location.href = '{{ route('admin.login') }}';
                    return;
                }
                if (!response.ok) {
                    throw new Error('Failed to fetch appointments');
                }
                 
                const data = await response.json();
                this.appointments = Array.isArray(data) ? data : (data.appointments || []);
                
                const today = this.localIsoDate();
                
                // Upcoming should only include CONFIRMED future appointments (exclude pending)
                this.upcomingAppointments = this.appointments.filter(app => {
                    const appDate = app.appointment_date ? app.appointment_date.split('T')[0] : '';
                    return app.status === 'confirmed' && appDate > today;
                });
                
                this.todayAppointments = this.appointments.filter(app => {
                    const appDate = app.appointment_date ? app.appointment_date.split('T')[0] : '';
                    return (app.status === 'confirmed' || app.status === 'pending') && appDate === today;
                });
                 
                 this.pendingAppointments = this.appointments.filter(app => 
                     app.status === 'pending'
                 );
                 this.ongoingAppointments = this.appointments.filter(app => 
                     app.status === 'confirmed'
                 );
                 this.completedAppointments = this.appointments.filter(app => 
                     app.status === 'completed'
                 );
                 this.cancelledAppointments = this.appointments.filter(app => 
                     app.status === 'cancelled'
                 );
                 
                 this.error = null;
                this.lastUpdated = new Date();
                 
             } catch (error) {
                 if (error && error.name !== 'AbortError') {
                     console.error('Error fetching appointments:', error);
                     this.error = error.message || 'Failed to load appointments';
                 }
             } finally {
                 this.loading = false;
             }
         },
         filterAppointments() {
             window.apiUtils.debounce('appointmentSearch', () => {
                 // No-op body: updating `searchQuery` is sufficient for Alpine to re-evaluate templates.
                 // Reset paging back to first page when search or filter changes
                 this.currentPage = 1;
             }, 300);
         },
        currentPage: 1,
        // Set to a large number so all appointments display on a single page (replaces pages with scrollless view)
        itemsPerPage: 1000,
         totalFiltered() {
             return this.getFilteredAppointments().length;
         },
         totalPages() {
             return Math.max(1, Math.ceil(this.totalFiltered() / this.itemsPerPage));
         },
         getDisplayAppointments() {
             const items = this.getFilteredAppointments();
             const start = (this.currentPage - 1) * this.itemsPerPage;
             return items.slice(start, start + this.itemsPerPage);
         },
         getFilteredAppointments() {
             const q = this.searchQuery ? this.searchQuery.trim().toLowerCase() : '';

             const match = (app) => {
                 if (!q) return true;
                 const name = (app.patient?.name || '').toLowerCase();
                 const email = (app.patient?.email || '').toLowerCase();
                 const id = String(app.patient?.id || '');
                 const service = (app.service?.name || '').toLowerCase();
                 const date = (app.appointment_date || '').toLowerCase();
                 return name.includes(q) || email.includes(q) || id.includes(q) || service.includes(q) || date.includes(q);
             };

             if (this.filterStatus === 'all') {
                 return this.appointments.filter(match);
             }
             if (this.filterStatus === 'upcoming') {
                 return this.upcomingAppointments.filter(match);
             }
             if (this.filterStatus === 'today') {
                 return this.todayAppointments.filter(match);
             }
             if (this.filterStatus === 'completed') {
                 return this.completedAppointments.filter(match);
             }
            if (this.filterStatus === 'pending') {
                return this.pendingAppointments.filter(match);
            }
            if (this.filterStatus === 'ongoing') {
                return this.ongoingAppointments.filter(match);
            }
             if (this.filterStatus === 'cancelled') {
                 return this.cancelledAppointments.filter(match);
             }
             return [];
         },
        formatDate(dateString) {
            if (!dateString) return 'N/A';
           const d = this.parseLocalISO(dateString);
           if (!d) return 'N/A';
           return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        },
        async refreshAppointments() {
            try {
                this.isRefreshing = true;
                this.error = null;

                const token = localStorage.getItem('token');
                if (!token) {
                    window.location.href = '{{ route('admin.login') }}';
                    return;
                }

                const response = await window.apiUtils.fetch('{{ url('/api/admin/appointments/refresh') }}', {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    credentials: 'include'
                });

                if (!response) {
                    throw new Error('Network error. Failed to refresh appointments');
                }
                if (response.status === 401) {
                    localStorage.removeItem('token');
                    window.location.href = '{{ route('admin.login') }}';
                    return;
                }
                if (!response.ok) {
                    let error = null;
                    try { error = await response.json(); } catch(e) { }
                    throw new Error((error && error.message) ? error.message : 'Failed to refresh appointments');
                }

                const data = await response.json();
                this.appointments = Array.isArray(data) ? data : (data.appointments || []);

                const today = this.localIsoDate();
                this.upcomingAppointments = this.appointments.filter(app => {
                    const appDate = app.appointment_date ? app.appointment_date.split('T')[0] : '';
                    return app.status === 'confirmed' && appDate > today;
                });
                this.todayAppointments = this.appointments.filter(app => {
                    const appDate = app.appointment_date ? app.appointment_date.split('T')[0] : '';
                    return (app.status === 'confirmed' || app.status === 'pending') && appDate === today;
                });
                this.pendingAppointments = this.appointments.filter(app => app.status === 'pending');
                this.ongoingAppointments = this.appointments.filter(app => app.status === 'confirmed');
                this.completedAppointments = this.appointments.filter(app => app.status === 'completed');
                this.cancelledAppointments = this.appointments.filter(app => app.status === 'cancelled');

                this.error = null;
                this.lastUpdated = new Date();
            } catch (error) {
                if (error && error.name !== 'AbortError') {
                    console.error('Error refreshing appointments:', error);
                    this.error = error.message || 'Failed to refresh appointments';
                }
            } finally {
                this.isRefreshing = false;
            }
        },
         formatTime(dateString) {
             if (!dateString) return 'N/A';
            const d = this.parseLocalISO(dateString);
            if (!d) return 'N/A';
            return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
         },
        localIsoDate() {
            const d = new Date();
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
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
        formatPrice(price) {
            if (!price && price !== 0) return '-';
            try {
                const n = parseFloat(price);
                if (Number.isNaN(n)) return '-';
                return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
            } catch (e) { return '-'; }
        },
        formatAppointmentPrice(appointment) {
            try {
                if (!appointment) return `${this.formatPrice(0)} (estimated)`;

                // If there is a persisted, authoritative total and it's not marked estimated, show it
                if (appointment.total_price !== undefined && appointment.total_price !== null && !appointment.price_is_estimated) {
                    return this.formatPrice(appointment.total_price);
                }

                // Compute sum from services if available
                let sum = 0;
                if (Array.isArray(appointment.services) && appointment.services.length > 0) {
                    sum = appointment.services.reduce((s, svc) => {
                        const p = parseFloat(svc?.price ?? 0);
                        return s + (Number.isNaN(p) ? 0 : p);
                    }, 0);
                } else if (appointment.service && appointment.service.price !== undefined && appointment.service.price !== null) {
                    const p = parseFloat(appointment.service.price);
                    if (!Number.isNaN(p)) sum = p;
                } else if (appointment.total_price !== undefined && appointment.total_price !== null) {
                    const p = parseFloat(appointment.total_price);
                    if (!Number.isNaN(p)) sum = p;
                }

                // If appointment uses comma-separated service_name and we have no services yet, try fetching services once
                if ((typeof appointment.service_name === 'string' && appointment.service_name.includes(',')) && this.services.length === 0 && !this._servicesFetchAttempted) {
                    this.fetchServices();
                }

                const formatted = this.formatPrice(sum);
                const isEstimated = !!appointment.price_is_estimated || appointment.total_price === undefined || appointment.total_price === null || Number(appointment.total_price) !== sum;
                return isEstimated ? `${formatted} (estimated)` : formatted;
            } catch (e) { return `${this.formatPrice(0)} (estimated)`; }
        },

        // Sum the prices of the currently displayed appointments
        totalPrice() {
            try {
                const items = this.getFilteredAppointments();
                return items.reduce((sum, a) => {
                    const p = parseFloat(a.service?.price ?? a.price ?? 0);
                    return sum + (Number.isNaN(p) ? 0 : p);
                }, 0);
            } catch (e) { return 0; }
        },
        // Admin modal & helper state
        showStatusModal: false,
        statusAppointmentPendingId: null,
        statusAppointmentPendingTitle: '',
        statusAppointmentPendingNewStatus: null,
        isUpdatingStatus: false,
        showAdminSuccessModal: false,
        adminSuccessMessage: '',
        getStatusColor(status) {
             if (status === 'confirmed') return 'bg-green-100 text-green-800';
             if (status === 'pending') return 'bg-amber-100 text-amber-800';
             if (status === 'cancelled') return 'bg-red-100 text-red-800';
             if (status === 'completed') return 'bg-blue-100 text-blue-800';
             return 'bg-gray-100 text-gray-800';
         },
        readableStatusLabel(status) {
            if (!status) return '';
            if (status === 'confirmed') return 'confirm';
            if (status === 'pending') return 'set to pending';
            if (status === 'cancelled') return 'cancel';
            if (status === 'completed') return 'mark as completed';
            return status;
        },
        capitalize(s) {
            if (!s) return '';
            return s.charAt(0).toUpperCase() + s.slice(1);
        },
        async updateStatus(appointmentId, newStatus) {
            const appointment = this.appointments.find(a => a.id === appointmentId);
            this.statusAppointmentPendingId = appointmentId;
            this.statusAppointmentPendingTitle = appointment ? (appointment.service_name || appointment.patient?.name || 'Appointment') : 'Appointment';
            this.statusAppointmentPendingNewStatus = newStatus;
            // Quick confirm for 'confirmed', 'completed', and 'cancelled' statuses: perform update directly without showing modal
            if (newStatus === 'confirmed' || newStatus === 'completed' || newStatus === 'cancelled') {
                // Immediately perform confirmation (cancellation will call the admin cancel route)
                await this.confirmUpdateStatus();
                return;
            }
            this.showStatusModal = true;
        },
        async confirmUpdateStatus() {
            const appointmentId = this.statusAppointmentPendingId;
            const newStatus = this.statusAppointmentPendingNewStatus;
            if (!appointmentId || !newStatus) return;
            this.isUpdatingStatus = true;
            try {
                const token = localStorage.getItem('token');
                // When cancelling from admin, call the dedicated cancel endpoint to match patient behavior
                const endpoint = newStatus === 'cancelled' ? `{{ url('/api/admin/appointments') }}/${appointmentId}/cancel` : `{{ url('/api/admin/appointments') }}/${appointmentId}/status`;
                const method = newStatus === 'cancelled' ? 'POST' : 'PUT';
                const response = await window.apiUtils.fetch(endpoint, {
                    method: method,
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    credentials: 'include',
                    body: newStatus === 'cancelled' ? undefined : JSON.stringify({ status: newStatus })
                });
                if (!response) {
                    throw new Error('Network error. Please try again.');
                }
                if (!response.ok) {
                    let error = null;
                    try { error = await response.json(); } catch(e) { /* ignore parse error */ }
                    throw new Error((error && error.message) ? error.message : 'Failed to update appointment status');
                }

                // Optimistic UI update: set status locally first
                try {
                    const localApp = this.appointments.find(a => a.id === appointmentId);
                    if (localApp) {
                        localApp.status = newStatus;
                    }
                } catch (e) { }
                await this.fetchAppointments();
                this.showStatusModal = false;
                this.statusAppointmentPendingId = null;
                this.statusAppointmentPendingNewStatus = null;
                this.isUpdatingStatus = false;
                this.adminSuccessMessage = `Appointment ${this.capitalize(newStatus)} successfully`;
                this.showAdminSuccessModal = true;
            } catch (error) {
                if (error && error.name !== 'AbortError') {
                    console.error('Error updating appointment status:', error);
                    this.error = error.message || 'Failed to update appointment status. Please try again.';
                }
            } finally {
                this.isUpdatingStatus = false;
            }
        },
     }">
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Manage Appointments</h1>
                <p class="text-sm text-gray-500 mt-1">View and manage all patient appointments</p>
            </div>
            
            <div class="flex items-center space-x-3">
                <button @click="refreshAppointments()" :disabled="isRefreshing" :class="isRefreshing ? 'opacity-50 cursor-not-allowed px-3 py-2 rounded-md bg-white border border-gray-200 text-sm text-gray-600' : 'px-3 py-2 rounded-md bg-white border border-gray-200 text-sm text-gray-600 hover:bg-gray-50'">
                    <template x-if="!isRefreshing">Refresh</template>
                    <template x-if="isRefreshing"><i data-lucide="loader" class="h-4 w-4 animate-spin mr-2 inline-block"></i>Refreshing…</template>
                </button>
            </div>
        </div>

        <!-- Error Message -->
        <div x-show="error" x-cloak 
             class="bg-red-50 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline" x-text="error"></span>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-600 uppercase tracking-wide">Total Appointments</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2" x-text="!loading ? appointments.length : 0"></p>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-3">
                        <i data-lucide="calendar" class="h-6 w-6 text-blue-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-600 uppercase tracking-wide">Today's Appointments</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2" x-text="!loading ? todayAppointments.length : 0"></p>
                    </div>
                    <div class="bg-green-50 rounded-lg p-3">
                        <i data-lucide="calendar-check" class="h-6 w-6 text-green-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-600 uppercase tracking-wide">Completed</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2" x-text="!loading ? completedAppointments.length : 0"></p>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-3">
                        <i data-lucide="check-circle" class="h-6 w-6 text-purple-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Tabs with Search on the right -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-100">
            <div class="flex items-center justify-between border-b border-gray-200 px-6">
                <div class="flex">
                    <!-- 'All' removed per request -->
                    <button @click="filterStatus = 'today'"
                            :class="filterStatus === 'today' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600 hover:text-gray-900'"
                            class="px-4 py-4 font-medium text-sm transition-colors">
                        Today (<span x-text="!loading ? todayAppointments.length : 0"></span>)
                    </button>
                    <button @click="filterStatus = 'pending'"
                            :class="filterStatus === 'pending' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600 hover:text-gray-900'"
                            class="px-4 py-4 font-medium text-sm transition-colors">
                        Pending (<span x-text="!loading ? pendingAppointments.length : 0"></span>)
                    </button>
                    <!-- Ongoing filter removed per request -->
                    <button @click="filterStatus = 'upcoming'"
                            :class="filterStatus === 'upcoming' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600 hover:text-gray-900'"
                            class="px-4 py-4 font-medium text-sm transition-colors">
                        Upcoming (<span x-text="!loading ? upcomingAppointments.length : 0"></span>)
                    </button>
                    <button @click="filterStatus = 'completed'"
                            :class="filterStatus === 'completed' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600 hover:text-gray-900'"
                            class="px-4 py-4 font-medium text-sm transition-colors">
                        Completed (<span x-text="!loading ? completedAppointments.length : 0"></span>)
                    </button>
                    <button @click="filterStatus = 'cancelled'"
                            :class="filterStatus === 'cancelled' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600 hover:text-gray-900'"
                            class="px-4 py-4 font-medium text-sm transition-colors">
                        Cancelled (<span x-text="!loading ? cancelledAppointments.length : 0"></span>)
                    </button>
                </div>

                <!-- Search positioned to the right of the tabs -->
                <div class="ml-4 w-80">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i data-lucide="search" class="h-5 w-5 text-gray-400"></i>
                        </div>
                        <input type="text"
                            x-model="searchQuery"
                            @input.debounce.300ms="filterAppointments()"
                            placeholder="Search appointments..."
                            class="block w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm transition-colors">
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div x-show="loading" x-cloak class="text-center py-12">
            <i data-lucide="loader" class="h-8 w-8 animate-spin text-blue-600 mx-auto mb-4"></i>
            <p class="text-gray-600">Loading appointments...</p>
        </div>

        <!-- Empty State -->
        <div x-show="false" x-cloak
             class="bg-white rounded-lg shadow-sm p-12 text-center border border-gray-100">
            <i data-lucide="calendar-x" class="h-16 w-16 text-gray-400 mx-auto mb-4"></i>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">No appointments found</h3>
            <p class="text-gray-600">There are no appointments matching the selected filter.</p>
        </div>

        <!-- Appointments List - Table Design (Copied from provided design) -->
        <div x-show="!loading" x-cloak>
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-4 py-4">
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-fixed divide-y divide-gray-200 text-xs">
                            <thead class="bg-white">
                                <tr>
                                    <th scope="col" class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider border-r border-gray-200 last:border-r-0" style="width:8%;">ID</th>
                                    <th scope="col" class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider border-r border-gray-200 last:border-r-0" style="width:18%;">Patient</th>
                                    <th scope="col" class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider border-r border-gray-200 last:border-r-0" style="width:16%;">Email</th>
                                    <th scope="col" class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider border-r border-gray-200 last:border-r-0" style="width:16%;">Date & Time</th>
                                    <th scope="col" class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider border-r border-gray-200 last:border-r-0" style="width:16%;">Service</th>
                                    <th scope="col" class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider border-r border-gray-200 last:border-r-0" style="width:8%;">Price</th>
                                    <th scope="col" class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider border-r border-gray-200 last:border-r-0" style="width:12%;">Status</th>
                                    <th scope="col" class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider" style="width:12%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <template x-if="getDisplayAppointments().length === 0">
                                    <tr><td class="px-3 py-3 text-center text-sm text-gray-500" colspan="8">No appointments found for this filter.</td></tr>
                                </template>
                                <template x-for="appointment in getDisplayAppointments()" :key="appointment.id">
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-500 border-r border-gray-200 last:border-r-0" style="width: 8%;">
                                            <span class="font-medium text-gray-900">#APT-<span x-text="String(appointment.id).padStart(3, '0')"></span></span>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap border-r border-gray-200 last:border-r-0" style="width: 18%;">
                                            <div class="text-xs font-semibold text-gray-900 truncate" x-text="appointment.patient?.name || 'Unknown'"></div>
                                            <div class="text-xs text-gray-500 mt-1">ID: <span class="font-medium" x-text="appointment.patient?.id || 'N/A'"></span></div>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-xs text-blue-600 truncate border-r border-gray-200 last:border-r-0" style="width: 16%;">
                                            <a class="text-blue-600 hover:underline truncate block" :href="`mailto:${appointment.patient?.email || ''}`" x-text="appointment.patient?.email || 'N/A'"></a>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-500 border-r border-gray-200 last:border-r-0" style="width: 16%;">
                                            <div class="font-semibold text-gray-900 text-xs" x-text="formatDate(appointment.appointment_date)"></div>
                                            <div class="text-xs text-gray-500 mt-1" x-text="formatTime(appointment.appointment_date)"></div>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-900 truncate border-r border-gray-200 last:border-r-0" style="width: 16%;" x-text="appointment.service_name || (appointment.service?.name || '-')"></td>
                                        <td class="px-3 py-3 whitespace-nowrap text-sm font-semibold text-gray-900 text-right border-r border-gray-200 last:border-r-0" style="width: 8%;" x-text="formatAppointmentPrice(appointment)"></td>
                                        <td class="px-3 py-3 whitespace-nowrap border-r border-gray-200 last:border-r-0" style="width: 12%;">
                                            <span :class="getStatusColor(appointment.status) + ' inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold capitalize'" x-text="capitalize(appointment.status)"></span>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-right" style="width: 12%;">
                                            <div class="flex items-center justify-end gap-2">
                                                <!-- Confirm button: solid green with white text -->
                                                <button @click="updateStatus(appointment.id, 'confirmed')"
                                                        x-show="appointment.status === 'pending'"
                                                        :disabled="isUpdatingStatus && statusAppointmentPendingId === appointment.id"
                                                        :aria-disabled="isUpdatingStatus && statusAppointmentPendingId === appointment.id ? 'true' : 'false'"
                                                        :class="(isUpdatingStatus && statusAppointmentPendingId === appointment.id) ? 'opacity-50 cursor-not-allowed px-2 py-1 rounded-md bg-emerald-600 text-white text-xs font-semibold' : 'px-2 py-1 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-emerald-200'">
                                                    <span x-show="!(isUpdatingStatus && statusAppointmentPendingId === appointment.id)">Confirm</span>
                                                    <span x-show="isUpdatingStatus && statusAppointmentPendingId === appointment.id">Updating&hellip;</span>
                                                </button>
                                                <button @click="updateStatus(appointment.id, 'completed')" x-show="appointment.status === 'confirmed'"
                                                        :disabled="isUpdatingStatus && statusAppointmentPendingId === appointment.id"
                                                        :aria-disabled="isUpdatingStatus && statusAppointmentPendingId === appointment.id ? 'true' : 'false'"
                                                        :class="(isUpdatingStatus && statusAppointmentPendingId === appointment.id) ? 'opacity-50 cursor-not-allowed px-2 py-1 rounded-md bg-blue-600 text-white text-xs font-semibold' : 'px-2 py-1 rounded-md bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold'">
                                                    <span x-show="!(isUpdatingStatus && statusAppointmentPendingId === appointment.id)">Complete</span>
                                                    <span x-show="isUpdatingStatus && statusAppointmentPendingId === appointment.id">Updating&hellip;</span>
                                                </button>
                                                <button @click="updateStatus(appointment.id, 'cancelled')" x-show="appointment.status !== 'cancelled' && appointment.status !== 'completed'"
                                                        :disabled="isUpdatingStatus && statusAppointmentPendingId === appointment.id"
                                                        :aria-disabled="isUpdatingStatus && statusAppointmentPendingId === appointment.id ? 'true' : 'false'"
                                                        :class="(isUpdatingStatus && statusAppointmentPendingId === appointment.id) ? 'opacity-50 cursor-not-allowed px-2 py-1 rounded-md bg-red-600 text-white text-xs font-semibold' : 'px-2 py-1 rounded-md bg-red-600 hover:bg-red-700 text-white text-xs font-semibold'">
                                                    <span x-show="!(isUpdatingStatus && statusAppointmentPendingId === appointment.id)">Cancel</span>
                                                    <span x-show="isUpdatingStatus && statusAppointmentPendingId === appointment.id">Updating&hellip;</span>
                                                </button>
                                                <span x-show="appointment.status === 'completed' || appointment.status === 'cancelled'" class="text-xs text-gray-400">No actions</span>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer with showing count and pagination -->
                <div class="px-3 py-2 bg-white border-t border-gray-100 flex items-center justify-between text-xs text-gray-700">
                    <div>
                        Showing <span x-text="(getDisplayAppointments().length > 0 ? ((currentPage - 1) * itemsPerPage + 1) : 0)"></span> to <span x-text="((currentPage - 1) * itemsPerPage + getDisplayAppointments().length)"></span> of <span x-text="totalFiltered()"></span> entries
                    </div>

                    <div class="flex items-center gap-2">
                        <button @click="if(currentPage > 1) currentPage--" :class="currentPage === 1 ? 'opacity-50 cursor-not-allowed' : ''" class="px-2 py-0.5 rounded-md border border-gray-200 bg-white text-gray-600 text-xs">Previous</button>
                        <template x-for="p in Array.from({length: totalPages()}, (_, i) => i + 1)" :key="p">
                            <button @click="currentPage = p" :class="currentPage === p ? 'bg-blue-600 text-white' : 'bg-white text-gray-700'" class="px-2 py-0.5 rounded-md border border-gray-200 text-xs"> <span x-text="p"></span> </button>
                        </template>
                        <button @click="if(currentPage < totalPages()) currentPage++" :class="currentPage === totalPages() ? 'opacity-50 cursor-not-allowed' : ''" class="px-2 py-0.5 rounded-md border border-gray-200 bg-white text-gray-600 text-xs">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

        
    <!-- Admin Status Change Confirmation Modal -->
    <div x-show="typeof showStatusModal !== 'undefined' && showStatusModal" x-cloak @keydown.escape.window="(showStatusModal = false, statusAppointmentPendingId = null)" @click.self="(showStatusModal = false, statusAppointmentPendingId = null)"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-40">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg max-w-md w-full p-6 relative">
            <button type="button" @click="(showStatusModal = false, statusAppointmentPendingId = null)" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 transition">×</button>
            <div class="flex items-center gap-4">
                <div class="flex-shrink-0 rounded-full bg-gray-100 p-3">
                    <i data-lucide="alert-triangle" class="h-6 w-6 text-red-600"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Confirm status change</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Are you sure you want to <span class="font-medium" x-text="typeof readableStatusLabel === 'function' ? readableStatusLabel(statusAppointmentPendingNewStatus) : ''"></span> the appointment for <span class="font-medium" x-text="typeof statusAppointmentPendingTitle !== 'undefined' ? statusAppointmentPendingTitle : ''"></span>?
                    </p>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="(showStatusModal = false, statusAppointmentPendingId = null)" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium">Close</button>
                    <button type="button" @click="confirmUpdateStatus()" :disabled="typeof isUpdatingStatus !== 'undefined' ? isUpdatingStatus : false" class="px-4 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700 font-medium">
                        <span x-show="typeof isUpdatingStatus !== 'undefined' && !isUpdatingStatus" x-text="typeof readableStatusLabel === 'function' ? (typeof capitalize === 'function' ? capitalize(readableStatusLabel(statusAppointmentPendingNewStatus)) : readableStatusLabel(statusAppointmentPendingNewStatus)) : ''"></span>
                        <span x-show="typeof isUpdatingStatus !== 'undefined' && isUpdatingStatus">Updating...</span>
                    </button>
            </div>
        </div>
    </div>

    <!-- Admin Success Modal -->
    <div x-show="typeof showAdminSuccessModal !== 'undefined' && showAdminSuccessModal" x-cloak @keydown.escape.window="(showAdminSuccessModal = false, adminSuccessMessage = '')" @click.self="(showAdminSuccessModal = false, adminSuccessMessage = '')"
         class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-black bg-opacity-30">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg max-w-sm w-full p-6">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 rounded-full bg-green-100 p-3">
                    <i data-lucide="check-circle" class="h-6 w-6 text-green-600"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Success</h3>
                    <p class="text-sm text-gray-500 mt-1" x-text="typeof adminSuccessMessage !== 'undefined' ? adminSuccessMessage : ''"></p>
                </div>
            </div>
            <div class="mt-6 text-right">
                <button type="button" @click="(showAdminSuccessModal = false, adminSuccessMessage = '')" class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 font-medium">Close</button>
            </div>
        </div>
    </div>

<script>
    lucide.createIcons();
</script>
@endsection

