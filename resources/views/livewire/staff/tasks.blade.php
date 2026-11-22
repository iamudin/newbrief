<div>
    <x-page-header title="Tugas Hari Ini">
        <div class="mt-3 flex flex-wrap gap-2">
            <span class="chip bg-navy-2 text-white">{{ $done }} dari {{ $total }} tuntas</span>
            @if ($onField)<span class="chip bg-sky-100 text-sky-700">Dinas luar hari ini</span>@endif
        </div>
    </x-page-header>

    <main class="mx-auto flex w-full max-w-3xl flex-col gap-3 px-4 py-4 lg:px-8">
        @unless ($checkin)
            <x-checkin-card :checkin="$checkin" :compliance="null" :deadline="$deadline" />
        @endunless

        @forelse ($recipients as $r)
            <section wire:key="r-{{ $r->id }}" class="card flex flex-col gap-2.5">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="text-[11px] font-bold text-muted uppercase">Dari {{ $r->direction->fromPosition->name }} · {{ $r->direction->created_at->format('H.i') }}</div>
                        <p class="mt-0.5 text-sm">{{ str($r->direction->content)->limit(140) }}</p>
                    </div>
                    <span class="chip {{ $r->stateClass() }}">{{ $r->stateLabel() }}</span>
                </div>
                <x-progress :percent="$r->progressPercent()" />
                @foreach ($r->tasks as $task)
                    <div wire:key="t-{{ $task->id }}" class="rounded-xl border border-line p-2.5" x-data="{ busy: false }">
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-sm font-semibold">{{ $task->title }}</span>
                            <span class="chip {{ $task->sourceClass() }}">{{ $task->sourceLabel() }}</span>
                        </div>
                        @if ($task->file_path)
                            <a href="{{ route('file', ['tugas', $task->id]) }}" class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-navy underline"><x-icon name="clip" size="13" />{{ $task->file_name }}</a>
                        @endif
                        @if ($r->verification_status !== 'approved')
                            <label class="mt-2 flex items-center gap-2 text-[11px] text-muted">
                                <x-icon name="clip" size="13" />
                                <input type="file" wire:model="taskFiles.{{ $task->id }}" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" class="min-w-0 flex-1 text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-surface file:px-2.5 file:py-1 file:text-[11px] file:font-semibold">
                            </label>
                            <span wire:loading wire:target="taskFiles.{{ $task->id }}" class="text-[11px] font-semibold text-navy">Mengunggah & menyimpan…</span>
                            @error("taskFiles.$task->id")<p class="error">{{ $message }}</p>@enderror
                            <div class="mt-2 grid grid-cols-3 gap-1.5">
                                @foreach ([\App\Enums\TaskStatus::Proses, \App\Enums\TaskStatus::Tunda, \App\Enums\TaskStatus::Selesai] as $status)
                                    <button type="button" :disabled="busy" x-on:click="busy = true; await $wire.updateTask({{ $task->id }}, '{{ $status->value }}', await briefLocate()); busy = false"
                                        @class(['btn btn-sm border', 'border-transparent '.$status->badgeClass() => $task->status === $status, 'border-line bg-white text-muted' => $task->status !== $status])>{{ $status->label() }}</button>
                                @endforeach
                            </div>
                        @else
                            <span class="chip mt-1 {{ $task->status->badgeClass() }}">{{ $task->status->label() }}</span>
                        @endif
                    </div>
                @endforeach
                @if ($r->reported_at)
                    <div class="flex flex-col gap-1 rounded-xl bg-surface p-2.5 text-xs">
                        <span class="font-bold text-muted">Laporan terkirim · {{ $r->reported_at->format('H.i') }}</span>
                        @if ($r->report_note)<span>{{ str($r->report_note)->limit(120) }}</span>@endif
                    </div>
                @endif
                <x-recipient-attachments :recipient="$r" :with-tasks="false" class="!mt-0" />
                <a href="{{ route('recipient.show', $r) }}" wire:navigate class="btn-ok">{{ $r->reported_at ? 'Lihat / kirim ulang laporan' : 'Tulis pesan & kirim laporan' }}</a>
            </section>
        @empty
            <x-empty>Belum ada tugas masuk hari ini. Tetap tekan SIAP sebelum pukul {{ $deadline }}.</x-empty>
        @endforelse

        <div class="flex flex-wrap gap-3 text-[11px] text-muted">
            <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-[3px] bg-info-soft"></span>Dari atasan</span>
            <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-[3px] bg-ok-soft"></span>Tupoksi</span>
            <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-[3px] bg-gold-soft"></span>Inisiatif</span>
        </div>
    </main>
</div>
