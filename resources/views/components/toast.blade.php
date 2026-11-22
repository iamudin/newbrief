<div x-data="{ show: false, message: '', tone: 'ok', timer: null }"
     x-on:toast.window="message = $event.detail.message ?? $event.detail[0]?.message; tone = $event.detail.tone ?? $event.detail[0]?.tone ?? 'ok'; show = true; clearTimeout(timer); timer = setTimeout(() => show = false, 3500)"
     @if (session('toast')) x-init="message = @js(session('toast')); show = true; timer = setTimeout(() => show = false, 3500)" @endif
     x-show="show" x-cloak x-transition
     class="fixed inset-x-3 top-3 z-[1000] mx-auto max-w-md">
    <div :class="tone === 'error' ? 'bg-rose-600 border-rose-500' : 'bg-ok border-ok-dark'" class="flex items-center justify-between gap-3 rounded-xl border px-3.5 py-2.5 text-xs font-semibold text-white shadow-xl">
        <span x-text="message"></span>
        <button type="button" x-on:click="show = false" class="cursor-pointer"><x-icon name="x" size="14" /></button>
    </div>
</div>
