@extends('layouts.dashboard')

@section('title', 'Appointment History')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
     x-data="{
         userData: null,
         appointmentHistory: [],
         completedAppointments: [],
         cancelledAppointments: [],
         searchQuery: '',
         currentPage: 1,
         itemsPerPage: 1000,
         error: null,
         loading: true,
         filterStatus: 'all',
         async init() {
             // Check if user is patient and redirect
             const isAdmin = localStorage.getItem('isAdmin') === 'true';
             if (!isAdmin) {
                 window.location.href = '/patient/patientdashboard';
                 return;
             }

             await Promise.all([this.fetchHistory(), this.fetchServices()]);
         },
         async fetchHistory() {
             try {
                 this.loading = true;
                 const token = localStorage.getItem('token');
                 
                 if (!token) {
                     window.location.href = '{{ route('admin.login') }}';
                     return;
                 }
                 
                 const response = await fetch('{{ url('/api/admin/appointments') }}', {
                     headers: {
                         'Authorization': `Bearer ${token}`,
                         'Accept': 'application/json'
                     },
                     credentials: 'include'
                 });
                 
                if (response && response.status === 401) {
                    localStorage.removeItem('token');
                    localStorage.removeItem('isAdmin');
                    window.location.href = '{{ route('admin.login') }}';
                    return;
                }
                 
                 if (!response.ok) {
                     throw new Error('Failed to fetch appointment history');
                 }
                 
                 let fetched = await response.json();
                 fetched = Array.isArray(fetched) ? fetched : (fetched.appointments || []);

                // Filter completed and cancelled for history and sort by date
                this.appointmentHistory = (fetched || [])
                    .filter(app => app.status === 'completed' || app.status === 'cancelled')
                    .sort((a, b) => {
                        const da = this.parseLocalISO(a.appointment_date) || new Date(0);
                        const db = this.parseLocalISO(b.appointment_date) || new Date(0);
                        return db - da;
                    });

                // Populate completed / cancelled lists for stats
                this.completedAppointments = this.appointmentHistory.filter(app => app.status === 'completed');
                this.cancelledAppointments = this.appointmentHistory.filter(app => app.status === 'cancelled');
                 
                 this.error = null;
                 
             } catch (error) {
                 console.error('Error fetching history:', error);
                 this.error = error.message || 'Failed to load appointment history';
             } finally {
                 this.loading = false;
             }
         },
         totalFiltered() {
             return this.getFilteredHistory().length;
         },
         totalPages() {
             return Math.max(1, Math.ceil(this.totalFiltered() / this.itemsPerPage));
         },
         getDisplayHistory() {
             const items = this.getFilteredHistory();
             const start = (this.currentPage - 1) * this.itemsPerPage;
             return items.slice(start, start + this.itemsPerPage);
         },
         getFilteredHistory() {
             const q = this.searchQuery ? this.searchQuery.trim().toLowerCase() : '';
             const match = (app) => {
                 if (!q) return true;
                 const service = (app.service_name || app.service?.name || '').toLowerCase();
                 const date = (app.appointment_date || '').toLowerCase();
                 const notes = (app.notes || '').toLowerCase();
                 return service.includes(q) || date.includes(q) || notes.includes(q);
             };
             if (this.filterStatus === 'all') return this.appointmentHistory.filter(match);
             return this.appointmentHistory.filter(app => app.status === this.filterStatus).filter(match);
         },
         formatDate(dateString) {
             const d = this.parseLocalISO(dateString);
             if (!d) return '';
             return d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
         },
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
         formatTime(dateString) {
             const d = this.parseLocalISO(dateString);
             if (!d) return '';
             return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
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

                if (appointment.total_price !== undefined && appointment.total_price !== null && !appointment.price_is_estimated) {
                    return this.formatPrice(appointment.total_price);
                }

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

                const formatted = this.formatPrice(sum);
                const isEstimated = !!appointment.price_is_estimated || appointment.total_price === undefined || appointment.total_price === null || Number(appointment.total_price) !== sum;
                return isEstimated ? `${formatted} (estimated)` : formatted;
            } catch (e) { return `${this.formatPrice(0)} (estimated)`; }
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
        // local YYYY-MM-DD (browser local time)
        localIsoDate() {
            const d = new Date();
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        },
         getStatusColor(status) {
             if (status === 'completed') return 'bg-green-100 text-green-800';
             if (status === 'cancelled') return 'bg-red-100 text-red-800';
             return 'bg-gray-100 text-gray-800';
         },
        displayStatus(status) {
            if (!status) return '';
            if (status === 'confirmed') return 'Pending';
            return status.charAt(0).toUpperCase() + status.slice(1);
        },
        capitalize(s) {
            if (!s) return '';
            return s.charAt(0).toUpperCase() + s.slice(1);
        }
     }">
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Appointment History</h1>
                <p class="text-gray-600">View all completed and cancelled appointments</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" 
               class="flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                <i data-lucide="arrow-left" class="h-4 w-4 mr-2"></i>
                Back to Dashboard
            </a>
        </div>

        <!-- Error Message -->
        <div x-show="error" x-cloak 
             class="bg-red-50 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline" x-text="error"></span>
        </div>

        <!-- Filter Tabs with Search on right (patient-style) -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-100">
            <div class="flex items-center justify-between border-b border-gray-200 px-6">
                <div class="flex">
                    <button @click="filterStatus = 'all'"
                            :class="filterStatus === 'all' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600 hover:text-gray-900'"
                            class="px-4 py-4 font-medium text-sm transition-colors">
                        All (<span x-text="!loading ? appointmentHistory.length : 0"></span>)
                    </button>
                    <button @click="filterStatus = 'completed'"
                            :class="filterStatus === 'completed' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600 hover:text-gray-900'"
                            class="px-4 py-4 font-medium text-sm transition-colors">
                        Completed (<span x-text="!loading ? appointmentHistory.filter(a => a.status === 'completed').length : 0"></span>)
                    </button>
                    <button @click="filterStatus = 'cancelled'"
                            :class="filterStatus === 'cancelled' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600 hover:text-gray-900'"
                            class="px-4 py-4 font-medium text-sm transition-colors">
                        Cancelled (<span x-text="!loading ? appointmentHistory.filter(a => a.status === 'cancelled').length : 0"></span>)
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
                            @input.debounce.300ms="(function(){ currentPage = 1; })()"
                            placeholder="Search appointments..."
                            class="block w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm transition-colors">
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div x-show="loading" x-cloak class="text-center py-12">
            <i data-lucide="loader" class="h-8 w-8 animate-spin text-blue-600 mx-auto mb-4"></i>
            <p class="text-gray-600">Loading appointment history...</p>
        </div>

        <!-- Empty State -->
        <div x-show="false" x-cloak
             class="bg-white rounded-xl shadow-sm p-12 text-center">
            <i data-lucide="calendar-x" class="h-16 w-16 text-gray-400 mx-auto mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No appointment history found</h3>
            <p class="text-gray-600 mb-6">You don't have any <span x-text="filterStatus === 'all' ? '' : filterStatus"></span> appointments yet.</p>
            <a href="{{ route('admin.appointments') }}" 
               class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                <i data-lucide="calendar-plus" class="h-4 w-4 mr-2"></i>
                View Appointments
            </a>
        </div>

        <!-- Appointment History Table (patient-style) -->
        <div x-show="!loading" x-cloak>
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-4 py-4">
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-fixed divide-y divide-gray-200 text-xs">
                            <thead class="bg-white">
                                <tr>
                                    <th scope="col" class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider border-r border-gray-200 last:border-r-0" style="width:8%">ID</th>
                                    <th scope="col" class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider border-r border-gray-200 last:border-r-0" style="width:18%">Patient</th>
                                    <th scope="col" class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider border-r border-gray-200 last:border-r-0" style="width:16%">Email</th>
                                    <th scope="col" class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider border-r border-gray-200 last:border-r-0" style="width:16%">Date &amp; Time</th>
                                    <th scope="col" class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider border-r border-gray-200 last:border-r-0" style="width:16%">Service</th>
                                    <th scope="col" class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider border-r border-gray-200 last:border-r-0" style="width:8%">Price</th>
                                    <th scope="col" class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider border-r border-gray-200 last:border-r-0" style="width:12%">Status</th>
                                    <th scope="col" class="px-3 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider" style="width:12%">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <template x-if="getDisplayHistory().length === 0">
                                    <tr><td class="px-3 py-3 text-center text-sm text-gray-500" colspan="8">No appointments found for this filter.</td></tr>
                                </template>
                                <template x-for="appointment in getDisplayHistory()" :key="appointment.id">
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-500 border-r border-gray-200 last:border-r-0" style="width:8%">
                                            <span class="font-medium text-gray-900">#HIS-<span x-text="String(appointment.id).padStart(3, '0')"></span></span>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap border-r border-gray-200 last:border-r-0" style="width:18%">
                                            <div class="text-xs font-semibold text-gray-900 truncate" x-text="appointment.patient?.name || 'N/A'"></div>
                                            <div class="text-xs text-gray-500 mt-1">ID: <span class="font-medium" x-text="appointment.patient?.id || 'N/A'"></span></div>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-xs text-blue-600 truncate border-r border-gray-200 last:border-r-0" style="width:16%">
                                            <a class="text-blue-600 hover:underline truncate block" :href="`mailto:${appointment.patient?.email || ''}`" x-text="appointment.patient?.email || 'N/A'"></a>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-500 border-r border-gray-200 last:border-r-0" style="width:16%">
                                            <div class="font-semibold text-gray-900 text-xs" x-text="formatDate(appointment.appointment_date)"></div>
                                            <div class="text-xs text-gray-500 mt-1" x-text="formatTime(appointment.appointment_date)"></div>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-900 truncate border-r border-gray-200 last:border-r-0" style="width:16%" x-text="appointment.service_name || (appointment.service?.name || '-')"></td>
                                        <td class="px-3 py-3 whitespace-nowrap text-sm font-semibold text-gray-900 text-right border-r border-gray-200 last:border-r-0" style="width:8%" x-text="formatAppointmentPrice(appointment)"></td>
                                        <td class="px-3 py-3 whitespace-nowrap border-r border-gray-200 last:border-r-0" style="width:12%">
                                            <span :class="getStatusColor(appointment.status) + ' inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold capitalize'" x-text="displayStatus(appointment.status)"></span>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-right" style="width:12%">
                                            <div class="flex items-center justify-end gap-2">
                                                <span class="text-xs text-gray-400">No actions</span>
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
                        Showing <span x-text="(getDisplayHistory().length > 0 ? ((currentPage - 1) * itemsPerPage + 1) : 0)"></span> to <span x-text="((currentPage - 1) * itemsPerPage + getDisplayHistory().length)"></span> of <span x-text="totalFiltered()"></span> entries
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

<script>
    lucide.createIcons();
</script>
@endsection

