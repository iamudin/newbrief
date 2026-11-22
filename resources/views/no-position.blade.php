<x-layouts::guest>
    <div class="flex min-h-dvh items-center justify-center p-6">
        <div class="card max-w-sm text-center">
            <h1 class="font-bold text-navy">Akun belum ditempatkan pada jabatan</h1>
            <p class="mt-1 text-sm text-muted">Hubungi super admin untuk menempatkan akun Anda di struktur pegawai.</p>
            <form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="btn-primary w-full">Keluar</button></form>
        </div>
    </div>
</x-layouts::guest>
