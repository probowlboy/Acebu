<div x-show="notificationsOpen" 
     x-cloak
     @click.away="notificationsOpen = false"
     x-data="{
        notifications: [],
        pollIntervalId: null,
        async init() {
            await this.fetchNotifications();
            // Optimize polling - pause when page is hidden, increase interval to reduce lag
            const startPolling = () => {
                if (this.pollIntervalId) clearInterval(this.pollIntervalId);
                this.pollIntervalId = setInterval(() => {
                    if (!document.hidden) this.fetchNotifications();
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
         async fetchNotifications() {
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
                    // Token invalid/expired
                    localStorage.removeItem('token');
                    window.location.href = '{{ route('patient.login') }}';
                    return;
                }
                 
                 if (response.ok) {
                     const serverList = await response.json();
                     // merge server list with current notifications to avoid losing items locally
                     const map = {};
                     serverList.forEach(n => map[n.id] = n);
                     const merged = serverList.slice();
                     (this.notifications || []).forEach(local => {
                         if (!map[local.id]) merged.push(local);
                     });
                     this.notifications = merged;
                 }
             } catch (error) {
                 console.error('Error fetching notifications:', error);
             }
         },
         // Optimistic mark-as-read triggered on hover
         async markAsReadOptimistic(notification) {
             try {
                 if (!notification || notification.status !== 'unread') return;
                // optimistic update
                const prev = { status: notification.status, is_read: notification.is_read };
                notification.status = 'read';
                notification.is_read = true;
                 // notify header to decrement unread count
                 window.dispatchEvent(new CustomEvent('notification-read', { detail: { id: notification.id } }));

                 // send request but don't refetch list (avoid removals)
                 const token = localStorage.getItem('token');
                 const resp = await fetch(`{{ url('/api/patient/notifications') }}/${notification.id}/read`, {
                     method: 'POST',
                     headers: {
                         'Authorization': `Bearer ${token}`,
                         'Accept': 'application/json'
                     }
                 });

                if (resp && resp.status === 401) {
                    localStorage.removeItem('token');
                    window.location.href = '{{ route('patient.login') }}';
                    return;
                }

                 if (!resp.ok) {
                     // revert on failure
                     notification.status = prev.status;
                     notification.is_read = prev.is_read;
                     window.dispatchEvent(new CustomEvent('notification-read-reverted', { detail: { id: notification.id } }));
                 }
             } catch (error) {
                 console.error('Error marking notification as read:', error);
                try { notification.status = prev.status; notification.is_read = prev.is_read; } catch (e) {}
                 window.dispatchEvent(new CustomEvent('notification-read-reverted', { detail: { id: notification.id } }));
             }
         }
     }"
     class="absolute right-0 mt-2 w-80 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-20 max-h-96 overflow-y-auto">
    <div class="py-1">
        <div class="px-4 py-2 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-900">Notifications</h3>
        </div>
        <template x-if="notifications.length === 0">
            <div class="px-4 py-8 text-center text-gray-500">
                <i data-lucide="bell-off" class="h-8 w-8 mx-auto mb-2 text-gray-400"></i>
                <p class="text-sm">No notifications</p>
            </div>
        </template>
        <template x-for="notification in notifications" :key="notification.id">
              <div @mouseenter.once="markAsReadOptimistic(notification)"
                  :class="(!(notification.is_read === true || notification.status === 'read')) ? 'bg-blue-50' : 'bg-white'"
                 class="px-4 py-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                                <i :data-lucide="notification.type === 'success' ? 'check-circle' : 'alert-circle'" 
                                    class="h-5 w-5"
                                    :class="(!(notification.is_read === true || notification.status === 'read')) ? 'text-blue-600' : 'text-gray-400'"></i>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm font-medium text-gray-900" x-text="notification.title"></p>
                        <p class="text-xs text-gray-500 mt-1" x-text="notification.message"></p>
                        <p class="text-xs text-gray-400 mt-1" x-text="new Date(notification.created_at).toLocaleString()"></p>
                    </div>
                      <span x-show="(!(notification.is_read === true || notification.status === 'read'))"
                          class="ml-2 h-2 w-2 bg-blue-600 rounded-full"></span>
                </div>
            </div>
        </template>
    </div>
</div>

<script>
    lucide.createIcons();
</script>

