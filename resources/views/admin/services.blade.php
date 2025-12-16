@extends('layouts.dashboard')

@section('title', 'Manage Services')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
    x-data="{
        services: [],
        filteredServices: [],
        error: null,
        success: null,
        loading: true,
        searchQuery: '',
        showModal: false,
        showFilters: false,
        activeTab: 'active',
        editingService: null,
        serviceForm: {
            name: '',
            description: '',
            price: '',
            original_price: '',
            duration_minutes: 60,
            clinic_name: '',
            is_active: true,
            visit_type: 'single',
            rating: null,
            review_count: 0
        },
        async init() {
            const isAdmin = localStorage.getItem('isAdmin') === 'true';
            if (!isAdmin) {
                window.location.href = '/patient/patientdashboard';
                return;
            }
            await this.fetchServices();
        },
        async fetchServices() {
            try {
                this.loading = true;
                const token = localStorage.getItem('token');
                if (!token) {
                    window.location.href = '{{ route('admin.login') }}';
                    return;
                }
                const response = await window.apiUtils.fetch('{{ url('/api/admin/services') }}', {
                    method: 'GET',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    },
                    credentials: 'include'
                });
                if (!response || !response.ok) {
                    throw new Error('Failed to fetch services');
                }
                const data = await response.json();
                this.services = Array.isArray(data) ? data : (data.services || []);
                this.applyFilters();
                this.error = null;
            } catch (error) {
                if (error && error.name !== 'AbortError') {
                    console.error('Error fetching services:', error);
                    this.error = error.message || 'Failed to load services';
                }
            } finally {
                this.loading = false;
            }
        },
        applyFilters() {
            let filtered = [...this.services];
            
            // Filter by active/inactive tab
            if (this.activeTab === 'active') {
                filtered = filtered.filter(s => s.is_active === true || s.is_active === undefined || s.is_active === null);
            } else if (this.activeTab === 'inactive') {
                filtered = filtered.filter(s => s.is_active === false);
            }
            
            // Filter by search query
            if (this.searchQuery.trim()) {
                const query = this.searchQuery.toLowerCase();
                filtered = filtered.filter(service => 
                    service.name?.toLowerCase().includes(query) ||
                    service.description?.toLowerCase().includes(query)
                );
            }
            
            this.filteredServices = filtered;
        },
        filterServices() {
            window.apiUtils.debounce('serviceSearch', () => {
                this.applyFilters();
            }, 300);
        },
        getActiveServicesCount() {
            return this.services.filter(s => s.is_active === true || s.is_active === undefined || s.is_active === null).length;
        },
        getInactiveServicesCount() {
            return this.services.filter(s => s.is_active === false).length;
        },
        getVisitType(service) {
            // Determine visit type based on duration or service property
            // Multiple visit if duration > 90 minutes or has multiple visits
            if (service.visit_type) {
                return service.visit_type;
            }
            return service.duration_minutes > 90 ? 'multiple' : 'single';
        },
        getRating(service) {
            return service.rating || service.average_rating || null;
        },
        getReviewCount(service) {
            return service.review_count || service.reviews_count || 0;
        },
        formatPriceStart(price) {
            if (!price) return 'N/A';
            return 'Start from ' + this.formatPrice(price);
        },
        formatDurationEstimate(minutes) {
            if (!minutes) return 'N/A';
            const hours = Math.floor(minutes / 60);
            const mins = minutes % 60;
            let result = '≈ ';
            if (hours > 0) {
                result += `${hours} hour${hours > 1 ? 's' : ''}`;
                if (mins > 0) {
                    result += ` ${mins} minute${mins > 1 ? 's' : ''}`;
                }
            } else {
                result += `${mins} minute${mins > 1 ? 's' : ''}`;
            }
            const visitType = this.getVisitType({ duration_minutes: minutes });
            if (visitType === 'multiple') {
                result += '/visits';
            }
            return result;
        },
        // selection removed: no bulk select in current UI
        showServiceMenu: null,
        toggleServiceMenu(serviceId) {
            this.showServiceMenu = this.showServiceMenu === serviceId ? null : serviceId;
        },
        openModal(service = null) {
            this.editingService = service;
            if (service) {
                this.serviceForm = {
                    name: service.name || '',
                    description: service.description || '',
                    price: service.price || '',
                    original_price: service.original_price || '',
                    duration_minutes: service.duration_minutes || 60,
                    clinic_name: service.clinic_name || '',
                    is_active: service.is_active !== undefined ? service.is_active : true,
                    visit_type: this.getVisitType(service),
                    rating: this.getRating(service),
                    review_count: this.getReviewCount(service)
                };
            } else {
                this.serviceForm = {
                    name: '',
                    description: '',
                    price: '',
                    original_price: '',
                    duration_minutes: 60,
                    clinic_name: '',
                    is_active: true,
                    visit_type: 'single',
                    rating: null,
                    review_count: 0
                };
            }
            this.showModal = true;
            this.error = null;
            this.success = null;
        },
        closeModal() {
            this.showModal = false;
            this.editingService = null;
            this.serviceForm = {
                name: '',
                description: '',
                price: '',
                original_price: '',
                duration_minutes: 60,
                clinic_name: '',
                is_active: true
            };
        },
        async saveService() {
            this.loading = true;
            this.error = null;
            this.success = null;
            try {
                const token = localStorage.getItem('token');
                const url = this.editingService 
                    ? `{{ url('/api/admin/services') }}/${this.editingService.id}`
                    : '{{ url('/api/admin/services') }}';
                const method = this.editingService ? 'PUT' : 'POST';
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    credentials: 'include',
                    body: JSON.stringify(this.serviceForm)
                });
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'Failed to save service');
                }
                this.success = this.editingService ? 'Service updated successfully!' : 'Service created successfully!';
                this.closeModal();
                await this.fetchServices();
                setTimeout(() => this.success = null, 3000);
            } catch (error) {
                console.error('Error saving service:', error);
                this.error = error.message || 'Failed to save service. Please try again.';
            } finally {
                this.loading = false;
            }
        },
        async deleteService(serviceId) {
            if (!confirm('Are you sure you want to delete this service?')) return;
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`{{ url('/api/admin/services') }}/${serviceId}`, {
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
                    throw new Error(error.message || 'Failed to delete service');
                }
                this.success = 'Service deleted successfully!';
                await this.fetchServices();
                setTimeout(() => this.success = null, 3000);
            } catch (error) {
                console.error('Error deleting service:', error);
                this.error = error.message || 'Failed to delete service. Please try again.';
            }
        },
        async setServiceStatus(serviceId, isActive) {
            this.loading = true;
            this.error = null;
            this.success = null;
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(`{{ url('/api/admin/services') }}/${serviceId}/status`, {
                    method: 'PUT',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    credentials: 'include',
                    body: JSON.stringify({ is_active: isActive })
                });
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'Failed to update service status');
                }
                this.success = isActive ? 'Service activated successfully!' : 'Service set to inactive.';
                await this.fetchServices();
                setTimeout(() => this.success = null, 3000);
            } catch (error) {
                console.error('Error updating service status:', error);
                this.error = error.message || 'Failed to update service status. Please try again.';
            } finally {
                this.loading = false;
            }
        },
        formatPrice(price) {
            if (!price) return 'N/A';
            // Format price with Philippine peso sign and no decimals for display
            return '₱' + parseFloat(price).toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        },
        formatPricePeso(price) {
            if (!price) return 'N/A';
            // Format with 2 decimal places for detailed price display using en-PH
            return '₱' + parseFloat(price).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        getDiscountPercent() {
            const o = parseFloat(this.serviceForm.original_price);
            const p = parseFloat(this.serviceForm.price);
            if (!o || !p || o <= p) return null;
            return Math.round(((o - p) / o) * 100);
        },
        formatDuration(minutes) {
            if (!minutes) return 'N/A';
            const hours = Math.floor(minutes / 60);
            const mins = minutes % 60;
            if (hours > 0) {
                return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
            }
            return `${mins}m`;
        }
    }">

    <div class="space-y-6">
        <!-- Header Section -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between gap-4">
                <!-- Left Column: Title & Description -->
                    <div class="flex flex-col gap-1">
                    <h1 class="text-3xl font-bold text-gray-900 leading-tight">Manage Services</h1>
                    <p class="text-gray-600 text-base leading-relaxed">View and manage all dental services</p>
                </div>

                <!-- Right Column: Buttons -->
                    <div class="flex items-center gap-3 flex-shrink-0">
                    <button @click="openModal()"
                            class="flex items-center justify-center px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium shadow-sm whitespace-nowrap">
                        <i data-lucide="plus" class="h-4 w-4 mr-2"></i>
                        Add Service
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabs Section -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div class="flex gap-1 border-b border-gray-200">
                    <button @click="activeTab = 'active'; applyFilters();"
                            :class="activeTab === 'active' ? 'border-b-2 border-blue-600 text-blue-600 font-semibold' : 'text-gray-600 hover:text-gray-900'"
                            class="px-6 py-3 text-sm font-medium transition-colors">
                        Active Services
                    </button>
                    <button @click="activeTab = 'inactive'; applyFilters();"
                            :class="activeTab === 'inactive' ? 'border-b-2 border-blue-600 text-blue-600 font-semibold' : 'text-gray-600 hover:text-gray-900'"
                            class="px-6 py-3 text-sm font-medium transition-colors">
                        Inactive Services
                    </button>
                </div>
                
                <!-- Services Count -->
                <div class="flex items-center gap-2 text-gray-700">
                    <i data-lucide="stethoscope" class="h-5 w-5"></i>
                    <span class="text-sm font-medium">
                        <span x-text="activeTab === 'active' ? getActiveServicesCount() : getInactiveServicesCount()"></span> 
                        <span>services</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Error/Success Messages -->
        <div x-show="error" x-cloak 
            class="bg-red-50 border-l-4 border-red-400 text-red-700 px-4 py-3 rounded-lg shadow-sm" role="alert">
            <div class="flex items-center">
                <i data-lucide="alert-circle" class="h-5 w-5 mr-2"></i>
                <span class="font-medium" x-text="error"></span>
            </div>
        </div>
        
        <div x-show="success" x-cloak 
            class="bg-green-50 border-l-4 border-green-400 text-green-700 px-4 py-3 rounded-lg shadow-sm" role="alert">
            <div class="flex items-center">
                <i data-lucide="check-circle" class="h-5 w-5 mr-2"></i>
                <span class="font-medium" x-text="success"></span>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i data-lucide="search" class="h-5 w-5 text-gray-400"></i>
                </div>
                <input type="text" 
                    x-model="searchQuery"
                    @input.debounce.300ms="filterServices()"
                    placeholder="Search services by name or description..."
                    class="block w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm transition-colors">
            </div>
        </div>

        <!-- Loading State -->
        <div x-show="loading" x-cloak class="text-center py-16">
            <div class="bg-gray-100 rounded-full p-4 w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                <i data-lucide="loader" class="h-8 w-8 animate-spin text-blue-600"></i>
            </div>
            <p class="text-gray-600 font-medium">Loading services...</p>
        </div>

        <!-- Empty State -->
        <div x-show="!loading && filteredServices.length === 0" x-cloak
            class="bg-white rounded-xl shadow-sm p-12 text-center border border-gray-200">
            <div class="bg-gray-100 rounded-full p-4 w-20 h-20 mx-auto mb-4 flex items-center justify-center">
                <i data-lucide="stethoscope" class="h-10 w-10 text-gray-400"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No services found</h3>
            <p class="text-gray-600 mb-6 max-w-md mx-auto" x-show="searchQuery">
                No services match your search criteria. Try adjusting your search terms.
            </p>
            <p class="text-gray-600 mb-6 max-w-md mx-auto" x-show="!searchQuery">
                There are no services yet. Add your first service to get started.
            </p>
        </div>

        <!-- Services Table -->
        <div x-show="!loading && filteredServices.length > 0" x-cloak class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-left w-20 text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                ID
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider cursor-pointer hover:text-gray-900 w-1/4 min-w-[160px]">
                                SERVICE NAME
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider cursor-pointer hover:text-gray-900 w-28 text-right">
                                PRICE
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider cursor-pointer hover:text-gray-900 w-36">
                                ESTIMATE DURATION
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider w-24">
                                ACTION
                            </th>
                            
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="(service, idx) in filteredServices" :key="service.id">
                            <tr class="hover:bg-gray-50 transition-colors align-top">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 font-medium">
                                    <span x-text="idx + 1"></span>
                                </td>
                                <td class="px-6 py-4 min-w-0">
                                    <div class="flex items-start gap-2 justify-between">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium text-gray-900 truncate max-w-[220px] block" x-text="service.name"></span>
                                                <span x-show="service.tag || service.is_sample" 
                                                      class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-xs font-medium">
                                                    SAMPLE
                                                </span>
                                                <span x-show="service.is_active === false" class="px-2 py-0.5 bg-red-600 border border-red-700 text-white rounded text-xs font-medium">
                                                    INACTIVE
                                                </span>
                                            </div>
                                            <!-- Mobile: show description stacked under name -->
                                            <div class="text-sm text-gray-700 mt-2 sm:hidden" x-text="service.description || '-'" style="display:block;">
                                            </div>
                                        </div>

                                        <!-- edit button moved to ACTION column -->
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right align-top">
                                    <div class="flex flex-col items-end justify-start">
                                        <span class="text-sm font-semibold text-gray-900" x-text="formatPrice(service.price)"></span>
                                        <span class="text-xs text-gray-500 mt-0.5" x-show="service.original_price" x-text="formatPricePeso(service.original_price)" style="text-decoration:line-through;"></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-700" x-text="formatDurationEstimate(service.duration_minutes)"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center align-top">
                                    <div class="flex items-center justify-center gap-2">
                                        <button @click="openModal(service)" aria-label="Edit service" title="Edit"
                                            class="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-600 text-white rounded-md text-xs font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">                                            <span class="hidden sm:inline">Edit</span>
                                        </button>
                                        <!-- Inactivate / Activate toggle -->
                                        <button x-show="service.is_active !== false" @click="setServiceStatus(service.id, false)" aria-label="Set service inactive" title="Set inactive"
                                            class="inline-flex items-center gap-2 px-3 py-1.5 bg-red-600 text-white border border-red-700 rounded-md text-xs font-medium hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-300">                                            <span class="hidden sm:inline">Inactive</span>
                                        </button>
                                        <button x-show="service.is_active === false" @click="setServiceStatus(service.id, true)" aria-label="Activate service" title="Activate"
                                            class="inline-flex items-center gap-2 px-3 py-1.5 bg-green-600 text-white rounded-md text-xs font-medium hover:bg-green-700 focus:outline-none">                                            <span class="hidden sm:inline">Activate</span>
                                        </button>
                                    </div>
                                </td>
                                
                                <!-- Actions removed per request -->
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Service Modal -->
    <div x-show="showModal" x-cloak
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click.self="closeModal()"
        @pointerdown.self="closeModal()">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-white" x-text="editingService ? 'Edit Service' : 'Add New Service'"></h2>
                    <button @click="closeModal()" class="text-white hover:text-gray-200 transition-colors rounded-full p-1 hover:bg-white/20">
                        <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                </div>
            </div>
            
            <!-- Modal Body -->
            <div class="overflow-y-auto flex-1 p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Left: Basic Info -->
                    <div class="space-y-5 h-full">
                        <div class="bg-white rounded-lg p-4 border border-gray-200 h-full">
                            <h3 class="text-sm font-semibold text-gray-700 mb-3">Basic Info</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Service Name <span class="text-red-500">*</span></label>
                                    <input type="text" x-model="serviceForm.name" required placeholder="e.g., Dental Cleaning, Root Canal"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                                    <textarea x-model="serviceForm.description" rows="6" placeholder="Brief description of the service..."
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none min-h-[9rem]"></textarea>
                                </div>

                                <div class="">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Duration (minutes) <span class="text-red-500">*</span></label>
                                    <input type="number" x-model="serviceForm.duration_minutes" required min="15" step="15"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <p class="text-xs text-gray-500 mt-1">Minimum: 15 minutes</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Pricing & Preview -->
                    <div class="space-y-5 h-full">
                        <div class="bg-white rounded-lg p-4 border border-gray-200 h-full">
                            <h3 class="text-sm font-semibold text-gray-700 mb-3">Pricing</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Current Price <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-sm text-gray-700">₱</span>
                                        <input type="number" x-model="serviceForm.price" step="0.01" min="0" required placeholder="0.00"
                                            class="w-full pl-9 px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Original Price (optional)</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-sm text-gray-700">₱</span>
                                        <input type="number" x-model="serviceForm.original_price" step="0.01" min="0" placeholder="0.00"
                                            class="w-full pl-9 px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                    </div>
                                </div>

                                <div class="mt-2">
                                    <p class="text-xs text-gray-500">Preview</p>
                                    <div class="mt-2 border border-gray-200 rounded-lg p-4 bg-gray-50">
                                        <div class="flex items-center justify-between">
                                            <div class="min-w-0">
                                                <div class="text-sm font-semibold text-gray-900 truncate" x-text="serviceForm.name || 'Service Name'"></div>
                                                <div class="text-xs text-gray-600 mt-1" x-text="serviceForm.description || 'Short description'"></div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-sm font-semibold text-gray-900" x-text="formatPrice(serviceForm.price)"></div>
                                                <div class="text-xs text-gray-500" x-show="serviceForm.original_price" style="text-decoration:line-through;" x-text="formatPricePeso(serviceForm.original_price)"></div>
                                                <div class="text-xs text-green-700 mt-1" x-show="getDiscountPercent()"> <span x-text="getDiscountPercent()"></span>% off</div>
                                            </div>
                                        </div>
                                        <div class="mt-3 text-xs text-gray-600">Duration: <span x-text="formatDuration(serviceForm.duration_minutes)"></span></div>
                                    </div>

                                <!-- Notice -->
                                <div class="mt-3 bg-yellow-50 border-l-4 border-yellow-300 p-3 rounded">
                                    <p class="text-xs text-yellow-800">Discounts may be available based on the type of treatment and individual situation.</p>
                                </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                <div class="flex gap-3">
                    <button type="button" 
                            @click="closeModal()"
                            class="flex-1 px-5 py-2.5 bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 rounded-lg transition-colors font-medium">
                        Cancel
                    </button>
                    <button type="button"
                            @click="saveService()"
                            :disabled="loading"
                            :class="loading ? 'opacity-70 cursor-not-allowed' : 'hover:bg-blue-700'"
                            class="flex-1 flex items-center justify-center px-5 py-2.5 bg-blue-600 text-white rounded-lg transition-colors font-medium shadow-sm">
                        <i x-show="loading" x-cloak data-lucide="loader" class="h-4 w-4 mr-2 animate-spin"></i>
                        <span x-show="!loading" x-text="editingService ? 'Update Service' : 'Create Service'"></span>
                        <span x-show="loading" x-cloak>Saving...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();
</script>
@endsection
