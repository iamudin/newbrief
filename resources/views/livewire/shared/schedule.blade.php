<div>
    <x-page-header title="Jadwal Alur 1 Hari Kerja" subtitle="Ritme instruksi berjenjang (WIB)" />
    <main class="mx-auto w-full max-w-3xl px-4 py-4 lg:px-8">
        <ol class="relative ml-3 border-l-2 border-line">
            @foreach ($steps as $step)
                <li class="mb-3 ml-5">
                    <span @class(['absolute -left-[9px] mt-4 size-4 rounded-full border-2 border-white', 'bg-gold' => $step['level'] === $level, 'bg-navy-3' => $step['level'] !== $level])></span>
                    <div @class(['card !p-3', 'border-gold bg-gold-soft/40' => $step['level'] === $level])>
                        <div class="flex items-center justify-between gap-2"><span class="text-sm font-extrabold text-navy">{{ $step['time'] }}{{ isset($step['until']) ? ' – '.$step['until'] : '' }}</span><span class="chip bg-info-soft text-navy">{{ $step['actor'] }}</span></div>
                        <p class="mt-1 text-[13px]">{{ $step['text'] }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
        <p class="text-[11px] text-muted">Akhir pekan dan hari libur yang ditetapkan super admin tidak dihitung dalam kepatuhan.</p>
    </main>
</div>
