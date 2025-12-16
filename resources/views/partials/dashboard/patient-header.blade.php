<header class="bg-blue-300 shadow-sm h-16 border-b border-gray-200" 
        x-data="{
            profileOpen: false,
            notificationsOpen: false,
            unreadCount: 0,
            pollIntervalId: null,
            async init() {
                await this.checkUnreadNotifications();
                // Optimize polling - pause when page is hidden, increase interval to reduce lag
                const startPolling = () => {
                    if (this.pollIntervalId) clearInterval(this.pollIntervalId);
                    this.pollIntervalId = setInterval(() => {
                        if (!document.hidden) this.checkUnreadNotifications();
                    }, 120000); // Increased to 2 minutes
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
                window.addEventListener('beforeunload', () => stopPolling());
            },
            async checkUnreadNotifications() {
                try {
                    const token = localStorage.getItem('token');
                    if (!token) return;
                    
                    const response = await fetch('{{ url('/api/patient/notifications') }}', {
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Accept': 'application/json'
                        }
                    });

                    if (response && response.status === 401) {
                        localStorage.removeItem('token');
                        window.location.href = '{{ route('patient.login') }}';
                        return;
                    }
                    
                    if (response.ok) {
                        const notifications = await response.json();
                        const unread = notifications.filter(n => n.status === 'unread');
                        this.unreadCount = unread.length;
                    }
                } catch (error) {
                    console.error('Error checking notifications:', error);
                }
            }
        }">
    <div class="mx-auto px-4 sm:px-6 lg:px-8 h-full">
        <div class="flex justify-between items-center h-full">
            <div class="flex items-center">
                <span class="text-xl font-bold text-gray-900">Patient Dashboard</span>
            </div>
            <div class="flex items-center space-x-4">
                <!-- Notifications -->
                <div class="relative">
                    <button
                        @click="notificationsOpen = !notificationsOpen; profileOpen = false"
                        class="p-2 rounded-full hover:bg-gray-100 relative">
                        <i data-lucide="bell" class="h-6 w-6 text-gray-900"></i>
                        <span x-show="unreadCount > 0" x-cloak
                              class="absolute top-0 right-0 h-4 w-4 rounded-full bg-red-500 text-white text-xs flex items-center justify-center">
                            <span x-text="unreadCount > 9 ? '9+' : unreadCount"></span>
                        </span>
                    </button>
                    @include('partials.dashboard.patient-notification-dropdown')
                </div>
                
                <!-- Profile Menu -->
                <div class="relative">
                    <button
                        @click="profileOpen = !profileOpen; notificationsOpen = false"
                        class="p-1 rounded-full hover:bg-gray-100 flex items-center">
                        <i data-lucide="user-circle" class="h-7 w-8 text-gray-900"></i>
                    </button>
                    <div x-show="profileOpen" x-cloak
                         @click.away="profileOpen = false"
                         class="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10">
                        <div class="py-1">
                            <a href="{{ route('patient.settings') }}"
                               class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i data-lucide="settings" class="mr-3 h-5 w-5"></i>
                                Settings
                            </a>
                            <a href="{{ route('home') }}"
                               onclick="localStorage.clear();"
                               class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50">
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

