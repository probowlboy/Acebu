@php
    $isAdmin = $isAdmin ?? false;
    $currentRoute = request()->route()->getName();
    
    $adminMenuItems = [
        [
            'name' => 'Dashboard',
            'icon' => 'layout-dashboard',
            'route' => 'admin.dashboard',
            'path' => '/admin/admindashboard',
        ],
        [
            'name' => 'Appointments',
            'icon' => 'calendar',
            'route' => 'admin.appointments',
            'path' => '/admin/adminappointments',
        ],
        [
            'name' => 'Patients',
            'icon' => 'users',
            'route' => 'admin.users',
            'path' => '/admin/adminusers',
        ],
        [
            'name' => 'Manage Services',
            'icon' => 'settings',
            'route' => 'admin.services',
            'path' => '/admin/adminservices',
        ],
        [
            'name' => 'History',
            'icon' => 'history',
            'route' => 'admin.history',
            'path' => '/admin/adminhistory',
        ],
    ];
    
    $patientMenuItems = [
        [
            'name' => 'Dashboard',
            'icon' => 'layout-dashboard',
            'route' => 'patient.dashboard',
            'path' => '/patient/patientdashboard',
        ],
        [
            'name' => 'Appointments',
            'icon' => 'calendar',
            'route' => 'patient.appointments',
            'path' => '/patient/patientappointment',
        ],
        [
            'name' => 'History',
            'icon' => 'history',
            'route' => 'patient.history',
            'path' => '/patient/patienthistory',
        ],
    ];
    
    $menuItems = $isAdmin ? $adminMenuItems : $patientMenuItems;
@endphp

<div class="h-full w-64 medical-sidebar bg-white border-r border-gray-200">
    <div class="flex flex-col h-full">
        <!-- Logo Section -->
        <div class="flex items-center justify-center h-20 border-b border-gray-200 px-4">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-gradient-to-br from-teal-500 to-teal-600 rounded-lg flex items-center justify-center">
                    <i data-lucide="heart-pulse" class="h-6 w-6 text-white"></i>
                </div>
                <h1 class="text-xl font-bold text-gray-900">Acebu Dental</h1>
            </div>
        </div>
        
        <!-- Navigation Menu -->
        <div class="flex-1 flex flex-col pt-6 pb-4 overflow-y-auto">
            <div class="flex-1 px-4 space-y-2">
                @foreach($menuItems as $item)
                    @php
                        $isActive = request()->is(trim($item['path'], '/')) || $currentRoute === $item['route'];
                    @endphp
                    <a href="{{ $item['path'] }}"
                       class="sidebar-item {{ $isActive ? 'active' : '' }} group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-120">
                        <i data-lucide="{{ $item['icon'] }}" 
                           class="mr-3 h-5 w-5 {{ $isActive ? 'text-teal-600' : 'text-gray-500' }}"></i>
                        <span class="{{ $isActive ? 'text-teal-700 font-semibold' : 'text-gray-700' }}">{{ $item['name'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();
</script>

