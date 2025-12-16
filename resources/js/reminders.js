(function(){
    // Simple ReminderManager attached to window.ReminderManager
    // Usage: const mgr = window.ReminderManager.createManager({appointments, userData, storageKey, onTrigger, nowOverride});

    function buildRemindersFromAppointments(appointments, userData, role){
        const reminders = [];
        const now = Date.now();
        const raw = Array.isArray(appointments) ? appointments : (appointments?.appointments || []);
        raw.forEach(app => {
            if (!app || !app.appointment_date) return;
            if (app.status !== 'confirmed') return;

            // role-specific filtering: if patient, ensure belongs to user
            if (role === 'patient' && userData) {
                const appUserId = app.patient?.id || app.patient_id || app.user_id;
                const appEmail = app.patient?.email || app.email;
                const currentUserId = userData.id;
                const currentEmail = userData.email;
                const belongsToPatient = (appUserId && appUserId === currentUserId) || (appEmail && appEmail === currentEmail);
                if (!belongsToPatient) return;
            }

            // Parse ISO date string - handle both UTC and local timezone properly
            let appt;
            try {
                // Try parsing as ISO string first (handles timezone properly)
                appt = new Date(app.appointment_date);
                // If parsing fails or gives invalid date, fall back to manual parsing
                if (isNaN(appt.getTime())) {
                    const parts = String(app.appointment_date).split('T');
                    const datePart = parts[0];
                    const timePart = (parts[1] || '').split(':');
                    const y = parseInt(datePart.split('-')[0],10);
                    const m = parseInt(datePart.split('-')[1],10) - 1;
                    const d = parseInt(datePart.split('-')[2],10);
                    const hh = parseInt(timePart[0]||'0',10);
                    const mm = parseInt(timePart[1]||'0',10);
                    const ss = parseInt((timePart[2]||'0').split('.')[0],10);
                    appt = new Date(y,m,d,hh,mm,ss);
                }
            } catch (e) {
                return; // Skip invalid dates
            }
            if (isNaN(appt.getTime())) return;

            const oneDayBefore = new Date(appt.getTime() - 24*60*60*1000);
            if (oneDayBefore.getTime() > now) {
                reminders.push({
                    id: `appt-${app.id}-1d`,
                    title: `Reminder: Tomorrow`,
                    datetime: localIsoString(oneDayBefore),
                    appointment: app
                });
            }
            const oneHourBefore = new Date(appt.getTime() - 60*60*1000);
            if (oneHourBefore.getTime() > now){
                reminders.push({
                    id: `appt-${app.id}-1h`,
                    title: `Reminder: In 1 hour`,
                    datetime: localIsoString(oneHourBefore),
                    appointment: app
                });
            }
        });
        return reminders;
    }

    // Ensure reminders are sorted by datetime ascending
    function sortReminders(remindersArray){
        try{
            return (remindersArray || []).slice().sort((a,b)=>{
                const pa = parseLocalISO(a.datetime);
                const pb = parseLocalISO(b.datetime);
                const ta = pa ? pa.getTime() : 0;
                const tb = pb ? pb.getTime() : 0;
                return ta - tb;
            });
        }catch(e){ return remindersArray || []; }
    }

    function localIsoString(dt){
        if (!dt || !(dt instanceof Date)) return '';
        const y = dt.getFullYear();
        const m = String(dt.getMonth()+1).padStart(2,'0');
        const d = String(dt.getDate()).padStart(2,'0');
        const hh = String(dt.getHours()).padStart(2,'0');
        const mm = String(dt.getMinutes()).padStart(2,'0');
        const ss = String(dt.getSeconds()).padStart(2,'0');
        return `${y}-${m}-${d}T${hh}:${mm}:${ss}`;
    }

    function nowMs(){ return Date.now(); }

    function readShown(storageKey){
        try{ const raw = localStorage.getItem(storageKey); return raw ? JSON.parse(raw) : {}; }catch(e){ return {}; }
    }
    function saveShown(storageKey, obj){
        try{ localStorage.setItem(storageKey, JSON.stringify(obj||{})); }catch(e){}
    }

    function createManager(opts){
        const options = Object.assign({appointments:[], userData:null, role:'patient', storageKey:'patient_shownReminders', onTrigger:null}, opts || {});
        let reminders = [];
        let timers = {};
        let sweepId = null;
        let shown = readShown(options.storageKey);

        function buildAndSchedule(){
            clearTimers();
            reminders = buildRemindersFromAppointments(options.appointments, options.userData, options.role);
            // sort chronologically
            reminders = sortReminders(reminders);
            const now = nowMs();
            reminders.forEach(rem => {
                if (shown && shown[rem.id]) return;
                const remDate = parseLocalISO(rem.datetime);
                if (!remDate) return;
                const ms = remDate.getTime() - now;
                if (ms <= 0) return;
                // cap 30 days
                if (ms > 30*24*60*60*1000) return;
                const tid = setTimeout(()=>{ trigger(rem); delete timers[rem.id]; }, ms);
                timers[rem.id] = tid;
            });
        }

        function trigger(rem){
            try{
                // call callback
                if (typeof options.onTrigger === 'function'){
                    try{ options.onTrigger(rem); }catch(e){console.error('onTrigger error',e);}    
                }
                // desktop notification
                if (window.Notification){
                    if (Notification.permission === 'granted'){
                        try{ new Notification('Reminder', { body: rem.title }); }catch(e){}
                    } else if (Notification.permission === 'default'){
                        Notification.requestPermission().then(p=>{ if (p==='granted'){ try{ new Notification('Reminder', { body: rem.title }); }catch(e){} } }).catch(()=>{});
                    }
                }
                // mark shown
                shown[rem.id] = Date.now();
                saveShown(options.storageKey, shown);
            }catch(e){ console.error('trigger error', e); }
        }

        function clearTimers(){ Object.values(timers||{}).forEach(id=>clearTimeout(id)); timers = {}; }
        function destroy(){ clearTimers(); if (sweepId) { clearInterval(sweepId); sweepId=null; } }

        function parseLocalISO(dateString){
            if (!dateString) return null;
            try{
                // Try parsing as ISO string first (handles timezone properly)
                let dt = new Date(dateString);
                if (!isNaN(dt.getTime())) {
                    return dt;
                }
                // Fall back to manual parsing if ISO parse fails
                const parts = String(dateString).split('T');
                const [datePart, timePart] = parts;
                const [y,m,d] = (datePart||'').split('-').map(v=>parseInt(v,10));
                if (!y||!m||!d) return null;
                const [hh='0', mm='0', ss='0'] = (timePart||'').split(':');
                dt = new Date(y, parseInt(m,10)-1, parseInt(d,10), parseInt(hh,10), parseInt(mm,10), parseInt((ss||'0').split('.')[0],10));
                return isNaN(dt.getTime()) ? null : dt;
            }catch(e){ return null; }
        }

        function updateAppointments(newApps){ options.appointments = newApps || []; buildAndSchedule(); }
        function updateUser(user){ options.userData = user; buildAndSchedule(); }

        // periodic sweep to catch missed reminders (every 60s)
        sweepId = setInterval(()=>{
            try{
                const now = nowMs();
                reminders.forEach(rem=>{
                    if (shown && shown[rem.id]) return;
                    const rd = parseLocalISO(rem.datetime);
                    if (!rd) return;
                    if (rd.getTime() <= now){ trigger(rem); }
                });
            }catch(e){ console.error('sweep error', e); }
        }, 60*1000);

        // initial schedule (ensure sorted list)
        reminders = sortReminders(buildRemindersFromAppointments(options.appointments, options.userData, options.role));
        buildAndSchedule();

        return { updateAppointments, updateUser, destroy, getReminders: ()=>reminders };
    }

    // expose
    window.ReminderManager = { createManager };
})();
