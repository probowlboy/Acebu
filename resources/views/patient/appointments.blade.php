@extends('layouts.dashboard')

@section('title', 'My Appointments')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
     x-data="{
         userData: null,
         reminders: [],
        // currently selected reminders filter: 'all' | 'today' | 'tomorrow' | 'week'
        remindersFilter: 'all',
         // raw appointments array (used to build reminders)
         _rawAppointments: [],
         // reminder timers keyed by reminder id
         _reminderTimers: {},
         // persisted shown reminders to avoid duplicates
         _shownReminders: {},
         // periodic sweep id
         _reminderSweepId: null,
         // transient in-page reminder alert
         reminderAlert: { visible: false, message: '' },
        appointments: [],
        pendingAppointments: [],
         upcomingAppointments: [],
         todayAppointments: [],
         services: [],
         _servicesFetchAttempted: false,
         error: null,
         loading: true,
         showBookingModal: false,
         // modal displayed after successful booking
         showSuccessModal: false,
         successMessage: '',
         successMessageHtml: '',
         // modal for the `Please select at least one service` confirmation
         showSelectServiceModal: false,
         selectServiceMessage: '',
         selectServiceMessageHtml: '',
        filterStatus: 'all',
        searchQuery: '',
        currentPage: 1,
        itemsPerPage: 1000,
         selectedServices: [],
         timeSlots: ['9–10 AM', '10–11 AM', '11–12 PM', '1–2 PM', '2–4 PM'],
         // keep only coarse timeSlots list
         slotMap: {
             '9–10 AM': '09:00',
             '10–11 AM': '10:00',
             '11–12 PM': '11:00',
             '1–2 PM': '13:00',
             '2–4 PM': '14:00',
             // new granular times
            // original mapping only for coarse slots
         },
         selectedSlot: '',
         // modal for the `Please select date and time` confirmation
         showSelectDateTimeModal: false,
         selectDateTimeMessage: '',
         selectDateTimeMessageHtml: '',
        // Cancel confirmation modal
        showCancelModal: false,
        cancelAppointmentPendingId: null,
        cancelAppointmentPendingTitle: '',
        isCanceling: false,
        // Slots that are unavailable by default (kept empty; date-specific rules applied in isSlotDisabled)
        disabledSlots: [],
         calendarDays: [],
         bookingsPerDay: {},
         currentMonth: new Date().getMonth(),
         currentYear: new Date().getFullYear(),
         selectedDateMeta: null,
         weekDays: ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'],
         bookingForm: {
             service_name: '',
             description: '',
             appointment_date: '',
             appointment_time: '',
             notes: ''
         },
         async init() {
             // Check if user is admin and redirect
             const isAdmin = localStorage.getItem('isAdmin') === 'true';
             if (isAdmin) {
                 window.location.href = '/admin/admindashboard';
                 return;
             }
             
                await Promise.all([this.fetchAppointments(), this.fetchServices()]);
                // load shown reminders from storage, then build and schedule reminders
                this.loadShownReminders();
                this.buildRemindersFromAppointments();
                this.scheduleReminders();
                // If there are appointments for today, default to the Today filter on load
                try {
                    if (Array.isArray(this.todayAppointments) && this.todayAppointments.length > 0) {
                        this.filterStatus = 'today';
                    }
                } catch (e) { console.error('Error setting default filter:', e); }
                // start periodic sweep (every 60 seconds) if not already running
                try { if (!this._reminderSweepId) { this._reminderSweepId = setInterval(() => this.periodicReminderSweep(), 60 * 1000); window.addEventListener('beforeunload', () => this.clearReminders()); } } catch (e) { console.error(e); }
                this.generateCalendar();
         },
         async fetchServices() {
             try {
                 if (this._servicesFetchAttempted) return;
                 this._servicesFetchAttempted = true;
                 const token = localStorage.getItem('token');
                 if (!token) return;
                 
                 const response = await window.apiUtils.fetch('{{ url('/api/patient/services') }}', {
                     method: 'GET',
                     headers: {
                         'Authorization': `Bearer ${token}`,
                         'Accept': 'application/json'
                     },
                     credentials: 'include'
                 });
                 
                 if (response && response.ok) {
                     const data = await response.json();
                     this.services = Array.isArray(data) ? data : [];
                 }
             } catch (error) {
                 console.error('Error fetching services:', error);
             }
         },
        async fetchAppointments() {
             try {
                 this.loading = true;
                // Reset collections to avoid stale or duplicate data during re-fetch
                this.appointments = [];
                this._rawAppointments = [];
                this.upcomingAppointments = [];
                this.todayAppointments = [];
                this.pendingAppointments = [];
                this.completedAppointments = [];
                this.cancelledAppointments = [];
                 const currentUser = localStorage.getItem('currentUser');
                 const token = localStorage.getItem('token');
                 
                 if (!currentUser || !token) {
                     window.location.href = '{{ route('patient.login') }}';
                     return;
                 }
                 
                 this.userData = JSON.parse(currentUser);
                 
                 const response = await window.apiUtils.fetch('{{ url('/api/patient/appointments') }}', {
                     method: 'GET',
                     headers: {
                         'Authorization': `Bearer ${token}`,
                         'Accept': 'application/json'
                     },
                     credentials: 'include'
                 });
                 
                if (!response) {
                    console.warn('Appointments fetch failed: no response');
                    this.error = 'Network error. Please try again.';
                    return;
                }
                if (response.status === 401) {
                    // Unauthenticated - redirect to login
                    localStorage.removeItem('token');
                    window.location.href = '{{ route('patient.login') }}';
                    return;
                }
                if (!response.ok) {
                    console.warn('Appointments fetch failed or returned non-ok response', response);
                    this.appointments = [];
                    this.upcomingAppointments = [];
                    this.todayAppointments = [];
                    this.buildBookingsMap();
                    this.generateCalendar();
                    this.error = null;
                    return;
                }

                const data = await response.json();
                 let fetched = Array.isArray(data) ? data : (data.appointments || []);
                 // Restrict to appointments that belong to the logged-in patient (by id or email)
                 try {
                     const currentUserId = this.userData?.id;
                     const currentUserEmail = this.userData?.email;
                     const onlyMine = (arr) => (arr || []).filter(app => {
                         const appUserId = app.patient?.id || app.patient_id || app.user_id;
                         const appEmail = app.patient?.email || app.email;
                         if (currentUserId && appUserId) return appUserId === currentUserId;
                         if (currentUserEmail && appEmail) return appEmail === currentUserEmail;
                         return false;
                     });
                     fetched = onlyMine(fetched);
                 } catch (e) { /* if anything goes wrong, fall back to returned data */ }

                 // Deduplicate appointments by id to avoid duplicates on reload or multiple fetches
                 const uniqueAppointments = (arr) => {
                     if (!Array.isArray(arr)) return [];
                     const ids = new Set();
                     return arr.filter(a => {
                         if (!a || typeof a.id === 'undefined' || a.id === null) return false;
                         if (ids.has(a.id)) return false;
                         ids.add(a.id);
                         return true;
                     });
                 };

                 const deduped = uniqueAppointments(fetched);
                 this.appointments = deduped;
                 // keep a raw copy for reminders builder
                 // Keep a raw but deduplicated copy for reminders and calendar
                 this._rawAppointments = deduped;
                 
                 const today = this.localIsoDate();
                 
                 this.upcomingAppointments = this.appointments.filter(app => {
                     const appDate = app.appointment_date ? app.appointment_date.split('T')[0] : '';
                     return app.status === 'confirmed' && appDate > today;
                 });
                 
                 this.todayAppointments = this.appointments.filter(app => {
                     const appDate = app.appointment_date ? app.appointment_date.split('T')[0] : '';
                     return app.status === 'confirmed' && appDate === today;
                 });

                 // Pending (list of appointments with 'pending' status only)
                 this.pendingAppointments = this.appointments.filter(app => app.status === 'pending');
                this.completedAppointments = this.appointments.filter(app => app.status === 'completed');
                this.cancelledAppointments = this.appointments.filter(app => app.status === 'cancelled');
                 
                this.buildBookingsMap();
                // rebuild reminders and reschedule when appointments change
                this.buildRemindersFromAppointments();
                this.scheduleReminders();
                 this.generateCalendar();
                 this.error = null;
                 
             } catch (error) {
                 if (error && error.name !== 'AbortError') {
                     console.error('Error fetching appointments:', error);
                     this.error = null;
                 }
             } finally {
                 this.loading = false;
             }
         },
         buildBookingsMap() {
             const map = {};
             this.appointments.forEach(app => {
                 if (!app.appointment_date) return;
                 if (app.status === 'cancelled') return;
                 const dateKey = app.appointment_date.split('T')[0];
                 map[dateKey] = (map[dateKey] || 0) + 1;
             });
             this.bookingsPerDay = map;
         },
         generateCalendar() {
             const today = new Date();
             today.setHours(0, 0, 0, 0);
             const firstDay = new Date(this.currentYear, this.currentMonth, 1).getDay();
             const daysInMonth = new Date(this.currentYear, this.currentMonth + 1, 0).getDate();
             const calendar = [];

             for (let i = 0; i < firstDay; i++) {
                 calendar.push({ date: null });
             }

             for (let day = 1; day <= daysInMonth; day++) {
                 const dateObj = new Date(this.currentYear, this.currentMonth, day);
                 dateObj.setHours(0, 0, 0, 0);
                 const iso = `${dateObj.getFullYear()}-${String(dateObj.getMonth()+1).padStart(2,'0')}-${String(dateObj.getDate()).padStart(2,'0')}`;
                 const count = this.bookingsPerDay[iso] || 0;
                 const isPast = dateObj < today;
                 calendar.push({
                     date: dateObj,
                     iso,
                     label: day,
                     count,
                     isPast,
                     status: this.getStatus(count)
                 });
             }

             this.calendarDays = calendar;
         },
        getStatus(count) {
             // Green for available (< 3 bookings)
             if (count < 3) return { color: 'bg-green-50 border border-green-200 text-green-700', label: `${count} available` };
             // Orange for almost full (3-4 bookings)
             if (count < 5) return { color: 'bg-orange-50 border border-orange-200 text-orange-700', label: `${count} booked` };
             // Red for full (5 bookings)
             return { color: 'bg-red-50 border border-red-200 text-red-700', label: 'Full' };
        },
           getTodayIndicatorColor(count) {
               // Based on max 5 appointments per day - BLUE for today indicator
               // Blue – minimal bookings (0-2 appointments)
               if (count < 2) return 'ring-2 ring-blue-500';
               // Lighter blue – low bookings (2-3 appointments)
               if (count < 4) return 'ring-2 ring-blue-400';
               // Orange – almost full (4 appointments)
               if (count < 5) return 'ring-2 ring-orange-400';
               // Red – completely full (5 appointments)
               return 'ring-2 ring-red-500';
           },
        selectDate(day) {
            if (!day.date) return;
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const selectedDate = new Date(day.date);
            selectedDate.setHours(0, 0, 0, 0);
            
            // Allow selecting past dates visually, but prevent booking submission
            // Prevent selecting fully booked days
            if (day.count >= 5) return;
            this.selectedDateMeta = day;
            this.bookingForm.appointment_date = day.iso;
            this.selectedSlot = ''; // Reset time slot when date changes
            // Fetch booked slots for the selected date from API (all patients, real-time)
            this.fetchBookedSlotsForDate(day.iso);
        },
         previousMonth() {
             if (this.currentMonth === 0) {
                 this.currentMonth = 11;
                 this.currentYear--;
             } else {
                 this.currentMonth--;
             }
             this.generateCalendar();
         },
         nextMonth() {
             if (this.currentMonth === 11) {
                 this.currentMonth = 0;
                 this.currentYear++;
             } else {
                 this.currentMonth++;
             }
             this.generateCalendar();
        },
        currentMonthYear() {
             return new Date(this.currentYear, this.currentMonth).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        },
        currentDateTime() {
            const now = new Date();
            return `Today: ${now.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })} | ${now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}`;
        },
        getDayClasses(day) {
            if (!day.date) return 'cursor-default';
            const todayIso = this.localIsoDate();
            const isToday = day.iso === todayIso;
            const base = `rounded-2xl py-3 flex flex-col items-center gap-1 text-sm font-semibold ${day.status.color}`;
            const disabled = day.count >=5 || day.iso < todayIso ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer hover:bg-white';
            const selected = this.selectedDateMeta && day.iso === this.selectedDateMeta.iso ? 'bg-blue-600 text-white ring-2 ring-blue-600 shadow-md' : '';
            const todayRing = isToday && !(this.selectedDateMeta && day.iso === this.selectedDateMeta.iso) ? this.getTodayIndicatorColor(day.count) : '';
            return `${base} ${disabled} ${selected} ${todayRing}`;
        },
        totalFiltered() {
            return this.getFilteredAppointments().length;
        },
        totalPages() {
            return Math.max(1, Math.ceil(this.totalFiltered() / this.itemsPerPage));
        },
        getDisplayAppointments() {
            if (this.loading) return [];
            const items = this.getFilteredAppointments();
            const start = (this.currentPage - 1) * this.itemsPerPage;
            return items.slice(start, start + this.itemsPerPage);
        },
        getFilteredAppointments() {
            const q = this.searchQuery ? this.searchQuery.trim().toLowerCase() : '';

            const match = (app) => {
                if (!q) return true;
                const name = (app.service_name || app.service?.name || '').toLowerCase();
                const date = (app.appointment_date || '').toLowerCase();
                const status = (app.status || '').toLowerCase();
                return name.includes(q) || date.includes(q) || status.includes(q);
            };

            if (this.loading) return [];
            if (this.filterStatus === 'all') {
                return this.appointments.filter(a => a.status !== 'completed' && a.status !== 'cancelled').filter(match);
            }
            if (this.filterStatus === 'upcoming') {
                return this.upcomingAppointments.filter(match);
            }
            if (this.filterStatus === 'today') {
                return this.todayAppointments.filter(match);
            }
            if (this.filterStatus === 'pending') {
                return this.pendingAppointments.filter(match);
            }
            if (this.filterStatus === 'completed') {
                return this.appointments.filter(a => a.status === 'completed').filter(match);
            }
            if (this.filterStatus === 'cancelled') {
                return this.appointments.filter(a => a.status === 'cancelled').filter(match);
            }
            return [];
        },
         formatDate(dateString) {
             if (!dateString) return 'N/A';
            const d = this.parseLocalISO(dateString);
            if (!d) return 'N/A';
            return d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
         },
        formatTime(dateString) {
            if (!dateString) return 'N/A';
            const d = this.parseLocalISO(dateString);
            if (!d) return 'N/A';
            return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        },
        parseLocalISO(dateString) {
            // Parse an ISO-like datetime string as local time to avoid cross-browser
            // timezone interpretation differences (treat stored YYYY-MM-DDTHH:MM:SS as local)
            if (!dateString) return null;
            try {
                const parts = String(dateString).split('T');
                if (parts.length === 0) return null;
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
        localIsoString(dt) {
            // return local ISO-like string without timezone (YYYY-MM-DDTHH:MM:SS)
            if (!dt || !(dt instanceof Date)) return '';
            const y = dt.getFullYear();
            const m = String(dt.getMonth() + 1).padStart(2, '0');
            const d = String(dt.getDate()).padStart(2, '0');
            const hh = String(dt.getHours()).padStart(2, '0');
            const mm = String(dt.getMinutes()).padStart(2, '0');
            const ss = String(dt.getSeconds()).padStart(2, '0');
            return `${y}-${m}-${d}T${hh}:${mm}:${ss}`;
        },
        // local YYYY-MM-DD (browser local time) to avoid UTC offset errors
        localIsoDate() {
            const d = new Date();
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        },
        dateIsToday(dateString) {
            const d = this.parseLocalISO(dateString);
            if (!d) return false;
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}` === this.localIsoDate();
        },
        dateIsTomorrow(dateString) {
            const d = this.parseLocalISO(dateString);
            if (!d) return false;
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const y = tomorrow.getFullYear();
            const m = String(tomorrow.getMonth() + 1).padStart(2, '0');
            const day = String(tomorrow.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}` === `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        },
        dateIsThisWeek(dateString) {
            const d = this.parseLocalISO(dateString);
            if (!d) return false;
            try {
                const now = new Date();
                const day = now.getDay();
                const diffToMonday = (day + 6) % 7;
                const start = new Date(now);
                start.setDate(now.getDate() - diffToMonday);
                start.setHours(0,0,0,0);
                const end = new Date(start);
                end.setDate(start.getDate() + 6);
                end.setHours(23,59,59,999);
                const check = new Date(d.getFullYear(), d.getMonth(), d.getDate());
                check.setHours(0,0,0,0);
                return check.getTime() >= start.getTime() && check.getTime() <= end.getTime();
            } catch (e) { return false; }
        },
        dateMatchesFilter(dateString, filter) {
            if (!dateString) return false;
            if (!filter || filter === 'all') return true;
            if (filter === 'today') return this.dateIsToday(dateString);
            if (filter === 'tomorrow') return this.dateIsTomorrow(dateString);
            if (filter === 'week') return this.dateIsThisWeek(dateString);
            return true;
        },
        hasVisibleReminders() {
            try {
                const f = this.remindersFilter;
                const hasToday = (this.appointments || []).some(app => app.appointment_date && this.dateMatchesFilter(app.appointment_date, f) && (f === 'all' ? true : (app.status === 'confirmed')));
                if (hasToday) return true;
                const hasUpcoming = (this.upcomingAppointments || []).some(app => this.dateMatchesFilter(app.appointment_date, f));
                if (hasUpcoming) return true;
                const hasReminders = (this.reminders || []).some(rem => this.dateMatchesFilter(rem.datetime, f));
                return !!hasReminders;
            } catch (e) { return (this.reminders || []).length > 0; }
        },
         getStatusColor(status) {
              if (status === 'confirmed') return 'bg-green-100 text-green-800';
              if (status === 'pending') return 'bg-amber-100 text-amber-800';
              if (status === 'cancelled') return 'bg-red-100 text-red-800';
              if (status === 'completed') return 'bg-blue-100 text-blue-800';
              return 'bg-gray-100 text-gray-800';
          },
        displayStatus(status) {
            if (!status) return '';
            // Show 'Pending' to patients when the backend status is 'confirmed' or 'pending'
            if (status === 'confirmed' || status === 'pending') return 'Pending';
            return status.charAt(0).toUpperCase() + status.slice(1);
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

                // If backend provided a total and it's authoritative (not estimated), show it
                if (appointment.total_price !== undefined && appointment.total_price !== null && !appointment.price_is_estimated) {
                    return this.formatPrice(appointment.total_price);
                }

                // Attempt to compute an estimate locally
                let sum = 0;
                let computed = false;

                if (Array.isArray(appointment.services) && appointment.services.length > 0) {
                    sum = appointment.services.reduce((s, svc) => {
                        const p = parseFloat(svc?.price ?? 0);
                        return s + (Number.isNaN(p) ? 0 : p);
                    }, 0);
                    computed = true;
                }

                if (!computed && appointment.service && (appointment.service.price !== undefined && appointment.service.price !== null)) {
                    const p = parseFloat(appointment.service.price);
                    if (!Number.isNaN(p)) { sum = p; computed = true; }
                }

                if (!computed && typeof appointment.service_name === 'string' && appointment.service_name.includes(',')) {
                    const names = appointment.service_name.split(',').map(s => s.trim().toLowerCase()).filter(Boolean);
                    if (Array.isArray(this.services) && this.services.length > 0 && names.length > 0) {
                        let ssum = 0; let found = false;
                        const map = (this.services || []).reduce((m, svc) => { m[(svc.name||'').toLowerCase()] = svc; return m; }, {});
                        names.forEach(n => {
                            const s = map[n];
                            const p = s && (s.price !== undefined && s.price !== null) ? parseFloat(s.price) : 0;
                            if (!Number.isNaN(p)) { ssum += p; found = true; }
                        });
                        if (found) { sum = ssum; computed = true; }
                    } else if (this.services.length === 0 && !this._servicesFetchAttempted) {
                        // Try fetching services so we can compute a correct estimate
                        this.fetchServices();
                    }
                }

                // If nothing computed from services, but total_price exists, use it as fallback
                if (!computed && appointment.total_price !== undefined && appointment.total_price !== null) {
                    const p = parseFloat(appointment.total_price);
                    if (!Number.isNaN(p)) { sum = p; computed = true; }
                }

                const formatted = this.formatPrice(sum);
                const isEstimated = !!appointment.price_is_estimated || !computed || appointment.total_price === undefined || appointment.total_price === null || Number(appointment.total_price) !== sum;
                return isEstimated ? `${formatted} (estimated)` : formatted;
            } catch (e) { return `${this.formatPrice(0)} (estimated)`; }
        },

        totalPrice() {
            try {
                const items = this.getFilteredAppointments();
                return items.reduce((sum, a) => {
                    const p = parseFloat(a.service?.price ?? a.price ?? 0);
                    return sum + (Number.isNaN(p) ? 0 : p);
                }, 0);
            } catch (e) { return 0; }
        },
        capitalize(s) {
            if (!s) return '';
            return s.charAt(0).toUpperCase() + s.slice(1);
        },
         async cancelAppointment(appointmentId) {
             // Show the modal to confirm cancellation instead of using browser confirm
             this.cancelAppointmentPendingId = appointmentId;
             // For clarity, get the appointment title to display (if available)
             const appointment = this.appointments.find(a => a.id === appointmentId);
             this.cancelAppointmentPendingTitle = appointment ? (appointment.service_name || 'Appointment') : 'Appointment';
             this.showCancelModal = true;
         },

        async confirmCancelAppointment() {
            const appointmentId = this.cancelAppointmentPendingId;
            if (!appointmentId) return;
            this.isCanceling = true;
            try {
                    const token = localStorage.getItem('token');
                    const response = await window.apiUtils.fetch(`{{ url('/api/patient/appointments') }}/${appointmentId}/cancel`, {
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
                        throw new Error('Network error. Please try again.');
                    }

                    if (!response.ok) {
                        let body = null;
                        try { body = await response.json(); } catch (e) { /* ignore */ }
                        const msg = body && body.message ? body.message : 'Failed to cancel appointment';
                        throw new Error(msg);
                    }

                this.showCancelModal = false;
                this.cancelAppointmentPendingId = null;
                // Optimistic UI update: set appointment locally as cancelled before refetch
                try { const localApp = (this.appointments || []).find(a => a.id === appointmentId); if (localApp) localApp.status = 'cancelled'; } catch (e) {}
                this.isCanceling = false;
                await this.fetchAppointments();
                // Show the existing success modal to keep styling consistent
                this.successMessage = 'Appointment cancelled successfully';
                this.successMessageHtml = 'Appointment cancelled<br>successfully';
                this.showSuccessModal = true;
            } catch (error) {
                if (error && error.name !== 'AbortError') {
                    console.error('Error cancelling appointment:', error);
                    this.showCancelModal = false;
                    this.cancelAppointmentPendingId = null;
                    this.error = 'Failed to cancel appointment. Please try again.';
                }
            } finally {
                this.isCanceling = false;
            }
        },
        openBookingModal() {
            this.bookingForm.appointment_date = '';
            this.bookingForm.appointment_time = '';
            this.selectedSlot = '';
            this.selectedDateMeta = null;
            this.selectedServices = [];
            this.bookingForm.description = '';
            this.disabledSlots = [];

            // reset calendar to current month/year and highlight today if available
            const today = new Date();
            const todayIso = this.localIsoDate();
            this.currentMonth = today.getMonth();
            this.currentYear = today.getFullYear();
            this.generateCalendar();
            const todayDay = this.calendarDays.find(d => d.iso === todayIso);
            if (todayDay && todayDay.count < 5) {
                this.selectedDateMeta = todayDay;
                this.bookingForm.appointment_date = todayDay.iso;
                // Fetch booked slots for the initial selected date (all patients, real-time)
                this.fetchBookedSlotsForDate(todayDay.iso);
            }

            this.showBookingModal = true;
        },
         closeBookingModal() {
             this.showBookingModal = false;
             this.selectedServices = [];
             this.selectedSlot = '';
             this.selectedDateMeta = null;
            // reset disabled slots when modal closes
            this.disabledSlots = [];
             this.bookingForm = {
                 service_name: '',
                 description: '',
                 appointment_date: '',
                 appointment_time: '',
                 notes: ''
             };
         },

        computeDisabledSlotsForDate(iso) {
            // Build disabled slots for a specific ISO date (yyyy-mm-dd)
            const disabled = [];
            try {
                this.appointments.forEach(app => {
                    if (!app.appointment_date) return;
                    if (app.status !== 'confirmed') return;
                    const appDateOnly = app.appointment_date.split('T')[0];
                    if (appDateOnly !== iso) return;

                    // extract HH:MM from appointment datetime
                    const timePart = (app.appointment_date.split('T')[1] || '').slice(0,5);
                    // find matching slot label from slotMap
                    for (const [label, hh] of Object.entries(this.slotMap)) {
                        if (!hh) continue;
                        if (hh === timePart) {
                            if (!disabled.includes(label)) disabled.push(label);
                            break;
                        }
                    }
                });
            } catch (e) {
                console.error('Error computing disabled slots:', e);
            }
            this.disabledSlots = disabled;
        },
        buildRemindersFromAppointments() {
            try {
                this.reminders = [];
                const now = Date.now();
                const raw = Array.isArray(this._rawAppointments) ? this._rawAppointments : (Array.isArray(this.appointments) ? this.appointments : []);
                raw.forEach(app => {
                    if (!app || !app.appointment_date) return;
                    if (app.status === 'cancelled') return;

                    // Filter to only patient's own appointments
                    if (this.userData) {
                        const appUserId = app.patient?.id || app.patient_id || app.user_id;
                        const appEmail = app.patient?.email || app.email;
                        const currentUserId = this.userData.id;
                        const currentEmail = this.userData.email;
                        const belongsToPatient = (appUserId && appUserId === currentUserId) || (appEmail && appEmail === currentEmail);
                        if (!belongsToPatient) return;
                    }

                    const appt = this.parseLocalISO(app.appointment_date);
                    if (!appt) return;

                    // 1 day before
                    const oneDayBefore = new Date(appt.getTime() - 24 * 60 * 60 * 1000);
                    if (oneDayBefore.getTime() > now) {
                        this.reminders.push({
                            id: `appt-${app.id}-1d`,
                            title: `Appointment tomorrow: ${app.service_name || 'Appointment'}`,
                            datetime: this.localIsoString(oneDayBefore),
                            date: this.localIsoString(oneDayBefore).split('T')[0],
                            time: oneDayBefore.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }),
                            appointment: app,
                            type: '1d'
                        });
                    }

                    // 1 hour before
                    const oneHourBefore = new Date(appt.getTime() - 60 * 60 * 1000);
                    if (oneHourBefore.getTime() > now) {
                        this.reminders.push({
                            id: `appt-${app.id}-1h`,
                            title: `Appointment in 1 hour: ${app.service_name || 'Appointment'}`,
                            datetime: this.localIsoString(oneHourBefore),
                            date: this.localIsoString(oneHourBefore).split('T')[0],
                            time: oneHourBefore.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }),
                            appointment: app,
                            type: '1h'
                        });
                    }
                });
            } catch (e) {
                console.error('Error building reminders:', e);
                this.reminders = [];
            }
        },
        scheduleReminders() {
            try {
                // clear existing timers
                Object.values(this._reminderTimers || {}).forEach(id => clearTimeout(id));
                this._reminderTimers = {};

                const now = Date.now();
                this.reminders.forEach(rem => {
                    // skip if already shown
                    if (this.isReminderShown && this.isReminderShown(rem.id)) return;
                    const remDate = this.parseLocalISO(rem.datetime);
                    if (!remDate) return;
                    const ms = remDate.getTime() - now;
                    if (ms <= 0) return; // skip past
                    // Cap scheduling to a reasonable window (e.g., 30 days)
                    if (ms > 30 * 24 * 60 * 60 * 1000) return;

                    const timerId = setTimeout(() => {
                        try {
                            this.triggerReminder(rem);
                        } finally {
                            delete this._reminderTimers[rem.id];
                        }
                    }, ms);

                    this._reminderTimers[rem.id] = timerId;
                });

                // request notification permission if not decided yet (non-blocking)
                if (window.Notification && Notification.permission === 'default') {
                    Notification.requestPermission().catch(() => {});
                }
            } catch (e) {
                console.error('Error scheduling reminders:', e);
            }
        },
        triggerReminder(reminder) {
            try {
                // in-page toast/alert
                this.reminderAlert.message = reminder.title;
                this.reminderAlert.visible = true;
                setTimeout(() => this.reminderAlert.visible = false, 8000);

                // desktop notification
                if (window.Notification) {
                    if (Notification.permission === 'granted') {
                        try { new Notification('Reminder', { body: reminder.title }); } catch (e) { console.error(e); }
                    } else if (Notification.permission === 'default') {
                        Notification.requestPermission().then(p => {
                            if (p === 'granted') {
                                try { new Notification('Reminder', { body: reminder.title }); } catch (e) { console.error(e); }
                            }
                        }).catch(() => {});
                    }
                }
                // mark shown so it won't fire again
                try { this.markReminderShown(reminder.id); } catch (e) { console.error(e); }
                console.info('Reminder triggered:', reminder.title);
            } catch (e) {
                console.error('Error triggering reminder:', e);
            }
        },
        clearReminders() {
            try { Object.values(this._reminderTimers || {}).forEach(id => clearTimeout(id)); this._reminderTimers = {}; if (this._reminderSweepId) { clearInterval(this._reminderSweepId); this._reminderSweepId = null; } } catch(e){}
        },
        // Persisted reminder helpers (patient)
        loadShownReminders() {
            try {
                const raw = localStorage.getItem('patient_shownReminders');
                this._shownReminders = raw ? JSON.parse(raw) : {};
            } catch (e) { this._shownReminders = {}; }
        },
        saveShownReminders() {
            try { localStorage.setItem('patient_shownReminders', JSON.stringify(this._shownReminders || {})); } catch (e) {}
        },
        isReminderShown(id) {
            return !!(this._shownReminders && this._shownReminders[id]);
        },
        markReminderShown(id) {
            try {
                this._shownReminders = this._shownReminders || {};
                this._shownReminders[id] = Date.now();
                this.saveShownReminders();
            } catch (e) { console.error(e); }
        },
        periodicReminderSweep() {
            try {
                const now = Date.now();
                (this.reminders || []).forEach(rem => {
                    if (this.isReminderShown(rem.id)) return;
                    const remDate = this.parseLocalISO(rem.datetime);
                    if (!remDate) return;
                    if (remDate.getTime() <= now) {
                        this.triggerReminder(rem);
                    }
                });
            } catch (e) { console.error('Reminder sweep error', e); }
        },
          toggleService(serviceId) {
             const index = this.selectedServices.findIndex(s => s.id === serviceId);
             if (index > -1) {
            this.selectedServices.splice(index, 1);
             } else {
                 const service = this.services.find(s => s.id === serviceId);
                 if (service) {
                     this.selectedServices.push(service);
                 }
             }
             this.updateDescription();
         },
         isServiceSelected(serviceId) {
             return this.selectedServices.some(s => s.id === serviceId);
         },
         updateDescription() {
             if (this.selectedServices.length === 0) {
                 this.bookingForm.description = '';
                 this.bookingForm.service_name = '';
                 return;
             }
             
             // Combine service names
             const serviceNames = this.selectedServices.map(s => s.name).join(', ');
             this.bookingForm.service_name = serviceNames;
             
             // Combine descriptions
             const descriptions = this.selectedServices
                 .map(s => s.description || s.name)
                 .filter(d => d)
                 .join(' | ');
             this.bookingForm.description = descriptions;
          },
         totalSelectedPrice() {
             try {
                 return this.selectedServices.reduce((sum, s) => {
                     const p = parseFloat(s.price || s.service?.price || 0);
                     return sum + (Number.isNaN(p) ? 0 : p);
                 }, 0);
             } catch (e) { return 0; }
         },
          selectSlot(slot) {
             // ignore clicks on disabled slots
             if (this.isSlotDisabled(slot)) return;

             // allow changing selection, but only one slot at a time
             this.selectedSlot = slot;
             this.bookingForm.appointment_time = this.slotMap[slot] || '';
          },

        isSlotDisabled(slot) {
             // Check if slot is booked (disabled) from server
             if (this.disabledSlots && this.disabledSlots.includes(slot)) return true;

             // Resolve the target date to consider for slot start time
             const dateIso = this.bookingForm.appointment_date || (this.selectedDateMeta && this.selectedDateMeta.iso) || this.localIsoDate();
             if (!dateIso) return false;
             const dateOnly = (dateIso || '').split('T')[0];

             // Map slot label to start HH:MM
             const hh = this.slotMap[slot];
             if (!hh) return false;

             // Build start datetime using local ISO like YYYY-MM-DDTHH:MM:SS
             const startIso = `${dateOnly}T${hh}:00`;
             const startDt = this.parseLocalISO(startIso);
             if (!startDt) return false;

             // If slot starts at or before now, disable it (user requirement)
             const now = new Date();
             return startDt <= now;
         },

         async fetchBookedSlotsForDate(iso) {
             try {
                 const token = localStorage.getItem('token');
                 if (!token) return;

                 const response = await window.apiUtils.fetch(`{{ url('/api/patient/appointments/booked-slots') }}/${iso}`, {
                     method: 'GET',
                     headers: {
                         'Authorization': `Bearer ${token}`,
                         'Accept': 'application/json'
                     },
                     credentials: 'include'
                 });

                 if (!response || !response.ok) {
                     console.error('Failed to fetch booked slots');
                     this.disabledSlots = [];
                     return;
                 }

                 const data = await response.json();
                 const bookedTimes = data.booked_slots || [];

                 // Map booked HH:MM times to slot labels
                 const disabled = [];
                 bookedTimes.forEach(time => {
                     for (const [label, hh] of Object.entries(this.slotMap)) {
                         if (hh === time) {
                             if (!disabled.includes(label)) disabled.push(label);
                             break;
                         }
                     }
                 });

                 this.disabledSlots = disabled;
             } catch (error) {
                 console.error('Error fetching booked slots:', error);
                 this.disabledSlots = [];
             }
         },
          async submitBooking() {
              if (this.selectedServices.length === 0) {
                  // show the modal confirmation instead of the browser alert
                  this.selectServiceMessage = 'Please select at least one service';
                  this.selectServiceMessageHtml = 'Please select at least one service';
                  this.showSelectServiceModal = true;
                  return;
              }
              if (!this.bookingForm.appointment_date || !this.bookingForm.appointment_time) {
                  this.selectDateTimeMessage = 'Please select date and time';
                  this.selectDateTimeMessageHtml = 'Please select date<br>and time';
                  this.showSelectDateTimeModal = true;
                  return;
              }

              // Only allow one appointment per day for this patient
              const selectedDate = this.bookingForm.appointment_date;
              const hasAppointmentSameDay = this.appointments.some(app => {
                  if (!app.appointment_date) return false;
                  if (app.status === 'cancelled') return false;
                  const appDateOnly = app.appointment_date.split('T')[0];
                  return appDateOnly === selectedDate;
              });

              if (hasAppointmentSameDay) {
                  alert('You already have an appointment for this day. Please choose another date.');
                  return;
              }
              
              try {
                  const token = localStorage.getItem('token');
                  const appointmentDateTime = `${this.bookingForm.appointment_date}T${this.bookingForm.appointment_time}:00`;
                  
                  const response = await fetch('{{ url('/api/patient/appointments') }}', {
                      method: 'POST',
                      headers: {
                          'Authorization': `Bearer ${token}`,
                          'Accept': 'application/json',
                          'Content-Type': 'application/json',
                          'X-CSRF-TOKEN': '{{ csrf_token() }}'
                      },
                      credentials: 'include',
                      body: JSON.stringify({
                          service_name: this.bookingForm.service_name,
                          description: this.bookingForm.description,
                          appointment_date: appointmentDateTime,
                          notes: this.bookingForm.notes,
                          total_price: this.totalSelectedPrice()
                      })
                  });
                  
                  if (!response.ok) {
                      let errorBody = null;
                      try {
                          errorBody = await response.json();
                      } catch (e) {
                          // ignore
                      }

                      // Validation errors (422)
                      if (response.status === 422 && errorBody && errorBody.errors) {
                          const firstErr = Object.values(errorBody.errors)[0];
                          const message = Array.isArray(firstErr) ? firstErr[0] : firstErr;
                          alert(message || 'Validation failed. Please check your input.');
                          return;
                      }

                      // Generic error message
                      const serverMessage = errorBody && (errorBody.message || JSON.stringify(errorBody));
                      alert(serverMessage || 'Failed to book appointment. Please try again.');
                      return;
                  }

                  this.closeBookingModal();
                  await this.fetchAppointments();
                  // Show custom success modal instead of browser alert
                  this.successMessage = 'Appointment request submitted';
                  this.successMessageHtml = 'Appointment booked<br>successfully!';
                  this.showSuccessModal = true;
              } catch (error) {
                  console.error('Error booking appointment:', error);
                  alert(error.message || 'Failed to book appointment. Please try again.');
              }
          }
     }">
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">My Appointments</h1>
                <p class="text-sm text-gray-500 mt-1" x-show="userData">Manage your appointments</p>
            </div>
            <div class="flex gap-3 items-center">
                <button @click="openBookingModal()"
                        class="flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors text-sm font-medium">
                    <i data-lucide="calendar-plus" class="h-4 w-4 mr-2"></i>
                    Book Appointment
                </button>
            </div>
        </div>
        <!-- In-page reminder toast -->
        <div x-show="reminderAlert.visible" x-cloak class="fixed top-5 right-5 z-50">
            <div class="bg-yellow-50 border-l-4 border-yellow-500 text-yellow-700 px-4 py-3 rounded shadow-md">
                <p class="font-semibold">Reminder</p>
                <p class="text-sm mt-1" x-text="reminderAlert.message"></p>
            </div>
        </div>

        <!-- Reminders Card removed -->

        <!-- Error Message -->
        <div x-show="error" x-cloak 
             class="bg-red-50 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline" x-text="error"></span>
        </div>

        <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-100">
                <div class="flex items-center">
                    <div class="bg-blue-500 rounded-full p-3">
                        <i data-lucide="clock" class="h-6 w-6 text-white"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Upcoming</p>
                        <p class="text-2xl font-semibold text-gray-900" x-text="!loading ? upcomingAppointments.length : 0"></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-100">
                <div class="flex items-center">
                    <div class="bg-amber-500 rounded-full p-3">
                        <i data-lucide="clock" class="h-6 w-6 text-white"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Pending</p>
                        <p class="text-2xl font-semibold text-gray-900" x-text="!loading ? pendingAppointments.length : 0"></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-100">
                <div class="flex items-center">
                    <div class="bg-green-500 rounded-full p-3">
                        <i data-lucide="calendar" class="h-6 w-6 text-white"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Today</p>
                        <p class="text-2xl font-semibold text-gray-900" x-text="!loading ? todayAppointments.length : 0"></p>
                    </div>
                </div>
            </div>

                <!-- Pending Appointments Top Section -->
                <div x-show="!loading" x-cloak class="mt-6">
                    <div class="flex items-center justify-between mb-4">
                        {{-- Pending header removed per request --}}
                        {{-- Total count removed per request --}}
                    </div>

                    <div class="space-y-4">
                        {{-- Pending appointment cards removed per request --}}
                    </div>
                </div>

                <!-- Success Modal (Appointment booked) - Styled to match provided design -->
                 <div x-show="showSuccessModal" x-cloak
                     class="fixed inset-0 bg-gray-900/50 flex items-center justify-center z-[9999] p-4"
                     style="z-index: 9999;"
                     @keydown.escape.window="showSuccessModal = false"
                     @click.self="showSuccessModal = false">
                    <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden flex flex-col p-8 text-center" role="dialog" aria-modal="true" aria-labelledby="success-modal-title">
                        <!-- Green Check Circle overlapping top -->
                        <div class="absolute -top-8 left-1/2 transform -translate-x-1/2">
                            <div class="h-14 w-14 rounded-full bg-emerald-100 flex items-center justify-center shadow-sm">
                                <i data-lucide="check" class="h-6 w-6 text-emerald-600"></i>
                            </div>
                        </div>

                        <!-- Close X -->
                        <button type="button" @click="showSuccessModal = false" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 transition">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>

                        <!-- Content -->
                        <div class="mt-6 pt-2">
                            <h3 id="success-modal-title" class="text-xl font-bold text-gray-900 leading-snug" x-html="successMessageHtml"></h3>
                            <p class="text-sm text-gray-600 mt-3 max-w-[36rem] mx-auto">Your appointment has been successfully booked. You will receive a confirmation email shortly with all the details.</p>
                        </div>

                        <!-- Button -->
                        <div class="mt-6 flex justify-center">
                            <button type="button" @click="showSuccessModal = false" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300">Close</button>
                        </div>
                    </div>
                </div>
                <!-- Select Service Modal (Please select at least one service) - same style as success modal -->
                 <div x-show="showSelectServiceModal" x-cloak
                     class="fixed inset-0 bg-gray-900/50 flex items-center justify-center z-[9999] p-4"
                     style="z-index: 9999;"
                     @keydown.escape.window="showSelectServiceModal = false"
                     @click.self="showSelectServiceModal = false">
                    <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden flex flex-col p-8 text-center" role="dialog" aria-modal="true" aria-labelledby="select-service-modal-title">
                        <!-- Green Check Circle overlapping top (keep consistent style) -->
                        <div class="absolute -top-8 left-1/2 transform -translate-x-1/2">
                            <div class="h-14 w-14 rounded-full bg-emerald-100 flex items-center justify-center shadow-sm">
                                <i data-lucide="check" class="h-6 w-6 text-emerald-600"></i>
                            </div>
                        </div>

                        <!-- Close X -->
                        <button type="button" @click="showSelectServiceModal = false" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 transition">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>

                        <!-- Content -->
                        <div class="mt-6 pt-2">
                            <h3 id="select-service-modal-title" class="text-xl font-bold text-gray-900 leading-snug" x-html="selectServiceMessageHtml"></h3>
                            <p class="text-sm text-gray-600 mt-3 max-w-[36rem] mx-auto">Please choose at least one service to continue with booking.</p>
                        </div>

                        <!-- Button -->
                        <div class="mt-6 flex justify-center">
                            <button type="button" @click="showSelectServiceModal = false" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300">Close</button>
                        </div>
                    </div>
                </div>
                <!-- Select Date and Time Modal (Please select date and time) - same style as success modal -->
                <div x-show="showSelectDateTimeModal" x-cloak
                     class="fixed inset-0 bg-gray-900/50 flex items-center justify-center z-[9999] p-4"
                     style="z-index: 9999;"
                     @keydown.escape.window="showSelectDateTimeModal = false"
                     @click.self="showSelectDateTimeModal = false">
                    <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden flex flex-col p-8 text-center" role="dialog" aria-modal="true" aria-labelledby="select-datetime-modal-title">
                        <!-- Green Check Circle overlapping top (keep consistent style) -->
                        <div class="absolute -top-8 left-1/2 transform -translate-x-1/2">
                            <div class="h-14 w-14 rounded-full bg-emerald-100 flex items-center justify-center shadow-sm">
                                <i data-lucide="check" class="h-6 w-6 text-emerald-600"></i>
                            </div>
                        </div>

                        <!-- Close X -->
                        <button type="button" @click="showSelectDateTimeModal = false" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 transition">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>

                        <!-- Content -->
                        <div class="mt-6 pt-2">
                            <h3 id="select-datetime-modal-title" class="text-xl font-bold text-gray-900 leading-snug" x-html="selectDateTimeMessageHtml"></h3>
                            <p class="text-sm text-gray-600 mt-3 max-w-[36rem] mx-auto">Please select a date and time before booking your appointment.</p>
                        </div>

                        <!-- Button -->
                        <div class="mt-6 flex justify-center">
                            <button type="button" @click="showSelectDateTimeModal = false" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300">Close</button>
                        </div>
                    </div>
                </div>
                <!-- Cancel Confirmation Modal (Are you sure you want to cancel this appointment?) -->
                 <div x-show="showCancelModal" x-cloak
                     class="fixed inset-0 bg-gray-900/50 flex items-center justify-center z-[9999] p-4"
                     style="z-index: 9999;"
                     @keydown.escape.window="(showCancelModal = false, cancelAppointmentPendingId = null)"
                     @click.self="(showCancelModal = false, cancelAppointmentPendingId = null)">
                    <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden flex flex-col p-8 text-center" role="dialog" aria-modal="true" aria-labelledby="cancel-modal-title">
                        <!-- Red X Circle overlapping top -->
                        <div class="absolute -top-8 left-1/2 transform -translate-x-1/2">
                            <div class="h-14 w-14 rounded-full bg-red-100 flex items-center justify-center shadow-sm">
                                <i data-lucide="x" class="h-6 w-6 text-red-600"></i>
                            </div>
                        </div>

                        <!-- Close X -->
                        <button type="button" @click="(showCancelModal = false, cancelAppointmentPendingId = null)" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 transition">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>

                        <!-- Content -->
                        <div class="mt-6 pt-2">
                            <h3 id="cancel-modal-title" class="text-xl font-bold text-gray-900 leading-snug">Are you sure you want to cancel this appointment?</h3>
                        </div>

                        <!-- Buttons -->
                        <div class="mt-6 flex justify-center gap-3">
                            <button type="button" @click="(showCancelModal = false, cancelAppointmentPendingId = null)" class="px-6 py-2 rounded-lg border-2 border-gray-300 text-gray-700 font-semibold hover:bg-gray-50 transition duration-150">Close</button>
                            <button type="button" @click="confirmCancelAppointment()" :disabled="isCanceling" :class="isCanceling ? 'px-6 py-2 rounded-lg bg-red-400 text-white font-semibold opacity-60 cursor-not-allowed' : 'px-6 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white font-semibold focus:outline-none focus:ring-2 focus:ring-red-300'"> 
                                <span x-show="!isCanceling">Cancel Appointment</span>
                                <span x-show="isCanceling">Cancelling&hellip;</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>        <!-- Filter Tabs -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-100">
            <div class="flex items-center justify-between border-b border-gray-200 px-6">
                <div class="flex">
                    <button @click="filterStatus = 'pending'"
                            :class="filterStatus === 'pending' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600 hover:text-gray-900'"
                            class="px-4 py-4 font-medium text-sm transition-colors">
                        Pending (<span x-text="!loading ? pendingAppointments.length : 0"></span>)
                    </button>
                    <button @click="filterStatus = 'today'"
                            :class="filterStatus === 'today' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600 hover:text-gray-900'"
                            class="px-4 py-4 font-medium text-sm transition-colors">
                        Today (<span x-text="!loading ? todayAppointments.length : 0"></span>)
                    </button>
                    <button @click="filterStatus = 'upcoming'"
                            :class="filterStatus === 'upcoming' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600 hover:text-gray-900'"
                            class="px-4 py-4 font-medium text-sm transition-colors">
                        Upcoming (<span x-text="!loading ? upcomingAppointments.length : 0"></span>)
                    </button>
                    <button @click="filterStatus = 'completed'"
                            :class="filterStatus === 'completed' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600 hover:text-gray-900'"
                            class="px-4 py-4 font-medium text-sm transition-colors">
                        Completed (<span x-text="!loading ? appointments.filter(a => a.status === 'completed').length : 0"></span>)
                    </button>
                    <button @click="filterStatus = 'cancelled'"
                            :class="filterStatus === 'cancelled' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600 hover:text-gray-900'"
                            class="px-4 py-4 font-medium text-sm transition-colors">
                        Cancelled (<span x-text="!loading ? appointments.filter(a => a.status === 'cancelled').length : 0"></span>)
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

        {{-- Calendar removed from appointments page per request; only modals remain --}}

        <!-- Loading State -->
        <div x-show="loading" x-cloak class="text-center py-12">
            <i data-lucide="loader" class="h-8 w-8 animate-spin text-blue-600 mx-auto mb-4"></i>
            <p class="text-gray-600">Loading appointments...</p>
        </div>

        <!-- Empty State -->
        <div x-show="false" x-cloak
             class="bg-white rounded-xl shadow-sm p-12 text-center">
            <i data-lucide="calendar-x" class="h-16 w-16 text-gray-400 mx-auto mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No appointments found</h3>
            <p class="text-gray-600 mb-6">You don't have any active appointments at the moment.</p>
            <button @click="openBookingModal()"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                <i data-lucide="calendar-plus" class="h-4 w-4 mr-2"></i>
                Book an Appointment
            </button>
        </div>

        <!-- Appointments Table: show even when no rows, display No records row -->

        <!-- Appointments List (Admin Table style for Patient) -->
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
                                            <div class="text-xs font-semibold text-gray-900 truncate" x-text="appointment.patient?.name || (userData ? userData.name : 'You')"></div>
                                            <div class="text-xs text-gray-500 mt-1">ID: <span class="font-medium" x-text="appointment.patient?.id || (userData ? userData.id : 'N/A')"></span></div>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-xs text-blue-600 truncate border-r border-gray-200 last:border-r-0" style="width: 16%;">
                                            <a class="text-blue-600 hover:underline truncate block" :href="`mailto:${appointment.patient?.email || ''}`" x-text="appointment.patient?.email || (userData ? userData.email : 'N/A')"></a>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-500 border-r border-gray-200 last:border-r-0" style="width: 16%;">
                                            <div class="font-semibold text-gray-900 text-xs" x-text="formatDate(appointment.appointment_date)"></div>
                                            <div class="text-xs text-gray-500 mt-1" x-text="formatTime(appointment.appointment_date)"></div>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-xs text-gray-900 truncate border-r border-gray-200 last:border-r-0" style="width: 16%;" x-text="appointment.service_name || (appointment.service?.name || '-')"></td>
                                        <td class="px-3 py-3 whitespace-nowrap text-sm font-semibold text-gray-900 text-right border-r border-gray-200 last:border-r-0" style="width: 8%;" x-text="formatAppointmentPrice(appointment)"></td>
                                        <td class="px-3 py-3 whitespace-nowrap border-r border-gray-200 last:border-r-0" style="width: 12%;">
                                            <span :class="getStatusColor(appointment.status) + ' inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold capitalize'" x-text="displayStatus(appointment.status)"></span>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-right" style="width: 12%;">
                                            <div class="flex items-center justify-end gap-2">
                                                <button @click="cancelAppointment(appointment.id)"
                                                        x-show="appointment.status !== 'cancelled' && appointment.status !== 'completed'"
                                                        :disabled="isCanceling && cancelAppointmentPendingId === appointment.id"
                                                        :aria-disabled="isCanceling && cancelAppointmentPendingId === appointment.id ? 'true' : 'false'"
                                                        :class="(isCanceling && cancelAppointmentPendingId === appointment.id) ? 'opacity-50 cursor-not-allowed px-2 py-1 rounded-md bg-red-600 text-white text-xs' : 'px-2 py-1 rounded-md bg-red-600 hover:bg-red-700 text-white text-xs font-semibold'">
                                                    <span x-show="!(isCanceling && cancelAppointmentPendingId === appointment.id)">Cancel</span>
                                                    <span x-show="isCanceling && cancelAppointmentPendingId === appointment.id">Cancelling&hellip;</span>
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

    <!-- Booking Modal -->
    <div x-show="showBookingModal" x-cloak
         class="fixed inset-0 bg-gray-900/50 flex items-center justify-center z-50 p-4"
         @click.self="closeBookingModal()">
        <div class="bg-white rounded-3xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col">
            <!-- Modal Header -->
            <div class="px-8 py-5 border-b border-gray-200 bg-white flex items-start justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">New Appointment</h2>
                    <p class="text-xs text-gray-600 mt-0.5">Choose Time & Services</p>
                </div>
                <button type="button" @click="closeBookingModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <!-- Modal Content -->
            <div class="flex-1 overflow-y-auto">
                <form @submit.prevent="submitBooking()" class="space-y-6 p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Left Column: Calendar -->
                        <div class="lg:col-span-1">
                            <div class="bg-white rounded-2xl shadow-md border border-gray-200 p-5">
                                <div class="flex items-center justify-between mb-5">
                                    <button type="button" class="text-gray-400 hover:text-gray-600 transition" @click="previousMonth">
                                        <i data-lucide="chevron-left" class="h-4 w-4"></i>
                                    </button>
                                    <h3 class="text-sm font-bold text-gray-900" x-text="currentMonthYear()"></h3>
                                    <button type="button" class="text-gray-400 hover:text-gray-600 transition" @click="nextMonth">
                                        <i data-lucide="chevron-right" class="h-4 w-4"></i>
                                    </button>
                                </div>

                                <!-- Weekday headers -->
                                <div class="grid grid-cols-7 gap-0.5 mb-2">
                                    <template x-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day">
                                        <div class="text-center text-xs font-semibold text-gray-600 py-0.5" x-text="day"></div>
                                    </template>
                                </div>

                                <!-- Calendar days -->
                                <div class="grid grid-cols-7 gap-0.5">
                                    <template x-for="(day, index) in calendarDays" :key="index">
                                        <button type="button"
                                            @click="selectDate(day)"
                                            :disabled="!day.date || day.count >= 5"
                                            :class="[
                                                'rounded-lg py-1.5 text-xs font-semibold transition duration-200',
                                                !day.date ? 'bg-transparent border-0 cursor-default' : '',
                                                day.isPast ? 'border-2 border-black opacity-50 text-gray-700 bg-white cursor-not-allowed' : day.count >= 5 ? 'bg-gray-100 text-gray-400 border border-gray-200 cursor-not-allowed opacity-40' : '',
                                                selectedDateMeta && day.date && (day.date.toDateString() === selectedDateMeta.date.toDateString()) && !day.isPast && day.count < 5
                                                    ? 'bg-blue-600 text-white border border-blue-600' 
                                                    : !day.isPast && day.count < 5 ? 'text-gray-700 border border-gray-200 hover:border-blue-400 cursor-pointer'
                                                    : ''
                                            ]">
                                            <span x-text="day.label"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <!-- Service Details Panel Below Calendar -->
                            <div x-show="selectedServices.length > 0" x-cloak class="mt-4 space-y-3">
                                <template x-for="service in selectedServices" :key="service.id">
                                    <div class="p-4 bg-blue-50 rounded-lg border border-blue-200">
                                        <div class="flex items-start justify-between mb-2">
                                            <h4 class="text-sm font-bold text-gray-900" x-text="service.name"></h4>
                                            <button type="button" @click="toggleService(service.id)" class="text-gray-400 hover:text-gray-600">
                                                <i data-lucide="x" class="h-4 w-4"></i>
                                            </button>
                                        </div>
                                        <p class="text-xs text-gray-700 mb-3" x-text="service.description || 'No description available'"></p>
                                        <div class="grid grid-cols-2 gap-2 text-xs">
                                            <div>
                                                <p class="text-gray-600">Duration</p>
                                                <p class="font-semibold text-gray-900" x-text="`${service.duration_minutes || 60} minutes`"></p>
                                            </div>
                                            <div>
                                                <p class="text-gray-600">Price</p>
                                                <p class="font-semibold text-gray-900" x-text="service.price ? `₱${service.price}` : '-' "></p>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <div class="p-3 bg-gray-50 rounded-lg border border-gray-200" x-show="selectedServices.length > 0">
                                    <div class="flex items-center justify-between">
                                        <div class="text-sm text-gray-600">Total Price</div>
                                        <div class="text-lg font-semibold text-gray-900" x-text="formatPrice(totalSelectedPrice())"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Content -->
                        <div class="lg:col-span-2 space-y-5">
                        <!-- Selected Date Display -->
                        <div class="bg-green-50 rounded-xl border border-green-200 p-4 flex items-center gap-3">
                            <i data-lucide="calendar" class="h-5 w-5 text-green-600 flex-shrink-0"></i>
                            <div>
                                <p class="text-xs font-semibold text-green-700">Selected Date</p>
                                <p class="text-base font-bold text-green-900" x-text="selectedDateMeta ? selectedDateMeta.formatted : 'Select a date'"></p>
                            </div>
                        </div>                            <!-- Time Slot Section -->
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-sm font-bold text-gray-900">Select Time Slot</h3>
                                    <p class="text-xs text-blue-600 font-medium">Morning — 9:00 AM to 4:00 PM</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="slot in timeSlots" :key="slot">
                                        <button type="button"
                                            :disabled="isSlotDisabled(slot)"
                                            @click="selectSlot(slot)"
                                            :aria-disabled="isSlotDisabled(slot) ? 'true' : 'false'"
                                            :title="isSlotDisabled(slot) ? 'Unavailable' : ''"
                                            :class="isSlotDisabled(slot)
                                                ? 'rounded-lg py-2 px-3 text-xs font-semibold transition duration-200 bg-gray-100 text-gray-400 border-2 border-gray-200 cursor-not-allowed opacity-60'
                                                : (selectedSlot === slot
                                                    ? 'bg-blue-600 text-white border-2 border-blue-600 rounded-lg py-2 px-3 text-xs font-semibold transition duration-200'
                                                    : 'bg-white text-gray-700 border-2 border-gray-200 hover:border-blue-400 rounded-lg py-2 px-3 text-xs font-semibold transition duration-200')"
                                            class="transition">
                                            <span x-text="slot"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <!-- Services Section -->
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-sm font-bold text-gray-900">Select Services</h3>
                                    <p class="text-xs text-gray-600">Choose up to 3 services — Multi-select</p>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <template x-for="service in services" :key="service.id">
                                        <button type="button"
                                            @click="toggleService(service.id)"
                                            :class="isServiceSelected(service.id)
                                                ? 'border-2 border-blue-600 bg-blue-50'
                                                : 'border-2 border-gray-200 hover:border-blue-400 bg-white'"
                                            class="rounded-lg p-3 cursor-pointer transition duration-200 text-left">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    <p class="font-bold text-gray-900 text-xs" x-text="service.name || 'Service'"></p>
                                                    <p class="text-xs text-gray-600 mt-1.5" x-show="service.description" x-text="service.description?.substring(0, 50) + (service.description?.length > 50 ? '...' : '')"></p>
                                                    <p class="text-xs text-gray-600 mt-0.5">
                                                        <i data-lucide="clock" class="h-3 w-3 inline mr-1"></i>
                                                        <span x-text="`${service.duration_minutes || 60} mins`"></span>
                                                    </p>
                                                </div>
                                                <div
                                                    :class="isServiceSelected(service.id)
                                                        ? 'bg-blue-600 border-blue-600'
                                                        : 'border-2 border-gray-300 bg-white'"
                                                    class="h-5 w-5 rounded-full flex items-center justify-center flex-shrink-0 transition">
                                                    <template x-if="isServiceSelected(service.id)">
                                                        <i data-lucide="check" class="h-3 w-3 text-white"></i>
                                                    </template>
                                                </div>
                                            </div>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <!-- Notice: Discounts information -->
                            <div class="mt-2 bg-yellow-50 border-l-4 border-yellow-300 p-3 rounded">
                                <p class="text-xs text-yellow-800">Discounts may be available based on the type of treatment and individual situation.</p>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Modal Footer -->
            <div class="px-8 py-4 border-t border-gray-200 bg-white flex gap-2 justify-end">
                <button type="button"
                        @click="closeBookingModal()"
                        class="px-6 py-2 rounded-lg border-2 border-gray-300 text-gray-700 font-semibold hover:bg-gray-50 transition duration-200 text-sm">
                    Cancel
                </button>
                <button type="submit"
                        @click="submitBooking()"
                        class="px-7 py-2 rounded-lg bg-blue-600 text-white font-semibold shadow-md hover:bg-blue-700 transition duration-200 flex items-center gap-2 text-sm">
                    <i data-lucide="check" class="h-4 w-4"></i>
                    Confirm Appointment
                </button>
            </div>
        </div>
    </div>

<script>
    lucide.createIcons();
</script>
@endsection