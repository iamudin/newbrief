<div class="flex min-h-dvh flex-col items-center justify-center px-4 py-10">
    <div class="mb-6 flex flex-col items-center gap-3 text-white">
        <div class="flex size-16 items-center justify-center rounded-2xl bg-white shadow-lg"><img src="/images/logo-brief.png" alt="Logo BRIEF" class="size-14 object-contain"></div>
        <div class="text-center">
            <div class="text-2xl font-extrabold tracking-[4px]">BRIEF</div>
            <div class="text-xs text-[#C9D3E3]">Sistem Informasi Briefing Instruksi Berjenjang</div>
        </div>
    </div>

    <form wire:submit="login" class="card w-full max-w-sm !p-6">
        <h1 class="mb-4 text-lg font-bold text-navy">Masuk</h1>
        <div class="mb-3">
            <label for="nip" class="label">NIP</label>
            <input id="nip" type="text" inputmode="numeric" autocomplete="username" wire:model="nip" class="field" autofocus>
            @error('nip')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div class="mb-3">
            <label for="password" class="label">Password</label>
            <input id="password" type="password" autocomplete="current-password" wire:model="password" class="field">
            @error('password')<p class="error">{{ $message }}</p>@enderror
        </div>
        <label class="mb-4 flex items-center gap-2 text-xs text-muted"><input type="checkbox" wire:model="remember" class="accent-navy"> Ingat saya</label>
        <button type="submit" class="btn-gold w-full text-base tracking-wide" wire:loading.attr="disabled">
            <span wire:loading.remove>MASUK</span><span wire:loading>Memproses…</span>
        </button>
    </form>

    @if (app()->isLocal() && config('app.demo_accounts'))
        <div class="mt-5 w-full max-w-sm rounded-xl bg-navy-2/60 p-3 text-[11px] text-[#C9D3E3]">
            <div class="mb-1 font-bold text-gold">Akun demo (lokal) · password: password</div>
            @foreach (\App\Models\User::query()->where('is_active', true)->with('currentAssignment.position')->get()->sortBy(fn ($u) => $u->is_admin ? 0 : $u->level()?->rank()) as $demo)
                <button type="button" wire:click="$set('nip', '{{ $demo->nip }}')" class="flex w-full cursor-pointer justify-between gap-2 py-0.5 text-left hover:text-white">
                    <span>{{ $demo->is_admin ? 'Super Admin' : $demo->position()?->name }}</span><span class="font-mono">{{ $demo->nip }}</span>
                </button>
            @endforeach
        </div>
    @endif
</div>
