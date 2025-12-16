@props([])

<x-medical-card>
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Reminders</h3>
    <template x-if="selectedDateMeta && selectedDateMeta.appointments && selectedDateMeta.appointments.length > 0">
        <div class="space-y-3">
            <template x-for="app in selectedDateMeta.appointments" :key="app.id">
                <div class="schedule-block patient p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <p class="font-medium text-sm" x-text="app.service_name || 'Appointment'"></p>
                            <p class="text-xs text-gray-600 mt-1">
                                <span x-text="formatTime(app.appointment_date)"></span>
                                <span class="mx-2">•</span>
                                <span x-text="app.status"></span>
                            </p>
                            <p x-show="app.patient && app.patient.name" class="text-xs text-gray-500 mt-1">
                                Patient: <span x-text="app.patient.name"></span>
                            </p>
                        </div>
                        <div class="flex items-center gap-2 ml-4">
                            <a :href="`{{ route('admin.appointments') }}?id=${app.id || ''}`" 
                               class="px-3 py-1.5 rounded-md bg-blue-600 hover:bg-blue-700 text-white text-xs transition-colors">
                                View
                            </a>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </template>
    <template x-if="!selectedDateMeta || !selectedDateMeta.appointments || selectedDateMeta.appointments.length === 0">
        <div class="text-sm text-gray-500 text-center py-4">No appointments for the selected date.</div>
    </template>
</x-medical-card>
