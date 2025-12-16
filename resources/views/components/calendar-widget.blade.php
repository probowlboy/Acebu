@props(['selectedDate' => null])

<div class="medical-card">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900" x-text="currentMonthYear"></h3>
        <div class="flex space-x-2">
            <button @click="previousMonth()" class="p-1 rounded hover:bg-gray-100 transition-all duration-120">
                <i data-lucide="chevron-left" class="h-5 w-5 text-gray-600"></i>
            </button>
            <button @click="nextMonth()" class="p-1 rounded hover:bg-gray-100 transition-all duration-120">
                <i data-lucide="chevron-right" class="h-5 w-5 text-gray-600"></i>
            </button>
        </div>
    </div>
    
    <div class="grid grid-cols-7 gap-1 mb-2">
        <template x-for="day in weekDays" :key="day">
            <div class="text-center text-xs font-semibold text-gray-500 py-2" x-text="day"></div>
        </template>
    </div>
    
    <div class="grid grid-cols-7 gap-1">
        <template x-for="(day, index) in calendarDays" :key="index">
            <div 
                @click="selectDate(day)"
                :class="[
                    'calendar-day text-center py-2 rounded-lg text-sm cursor-pointer',
                    day === selectedDate ? 'selected bg-red-100 text-red-600' : 'text-gray-700 hover:bg-gray-100',
                    day === null ? 'text-gray-300 cursor-default' : ''
                ]"
                x-text="day || ''">
            </div>
        </template>
    </div>
</div>

