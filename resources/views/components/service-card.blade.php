<!--
  Partial: service-card
  This expects to be used inside an Alpine `template x-for="service in services"`
  It references `service` and selection functions from parent Alpine scope.
-->
<label class="cursor-pointer">
    <input type="radio" class="sr-only" name="service" :value="service.id" x-model="selectedService">
    <div :class="selectedService === service.id ? 'ring-2 ring-emerald-500 bg-emerald-50/50' : 'ring-1 ring-slate-100 bg-white'"
         class="rounded-2xl p-1 transition shadow-sm hover:shadow-md flex flex-col gap-1">
        <div class="flex items-center gap-3">
            <div class="h-6 w-6 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-sm font-semibold shadow-inner">
                <i :data-lucide="service.icon"></i>
            </div>
            <div>
                <p class="text-[9px] font-semibold text-emerald-600 uppercase tracking-wide" x-text="service.category"></p>
                <h3 class="text-[12px] font-semibold text-slate-900" x-text="service.name"></h3>
            </div>
        </div>
        <p class="text-[10px] text-slate-500 flex-1 line-clamp-2" x-text="service.description"></p>
        <div class="flex items-center justify-between text-sm font-medium text-slate-500">
            <span class="flex items-center gap-1">
                <i data-lucide="clock-4" class="h-4 w-4"></i>
                <span x-text="service.duration"></span>
            </span>
            <span class="text-emerald-600 font-semibold" x-text="service.price ? `₱${service.price}` : 'Contact for pricing'"></span>
        </div>
    </div>
</label>