<div class="flex min-h-dvh flex-col items-center justify-center px-4 py-10">
    <x-toast />
    <form wire:submit="save" class="card w-full max-w-sm !p-6">
        <h1 class="text-lg font-bold text-navy">Ganti password</h1>
        @if ($forced)
            <p class="mt-1 mb-3 rounded-lg bg-amber-50 p-2.5 text-xs font-semibold text-amber-800">Demi keamanan, ganti password sementara Anda sebelum melanjutkan.</p>
        @else
            <p class="mt-1 mb-3 text-xs text-muted">{{ auth()->user()->name }} · NIP {{ auth()->user()->nip }}</p>
        @endif
        <div class="mb-3"><label class="label" for="cp">Password lama</label><input id="cp" type="password" wire:model="current_password" autocomplete="current-password" class="field">@error('current_password')<p class="error">{{ $message }}</p>@enderror</div>
        <div class="mb-3"><label class="label" for="np">Password baru</label><input id="np" type="password" wire:model="password" autocomplete="new-password" class="field">@error('password')<p class="error">{{ $message }}</p>@enderror
            <p class="mt-1 text-[11px] text-muted">Minimal 8 karakter, berisi huruf dan angka, bukan NIP.</p></div>
        <div class="mb-4"><label class="label" for="pc">Ulangi password baru</label><input id="pc" type="password" wire:model="password_confirmation" autocomplete="new-password" class="field"></div>
        <button class="btn-gold w-full" wire:loading.attr="disabled">Simpan password</button>
        <div class="mt-3 flex justify-between text-xs">
            @unless ($forced)<a href="{{ auth()->user()->homeRoute() }}" class="font-semibold text-navy underline">Kembali</a>@endunless
        </div>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf<button class="text-xs font-semibold text-[#C9D3E3] underline">Keluar</button></form>
</div>
