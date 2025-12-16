<!--
  Partial: time-slot-chip
  Expected to be used inside an Alpine `template x-for="slot in timeSlots"`
  It references `slot` and `selectedSlot` from the parent Alpine scope
  It preserves existing aria, keyboard handlers and styles.
-->
<label class="inline-block">
        <input type="radio" name="appointment_slot" class="sr-only"
            :checked="selectedSlot === slot"
            :disabled="typeof isSlotDisabled === 'function' && isSlotDisabled(slot)"
            @change="selectSlot(slot)">
    <button type="button"
            :aria-label="`Select time slot ${slot}`"
            role="radio"
            :aria-checked="selectedSlot === slot"
            tabindex="0"
            @click="selectSlot(slot)"
            @keydown.enter.prevent="selectSlot(slot)"
            @keydown.space.prevent="selectSlot(slot)"
            :disabled="typeof isSlotDisabled === 'function' && isSlotDisabled(slot)"
            :aria-disabled="typeof isSlotDisabled === 'function' && isSlotDisabled(slot) ? 'true' : 'false'"
            :title="typeof isSlotDisabled === 'function' && isSlotDisabled(slot) ? 'Unavailable' : ''"
            :class="(typeof isSlotDisabled === 'function' && isSlotDisabled(slot))
                ? 'cursor-not-allowed opacity-60 bg-gray-100 text-gray-400 border border-gray-200 rounded-2xl px-4 py-2 text-sm font-medium transition flex items-center gap-3 min-w-[110px] focus:outline-none'
                : (selectedSlot === slot
                ? 'bg-blue-600 text-white border-blue-600 shadow-lg ring-2 ring-blue-400'
                : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 hover:border-blue-200 hover:text-blue-600'"
            class="rounded-2xl border px-4 py-2 text-sm font-medium transition flex items-center gap-3 min-w-[110px] focus:outline-none focus:ring-2 focus:ring-blue-300">
        <span class="h-4 w-4 rounded-full border-2 flex items-center justify-center"
              :class="selectedSlot === slot ? 'border-white bg-white' : 'border-slate-300 bg-white'">
            <span class="h-2 w-2 rounded-full" x-show="selectedSlot === slot"
                  :class="selectedSlot === slot ? 'bg-blue-600' : 'bg-white'"></span>
        </span>
        <i data-lucide="clock" class="h-4 w-4"></i>
        <span class="flex-1 text-left text-[13px] font-medium" x-text="slot"></span>
        <template x-if="selectedSlot === slot">
            <i data-lucide="chevron-right" class="h-4 w-4 text-white ml-1"></i>
        </template>
    </button>
</label>