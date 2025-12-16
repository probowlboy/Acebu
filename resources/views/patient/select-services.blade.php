@extends('layouts.app')

@section('title', 'New Appointment')

@push('styles')
<style>
  .calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0.5rem;
  }
  .time-slot-container {
    display: flex;
    gap: 0.75rem;
    overflow-x: auto;
    padding-bottom: 0.5rem;
  }
  .time-slot-container::-webkit-scrollbar {
    height: 6px;
  }
  .time-slot-container::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 10px;
  }
  .time-slot-container::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
  }
</style>
@endpush

@section('content')
<div class="min-h-screen bg-white py-12 px-4 sm:px-6 lg:px-8"
     x-data="appointmentBooking()"
     x-init="init()">
  <div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-12">
      <h1 class="text-4xl font-bold text-gray-900">New Appointment</h1>
      <p class="text-gray-600 mt-1">Choose Time & Services</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
      <!-- Left Column: Calendar -->
      <div class="lg:col-span-1">
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6">
          <div class="flex items-center justify-between mb-6">
            <button class="text-gray-400 hover:text-gray-600 transition" @click="previousMonth">
              <i data-lucide="chevron-left" class="h-5 w-5"></i>
            </button>
            <h3 class="text-lg font-semibold text-gray-900" x-text="currentMonthYear"></h3>
            <button class="text-gray-400 hover:text-gray-600 transition" @click="nextMonth">
              <i data-lucide="chevron-right" class="h-5 w-5"></i>
            </button>
          </div>

          <!-- Weekday headers -->
          <div class="grid grid-cols-7 gap-2 mb-4">
            <template x-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day">
              <div class="text-center text-xs font-semibold text-gray-500 py-2" x-text="day"></div>
            </template>
          </div>

          <!-- Calendar days -->
          <div class="calendar-grid">
            <template x-for="day in calendarDays" :key="day.date">
              <button
                @click="selectDate(day)"
                :disabled="!day.date"
                :class="getDayClasses(day)"
                class="rounded-lg py-3 text-sm font-medium transition duration-200 disabled:cursor-default">
                <span x-text="day.label"></span>
              </button>
            </template>
          </div>
        </div>
      </div>

      <!-- Right Column: Main Content -->
      <div class="lg:col-span-3 space-y-8">
        <!-- Selected Date Display -->
        <div class="bg-blue-50 rounded-2xl border border-blue-200 p-6">
          <p class="text-xs text-gray-600 font-medium mb-2">Selected Date</p>
          <div class="flex items-center gap-3">
            <i data-lucide="calendar" class="h-6 w-6 text-blue-600"></i>
            <p class="text-2xl font-bold text-gray-900" x-text="formattedSelectedDate"></p>
          </div>
        </div>

        <!-- Time Slot Section -->
        <div>
          <div class="mb-4">
            <h2 class="text-xl font-semibold text-gray-900">Select Time Slot</h2>
            <p class="text-sm text-gray-600 mt-2">
              <span x-text="`Morning - ${morningSlots[0]} to ${morningSlots[morningSlots.length - 1]}`"></span>
            </p>
          </div>
          <div :class="isFetchingBlockedSlots ? 'time-slot-container opacity-60 pointer-events-none' : 'time-slot-container'">
            <template x-for="slot in timeSlots" :key="slot">
              <button
                type="button"
                @click="selectSlot(slot)"
                :disabled="isSlotDisabled(slot)"
                :aria-disabled="isSlotDisabled(slot) ? 'true' : 'false'"
                :class="isSlotDisabled(slot)
                  ? 'border-2 rounded-full py-3 px-6 text-sm font-semibold transition duration-200 whitespace-nowrap flex-shrink-0 bg-gray-100 text-gray-400 cursor-not-allowed opacity-60'
                  : (selectedSlot === slot
                    ? 'bg-blue-600 text-white border-blue-600'
                    : 'bg-white text-gray-700 border-gray-200 hover:border-blue-400')"
                class="border-2 rounded-full py-3 px-6 text-sm font-semibold transition duration-200 whitespace-nowrap flex-shrink-0">
                <span x-text="slot"></span>
              </button>
            </template>
          </div>
          <div class="mt-2">
            <div x-show="isFetchingBlockedSlots" class="text-sm text-gray-500">Checking availability…</div>
          </div>
        </div>

        <!-- Services Section -->
        <div>
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-semibold text-gray-900">Select Services</h2>
            <p class="text-sm text-gray-600">Choose up to 3 services — Multi-select</p>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <template x-for="service in services" :key="service.id">
              <div
                @click="toggleService(service)"
                :class="selectedServices.find(s => s.id === service.id)
                  ? 'border-2 border-blue-600 bg-blue-50 ring-2 ring-blue-300'
                  : 'border-2 border-gray-200 hover:border-blue-300 bg-white'"
                class="rounded-2xl p-5 cursor-pointer transition duration-200">
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <p class="font-semibold text-gray-900" x-text="service.name"></p>
                    <p class="text-sm text-gray-600 mt-2 truncate" x-text="service.description"></p>
                    <p class="text-sm text-gray-600 mt-2">ID: <span x-text="service.id"></span></p>
                    <p class="text-sm text-gray-600 mt-1">
                      <i data-lucide="clock" class="h-4 w-4 inline mr-1"></i>
                      <span x-text="service.duration"></span>
                    </p>
                  </div>
                  <div
                    :class="selectedServices.find(s => s.id === service.id)
                      ? 'bg-blue-600 border-blue-600'
                      : 'border-2 border-gray-300 bg-white'"
                    class="h-6 w-6 rounded-full flex items-center justify-center flex-shrink-0 transition">
                    <template x-if="selectedServices.find(s => s.id === service.id)">
                      <i data-lucide="check" class="h-4 w-4 text-white"></i>
                    </template>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>

        <!-- Notes Section -->
        <div>
          <label class="block text-sm font-semibold text-gray-900 mb-4">Notes (optional)</label>
          <textarea
            x-model="notes"
            placeholder="Add any special requests or notes..."
            class="w-full px-5 py-3 rounded-xl border-2 border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition duration-200 resize-none text-gray-700"
            rows="4"></textarea>
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-4 justify-end pt-4">
          <button
            @click="resetForm()"
            class="px-8 py-3 rounded-lg border-2 border-gray-300 text-gray-700 font-semibold hover:bg-gray-50 transition duration-200">
            Cancel
          </button>
          <button
            @click="confirmAppointment()"
            class="px-8 py-3 rounded-lg bg-blue-600 text-white font-semibold shadow-lg hover:bg-blue-700 transition duration-200 flex items-center gap-2 min-w-[200px] justify-center">
            <i data-lucide="check" class="h-5 w-5"></i>
            Confirm Appointment
          </button>
        </div>
      </div>
    </div>
  </div>
  <!-- Select Service Modal (Please select a date and at least one service) - same style as success modal -->
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
        <p class="text-sm text-gray-600 mt-3 max-w-[36rem] mx-auto">Please choose a date and at least one service to continue with booking.</p>
      </div>

      <!-- Button -->
      <div class="mt-6 flex justify-center">
        <button type="button" @click="showSelectServiceModal = false" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300">Close</button>
      </div>
    </div>
  </div>

</div>

<script>
  function appointmentBooking() {
    return {
      services: [
        { id: 123123, name: 'Dental Cleaning', description: 'Routine cleaning and polish to remove plaque and tartar.', duration: '60 mins' },
        { id: 234234, name: 'Root Canal Therapy', description: 'Treatment to remove infected pulp and save the tooth.', duration: '90 mins' },
        { id: 345345, name: 'Teeth Whitening', description: 'Cosmetic whitening to brighten your smile.', duration: '45 mins' },
        { id: 456456, name: 'Tooth Extraction', description: 'Safe removal of damaged or problematic teeth.', duration: '30 mins' },
        { id: 567567, name: 'Dental Implant Consultation', description: 'Assessment and planning for dental implants.', duration: '60 mins' },
        { id: 678678, name: 'Orthodontic Consultation', description: 'Evaluation for braces or aligners.', duration: '45 mins' },
      ],
      timeSlots: ['9–10 AM', '10–11 AM', '11–12 PM', '1–2 PM', '2–4 PM'],
      morningSlots: ['9–10 AM', '10–11 AM', '11–12 PM', '1–2 PM', '2–4 PM'],
      slotMap: {
        '9–10 AM': '09:00',
        '10–11 AM': '10:00',
        '11–12 PM': '11:00',
        '1–2 PM': '13:00',
        '2–4 PM': '14:00'
      },
      disabledSlots: [],
      blockedSlots: [],
      // Abort controller for canceling in-flight fetches
      _blockedFetchController: null,
      // Simple cache for blocked slots: { [dateString]: { slots: [], ts: 0 } }
      blockedSlotsCache: {},
      // TTL for cache in ms
      blockedFetchTTL: 30 * 1000,
      // Polling interval id and ms
      _blockedPollId: null,
      blockedPollIntervalMs: 30 * 1000,
      // Debounce timer to avoid rapid repeated fetches when changing date
      _blockedFetchDebounce: null,
      isFetchingBlockedSlots: false,
      selectedSlot: '10–11 AM',
      selectedDate: null,
      selectedServices: [],
      // modal for service selection confirmation
      showSelectServiceModal: false,
      selectServiceMessage: '',
      selectServiceMessageHtml: '',
      notes: '',
      currentMonth: new Date().getMonth(),
      currentYear: new Date().getFullYear(),
      calendarDays: [],
      bookings: {},

      init() {
        // Set today as default
        this.selectedDate = new Date();
        this.generateBookings();
        this.generateCalendar();
        // choose sensible default slot for the date
        this.chooseDefaultSlotForDate();
        // load blocked slots for today (debounced)
        try { this.fetchBlockedSlots(this.formattedSelectedDate); } catch(e) {}
        // start periodic poll to pick up admin cancellations - optimized to reduce lag
        try {
            if (!this._blockedPollId) {
                // Poll for updates - pause when page is hidden, increase interval to reduce lag
                const startPolling = () => {
                    if (this._blockedPollId) clearInterval(this._blockedPollId);
                    this._blockedPollId = setInterval(() => {
                        if (!document.hidden) this.fetchBlockedSlots(this.formattedSelectedDate, true);
                    }, 120000); // Increased to 2 minutes to reduce lag
                };
                const stopPolling = () => {
                    if (this._blockedPollId) {
                        clearInterval(this._blockedPollId);
                        this._blockedPollId = null;
                    }
                };
                startPolling();
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) stopPolling();
                    else startPolling();
                });
                window.addEventListener('beforeunload', () => { 
                    try { 
                        stopPolling();
                        if (this._blockedFetchController) { 
                            this._blockedFetchController.abort(); 
                            this._blockedFetchController = null; 
                        } 
                    } catch(e){} 
                });
            }
        } catch(e){}
        lucide.createIcons();
      },

      chooseDefaultSlotForDate() {
        // Pick first non-disabled future slot for the selected date
        for (const slot of this.timeSlots) {
          if (!this.isSlotDisabled(slot)) {
            this.selectedSlot = slot;
            return;
          }
        }
        // If none available, clear
        this.selectedSlot = null;
      },

      generateBookings() {
        const daysInMonth = new Date(this.currentYear, this.currentMonth + 1, 0).getDate();
        for (let day = 1; day <= daysInMonth; day++) {
          const random = Math.floor(Math.random() * 6);
          this.bookings[`${this.currentYear}-${this.currentMonth}-${day}`] = random;
        }
      },

      generateCalendar() {
        const firstDay = new Date(this.currentYear, this.currentMonth, 1).getDay();
        const daysInMonth = new Date(this.currentYear, this.currentMonth + 1, 0).getDate();
        const calendar = [];

        for (let i = 0; i < firstDay; i++) {
          calendar.push({ date: null, label: '' });
        }

        for (let day = 1; day <= daysInMonth; day++) {
          calendar.push({
            date: new Date(this.currentYear, this.currentMonth, day),
            label: day,
          });
        }

        this.calendarDays = calendar;
      },

      previousMonth() {
        if (this.currentMonth === 0) {
          this.currentMonth = 11;
          this.currentYear--;
        } else {
          this.currentMonth--;
        }
        this.generateBookings();
        this.generateCalendar();
      },

      nextMonth() {
        if (this.currentMonth === 11) {
          this.currentMonth = 0;
          this.currentYear++;
        } else {
          this.currentMonth++;
        }
        this.generateBookings();
        this.generateCalendar();
      },

      get currentMonthYear() {
        return new Date(this.currentYear, this.currentMonth).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
      },

      get formattedSelectedDate() {
        if (!this.selectedDate) return 'No date selected';
        const year = this.selectedDate.getFullYear();
        const month = String(this.selectedDate.getMonth() + 1).padStart(2, '0');
        const day = String(this.selectedDate.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
      },

      selectDate(day) {
        if (!day.date) return;
        this.selectedDate = day.date;
        // Debounce fetches to avoid rapid repeated network requests
        if (this._blockedFetchDebounce) clearTimeout(this._blockedFetchDebounce);
        this._blockedFetchDebounce = setTimeout(() => {
          try { this.fetchBlockedSlots(this.formattedSelectedDate); } catch(e) {}
        }, 200);
        // pick a default valid slot for the newly selected date
        this.chooseDefaultSlotForDate();
      },

      // Attempt to select a time slot, respecting disabled state
      selectSlot(slot) {
        if (this.isSlotDisabled(slot)) return;
        this.selectedSlot = slot;
      },

      isSlotDisabled(slot) {
        if (!this.selectedDate) return false;
        const startHH = this.slotMap[slot];
        if (!startHH) return false;
        const [hh, mm] = (startHH || '00:00').split(':').map(v => parseInt(v, 10));
        const sd = new Date(this.selectedDate.getFullYear(), this.selectedDate.getMonth(), this.selectedDate.getDate(), hh, mm, 0);
        const now = new Date();
        // Disable if the slot starts at or before the current time
        if (sd <= now) return true;
        // Disable if this exact time is blocked (admin cancelled)
        const timeKey = startHH;
        if (Array.isArray(this.blockedSlots) && this.blockedSlots.indexOf(timeKey) !== -1) return true;
        return false;
      },

      async fetchBlockedSlots(dateString, force = false) {
        try {
          if (!dateString) { this.blockedSlots = []; return; }

          // Check cache first
          const cached = this.blockedSlotsCache[dateString];
          const now = Date.now();
          if (!force && cached && (now - cached.ts) < this.blockedFetchTTL) {
            this.blockedSlots = Array.isArray(cached.slots) ? cached.slots : [];
            return;
          }

          // Abort previous fetch if in-flight
          try { if (this._blockedFetchController) { this._blockedFetchController.abort(); } } catch(e){}
          this._blockedFetchController = new AbortController();
          this.isFetchingBlockedSlots = true;

          const url = `{{ url('/api/appointments') }}/${dateString}/blocked`;
          const resp = await fetch(url, { headers: { 'Accept': 'application/json' }, signal: this._blockedFetchController.signal });
          if (!resp || !resp.ok) { this.blockedSlots = []; this.blockedSlotsCache[dateString] = { slots: [], ts: now }; return; }
          const data = await resp.json();
          const slots = Array.isArray(data.blocked_slots) ? data.blocked_slots : [];
          this.blockedSlots = slots;
          this.blockedSlotsCache[dateString] = { slots, ts: now };
          // If the currently selected slot became blocked, pick a new default
          if (this.selectedSlot && this.isSlotDisabled(this.selectedSlot)) {
            this.chooseDefaultSlotForDate();
          }
        } catch (e) {
          if (e && e.name === 'AbortError') {
            // fetch was aborted — ignore
            return;
          }
          console.error('Error fetching blocked slots', e);
          this.blockedSlots = [];
        } finally {
          this.isFetchingBlockedSlots = false;
          try { if (this._blockedFetchController) { this._blockedFetchController = null; } } catch(e){}
        }
      },

      getDayClasses(day) {
        if (!day.date) return 'bg-transparent cursor-default';
        const isSelected = this.selectedDate && day.date.toDateString() === this.selectedDate.toDateString();
        const isToday = day.date.toDateString() === new Date().toDateString();
        
        if (isSelected) {
          return 'bg-blue-600 text-white border-blue-600 border-2';
        } else if (isToday) {
          return 'bg-blue-100 border-blue-300 text-blue-900 font-semibold border-2';
        }
        return 'text-gray-700 border-gray-200 border-2 hover:border-blue-300 hover:bg-blue-50';
      },

      toggleService(service) {
        const index = this.selectedServices.findIndex(s => s.id === service.id);
        if (index > -1) {
          this.selectedServices.splice(index, 1);
        } else {
          if (this.selectedServices.length < 3) {
            this.selectedServices.push(service);
          }
        }
      },

      confirmAppointment() {
        if (!this.selectedDate || this.selectedServices.length === 0) {
          this.selectServiceMessage = 'Please select a date and at least one service';
          this.selectServiceMessageHtml = 'Please select a date<br>and at least one service';
          this.showSelectServiceModal = true;
          return;
        }
        if (!this.selectedSlot || this.isSlotDisabled(this.selectedSlot)) {
          this.selectServiceMessage = 'Please select a valid time slot';
          this.selectServiceMessageHtml = 'Please select a valid time slot';
          this.showSelectServiceModal = true;
          return;
        }
        // TODO: replace alert with a proper submission flow
        alert(`Appointment request submitted!\nDate: ${this.formattedSelectedDate}\nTime: ${this.selectedSlot}\nServices: ${this.selectedServices.length}`);
      },

      resetForm() {
        this.selectedDate = new Date();
        this.selectedSlot = '10–11 AM';
        this.selectedServices = [];
        this.notes = '';
      }
    }
  }
</script>
@endsection

