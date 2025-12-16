@extends('layouts.dashboard')

@section('title', 'Doctor Dashboard')

@push('scripts')
<script>
    let patientsChart = null;
</script>
@endpush

@section('content')
<div class="max-w-[1920px] mx-auto px-4 sm:px-6 lg:px-8 py-4"
     x-data="{
         userData: null,
         todayPatients: 0,
        // number of pending appointments (for stat card)
        pendingAppointments: 0,
         totalPatients: 0,
         newPatients: 0,
         oldPatients: 0,
         allPatients: [],
        appointments: [],
        upcomingAppointments: [],
         // reminders for admin (built from appointments)
         reminders: [],
        // currently selected reminders filter: 'all' | 'today' | 'tomorrow' | 'weekend'
        remindersFilter: 'all',
         _reminderTimers: {},
         // persisted shown reminders to avoid duplicates
         _shownReminders: {},
         // periodic sweep id
         _reminderSweepId: null,
         // in-page reminder alert
         reminderAlert: { visible: false, message: '' },
        requests: [],
         patientStats: {
             labels: [],
             new: [],
             old: []
         },
        lastUpdated: null,
        // Calendar state (copied from patient dashboard)
        currentDate: new Date(),
        // real-time clock
        currentTime: new Date(),
        _currentTimeInterval: null,
        // store full date meta for selection
        selectedDateMeta: null,
        // Prevent concurrent fetches
        _isFetching: false,
        _calendarDebounceTimer: null,
        currentMonth: new Date().getMonth(),
        currentYear: new Date().getFullYear(),
        calendarDays: [],
        monthNames: ['JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER'],
        // Use full uppercase weekday labels to match reference exactly
        weekDays: ['SUN','MON','TUE','WED','THU','FRI','SAT'],
        buildRemindersFromAppointments() {
            try {
                    this.reminders = [];
                const now = Date.now();
                const raw = Array.isArray(this.appointments) ? this.appointments : [];
                raw.forEach(app => {
                    if (!app || !app.appointment_date) return;
                    if (app.status !== 'confirmed') return;
                    const appt = this.parseLocalISO(app.appointment_date);
                    if (!appt) return;

                    // 1 day before
                    const oneDayBefore = new Date(appt.getTime() - 24 * 60 * 60 * 1000);
                    if (oneDayBefore.getTime() > now) {
                        this.reminders.push({
                            id: `a-${app.id}-1d`,
                            title: `Reminder: ${app.patient?.name || 'Patient'} - Tomorrow`,
                            datetime: this.localIsoString(oneDayBefore),
                            appointment: app,
                            type: '1d'
                        });
                    }

                    // 1 hour before
                    const oneHourBefore = new Date(appt.getTime() - 60 * 60 * 1000);
                    if (oneHourBefore.getTime() > now) {
                        this.reminders.push({
                            id: `a-${app.id}-1h`,
                            title: `Reminder: ${app.patient?.name || 'Patient'} - In 1 hour`,
                            datetime: this.localIsoString(oneHourBefore),
                            appointment: app,
                            type: '1h'
                        });
                    }
                });

                // sort reminders chronologically (earliest first)
                try {
                    this.reminders = (this.reminders || []).slice().sort((a, b) => {
                        const ta = this.parseLocalISO(a.datetime)?.getTime() || 0;
                        const tb = this.parseLocalISO(b.datetime)?.getTime() || 0;
                        return ta - tb;
                    });
                } catch (e) { console.error('reminder sort error', e); }
            } catch (e) {
                console.error('Error building admin reminders:', e);
                this.reminders = [];
            }
        },
        scheduleReminders() {
            try {
                Object.values(this._reminderTimers || {}).forEach(id => clearTimeout(id));
                this._reminderTimers = {};
                const now = Date.now();
                this.reminders.forEach(rem => {
                    // skip if already shown
                    if (this.isReminderShown && this.isReminderShown(rem.id)) return;
                    const remDate = this.parseLocalISO(rem.datetime);
                    if (!remDate) return;
                    const ms = remDate.getTime() - now;
                    if (ms <= 0) return;
                    if (ms > 30 * 24 * 60 * 60 * 1000) return;
                    const tid = setTimeout(() => {
                        try { this.triggerReminder(rem); } finally { delete this._reminderTimers[rem.id]; }
                    }, ms);
                    this._reminderTimers[rem.id] = tid;
                });
                if (window.Notification && Notification.permission === 'default') {
                    Notification.requestPermission().catch(() => {});
                }
            } catch (e) {
                console.error('Error scheduling admin reminders:', e);
            }
        },
        triggerReminder(rem) {
            try {
                // in-page toast/alert
                this.reminderAlert.message = rem.title;
                this.reminderAlert.visible = true;
                setTimeout(() => this.reminderAlert.visible = false, 8000);

                // Desktop notification
                if (window.Notification) {
                    if (Notification.permission === 'granted') {
                        try { new Notification('Reminder', { body: rem.title }); } catch (e) { console.error(e); }
                    } else if (Notification.permission === 'default') {
                        Notification.requestPermission().then(p => {
                            if (p === 'granted') {
                                try { new Notification('Reminder', { body: rem.title }); } catch (e) { console.error(e); }
                            }
                        }).catch(()=>{});
                    }
                }
                // mark shown so it won't fire again
                try { this.markReminderShown(rem.id); } catch (e) { console.error(e); }
                console.info('Reminder triggered:', rem.title);
            } catch (e) {
                console.error('Error triggering admin reminder:', e);
            }
        },
        clearReminders() {
            try { Object.values(this._reminderTimers || {}).forEach(id => clearTimeout(id)); this._reminderTimers = {}; if (this._reminderSweepId) { clearInterval(this._reminderSweepId); this._reminderSweepId = null; } } catch(e){}
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
        dateIsThisWeekend(dateString) {
            const d = this.parseLocalISO(dateString);
            if (!d) return false;
            const now = new Date();
            const day = now.getDay();
            const daysUntilSaturday = (6 - day + 7) % 7;
            const saturday = new Date(now);
            saturday.setDate(now.getDate() + daysUntilSaturday);
            saturday.setHours(0,0,0,0);
            const sunday = new Date(saturday);
            sunday.setDate(saturday.getDate() + 1);
            sunday.setHours(0,0,0,0);
            const check = new Date(d.getFullYear(), d.getMonth(), d.getDate());
            check.setHours(0,0,0,0);
            return check.getTime() === saturday.getTime() || check.getTime() === sunday.getTime();
        },
        dateIsThisWeek(dateString) {
            const d = this.parseLocalISO(dateString);
            if (!d) return false;
            try {
                const now = new Date();
                // calculate start of week (Monday)
                const day = now.getDay(); // 0 (Sun) - 6 (Sat)
                const diffToMonday = (day + 6) % 7; // days since Monday
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
                // Today's appointments (exclude completed and cancelled)
                const hasToday = (this.appointments || []).some(app => app.appointment_date && app.status !== 'completed' && app.status !== 'cancelled' && app.appointment_date.split('T')[0] === today && (f === 'all' ? true : (typeof dateMatchesFilter === 'function' ? dateMatchesFilter(app.appointment_date, f) : true)));
                if (hasToday) return true;
                // Upcoming (future) appointments (exclude completed and cancelled)
                const hasUpcoming = (this.appointments || []).some(app => app.appointment_date && app.status !== 'completed' && app.status !== 'cancelled' && ((app.appointment_date || '').split('T')[0] > today) && (f === 'all' ? true : (typeof dateMatchesFilter === 'function' ? dateMatchesFilter(app.appointment_date, f) : true)));
                if (hasUpcoming) return true;
                // Reminder entries (filter by datetime, exclude completed and cancelled appointments)
                const hasReminders = (this.reminders || []).some(rem => rem.datetime && (rem.appointment ? !((this.appointments || []).some(a => a.id === rem.appointment.id && (a.status === 'completed' || a.status === 'cancelled'))) && (f === 'all' ? ((rem.datetime||'').split('T')[0] >= today) : (typeof dateMatchesFilter === 'function' ? dateMatchesFilter(rem.datetime, f) : true)) : (f === 'all' ? ((rem.datetime||'').split('T')[0] >= today) : (typeof dateMatchesFilter === 'function' ? dateMatchesFilter(rem.datetime, f) : true))));
                return !!hasReminders;
            } catch (e) { return (this.reminders || []).length > 0; }
        },
        // Return local YYYY-MM-DD (uses browser local date, avoids UTC offset issues)
        localIsoDate() {
            const d = new Date();
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        },
        localIsoString(dt) {
            if (!dt || !(dt instanceof Date)) return '';
            const y = dt.getFullYear();
            const m = String(dt.getMonth() + 1).padStart(2, '0');
            const d = String(dt.getDate()).padStart(2, '0');
            const hh = String(dt.getHours()).padStart(2, '0');
            const mm = String(dt.getMinutes()).padStart(2, '0');
            const ss = String(dt.getSeconds()).padStart(2, '0');
            return `${y}-${m}-${d}T${hh}:${mm}:${ss}`;
        },
         // Calendar and visit stats removed to reduce unused UI and JS
         loading: true,
         error: null,
        pollIntervalId: null,
         async init() {
             const isAdmin = localStorage.getItem('isAdmin') === 'true';
             if (!isAdmin) {
                 window.location.href = '/patient/patientdashboard';
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
            try {
                // Poll less frequently to reduce server load, pause when page is hidden
                const startPolling = () => {
                    if (this.pollIntervalId) clearInterval(this.pollIntervalId);
                    this.pollIntervalId = setInterval(() => {
                        if (!document.hidden) this.fetchDashboardData();
                    }, 120000); // Increased to 2 minutes to reduce lag
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
                const self = this;
                window.addEventListener('beforeunload', function() { stopPolling.call(self); });
            } catch (e) { console.error('Failed to start dashboard polling', e); }
           // initialize centralized ReminderManager (handles scheduling, persistence, notifications)
           try { this.createReminderManager(); } catch (e) { console.error('Reminder manager init error', e); }
             // Initialize charts after a short delay to ensure DOM is ready
             setTimeout(() => {
                 this.initCharts();
             }, 300);
         },

        createReminderManager() {
            try {
                if (!window.ReminderManager || typeof window.ReminderManager.createManager !== 'function') return;
                // destroy existing
                if (this._reminderManager && typeof this._reminderManager.destroy === 'function') {
                    try { this._reminderManager.destroy(); } catch(e){}
                    this._reminderManager = null;
                }

                const mgr = window.ReminderManager.createManager({
                    appointments: this.appointments,
                    userData: this.userData,
                    role: 'admin',
                    storageKey: 'admin_shownReminders',
                    onTrigger: (rem) => {
                        try {
                            this.reminderAlert.message = rem.title;
                            this.reminderAlert.visible = true;
                            setTimeout(() => this.reminderAlert.visible = false, 8000);
                        } catch (e) { console.error(e); }
                    }
                });

                this._reminderManager = mgr;
            } catch (e) { console.error('createReminderManager error', e); }
        },
         // Calendar removed from admin dashboard to reduce unused UI and JS

         generateChartData() {
             const monthNames = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
             const today = new Date();
             
             // Generate labels for last 6 months (only patient stats retained)
             this.patientStats.labels = [];
             for (let i = 5; i >= 0; i--) {
                 const date = new Date(today.getFullYear(), today.getMonth() - i, 1);
                 const monthLabel = monthNames[date.getMonth()];
                 this.patientStats.labels.push(monthLabel);
             }
             
             // Calculate patient stats (new vs old) for each month
             this.patientStats.new = [];
             this.patientStats.old = [];
             
             for (let i = 5; i >= 0; i--) {
                 const monthStart = new Date(today.getFullYear(), today.getMonth() - i, 1);
                 const monthEnd = new Date(today.getFullYear(), today.getMonth() - i + 1, 0);
                 
                 const newInMonth = this.allPatients.filter(patient => {
                     const createdDate = new Date(patient.created_at);
                     return createdDate >= monthStart && createdDate <= monthEnd;
                 }).length;
                 
                 // Old patients = total patients before this month
                 const totalBeforeMonth = this.allPatients.filter(patient => {
                     const createdDate = new Date(patient.created_at);
                     return createdDate < monthStart;
                 }).length;
                 
                 this.patientStats.new.push(newInMonth);
                 this.patientStats.old.push(totalBeforeMonth);
             }
         },
         generateRequests() {
             // Get upcoming appointments for requests
             const today = new Date();
             today.setHours(0, 0, 0, 0);
             
             const upcoming = this.appointments
                 .filter(app => {
                     if (!app.appointment_date) return false;
                     const appDate = new Date(app.appointment_date);
                     appDate.setHours(0, 0, 0, 0);
                     return appDate >= today && (app.status === 'pending' || app.status === 'confirmed');
                 })
                 .sort((a, b) => new Date(a.appointment_date) - new Date(b.appointment_date))
                 .slice(0, 3);
             
             this.requests = upcoming.map(app => {
                 const appDate = new Date(app.appointment_date);
                 const dateStr = appDate.toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
                 const timeStr = appDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                 
                 return {
                     name: app.patient?.name || 'Unknown Patient',
                     type: app.status === 'pending' ? 'emergency' : 'consultation',
                     date: dateStr,
                     time: timeStr,
                     caseType: app.status === 'pending' ? 'Pending Case' : 'Confirmed Case'
                 };
             });
             
             // Fill with sample data if not enough requests
             if (this.requests.length < 3) {
                 const sampleRequests = [
                     { name: 'Donia El Malky', type: 'emergency', date: new Date().toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' }), time: '10:00 AM', caseType: 'Emergency Case' },
                     { name: 'Mourad Ahmed', type: 'consultation', date: new Date().toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' }), time: '10:00 AM', caseType: 'Consultation Case' },
                     { name: 'Riham Osama', type: 'emergency', date: new Date().toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' }), time: '10:00 AM', caseType: 'Emergency Case' }
                 ];
                 
                 for (let i = this.requests.length; i < 3; i++) {
                     this.requests.push(sampleRequests[i - this.requests.length]);
                 }
             }
         },
        // Persisted reminder helpers (admin)
        loadShownReminders() {
            try {
                const raw = localStorage.getItem('admin_shownReminders');
                this._shownReminders = raw ? JSON.parse(raw) : {};
            } catch (e) { this._shownReminders = {}; }
        },
        saveShownReminders() {
            try { localStorage.setItem('admin_shownReminders', JSON.stringify(this._shownReminders || {})); } catch (e) {}
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
            } catch (e) { console.error('Admin reminder sweep error', e); }
        },
         async fetchDashboardData() {
             // Prevent concurrent fetches
             if (this._isFetching) {
                 console.log('Fetch already in progress, skipping...');
                 return;
             }
             
             let _fetchTimeoutId = null;
             try {
                 this._isFetching = true;
                 this.loading = true;
                 this.error = null;
                // Safety timeout: ensure loading indicator doesn't remain indefinitely
                const _fetchTimeoutMs = 15000; // 15s
                _fetchTimeoutId = setTimeout(() => {
                    if (this.loading) {
                        console.warn('Dashboard fetch timed out. Clearing loading state.');
                        this.error = 'Timed out while loading dashboard';
                        this.loading = false;
                    }
                }, _fetchTimeoutMs);
                 const token = localStorage.getItem('token');
                 if (!token) {
                     window.location.href = '{{ route('admin.login') }}';
                     return;
                 }
                 
                 const [usersResponse, appointmentsResponse] = await Promise.all([
                     window.apiUtils.fetch('{{ url('/api/patients') }}', {
                         method: 'GET',
                         headers: {
                             'Authorization': `Bearer ${token}`,
                             'Accept': 'application/json'
                         }
                     }),
                     window.apiUtils.fetch('{{ url('/api/appointments') }}', {
                         method: 'GET',
                         headers: {
                             'Authorization': `Bearer ${token}`,
                             'Accept': 'application/json'
                         }
                     })
                 ]);
                 
                 // Users response handling
                 if (usersResponse && usersResponse.ok) {
                     try {
                         const usersData = await usersResponse.json();
                         this.allPatients = usersData.patients || [];
                         this.totalPatients = this.allPatients.length;

                         const thirtyDaysAgo = new Date();
                         thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);

                         this.newPatients = this.allPatients.filter(patient => {
                             const createdDate = new Date(patient.created_at);
                             return createdDate >= thirtyDaysAgo;
                         }).length;

                         this.oldPatients = this.totalPatients - this.newPatients;
                     } catch (e) {
                         console.error('Failed parsing users response:', e);
                         this.allPatients = [];
                         this.totalPatients = 0;
                         this.newPatients = 0;
                         this.oldPatients = 0;
                     }
                 } else {
                     // ensure we don't show stale patient data on failure
                     this.allPatients = [];
                     this.totalPatients = 0;
                     this.newPatients = 0;
                     this.oldPatients = 0;
                     if (usersResponse) console.warn('Users fetch failed', usersResponse.status);
                 }

                 // Appointments response handling
                 if (appointmentsResponse && appointmentsResponse.ok) {
                     try {
                         const appointmentsData = await appointmentsResponse.json();
                         const today = this.localIsoDate();

                         this.appointments = Array.isArray(appointmentsData) ? appointmentsData : [];

                        // Count pending appointments for the stat card
                        try {
                            this.pendingAppointments = (Array.isArray(appointmentsData) ? appointmentsData : []).filter(app => app && app.status === 'pending').length;
                        } catch (e) { this.pendingAppointments = 0; }

                         // Build admin view's upcomingAppointments (next confirmed future appointments)
                         try {
                            // Upcoming for admin: only CONFIRMED future appointments (exclude pending)
                            this.upcomingAppointments = (Array.isArray(appointmentsData) ? appointmentsData : []).filter(app => {
                                const appDate = app.appointment_date?.split('T')[0] || '';
                                return app.status === 'confirmed' && appDate > today;
                            }).sort((a,b) => new Date(a.appointment_date) - new Date(b.appointment_date)).slice(0,3);
                         } catch (e) { this.upcomingAppointments = []; }

                         // Count today's patients
                         this.todayPatients = this.appointments.filter(app => {
                             const appDate = app.appointment_date?.split('T')[0] || '';
                             return appDate === today && (app.status === 'confirmed' || app.status === 'pending');
                         }).length;

                         // Generate chart data for last 6 months
                         this.generateChartData();

                         // Generate requests from recent appointments
                         this.generateRequests();

                         // Regenerate calendar with new appointments
                         try { this.generateCalendar(); } catch(e) { console.error('calendar generate error', e); }

                         // Build reminders after appointments fetched (only for confirmed appointments)
                         this.buildRemindersFromAppointments();
                         this.scheduleReminders();
                         // Update reminder manager with new appointments
                         try {
                             if (this._reminderManager && typeof this._reminderManager.updateAppointments === 'function') {
                                 this._reminderManager.updateAppointments(this.appointments);
                             } else {
                                 this.createReminderManager();
                             }
                         } catch (e) { console.error('Error updating reminder manager:', e); }

                         // Initialize charts after data is loaded
                         this.$nextTick(() => {
                             setTimeout(() => { this.initCharts(); }, 200);
                         });

                         // mark successful update time
                         this.lastUpdated = new Date().toISOString();
                     } catch (e) {
                         console.error('Failed parsing appointments response:', e);
                         this.appointments = [];
                         this.upcomingAppointments = [];
                         this.todayPatients = 0;
                     }
                 } else {
                     // ensure we don't show stale appointment data on failure
                     this.appointments = [];
                     this.upcomingAppointments = [];
                     this.todayPatients = 0;
                     if (appointmentsResponse) console.warn('Appointments fetch failed', appointmentsResponse.status);
                 }
             } catch (error) {
                 if (error && error.name !== 'AbortError') {
                     console.error('Error fetching dashboard data:', error);
                     this.error = error.message || 'Failed to load dashboard data';
                 }
            } finally {
                try { if (_fetchTimeoutId) clearTimeout(_fetchTimeoutId); } catch(e){}
                this.loading = false;
                this._isFetching = false;
            }
         },
         initCharts() {
             if (typeof Chart === 'undefined') {
                 setTimeout(() => this.initCharts(), 100);
                 return;
             }
             
             // Check if we have data
             if (!this.patientStats.labels || this.patientStats.labels.length === 0) {
                 return;
             }
             
             // Destroy existing charts if they exist
            if (patientsChart) {
                patientsChart.destroy();
                patientsChart = null;
            }
             
             // Patients Chart
             const patientsCtx = document.getElementById('patientsChart');
             if (patientsCtx) {
                 patientsChart = window.MedicalCharts.createBarChart('patientsChart', 
                     this.patientStats.labels, 
                     this.patientStats.new, 
                     this.patientStats.old
                 );
             }
             
            // Visits chart removed from admin dashboard
         },
         getGreeting() {
             const hour = new Date().getHours();
             if (hour < 12) return 'Good Morning';
             if (hour < 18) return 'Good Afternoon';
             return 'Good Evening';
         },
        getFormattedDate() {
            const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            const date = new Date();
            return `${days[date.getDay()]} ${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
        },
        // Calendar functions (copied from patient dashboard)
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
                    (Array.isArray(this.appointments) ? this.appointments : []).forEach(app => {
                        if (!app || !app.appointment_date) return;
                        const appDate = String(app.appointment_date).split('T')[0];
                        if (!appointmentsMap.has(appDate)) {
                            appointmentsMap.set(appDate, []);
                        }
                        if (app.status === 'confirmed' || app.status === 'pending') {
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
     }"
     x-init="init()"
     style="opacity: 1; transition: opacity 120ms ease-out;">
    
    <!-- Error State -->
    <div x-show="error" x-cloak 
         class="bg-red-50 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
        <span x-text="error"></span>
    </div>

    <!-- Main Content (loading indicator removed; show content unless there's an error) -->
    <div x-show="!error" x-cloak>
        <!-- Welcome Card -->
        <x-medical-card class="mb-6">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-gray-600 mb-2 text-sm" x-text="getFormattedDate()"></p>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">
                        <span x-text="getGreeting()"></span> Dr. Acebu
                    </h2>
                    <p class="text-lg text-gray-700">
                        You Have <span class="text-red-600 font-semibold" x-text="todayPatients"></span> Patients Today
                    </p>
                </div>
                @php $docPath = public_path('images/doctor.png'); @endphp
                <div class="hidden lg:block ml-6">
                    @if (file_exists($docPath))
                        <div class="w-24 h-24 rounded-full overflow-hidden border-4 border-white shadow-md">
                            <img src="{{ asset('images/doctor.png') }}" alt="Doctor Acebu Avatar" class="w-full h-full object-cover">
                        </div>
                    @else
                        <img 
                            src="https://ui-avatars.com/api/?name=Acebu&background=0D9488&color=fff&size=128" 
                            alt="Doctor Acebu Avatar"
                            class="w-24 h-24 rounded-full shadow-md border-4 border-white object-cover">
                    @endif
                </div>
                <div class="hidden lg:flex lg:flex-col lg:items-end lg:ml-6">
                </div>
            </div>
        </x-medical-card>

        <!-- Top stats removed; Pending Appointments and New Patients moved into left chart column for cleaner layout -->

        <!-- Charts and Calendar Row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6 mb-6">
            <!-- Patients Chart -->
            <div class="lg:col-span-2">
                    <!-- Left-side stats: Pending Appointments & New Patients -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <x-stat-card icon="calendar" label="Pending Appointments" color="green" href="{{ route('admin.appointments') }}">
                        <span x-text="pendingAppointments"></span>
                    </x-stat-card>
                    <x-stat-card icon="user-plus" label="New Patients (Last 30 Days)" color="blue">
                        <span x-text="newPatients"></span>
                    </x-stat-card>
                </div>
                
                <!-- Calendar (copied from patient dashboard) -->
                <div class="flex flex-col mb-4">
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
                
                <x-medical-card>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Patients (Last 6 Months)</h3>
                    <div class="flex items-center space-x-4 mb-4">
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full bg-teal-500 mr-2"></div>
                            <span class="text-sm text-gray-600">New</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full bg-red-500 mr-2"></div>
                            <span class="text-sm text-gray-600">Old</span>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="patientsChart"></canvas>
                    </div>
                </x-medical-card>
            </div>

            <!-- Reminders (admin component) -->
            <div class="flex flex-col">
                @include('components.reminders-card-admin')
            </div>
        </div>

        <!-- Visits Chart and Calendar removed per request -->

        <!-- Patient Requests removed per request -->
    </div>
</div>

<script>
    lucide.createIcons();
</script>
@endsection
