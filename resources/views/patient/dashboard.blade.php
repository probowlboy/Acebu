@extends('layouts.dashboard')

@section('title', 'Patient Dashboard')

@section('content')
<div class="max-w-[1920px] mx-auto"
     x-data="{
         userData: null,
         upcomingAppointments: [],
        // number of pending appointments for patient
        pendingAppointments: 0,
         todayAppointments: [],
         assignedDentist: null,
         medicalHistory: [],
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
        showSuccessModal: false,
        successMessage: '',
        successMessageHtml: '',
        // Cancel confirmation modal (dashboard)
        showCancelModal: false,
        cancelAppointmentPendingId: null,
        cancelAppointmentPendingTitle: '',
        isCanceling: false,
         completedVisits: 0,
         loading: true,
         error: null,
                // Calendar state (copied from admin calendar)
         currentDate: new Date(),
                 // real-time clock
                 currentTime: new Date(),
                 _currentTimeInterval: null,
        // store full date meta for selection
        selectedDateMeta: null,
        currentMonth: new Date().getMonth(),
        currentYear: new Date().getFullYear(),
        calendarDays: [],
        // Prevent concurrent fetches
        _isFetching: false,
        _calendarDebounceTimer: null,
         monthNames: ['JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER'],
         // Use full uppercase weekday labels to match reference exactly
         weekDays: ['SUN','MON','TUE','WED','THU','FRI','SAT'],
         async init() {
             const isAdmin = localStorage.getItem('isAdmin') === 'true';
             if (isAdmin) {
                 window.location.href = '/admin/admindashboard';
                 return;
             }
             
             await this.fetchDashboardData();
            // build calendar after fetching appointments
            this.generateCalendar();
                         // start realtime clock update
                         try {
                                 // Start realtime clock update; pause when page is hidden to save CPU
                                 const startClock = () => { if (!this._currentTimeInterval) this._currentTimeInterval = setInterval(() => { this.currentTime = new Date(); }, 1000); };
                                 const stopClock = () => { try { if (this._currentTimeInterval) { clearInterval(this._currentTimeInterval); this._currentTimeInterval = null; } } catch(e) {} };
                                 startClock();
                                 document.addEventListener('visibilitychange', () => { if (document.hidden) stopClock(); else startClock(); });
                                 window.addEventListener('beforeunload', () => { try { stopClock(); } catch(e) {} });
                         } catch (e) { console.error('clock init error', e); }
              // initialize centralized ReminderManager (handles scheduling, persistence, notifications)
              try { this.createReminderManager(); } catch (e) { console.error('Reminder manager init error', e); }
               // load persisted shown reminders and schedule ticks
               try { this.loadShownReminders(); this.buildRemindersFromAppointments(); this.scheduleReminders(); } catch (e) { console.error('reminder init error', e); }
               try { if (!this._reminderSweepId) { this._reminderSweepId = setInterval(() => this.periodicReminderSweep(), 60 * 1000); window.addEventListener('beforeunload', () => this.clearReminders()); } } catch(e){}
         },
         async fetchDashboardData() {
             // Prevent concurrent fetches
             if (this._isFetching) {
                 console.log('Fetch already in progress, skipping...');
                 return;
             }
             
             try {
                 this._isFetching = true;
                 this.loading = true;
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
                     }
                 });
                 
                if (!response) {
                    console.warn('Appointments network error', response);
                    this.upcomingAppointments = [];
                    this.todayAppointments = [];
                    this.completedVisits = 0;
                    this.error = null;
                    return;
                }

                if (response && response.status === 401) {
                    localStorage.removeItem('token');
                    localStorage.removeItem('currentUser');
                    window.location.href = '{{ route('patient.login') }}';
                    return;
                }

                if (!response.ok) {
                    console.warn('Appointments fetch returned non-ok response', response);
                    // Fallback: treat as no appointments to avoid showing an unnecessary error
                    this.upcomingAppointments = [];
                    this.todayAppointments = [];
                    this.completedVisits = 0;
                    this.error = null;
                    return;
                }
                
                const appointments = await response.json();
                // keep a raw copy for building reminders (restrict to patient's appointments)
                let raw = Array.isArray(appointments) ? appointments : (appointments.appointments || []);
                // Deduplicate raw appointments by id to avoid showing duplicates
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
                raw = uniqueAppointments(raw);
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
                    raw = onlyMine(raw);
                } catch (e) { /* fall back to raw */ }
                this._rawAppointments = raw;
                 // Get today's date in local timezone (YYYY-MM-DD format)
                 const now = new Date();
                 const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
                 
                this.upcomingAppointments = raw
                     .filter(app => {
                         const appDate = app.appointment_date?.split('T')[0] || '';
                         return app.status === 'confirmed' && appDate > today;
                     })
                     .slice(0, 3)
                     .sort((a, b) => new Date(a.appointment_date) - new Date(b.appointment_date));

                // Count pending appointments for patient stat card
                try {
                    this.pendingAppointments = (raw || []).filter(app => app && app.status === 'pending').length;
                } catch (e) { this.pendingAppointments = 0; }
                 
                this.todayAppointments = raw.filter(app => {
                     const appDate = app.appointment_date?.split('T')[0] || '';
                     return app.status === 'confirmed' && appDate === today;
                 });
                 
                this.completedVisits = raw.filter(app => 
                     app.status === 'completed'
                 ).length;
                 
                 // Assigned dentist information
                 this.assignedDentist = {
                     name: 'Dr. Acebu',
                     specialization: 'Dental Surgeon',
                     experience: '10+ years',
                     rating: 4.8
                 };
                 
                 // Sample medical history
                 this.medicalHistory = [
                     { date: '2024-01-15', procedure: 'Dental Cleaning', status: 'completed' },
                     { date: '2024-02-20', procedure: 'Root Canal', status: 'completed' },
                     { date: '2024-03-10', procedure: 'Teeth Whitening', status: 'completed' }
                 ];
                 
                 this.error = null;
                // regenerate calendar now that appointments are loaded
                try { this.generateCalendar(); } catch(e) { console.error('calendar generate error', e); }
                     // rebuild reminders now that appointments are loaded
                     try { this.buildRemindersFromAppointments(); this.scheduleReminders(); this.createReminderManager(); } catch (e) { console.error('reminder rebuild error', e); }
                 } catch (error) {
                     if (error && error.name !== 'AbortError') {
                     console.error('Error fetching dashboard data:', error);
                     this.error = error.message || 'Failed to load dashboard data';
                 }
             } finally {
                 this.loading = false;
                 this._isFetching = false;
             }
         },
         getGreeting() {
             const hour = new Date().getHours();
             if (hour < 12) return 'Good Morning';
             if (hour < 18) return 'Good Afternoon';
             return 'Good Evening';
         },
        // Return a display name derived from available user fields (first/middle/last or name)
        titleCase(str) {
            if (!str) return '';
            return String(str).toLowerCase().split(/\s+/).filter(Boolean).map(s => s.charAt(0).toUpperCase() + s.slice(1)).join(' ');
        },
        getDisplayName() {
            try {
                const u = this.userData || {};
                const parts = [];
                if (u.first_name) parts.push(u.first_name);
                if (u.middle_name) parts.push(u.middle_name);
                if (u.last_name) parts.push(u.last_name);
                // fallback to generic name field
                if (parts.length === 0 && u.name) {
                    return this.titleCase(u.name);
                }
                const full = parts.join(' ').trim();
                return full ? this.titleCase(full) : (u.name ? this.titleCase(u.name) : 'Patient');
            } catch (e) { return this.userData?.name || 'Patient'; }
        },
         formatDate(dateString) {
             if (!dateString) return '';
            const d = this.parseLocalISO(dateString);
            if (!d) return '';
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
         },
         formatTime(dateString) {
             if (!dateString) return '';
            const d = this.parseLocalISO(dateString);
            if (!d) return '';
            return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
         }
        ,
        // formatted current date/time shown near calendar header
        formattedCurrentDate() {
            return this.currentTime ? this.currentTime.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : '';
        },
        formattedCurrentTime() {
            return this.currentTime ? this.currentTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit' }) : '';
        },
        generateCalendar() {
            // Debounce calendar generation to prevent excessive re-renders
            if (this._calendarDebounceTimer) {
                clearTimeout(this._calendarDebounceTimer);
            }
            
            this._calendarDebounceTimer = setTimeout(() => {
                try {
                    const today = new Date();
                    today.setHours(0,0,0,0);
                    const firstDay = new Date(this.currentYear, this.currentMonth, 1).getDay();
                    const daysInMonth = new Date(this.currentYear, this.currentMonth + 1, 0).getDate();
                    const prevLastDay = new Date(this.currentYear, this.currentMonth, 0).getDate();
                    const totalSlots = Math.ceil((firstDay + daysInMonth) / 7) * 7;

                    const calendar = [];
                    // Cache appointments map for faster lookup
                    const appointmentsMap = new Map();
                    (Array.isArray(this._rawAppointments) ? this._rawAppointments : []).forEach(app => {
                        if (!app || !app.appointment_date) return;
                        const appDate = String(app.appointment_date).split('T')[0];
                        if (!appointmentsMap.has(appDate)) {
                            appointmentsMap.set(appDate, []);
                        }
                        if (app.status === 'confirmed') {
                            appointmentsMap.get(appDate).push(app);
                        }
                    });

                    for (let i = 0; i < totalSlots; i++) {
                        let slotDate = null;
                        let inCurrentMonth = false;
                        if (i < firstDay) {
                            // previous month
                            const d = prevLastDay - (firstDay - 1 - i);
                            slotDate = new Date(this.currentYear, this.currentMonth - 1, d);
                            inCurrentMonth = false;
                        } else if (i < firstDay + daysInMonth) {
                            // current month
                            const d = i - firstDay + 1;
                            slotDate = new Date(this.currentYear, this.currentMonth, d);
                            inCurrentMonth = true;
                        } else {
                            // next month
                            const d = i - (firstDay + daysInMonth) + 1;
                            slotDate = new Date(this.currentYear, this.currentMonth + 1, d);
                            inCurrentMonth = false;
                        }

                        if (slotDate) {
                            slotDate.setHours(0,0,0,0);
                            const iso = `${slotDate.getFullYear()}-${String(slotDate.getMonth()+1).padStart(2,'0')}-${String(slotDate.getDate()).padStart(2,'0')}`;
                            const dayAppointments = appointmentsMap.get(iso) || [];

                            calendar.push({
                                date: slotDate,
                                iso,
                                label: slotDate.getDate(),
                                isPast: slotDate < today,
                                inCurrentMonth,
                                appointments: dayAppointments,
                                hasAppointments: dayAppointments.length > 0
                            });
                        }
                    }

                    this.calendarDays = calendar;
                } catch (e) {
                    console.error('Error generating calendar:', e);
                }
            }, 100); // 100ms debounce
        },

        // compute classes for each calendar day to match the appointments-style UI
        getDayClasses(day) {
            if (!day || !day.date) return 'text-gray-300 cursor-default rounded-lg py-2 text-sm';
            const todayIso = this.localIsoDate();
            const isToday = day.iso === todayIso;
            const selected = this.selectedDateMeta && day.iso === this.selectedDateMeta.iso;

            const base = 'rounded-2xl py-3 flex items-center justify-center text-sm font-semibold';
            if (selected) return `${base} bg-blue-600 text-white ring-2 ring-blue-600 shadow-md`;
            if (isToday) return `${base} bg-white text-blue-600 ring-2 ring-blue-500`;
            return `${base} text-gray-700 hover:bg-gray-100 cursor-pointer`;
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
        getCurrentMonthYear() {
            return `${this.monthNames[this.currentMonth]} ${this.currentYear}`;
        },
        isToday(day) {
            if (!day || !day.date) return false;
            const todayIso = this.localIsoDate();
            return day.iso === todayIso;
        },
        selectDate(day) {
            if (!day || !day.date) return;
            this.selectedDateMeta = day;
            // day already contains appointments in render
            this.selectedDateMeta.appointments = day.appointments || [];
        },

        getEventPillClasses(app) {
            try {
                if (!app) return 'bg-gray-100 text-gray-700';
                if (app.status === 'confirmed') return 'bg-red-600 text-white';
                if (app.status === 'pending') return 'bg-yellow-400 text-gray-800';
                if (app.status === 'completed') return 'bg-gray-200 text-gray-700';
                return 'bg-blue-100 text-gray-900';
            } catch (e) { return 'bg-gray-100 text-gray-700'; }
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
                const today = this.localIsoDate();
                // Patient's today's confirmed appointments
                const hasToday = (this._rawAppointments || []).some(app => app.appointment_date && app.appointment_date.split('T')[0] === today && app.status === 'confirmed' && (f === 'all' ? true : (typeof dateMatchesFilter === 'function' ? dateMatchesFilter(app.appointment_date, f) : true)));
                if (hasToday) return true;
                // Upcoming confirmed (future) appointments for patient
                const hasUpcoming = (this._rawAppointments || []).some(app => app.appointment_date && app.status === 'confirmed' && ((app.appointment_date || '').split('T')[0] > today) && (f === 'all' ? true : (typeof dateMatchesFilter === 'function' ? dateMatchesFilter(app.appointment_date, f) : true)));
                if (hasUpcoming) return true;
                // Reminders entries for patient
                const hasReminders = (this.reminders || []).some(rem => rem.datetime && (rem.appointment ? !((this._rawAppointments || []).some(a => a.id === rem.appointment.id)) && (f === 'all' ? ((rem.datetime||'').split('T')[0] >= today) : (typeof dateMatchesFilter === 'function' ? dateMatchesFilter(rem.datetime, f) : true)) : (f === 'all' ? ((rem.datetime||'').split('T')[0] >= today) : (typeof dateMatchesFilter === 'function' ? dateMatchesFilter(rem.datetime, f) : true))));
                return !!hasReminders;
            } catch (e) { return (this.reminders || []).length > 0; }
        },
        localIsoDate() {
            const d = new Date();
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        },
        buildRemindersFromAppointments() {
            try {
                this.reminders = [];
                const now = Date.now();
                const raw = Array.isArray(this._rawAppointments) ? this._rawAppointments : [];
                raw.forEach(app => {
                    if (!app || !app.appointment_date) return;
                    if (app.status !== 'confirmed') return;
                    
                    // Filter to only patient's own appointments
                    if (this.userData) {
                        const appUserId = app.patient?.id || app.patient_id || app.user_id;
                        const appEmail = app.patient?.email || app.email;
                        const currentUserId = this.userData.id;
                        const currentEmail = this.userData.email;
                        
                        // Check if appointment belongs to current patient
                        const belongsToPatient = (appUserId && appUserId === currentUserId) || 
                                                 (appEmail && appEmail === currentEmail);
                        if (!belongsToPatient) return;
                    }
                    
                    const appt = this.parseLocalISO(app.appointment_date);
                    if (!appt) return;

                    // 1 day before
                    const oneDayBefore = new Date(appt.getTime() - 24 * 60 * 60 * 1000);
                    if (oneDayBefore.getTime() > now) {
                        this.reminders.push({
                            id: `appt-${app.id}-1d`,
                            title: `Reminder: Tomorrow`,
                            datetime: this.localIsoString(oneDayBefore),
                            appointment: app,
                            type: '1d'
                        });
                    }

                    // 1 hour before
                    const oneHourBefore = new Date(appt.getTime() - 60 * 60 * 1000);
                    if (oneHourBefore.getTime() > now) {
                        this.reminders.push({
                            id: `appt-${app.id}-1h`,
                            title: `Reminder: In 1 hour`,
                            datetime: this.localIsoString(oneHourBefore),
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
        async cancelAppointment(appointmentId) {
            this.cancelAppointmentPendingId = appointmentId;
            const appointment = this._rawAppointments.find(a => a.id === appointmentId) || this.todayAppointments.find(a => a.id === appointmentId) || null;
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
                if (!response || !response.ok) {
                    let body = null;
                    try { body = await response.json(); } catch(e) { /* ignore */ }
                    const msg = body && body.message ? body.message : 'Failed to cancel appointment';
                    throw new Error(msg);
                }
                this.showCancelModal = false;
                this.cancelAppointmentPendingId = null;
                // Optimistic UI: mark appointment locally as cancelled so the UI updates immediately
                try { 
                    const localApp = (this.appointments || []).find(a => a.id === appointmentId) || (this._rawAppointments || []).find(a => a.id === appointmentId);
                    if (localApp) localApp.status = 'cancelled';
                } catch (e) { }
                this.isCanceling = false;
                await this.fetchDashboardData();
                this.successMessage = 'Appointment cancelled successfully';
                this.successMessageHtml = 'Appointment cancelled<br>successfully';
                this.showSuccessModal = true;
            } catch (error) {
                console.error('Error cancelling appointment from dashboard:', error);
                this.showCancelModal = false;
                this.cancelAppointmentPendingId = null;
                this.error = 'Failed to cancel appointment. Please try again.';
            } finally {
                this.isCanceling = false;
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

        createReminderManager() {
            try {
                if (!window.ReminderManager || typeof window.ReminderManager.createManager !== 'function') return;
                if (this._reminderManager && typeof this._reminderManager.destroy === 'function') {
                    try { this._reminderManager.destroy(); } catch(e){}
                    this._reminderManager = null;
                }

                const mgr = window.ReminderManager.createManager({
                    appointments: this._rawAppointments || [],
                    userData: this.userData,
                    role: 'patient',
                    storageKey: 'patient_shownReminders',
                    onTrigger: (rem) => {
                        try { this.triggerReminderUI(rem); } catch(e){ console.error(e); }
                    }
                });

                this._reminderManager = mgr;
            } catch (e) { console.error('createReminderManager error', e); }
        },

        triggerReminderUI(rem) {
            try {
                if (this.reminderAlert) {
                    this.reminderAlert.message = rem.title || '';
                    this.reminderAlert.visible = true;
                    setTimeout(() => this.reminderAlert.visible = false, 8000);
                    return;
                }
                if (this.alert) {
                    this.alert.message = rem.title || '';
                    this.alert.visible = true;
                    setTimeout(() => this.alert.visible = false, 8000);
                }
            } catch (e) { console.error('triggerReminderUI error', e); }
        }
     }"
     x-init="init()"
     style="opacity: 1; transition: opacity 120ms ease-out;">
    
    <!-- In-page reminder toast -->
    <div x-show="reminderAlert.visible" x-cloak class="fixed top-5 right-5 z-50">
        <div class="bg-yellow-50 border-l-4 border-yellow-500 text-yellow-700 px-4 py-3 rounded shadow-md">
            <p class="font-semibold">Reminder</p>
            <p class="text-sm mt-1" x-text="reminderAlert.message"></p>
        </div>
    </div>

    <!-- Loading State -->
    <div x-show="loading" x-cloak class="text-center py-12">
        <i data-lucide="loader" class="h-8 w-8 animate-spin text-teal-600 mx-auto mb-2"></i>
        <p class="text-gray-600">Loading dashboard...</p>
    </div>

    <!-- Error State -->
    <div x-show="error" x-cloak 
         class="bg-red-50 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
        <span x-text="error"></span>
    </div>

    <!-- Main Content -->
    <div x-show="!loading && !error" x-cloak class="space-y-6">
        <!-- Welcome Card -->
        <x-medical-card>
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">
                        <span x-text="getGreeting()"></span>, <span x-text="getDisplayName()"></span>
                    </h2>
                    <p class="text-gray-600">Welcome back to your health dashboard</p>
                </div>
                @php $manPath = public_path('images/man.png'); @endphp
                <div class="hidden lg:block">
                    @if (file_exists($manPath))
                        <div class="w-24 h-24 rounded-full overflow-hidden border-2 border-teal-100 shadow-sm">
                            <img src="{{ asset('images/man.png') }}" alt="Profile" class="w-full h-full object-cover">
                        </div>
                    @else
                        <div class="w-24 h-24 bg-gradient-to-br from-teal-100 to-teal-200 rounded-full flex items-center justify-center">
                            <i data-lucide="user" class="h-12 w-12 text-teal-600"></i>
                        </div>
                    @endif
                </div>
            </div>
        </x-medical-card>

        <!-- Stats Grid removed from top - will appear in left column -->

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <!-- Left Column - Appointments & Dentist -->
            <div class="lg:col-span-2 space-y-6 flex flex-col">
                

                <!-- Stats Grid (moved to left) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-stretch">
                    <div class="h-full">
                        <x-stat-card 
                                icon="calendar" 
                                label="Pending Appointments" 
                                color="teal"
                                href="{{ route('patient.appointments') }}"
                            >
                                <span x-text="!loading ? pendingAppointments : 0"></span>
                            </x-stat-card>
                    </div>
                    <div class="h-full">
                        <x-stat-card 
                            icon="clock" 
                            label="Today's Appointments" 
                            color="green"
                            href="{{ route('patient.appointments') }}"
                        >
                            <span x-text="!loading ? todayAppointments.length : 0"></span>
                        </x-stat-card>
                    </div>
                    <div class="h-full">
                        <x-stat-card 
                            icon="check-circle" 
                            label="Completed Visits" 
                            color="blue"
                            href="{{ route('patient.history') }}"
                        >
                            <span x-text="!loading ? completedVisits : 0"></span>
                        </x-stat-card>
                    </div>
                </div>

                <!-- Calendar (Admin-style) -->
                <div class="flex flex-col">
                    <x-medical-card class="flex-1">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-2">
                                <button @click="previousMonth()" class="bg-gray-800 text-white p-2 rounded-md shadow hover:bg-gray-900 transition-all duration-120 flex items-center justify-center">
                                    <i data-lucide="chevron-left" class="h-4 w-4"></i>
                                </button>
                                <button @click="nextMonth()" class="bg-gray-800 text-white p-2 rounded-md shadow hover:bg-gray-900 transition-all duration-120 flex items-center justify-center">
                                    <i data-lucide="chevron-right" class="h-4 w-4"></i>
                                </button>
                                <button @click="(function(){ const now = new Date(); currentMonth = now.getMonth(); currentYear = now.getFullYear(); generateCalendar(); })()" class="bg-gray-300 text-gray-700 px-3 py-2 rounded-md hover:bg-gray-200 transition-all duration-120 font-medium">today</button>
                            </div>

                            <div class="flex-1 text-center">
                                <h3 class="text-2xl font-semibold text-gray-900" x-text="getCurrentMonthYear()"></h3>
                                <p class="text-xs text-gray-500 mt-1 tracking-wide" x-text="formattedCurrentDate() + ' • ' + formattedCurrentTime()"></p>
                            </div>

                            <div class="w-12"></div>
                        </div>

                        <div class="grid grid-cols-7 gap-0 mb-2">
                            <template x-for="day in weekDays" :key="day">
                                <div class="text-center text-xs font-semibold text-blue-500 py-2 tracking-wider uppercase" x-text="day"></div>
                            </template>
                        </div>

                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="grid grid-cols-7 gap-0 divide-y divide-x divide-gray-200">
                            <template x-for="(day, index) in calendarDays" :key="index">
                                <div 
                                    @click="selectDate(day)"
                                    :class="[
                                        'relative min-h-[92px] h-[112px] p-3 bg-white overflow-hidden transition',
                                        day?.inCurrentMonth ? 'bg-white' : 'bg-transparent opacity-40',
                                        (selectedDateMeta && selectedDateMeta.iso === day.iso) ? 'bg-yellow-50' : '',
                                    ]">
                                    <div class="absolute top-2 right-3">
                                        <span :class="day?.inCurrentMonth ? 'text-sm font-semibold text-blue-600' : 'text-sm text-gray-300'" x-text="day ? day.label : ''"></span>
                                    </div>
                                    <!-- Events / Pills -->
                                    <div class="mt-8 space-y-1">
                                        <template x-if="day && day.appointments && day.appointments.length > 0">
                                            <template x-for="(app, i) in day.appointments.slice(0,2)" :key="app.id">
                                                <div :class="[ getEventPillClasses(app), !day.inCurrentMonth ? 'opacity-50' : '' ]" class="inline-block px-2 py-0.5 rounded text-[11px] font-medium truncate max-w-[86%] shadow-sm leading-none" x-text="app.service_name || 'Appointment'"></div>
                                            </template>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            </div>
                        </div>
                    </x-medical-card>
                </div>

                
            </div>

            <!-- Right Column - Dentist & Reminders -->
            <div class="space-y-6 flex flex-col">
                <div class="flex-1">
                    <!-- Assigned Dentist (moved back to right column) -->
                    <x-medical-card>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Your Dentist</h3>
                        <div class="text-center">
                            @php $docPath = public_path('images/doctor.png'); @endphp
                            @if (file_exists($docPath))
                                <div class="w-20 h-20 rounded-full overflow-hidden mx-auto mb-4">
                                    <img src="{{ asset('images/doctor.png') }}" alt="Dr. Acebu" class="w-full h-full object-cover">
                                </div>
                            @else
                                <div class="w-20 h-20 bg-teal-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i data-lucide="user-md" class="h-10 w-10 text-teal-600"></i>
                                </div>
                            @endif
                            <p class="font-semibold text-gray-900 text-lg" x-text="assignedDentist?.name || 'Dr. Acebu'"></p>
                            <p class="text-sm text-gray-600 mt-1" x-text="assignedDentist?.specialization || 'Dental Surgeon'"></p>
                            <template x-if="assignedDentist && typeof assignedDentist.rating !== 'undefined' && assignedDentist.rating !== null">
                                <div class="flex items-center justify-center mt-3 space-x-1">
                                    <i data-lucide="star" class="h-4 w-4 text-yellow-400 fill-current"></i>
                                    <span class="text-sm font-medium text-gray-700" x-text="assignedDentist.rating"></span>
                                </div>
                            </template>
                        </div>
                    </x-medical-card>
                </div>

                <div class="flex-1">
                    <x-medical-card>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Reminders</h3>
                        <template x-if="selectedDateMeta && selectedDateMeta.appointments && selectedDateMeta.appointments.length > 0">
                            <div class="space-y-3">
                                <template x-for="app in selectedDateMeta.appointments" :key="app.id">
                                    <div class="schedule-block patient p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-start justify-between">
                                            <div>
                                                <p class="font-medium text-sm" x-text="app.service_name || 'Appointment'"></p>
                                                <p class="text-xs text-gray-600 mt-1">
                                                    <span x-text="formatTime(app.appointment_date)"></span>
                                                    <span class="mx-2">•</span>
                                                    <span x-text="app.status"></span>
                                                </p>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <button @click="cancelAppointment(app.id)"
                                                        x-show="app.status !== 'cancelled' && app.status !== 'completed'"
                                                        :disabled="isCanceling && cancelAppointmentPendingId === app.id"
                                                        :aria-disabled="isCanceling && cancelAppointmentPendingId === app.id ? 'true' : 'false'"
                                                        :class="(isCanceling && cancelAppointmentPendingId === app.id) ? 'opacity-50 cursor-not-allowed px-2 py-1 rounded-md bg-red-600 text-white text-xs' : 'px-2 py-1 rounded-md bg-red-600 hover:bg-red-700 text-white text-xs'">
                                                    <span x-show="!(isCanceling && cancelAppointmentPendingId === app.id)">Cancel</span>
                                                    <span x-show="isCanceling && cancelAppointmentPendingId === app.id">Cancelling&hellip;</span>
                                                </button>
                                                <span x-show="app.status === 'cancelled' || app.status === 'completed'" class="text-xs text-gray-400">No actions</span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <template x-if="!selectedDateMeta || !selectedDateMeta.appointments || selectedDateMeta.appointments.length === 0">
                            <div class="text-sm text-gray-500">No appointments for the selected date.</div>
                        </template>
                    </x-medical-card>
                </div>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();
</script>
<!-- Cancel Confirmation Modal (Dashboard) -->
<div x-show="showCancelModal" x-cloak
     class="fixed inset-0 bg-gray-900/50 flex items-center justify-center z-[9999] p-4"
     style="z-index: 9999;"
     @keydown.escape.window="(showCancelModal = false, cancelAppointmentPendingId = null)"
     @click.self="(showCancelModal = false, cancelAppointmentPendingId = null)">
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden flex flex-col p-8 text-center" role="dialog" aria-modal="true" aria-labelledby="cancel-modal-title">
        <div class="absolute -top-8 left-1/2 transform -translate-x-1/2">
            <div class="h-14 w-14 rounded-full bg-red-100 flex items-center justify-center shadow-sm">
                <i data-lucide="x" class="h-6 w-6 text-red-600"></i>
            </div>
        </div>
        <button type="button" @click="(showCancelModal = false, cancelAppointmentPendingId = null)" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 transition">
            <i data-lucide="x" class="h-4 w-4"></i>
        </button>
        <div class="mt-6 pt-2">
            <h3 id="cancel-modal-title" class="text-xl font-bold text-gray-900 leading-snug">Are you sure you want to cancel this appointment?</h3>
        </div>
        <div class="mt-6 flex justify-center gap-3">
            <button type="button" @click="(showCancelModal = false, cancelAppointmentPendingId = null)" class="px-6 py-2 rounded-lg border-2 border-gray-300 text-gray-700 font-semibold hover:bg-gray-50 transition duration-150">Close</button>
            <button type="button" @click="confirmCancelAppointment()" :disabled="isCanceling" :class="isCanceling ? 'px-6 py-2 rounded-lg bg-red-400 text-white font-semibold opacity-60 cursor-not-allowed' : 'px-6 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white font-semibold focus:outline-none focus:ring-2 focus:ring-red-300'"> 
                <span x-show="!isCanceling">Cancel Appointment</span>
                <span x-show="isCanceling">Cancelling&hellip;</span>
            </button>
        </div>
    </div>
</div>

<!-- Success Modal (Dashboard) -->
<div x-show="showSuccessModal" x-cloak @keydown.escape.window="showSuccessModal = false" @click.self="showSuccessModal = false"
     class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center p-4 z-40">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg max-w-sm w-full p-6" role="dialog" aria-modal="true">
        <div class="flex items-start gap-4">
            <div class="flex-shrink-0 rounded-full bg-green-100 p-3">
                <i data-lucide="check-circle" class="h-6 w-6 text-green-600"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Success</h3>
                <p class="text-sm text-gray-500 mt-1" x-text="successMessage"></p>
            </div>
        </div>
        <div class="mt-6 text-right">
            <button type="button" @click="(showSuccessModal = false, successMessage = '')" class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 font-medium">Close</button>
        </div>
    </div>
</div>
@endsection
