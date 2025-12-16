@props(['message', 'type' => 'success', 'duration' => 3000])

<div
    x-data="{
        show: true,
        progress: 100,
        init() {
            const interval = setInterval(() => {
                this.progress -= 100 / ({{ $duration }} / 50);
                if (this.progress <= 0) {
                    clearInterval(interval);
                    this.show = false;
                }
            }, 50);

            setTimeout(() => {
                this.show = false;
            }, {{ $duration }});
        }
    }"
    x-show="show"
    x-cloak
    x-transition:enter="transform transition-all ease-out duration-300"
    x-transition:enter-start="translate-y-3 opacity-0 scale-95"
    x-transition:enter-end="translate-y-0 opacity-100 scale-100"
    x-transition:leave="transform transition-all ease-in duration-200"
    x-transition:leave-start="translate-y-0 opacity-100 scale-100"
    x-transition:leave-end="-translate-y-2 opacity-0 scale-95"
    class="fixed inset-x-0 top-6 z-50 flex justify-center px-4 pointer-events-none"
>
    <div class="pointer-events-auto w-full max-w-md">
        <div class="overflow-hidden rounded-2xl shadow-2xl ring-1 ring-black/10">
            <div class="{{ $type === 'success'
                ? 'bg-gradient-to-r from-emerald-500 via-emerald-400 to-lime-400'
                : 'bg-gradient-to-r from-rose-500 via-amber-500 to-yellow-400'
            }} px-5 py-4 text-white">
                <div class="flex items-start gap-4">
                    <div class="rounded-xl bg-white/25 p-2 backdrop-blur">
                        <i
                            data-lucide="{{ $type === 'success' ? 'check-circle' : 'alert-triangle' }}"
                            class="h-6 w-6"
                        ></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-base font-semibold leading-snug">
                            {{ $type === 'success' ? 'Success' : 'Heads up' }}
                        </p>
                        <p class="mt-1 text-sm leading-relaxed text-white/90">
                            {{ $message }}
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="show = false"
                        class="text-white/80 hover:text-white transition focus:outline-none"
                    >
                        <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                </div>
            </div>
            <div class="h-1 bg-black/5">
                <div
                    class="{{ $type === 'success' ? 'bg-emerald-400' : 'bg-rose-400' }}"
                    :style="`width: ${progress}%; transition: width 50ms linear;`"
                    class="h-full"
                ></div>
            </div>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();
</script>

