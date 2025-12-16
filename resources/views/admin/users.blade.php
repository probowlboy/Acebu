@extends('layouts.dashboard')

@section('title', 'Manage Patients')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6"
    x-data="{
        users: [],
        filteredUsers: [],
        error: null,
        loading: true,
        searchQuery: '',
        sortBy: 'last_appointment',
        selectedUsers: [],
        showUserMenu: null,
        async init() {
            const isAdmin = localStorage.getItem('isAdmin') === 'true';
            if (!isAdmin) {
                window.location.href = '/patient/patientdashboard';
                return;
            }
            await this.fetchUsers();
        },
        async fetchUsers() {
             try {
                 this.loading = true;
                 const token = localStorage.getItem('token');
                 
                 if (!token) {
                     window.location.href = '{{ route('admin.login') }}';
                     return;
                 }
                 
                 const response = await window.apiUtils.fetch('{{ url('/api/admin/patients') }}', {
                     method: 'GET',
                     headers: {
                         'Authorization': `Bearer ${token}`,
                         'Accept': 'application/json'
                     },
                     credentials: 'include'
                 });
                 
                 if (!response || !response.ok) {
                     throw new Error('Failed to fetch users');
                 }
                 
                const data = await response.json();
                this.users = data.patients || [];
                
                // Fetch appointments for each user
                await this.fetchAppointmentsForUsers();
                
                this.applyFilters();
                this.error = null;
                
            } catch (error) {
                 if (error && error.name !== 'AbortError') {
                     console.error('Error fetching users:', error);
                     this.error = error.message || 'Failed to load users';
                 }
             } finally {
                 this.loading = false;
             }
         },
        async fetchAppointmentsForUsers() {
            const token = localStorage.getItem('token');
            const today = new Date();
            
            for (let user of this.users) {
                try {
                    const response = await window.apiUtils.fetch(`{{ url('/api/admin/patients') }}/${user.id}/history`, {
                        method: 'GET',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Accept': 'application/json'
                        },
                        credentials: 'include'
                    });
                    
                    if (response && response.ok) {
                        const data = await response.json();
                        const appointments = data.appointments || [];
                        
                        // Get next appointment (future appointments, sorted by date)
                        const futureAppointments = appointments
                            .filter(apt => {
                                const aptDate = this.parseLocalISO ? this.parseLocalISO(apt.appointment_date) : new Date(apt.appointment_date);
                                return aptDate && aptDate > today && (apt.status === 'confirmed' || apt.status === 'pending');
                            })
                            .sort((a, b) => {
                                const aa = this.parseLocalISO ? this.parseLocalISO(a.appointment_date) : new Date(a.appointment_date);
                                const bb = this.parseLocalISO ? this.parseLocalISO(b.appointment_date) : new Date(b.appointment_date);
                                return aa - bb;
                            });
                        
                        user.next_appointment = futureAppointments.length > 0 ? futureAppointments[0] : null;
                        
                        // Get last appointment (past appointments, sorted by date desc)
                        const pastAppointments = appointments
                            .filter(apt => {
                                const aptDate = this.parseLocalISO ? this.parseLocalISO(apt.appointment_date) : new Date(apt.appointment_date);
                                return aptDate && (aptDate <= today || apt.status === 'completed');
                            })
                            .sort((a, b) => {
                                const aa = this.parseLocalISO ? this.parseLocalISO(a.appointment_date) : new Date(a.appointment_date);
                                const bb = this.parseLocalISO ? this.parseLocalISO(b.appointment_date) : new Date(b.appointment_date);
                                return bb - aa;
                            });
                        
                        user.last_appointment = pastAppointments.length > 0 ? pastAppointments[0] : null;
                    }
                } catch (error) {
                    console.error(`Error fetching appointments for user ${user.id}:`, error);
                    user.next_appointment = null;
                    user.last_appointment = null;
                }
            }
        },
        applyFilters() {
            let filtered = [...this.users];
            
            // Filter by search query
            if (this.searchQuery.trim()) {
                const query = this.searchQuery.toLowerCase();
                filtered = filtered.filter(user => 
                    user.name?.toLowerCase().includes(query) ||
                    user.email?.toLowerCase().includes(query) ||
                    user.phone?.toLowerCase().includes(query) ||
                    user.municipality?.toLowerCase().includes(query)
                );
            }

            
            
            // Sort
            filtered.sort((a, b) => {
                switch(this.sortBy) {
                    case 'last_appointment':
                        const aLast = a.last_appointment ? this.parseLocalISO(a.last_appointment.appointment_date) || new Date(0) : new Date(0);
                        const bLast = b.last_appointment ? this.parseLocalISO(b.last_appointment.appointment_date) || new Date(0) : new Date(0);
                        return bLast - aLast;

                    case 'register_date':
                        return (this.parseLocalISO(b.created_at) || new Date(0)) - (this.parseLocalISO(a.created_at) || new Date(0));
                    case 'name':
                        return (a.name || '').localeCompare(b.name || '');
                    default:
                        return 0;
                }
            });
            
            this.filteredUsers = filtered;
        },
        filterUsers() {
            window.apiUtils.debounce('userSearch', () => {
                this.applyFilters();
            }, 300);
        },
        
        formatDate(dateString) {
            if (!dateString) return '-';
            const date = this.parseLocalISO ? this.parseLocalISO(dateString) : new Date(dateString);
            if (!date) return '-';
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        },
        formatDateTime(dateString) {
            if (!dateString) return '-';
            const date = this.parseLocalISO ? this.parseLocalISO(dateString) : new Date(dateString);
            const dateStr = date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
            const timeStr = date.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });
            return `${dateStr} - ${timeStr}`;
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
        getInitials(name) {
            if (!name) return '??';
            const parts = name.trim().split(' ');
            if (parts.length >= 2) {
                return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
            }
            return name.substring(0, 2).toUpperCase();
        },
        toggleSelectAll(event) {
            if (event.target.checked) {
                this.selectedUsers = this.filteredUsers.map(u => u.id);
            } else {
                this.selectedUsers = [];
            }
        },
        toggleSelectUser(userId) {
            const index = this.selectedUsers.indexOf(userId);
            if (index > -1) {
                this.selectedUsers.splice(index, 1);
            } else {
                this.selectedUsers.push(userId);
            }
        },
        isSelected(userId) {
            return this.selectedUsers.includes(userId);
        },
        toggleUserMenu(userId) {
            this.showUserMenu = this.showUserMenu === userId ? null : userId;
        },
        viewPatient(userId) {
            window.location.href = `{{ url('/admin/patients') }}/${userId}`;
        },
        editPatient(userId) {
            window.location.href = `{{ url('/admin/patients') }}/${userId}`;
        },
        async duplicatePatient(userId) {
            // TODO: Implement duplicate functionality
            alert('Duplicate functionality coming soon');
        },
        async deletePatient(userId) {
            if (!confirm('Are you sure you want to delete this patient?')) return;
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`{{ url('/api/admin/patients') }}/${userId}`, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    credentials: 'include'
                });
                
                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.message || 'Failed to delete patient');
                }
                
                await this.fetchUsers();
            } catch (error) {
                console.error('Error deleting patient:', error);
                alert(error.message || 'Failed to delete patient');
            }
        },
        // print removed per request
    }">
    <div class="space-y-6">
        <!-- Top Bar -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <!-- Left: Patient Count -->
                <div class="flex items-center gap-4">
                    <span class="text-blue-600 font-semibold" x-text="`${filteredUsers.length} patients`"></span>
                    
                    <!-- Sort By -->
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-gray-600">Sort by:</label>
                        <select x-model="sortBy" @change="applyFilters()"
                                class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="last_appointment">Last appointment</option>
                            <option value="register_date">Register Date</option>
                            <option value="name">Name</option>
                        </select>
                    </div>
                </div>
                
                <!-- Right: Search -->
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400"></i>
                        <input x-model="searchQuery" @input="filterUsers()" placeholder="Search patients..."
                               class="pl-10 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-64" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Message -->
        <div x-show="error" x-cloak 
             class="bg-red-50 border-l-4 border-red-400 text-red-700 px-4 py-3 rounded-lg" role="alert">
            <span x-text="error"></span>
        </div>

        <!-- Loading State -->
        <div x-show="loading" x-cloak class="text-center py-16">
            <div class="bg-gray-100 rounded-full p-4 w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                <i data-lucide="loader" class="h-8 w-8 animate-spin text-blue-600"></i>
            </div>
            <p class="text-gray-600 font-medium">Loading patients...</p>
        </div>

        <!-- Empty State -->
        <div x-show="!loading && filteredUsers.length === 0" x-cloak
             class="bg-white rounded-xl shadow-sm p-12 text-center border border-gray-200">
            <div class="bg-gray-100 rounded-full p-4 w-20 h-20 mx-auto mb-4 flex items-center justify-center">
                <i data-lucide="user-x" class="h-10 w-10 text-gray-400"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No patients found</h3>
            <p class="text-gray-600 mb-6 max-w-md mx-auto" x-show="searchQuery">
                No patients match your search criteria. Try adjusting your search terms.
            </p>
            <p class="text-gray-600 mb-6 max-w-md mx-auto" x-show="!searchQuery">
                There are no registered patients yet.
            </p>
        </div>

        <!-- Patients Table -->
        <div x-show="!loading && filteredUsers.length > 0" x-cloak 
             class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden print-content">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-left">
                                <input type="checkbox" 
                                       @change="toggleSelectAll($event)"
                                       :checked="selectedUsers.length === filteredUsers.length && filteredUsers.length > 0"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                Basic Info
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                Phone Number
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                City
                            </th>

                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                Last Appointment
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                Register Date
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                Actions
                            </th>
                            <th class="px-6 py-4 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="user in filteredUsers" :key="user.id">
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="checkbox" 
                                           :checked="isSelected(user.id)"
                                           @change="toggleSelectUser(user.id)"
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <!-- Profile Picture or Initials -->
                                        <div class="flex-shrink-0">
                                            <img x-show="user.profile_photo_url" 
                                                 :src="user.profile_photo_url" 
                                                 :alt="user.name"
                                                 class="h-10 w-10 rounded-full object-cover">
                                            <div x-show="!user.profile_photo_url"
                                                 class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-white font-semibold text-sm"
                                                 x-text="getInitials(user.name)">
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-sm font-semibold text-gray-900" x-text="user.name || 'N/A'"></div>
                                            <div class="text-sm text-gray-500" x-text="user.email || 'N/A'"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-700" x-text="user.phone || '-'"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-700" x-text="user.municipality || user.city || '-'"></span>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-700" 
                                          x-text="user.last_appointment ? formatDate(user.last_appointment.appointment_date) : '-'">
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-medium text-gray-900" x-text="formatDate(user.created_at)"></span>
                                </td>
<td class="px-6 py-4 whitespace-nowrap text-center">
    <a :href="`{{ url('/admin/patients') }}/${user.id}`"
       class="inline-flex items-center justify-center gap-2 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm hover:shadow-md text-center"
       aria-label="View patient">
        <span>View</span>
    </a>
</td>


                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="relative inline-block">
                                        <button @click.stop="toggleUserMenu(user.id)"
                                                class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition-colors">
                                            <i data-lucide="more-horizontal" class="h-5 w-5"></i>
                                        </button>
                                        <div x-show="showUserMenu === user.id" 
                                             x-cloak
                                             x-transition:enter="transition ease-out duration-100"
                                             x-transition:enter-start="transform opacity-0 scale-95"
                                             x-transition:enter-end="transform opacity-100 scale-100"
                                             x-transition:leave="transition ease-in duration-75"
                                             x-transition:leave-start="transform opacity-100 scale-100"
                                             x-transition:leave-end="transform opacity-0 scale-95"
                                             @click.away="showUserMenu = null"
                                             class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-50 py-1">
                                            <button @click.stop="viewPatient(user.id); showUserMenu = null;"
                                                    class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2 transition-colors">
                                                <i data-lucide="eye" class="h-4 w-4"></i>
                                                View
                                            </button>
                                            <button @click.stop="editPatient(user.id); showUserMenu = null;"
                                                    class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2 transition-colors">
                                                <i data-lucide="edit" class="h-4 w-4"></i>
                                                Edit Patient
                                            </button>
                                            <button @click.stop="duplicatePatient(user.id); showUserMenu = null;"
                                                    class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2 transition-colors">
                                                <i data-lucide="copy" class="h-4 w-4"></i>
                                                Duplicate
                                            </button>
                                            <button @click.stop="deletePatient(user.id); showUserMenu = null;"
                                                    class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2 transition-colors">
                                                <i data-lucide="trash-2" class="h-4 w-4"></i>
                                                Delete Patient
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Print styles removed (printer UI removed) -->

<script>
    lucide.createIcons();
</script>
@endsection
