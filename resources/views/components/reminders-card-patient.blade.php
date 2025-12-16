@props([])

<x-medical-card class="max-h-[380px] w-full flex flex-col">
    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-3">
        <i data-lucide="calendar" class="h-5 w-5 text-gray-600"></i>
        <span>Reminders</span>
    </h3>

    <div class="flex items-center space-x-2 mb-3">
        <button @click.prevent="remindersFilter = 'all'" :class="remindersFilter === 'all' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 border'" class="px-2 py-1 text-sm rounded-md border">All</button>
        <button @click.prevent="remindersFilter = 'today'" :class="remindersFilter === 'today' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 border'" class="px-2 py-1 text-sm rounded-md border">Today</button>
        <button @click.prevent="remindersFilter = 'tomorrow'" :class="remindersFilter === 'tomorrow' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 border'" class="px-2 py-1 text-sm rounded-md border">Tomorrow</button>
        <button @click.prevent="remindersFilter = 'week'" :class="remindersFilter === 'week' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 border'" class="px-2 py-1 text-sm rounded-md border">This Week</button>
    </div>

    <div class="overflow-y-auto space-y-3 max-h-[320px]">
        <!-- Today's appointment (if any) -->
        <div x-show="(appointments || []).some(app => app.appointment_date && app.appointment_date.split('T')[0] === localIsoDate() && app.status === 'confirmed' && (app.patient && app.patient.id == @json(auth()->id())))" class="mb-2">
                <div class="flex items-center justify-between bg-white rounded-lg border px-3 py-3">
                    <div class="flex items-center space-x-3">
                        <div class="w-1.5 h-10 bg-green-500 rounded-full" aria-hidden="true"></div>
                        <div class="min-w-0">
                            <p class="font-semibold text-sm text-gray-900" x-text="(function(){ const a = (appointments || []).find(app => app.appointment_date && app.appointment_date.split('T')[0] === localIsoDate() && app.status === 'confirmed' && (app.patient && app.patient.id == @json(auth()->id()))); return (a && a.patient && a.patient.name) ? a.patient.name : 'Today'; })()">Today</p>
                            <p class="text-sm text-gray-500 mt-1">
                                <span x-text="(function(){ const a = (appointments || []).find(app => app.appointment_date && app.appointment_date.split('T')[0] === localIsoDate() && app.status === 'confirmed' && (app.patient && app.patient.id == @json(auth()->id()))); return a && a.service_name ? a.service_name : ''; })()"></span>
                                <span x-show="(function(){ const a = (appointments || []).find(app => app.appointment_date && app.appointment_date.split('T')[0] === localIsoDate() && app.status === 'confirmed' && (app.patient && app.patient.id == @json(auth()->id()))); return !!(a && a.service_name); })()"> — </span>
                                <span x-text="(function(){ const a = (appointments || []).find(app => app.appointment_date && app.appointment_date.split('T')[0] === localIsoDate() && app.status === 'confirmed' && (app.patient && app.patient.id == @json(auth()->id()))); if (!a || !a.appointment_date) return ''; try { const isoStr = String(a.appointment_date); const parts = isoStr.split('T'); const datePart = parts[0]; const timePart = (parts[1] || '').split(':'); const [y, m, d] = datePart.split('-').map(Number); const hours = parseInt(timePart[0] || '0', 10); const minutes = parseInt(timePart[1] || '0', 10); const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']; const date = `${monthNames[m-1]} ${d}, ${y}`; const h12 = hours % 12 === 0 ? 12 : hours % 12; const minuteStr = minutes.toString().padStart(2,'0'); const meridiem = hours >= 12 ? 'pm' : 'am'; return `${date} — ${h12}:${minuteStr} ${meridiem}`; } catch(e) { return ''; } })()"></span>
                            </p>
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <a :href="`{{ route('admin.appointments') }}?id=${((appointments || []).find(app => app.appointment_date && app.appointment_date.split('T')[0] === localIsoDate() && app.status === 'confirmed' && (app.patient && app.patient.id == @json(auth()->id()))) || {}).id || ''}`" class="inline-flex items-center px-3 py-1.5 border border-gray-200 rounded-lg text-sm text-gray-700 hover:bg-gray-50">View</a>
                    </div>
                </div>
        </div>

        <!-- Upcoming confirmed appointments -->
        <div x-show="(appointments || []).filter(a => a.appointment_date && (remindersFilter === 'all' ? ((a.appointment_date || '').split('T')[0] >= localIsoDate()) : (typeof dateMatchesFilter === 'function' ? dateMatchesFilter(a.appointment_date, remindersFilter) : true)) && a.status === 'confirmed' && ((a.appointment_date || '').split('T')[0] > localIsoDate()) && (a.patient && a.patient.id == @json(auth()->id()))).length > 0" class="mb-2">
            <h4 class="text-sm font-medium text-gray-600 mb-2">Upcoming</h4>
            <template x-for="app in (appointments || []).filter(a => a.appointment_date && (remindersFilter === 'all' ? ((a.appointment_date || '').split('T')[0] >= localIsoDate()) : (typeof dateMatchesFilter === 'function' ? dateMatchesFilter(a.appointment_date, remindersFilter) : true)) && a.status === 'confirmed' && ((a.appointment_date || '').split('T')[0] > localIsoDate()) && (a.patient && a.patient.id == @json(auth()->id()))" :key="app.id">
                    <div class="flex items-center justify-between bg-white rounded-lg border px-3 py-3 mb-2">
                        <div class="flex items-center space-x-3">
                            <div class="w-1.5 h-10 bg-indigo-500 rounded-full" aria-hidden="true"></div>
                            <div class="min-w-0">
                                <p class="font-semibold text-sm text-gray-900 truncate" x-text="app.patient?.name || app.service_name"></p>
                                <p class="text-sm text-gray-500 mt-1"><span x-text="app.service_name"></span><span x-show="app.service_name"> — </span><span x-text="(function(){ try { const isoStr = String(app.appointment_date || ''); const parts = isoStr.split('T'); const datePart = parts[0]; const timePart = (parts[1] || '').split(':'); const [y, m, d] = datePart.split('-').map(Number); const hours = parseInt(timePart[0] || '0', 10); const minutes = parseInt(timePart[1] || '0', 10); const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']; const date = `${monthNames[m-1]} ${d}, ${y}`; const h12 = hours % 12 === 0 ? 12 : hours % 12; const minuteStr = minutes.toString().padStart(2,'0'); const meridiem = hours >= 12 ? 'pm' : 'am'; return `${date} — ${h12}:${minuteStr} ${meridiem}`; } catch(e) { return ''; } })()"></span></p>
                            </div>
                        </div>
                        <div class="flex-shrink-0">
                            <a :href="`{{ route('admin.appointments') }}?id=${app.id || ''}`" class="inline-flex items-center px-3 py-1.5 border border-gray-200 rounded-lg text-sm text-gray-700 hover:bg-gray-50">View</a>
                        </div>
                    </div>
                </template>
        </div>
        <!-- Reminder entries (1 day before, 1 hour before) - check if reminder belongs to current patient, deduplicate by appointment date/time -->
        <div x-show="(function() {
            const filtered = (reminders || []).filter(r => {
                if (!r || !r.appointment) return false;
                if (!(r.appointment.patient && r.appointment.patient.id == @json(auth()->id()))) return false;
                // Filter based on appointment date (not reminder datetime) to avoid duplicates
                const apptDate = r.appointment?.appointment_date || '';
                if (!apptDate) return false;
                const apptDateOnly = apptDate.split('T')[0];
                const dateMatch = remindersFilter === 'all' ? (apptDateOnly >= localIsoDate()) : (typeof dateMatchesFilter === 'function' ? dateMatchesFilter(apptDate, remindersFilter) : true);
                return dateMatch;
            });
            // Sort and deduplicate
            filtered.sort((a, b) => {
                if (a.type === '1h' && b.type === '1d') return -1;
                if (a.type === '1d' && b.type === '1h') return 1;
                const aDate = a.appointment?.appointment_date || '';
                const bDate = b.appointment?.appointment_date || '';
                return aDate.localeCompare(bDate);
            });
            const seen = new Set();
            const unique = filtered.filter(r => {
                const apptKey = r.appointment?.appointment_date || '';
                if (!apptKey || seen.has(apptKey)) return false;
                seen.add(apptKey);
                return true;
            });
            return unique.length > 0;
        })()">
            <template x-for="(reminder, idx) in (function() {
                const filtered = (reminders || []).filter(r => {
                    if (!r || !r.appointment) return false;
                    if (!(r.appointment.patient && r.appointment.patient.id == @json(auth()->id()))) return false;
                    // Filter based on appointment date (not reminder datetime) to avoid duplicates
                    const apptDate = r.appointment?.appointment_date || '';
                    if (!apptDate) return false;
                    const apptDateOnly = apptDate.split('T')[0];
                    const dateMatch = remindersFilter === 'all' ? (apptDateOnly >= localIsoDate()) : (typeof dateMatchesFilter === 'function' ? dateMatchesFilter(apptDate, remindersFilter) : true);
                    return dateMatch;
                });
                // Sort: 1h reminders first (closer to appointment), then 1d, then by appointment date
                filtered.sort((a, b) => {
                    // Prefer 1h over 1d
                    if (a.type === '1h' && b.type === '1d') return -1;
                    if (a.type === '1d' && b.type === '1h') return 1;
                    // Then sort by appointment date
                    const aDate = a.appointment?.appointment_date || '';
                    const bDate = b.appointment?.appointment_date || '';
                    return aDate.localeCompare(bDate);
                });
                // Deduplicate: only show one reminder per appointment date/time (prefer 1h over 1d)
                const seen = new Set();
                return filtered.filter(r => {
                    const apptKey = r.appointment?.appointment_date || '';
                    if (!apptKey || seen.has(apptKey)) return false;
                    seen.add(apptKey);
                    return true;
                });
            })()" :key="reminder.id">
                <div class="flex items-center justify-between bg-white rounded-lg border px-3 py-3">
                    <div class="flex items-center space-x-3">
                        <div :class="(reminder.type === '1h' ? 'bg-red-500' : (reminder.type === '1d' ? 'bg-yellow-400' : (reminder.appointment && reminder.appointment.status === 'cancelled' ? 'bg-red-500' : 'bg-red-500'))) + ' w-1.5 h-10 rounded-full'" aria-hidden="true"></div>
                        <div class="min-w-0">
                            <p class="font-semibold text-sm text-gray-900 truncate" x-text="reminder.appointment?.service_name || 'Appointment'"></p>
                            <p class="text-sm text-gray-500 mt-1">
                                <span x-text="(function(){ try { const apptDate = reminder.appointment?.appointment_date; if (!apptDate) return ''; const isoStr = String(apptDate); const parts = isoStr.split('T'); const datePart = parts[0]; const timePart = (parts[1] || '').split(':'); const [y, m, d] = datePart.split('-').map(Number); const hours = parseInt(timePart[0] || '0', 10); const minutes = parseInt(timePart[1] || '0', 10); const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']; const date = `${monthNames[m-1]} ${d}, ${y}`; const h12 = hours % 12 === 0 ? 12 : hours % 12; const minuteStr = minutes.toString().padStart(2,'0'); const meridiem = hours >= 12 ? 'pm' : 'am'; return `${date} at ${h12}:${minuteStr} ${meridiem}`; } catch(e) { return ''; } })()"></span>
                            </p>
                            <p class="text-xs text-gray-400 mt-0.5" x-text="reminder.type === '1d' ? 'Reminder: 1 day before' : (reminder.type === '1h' ? 'Reminder: 1 hour before' : '')"></p>
                        </div>
                    </div>

                    <div class="flex-shrink-0">
                        <a :href="`{{ route('admin.appointments') }}${reminder.appointment && reminder.appointment.id ? '?id=' + reminder.appointment.id : ''}`" class="inline-flex items-center px-3 py-1.5 border border-gray-200 rounded-lg text-sm text-gray-700 hover:bg-gray-50">View</a>
                    </div>
                </div>
            </template>
        </template>

        <div x-show="!hasVisibleReminders()" class="text-center py-4 text-gray-500 text-sm flex items-center justify-center h-full">No upcoming events for selected filter</div>
    </div>
</x-medical-card>
