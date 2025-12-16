<header class="bg-white shadow-sm h-16 border-b border-gray-200"
    @notification-read.window="if(unreadCount>0) unreadCount = Math.max(0, unreadCount - 1)"
    @notification-read-reverted.window="unreadCount = (unreadCount || 0) + 1"
    x-data="{
            profileOpen: false,
            notificationsOpen: false,
            unreadCount: 0,
            async init() {
                await this.checkUnreadNotifications();
                setInterval(() => this.checkUnreadNotifications(), 60000);
            },
            async checkUnreadNotifications() {
                try {
                    const token = localStorage.getItem('token');
                    if (!token) return;
                    
                    const apiUrl = '{{ $isAdmin ?? false ? url('/api/admin/notifications') : url('/api/patient/notifications') }}';
                    const response = await window.apiUtils.fetch(apiUrl, {
                        method: 'GET',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Accept': 'application/json'
                        }
                    });
                    
                    if (response && response.ok) {
                        const notifications = await response.json();
                        const unread = notifications.filter(n => !n.is_read);
                        this.unreadCount = unread.length;
                    }
                } catch (error) {
                    console.error('Error checking notifications:', error);
                }
            }
        }">
    <div class="mx-auto px-6 h-full">
        <div class="flex justify-between items-center h-full">
            <!-- Left Side - Title (only show if no search) -->
            @if(!($isAdmin ?? false))
            <div class="flex-1">
                <h1 class="text-xl lg:text-2xl font-bold text-gray-900">Patient Dashboard</h1>
            </div>
            @else
            <div class="flex-1">
                <span class="text-xl font-bold text-gray-900">Admin Dashboard</span>
            </div>
            @endif
            
            <!-- Right Side Icons -->
            <div class="flex items-center space-x-4">
                <!-- Notifications -->
                <div class="relative">
                    <button
                        @click="notificationsOpen = !notificationsOpen; profileOpen = false"
                        class="p-2 rounded-full hover:bg-gray-100 relative transition-all duration-120">
                        <i data-lucide="bell" class="h-6 w-6 text-gray-600"></i>
                        <span x-show="unreadCount > 0" x-cloak
                              class="absolute top-1 right-1 h-5 w-5 rounded-full bg-red-500 text-white text-xs flex items-center justify-center font-semibold">
                            <span x-text="unreadCount > 9 ? '9+' : unreadCount"></span>
                        </span>
                    </button>
                    @if(isset($isAdmin) && $isAdmin)
                        @include('partials.dashboard.admin-notification-dropdown')
                    @else
                        @include('partials.dashboard.patient-notification-dropdown')
                    @endif
                </div>
                
                <!-- More Options -->
                <div class="relative">
                    <button
                        @click="profileOpen = !profileOpen; notificationsOpen = false"
                        class="p-2 rounded-full hover:bg-gray-100 transition-all duration-120">
                        <i data-lucide="more-vertical" class="h-6 w-6 text-gray-600"></i>
                    </button>
                    <div x-show="profileOpen" x-cloak
                         @click.away="profileOpen = false"
                         x-transition:enter="transition ease-out duration-120"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-120"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-48 rounded-lg shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                        <div class="py-1">
                            <a href="{{ ($isAdmin ?? false) ? route('admin.settings') : route('patient.settings') }}"
                               class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-all duration-120">
                                <i data-lucide="settings" class="mr-3 h-5 w-5"></i>
                                Settings
                            </a>
                            <a href="{{ route('home') }}"
                               onclick="localStorage.clear();"
                               class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-all duration-120">
                                <i data-lucide="log-out" class="mr-3 h-5 w-5"></i>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
    lucide.createIcons();
</script>


