@extends('layouts.dashboard')

@section('title', 'Contact Messages')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
     x-data="{
         messages: [],
         filteredMessages: [],
         error: null,
         loading: true,
         searchQuery: '',
         filterRead: 'all',
         selectedMessage: null,
         async init() {
             const isAdmin = localStorage.getItem('isAdmin') === 'true';
             if (!isAdmin) {
                 window.location.href = '/patient/patientdashboard';
                 return;
             }
             
            await this.fetchMessages();
            // Refresh messages - pause when page is hidden, increase interval to reduce lag
            this.pollIntervalId = null;
            const startPolling = () => {
                if (this.pollIntervalId) clearInterval(this.pollIntervalId);
                this.pollIntervalId = setInterval(() => {
                    if (!document.hidden) this.fetchMessages();
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
         async fetchMessages() {
             try {
                 this.loading = true;
                 const token = localStorage.getItem('token');
                 
                 if (!token) {
                     window.location.href = '{{ route('admin.login') }}';
                     return;
                 }
                 
                 const response = await window.apiUtils.fetch('{{ url('/api/admin/contact-messages') }}', {
                     method: 'GET',
                     headers: {
                         'Authorization': `Bearer ${token}`,
                         'Accept': 'application/json'
                     },
                     credentials: 'include'
                 });
                 
                 if (!response || !response.ok) {
                     throw new Error('Failed to fetch messages');
                 }
                 
                 const data = await response.json();
                 this.messages = Array.isArray(data) ? data : [];
                 this.applyFilters();
                 this.error = null;
                 
             } catch (error) {
                 if (error && error.name !== 'AbortError') {
                     console.error('Error fetching messages:', error);
                     this.error = error.message || 'Failed to load messages';
                 }
             } finally {
                 this.loading = false;
             }
         },
         applyFilters() {
             let filtered = [...this.messages];
             
             // Filter by read status
             if (this.filterRead === 'unread') {
                 filtered = filtered.filter(m => !m.is_read);
             } else if (this.filterRead === 'read') {
                 filtered = filtered.filter(m => m.is_read);
             }
             
             // Filter by search query
             if (this.searchQuery) {
                 const query = this.searchQuery.toLowerCase();
                 filtered = filtered.filter(m => 
                     m.name.toLowerCase().includes(query) ||
                     m.email.toLowerCase().includes(query) ||
                     m.subject.toLowerCase().includes(query) ||
                     m.message.toLowerCase().includes(query)
                 );
             }
             
             this.filteredMessages = filtered;
         },
         async markAsRead(messageId) {
             try {
                 const token = localStorage.getItem('token');
                 const response = await window.apiUtils.fetch(`{{ url('/api/admin/contact-messages') }}/${messageId}/read`, {
                     method: 'POST',
                     headers: {
                         'Authorization': `Bearer ${token}`,
                         'Accept': 'application/json'
                     },
                     credentials: 'include'
                 });
                 
                 if (response && response.ok) {
                     await this.fetchMessages();
                 }
             } catch (error) {
                 console.error('Error marking message as read:', error);
             }
         },
         async deleteMessage(messageId) {
             if (!confirm('Are you sure you want to delete this message?')) {
                 return;
             }
             
             try {
                 const token = localStorage.getItem('token');
                 const response = await window.apiUtils.fetch(`{{ url('/api/admin/contact-messages') }}/${messageId}`, {
                     method: 'DELETE',
                     headers: {
                         'Authorization': `Bearer ${token}`,
                         'Accept': 'application/json'
                     },
                     credentials: 'include'
                 });
                 
                 if (response && response.ok) {
                     await this.fetchMessages();
                     this.selectedMessage = null;
                 }
             } catch (error) {
                 console.error('Error deleting message:', error);
                 alert('Failed to delete message');
             }
         },
         openMessage(message) {
             this.selectedMessage = message;
             if (!message.is_read) {
                 this.markAsRead(message.id);
             }
         },
         getUnreadCount() {
             return this.messages.filter(m => !m.is_read).length;
         },
         formatDate(dateString) {
             const date = new Date(dateString);
             return date.toLocaleString('en-US', {
                 year: 'numeric',
                 month: 'short',
                 day: 'numeric',
                 hour: '2-digit',
                 minute: '2-digit'
             });
         }
     }">
    
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Contact Messages</h1>
        <p class="mt-1 text-sm text-gray-500">Manage messages from the contact form</p>
    </div>

    <!-- Error Message -->
    <div x-show="error" x-cloak
         class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
        <p x-text="error"></p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-blue-100 rounded-lg p-3">
                    <i data-lucide="mail" class="h-6 w-6 text-blue-600"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Messages</p>
                    <p class="text-2xl font-semibold text-gray-900" x-text="messages.length"></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-yellow-100 rounded-lg p-3">
                    <i data-lucide="mail-warning" class="h-6 w-6 text-yellow-600"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Unread Messages</p>
                    <p class="text-2xl font-semibold text-gray-900" x-text="getUnreadCount()"></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-green-100 rounded-lg p-3">
                    <i data-lucide="mail-check" class="h-6 w-6 text-green-600"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Read Messages</p>
                    <p class="text-2xl font-semibold text-gray-900" x-text="messages.filter(m => m.is_read).length"></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="bg-white rounded-lg shadow mb-6 p-4">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" 
                       x-model="searchQuery"
                       @input="applyFilters()"
                       placeholder="Search by name, email, subject, or message..."
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <select x-model="filterRead" @change="applyFilters()"
                        class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="all">All Messages</option>
                    <option value="unread">Unread Only</option>
                    <option value="read">Read Only</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Loading State -->
    <div x-show="loading" class="text-center py-12">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <p class="mt-2 text-gray-500">Loading messages...</p>
    </div>

    <!-- Messages List -->
    <div x-show="!loading" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Messages List -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow">
                <div class="p-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Messages</h2>
                    <p class="text-sm text-gray-500" x-text="`${filteredMessages.length} message${filteredMessages.length !== 1 ? 's' : ''}`"></p>
                </div>
                
                <div class="divide-y divide-gray-200 max-h-[600px] overflow-y-auto">
                    <template x-for="message in filteredMessages" :key="message.id">
                        <div @click="openMessage(message)"
                             :class="{
                                 'bg-blue-50 border-l-4 border-blue-500': !message.is_read,
                                 'bg-white': message.is_read,
                                 'bg-gray-50': selectedMessage && selectedMessage.id === message.id
                             }"
                             class="p-4 cursor-pointer hover:bg-gray-50 transition">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <p class="font-semibold text-gray-900" x-text="message.name"></p>
                                        <span x-show="!message.is_read" 
                                              class="h-2 w-2 bg-blue-500 rounded-full"></span>
                                    </div>
                                    <p class="text-sm text-gray-600 mt-1" x-text="message.email"></p>
                                    <p class="text-sm font-medium text-gray-900 mt-2" x-text="message.subject"></p>
                                    <p class="text-xs text-gray-500 mt-1" x-text="formatDate(message.created_at)"></p>
                                </div>
                            </div>
                        </div>
                    </template>
                    
                    <div x-show="filteredMessages.length === 0" class="p-8 text-center text-gray-500">
                        <i data-lucide="inbox" class="h-12 w-12 mx-auto mb-2 text-gray-400"></i>
                        <p>No messages found</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Message Detail -->
        <div class="lg:col-span-2">
            <div x-show="selectedMessage" class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-start justify-between">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900" x-text="selectedMessage?.subject"></h2>
                            <div class="mt-2 flex items-center gap-4 text-sm text-gray-500">
                                <div class="flex items-center gap-1">
                                    <i data-lucide="user" class="h-4 w-4"></i>
                                    <span x-text="selectedMessage?.name"></span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <i data-lucide="mail" class="h-4 w-4"></i>
                                    <a :href="`mailto:${selectedMessage?.email}`" 
                                       x-text="selectedMessage?.email"
                                       class="text-blue-600 hover:underline"></a>
                                </div>
                                <div class="flex items-center gap-1">
                                    <i data-lucide="clock" class="h-4 w-4"></i>
                                    <span x-text="formatDate(selectedMessage?.created_at)"></span>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button @click="deleteMessage(selectedMessage?.id)"
                                    class="px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg transition">
                                <i data-lucide="trash-2" class="h-5 w-5"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="p-6">
                    <div class="prose max-w-none">
                        <p class="text-gray-700 whitespace-pre-wrap" x-text="selectedMessage?.message"></p>
                    </div>
                </div>
            </div>
            
            <div x-show="!selectedMessage" class="bg-white rounded-lg shadow p-12 text-center">
                <i data-lucide="mail-open" class="h-16 w-16 mx-auto mb-4 text-gray-400"></i>
                <p class="text-gray-500">Select a message to view details</p>
            </div>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();
</script>
@endsection

