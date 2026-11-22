<div>
    <x-page-header :title="$direction->typeLabel()" :subtitle="$direction->date->translatedFormat('l, d F Y').' · '.$direction->created_at->format('H.i')" :back="url()->previous() !== url()->current() ? url()->previous() : auth()->user()->homeRoute()">
        <div class="mt-3 flex flex-wrap gap-2">
            <span class="chip {{ $recipient->stateClass() }}">{{ $recipient->stateLabel() }}</span>
            @if ($recipient->tasks->isNotEmpty())<span class="chip bg-navy-2 text-white">{{ $recipient->doneCount() }}/{{ $recipient->tasks->count() }} selesai · {{ $recipient->progressPercent() }}%</span>@endif
        </div>
    </x-page-header>

    <main class="mx-auto flex w-full max-w-3xl flex-col gap-3 px-4 py-4 lg:px-8">
        <section class="rounded-[14px] bg-navy p-4 text-white">
            <div class="text-[11px] font-bold tracking-wider text-gold uppercase">Dari {{ $direction->fromPosition->name }} · {{ $direction->fromUser->name }}</div>
            @if ($direction->title)<div class="mt-1 font-bold">{{ $direction->title }}</div>@endif
            <p class="mt-1 text-[15px] leading-relaxed whitespace-pre-line">{{ $direction->content }}</p>
            @if ($direction->attachment_path)
                <a href="{{ route('file', ['arahan', $direction->id]) }}" class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-gold underline"><x-icon name="clip" size="14" />{{ $direction->attachment_name }}</a>
            @endif
            @if ($direction->parent)
                <div class="mt-3 rounded-lg bg-navy-2 p-2.5 text-xs text-[#C9D3E3]"><b class="text-white">Pemicu:</b> {{ $direction->parent->typeLabel() }} {{ $direction->parent->fromUser->name }} — {{ str($direction->parent->content)->limit(140) }}</div>
            @endif
        </section>

        <section class="card flex flex-col gap-1 text-xs text-muted">
            <div><b class="text-ink">Penerima:</b> {{ $recipient->toPosition->name }} · {{ $recipient->toUser?->name ?? 'Jabatan kosong' }}</div>
            <div><b class="text-ink">SIAP:</b> {{ $recipient->confirmed_at?->format('d/m H.i') ?? '—' }} · <b class="text-ink">Lapor:</b> {{ $recipient->reported_at?->format('d/m H.i') ?? '—' }} · <b class="text-ink">Verifikasi:</b> {{ $recipient->verified_at?->format('d/m H.i') ?? '—' }}</div>
        </section>

        @if ($isRecipient && ! $recipient->confirmed_at)
            <button type="button" x-data="{ busy: false }" x-on:click="busy = true; await $wire.confirm(await briefLocate()); busy = false" :disabled="busy" class="btn-gold h-14 text-lg tracking-[3px]">
                <span x-show="!busy">SIAP</span><span x-show="busy" x-cloak class="text-sm tracking-normal">Merekam lokasi…</span>
            </button>
        @endif

        @if ($recipient->tasks->isNotEmpty())
            <section class="card flex flex-col gap-3">
                <div class="flex items-baseline justify-between">
                    <h2 class="card-title">Checklist tugas</h2>
                    <span class="text-xs font-bold text-ok-dark">{{ $recipient->doneCount() }} dari {{ $recipient->tasks->count() }} tuntas</span>
                </div>
                <x-progress :percent="$recipient->progressPercent()" />
                @foreach ($recipient->tasks as $task)
                    <div wire:key="task-{{ $task->id }}" class="rounded-xl border border-line p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex flex-col gap-1">
                                <span class="text-sm font-semibold">{{ $task->title }}</span>
                                <span class="chip {{ $task->sourceClass() }} w-fit">{{ $task->sourceLabel() }}</span>
                            </div>
                            <span class="chip {{ $task->status->badgeClass() }}">{{ $task->status->label() }}</span>
                        </div>
                        @if ($task->note && ! $isRecipient)<p class="mt-2 text-xs text-muted">Catatan: {{ $task->note }}</p>@endif
                        @if ($task->file_path)<a href="{{ route('file', ['tugas', $task->id]) }}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-navy underline"><x-icon name="clip" size="13" />{{ $task->file_name }}</a>@endif
                        @if ($task->location_zone)<span class="chip ml-1 {{ \App\Models\LocationLog::zoneClass($task->location_zone) }}">{{ \App\Models\LocationLog::zoneLabel($task->location_zone) }}</span>@endif

                        @if ($isRecipient && $recipient->verification_status !== 'approved')
                            <div class="mt-3 flex flex-col gap-2" x-data="{ busy: false }">
                                <input type="text" wire:model="taskNotes.{{ $task->id }}" placeholder="Catatan (opsional)" class="field !py-2 text-xs">
                                <label class="flex flex-col gap-1">
                                    <span class="text-[11px] font-semibold text-muted">{{ $task->file_path ? 'Ganti lampiran bukti' : 'Lampiran bukti (opsional)' }} · tersimpan otomatis setelah dipilih</span>
                                    <input type="file" wire:model="taskFiles.{{ $task->id }}" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" class="text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-surface file:px-3 file:py-1.5 file:text-xs file:font-semibold">
                                </label>
                                <span wire:loading wire:target="taskFiles.{{ $task->id }}" class="text-[11px] font-semibold text-navy">Mengunggah & menyimpan…</span>
                                @error("taskFiles.$task->id")<p class="error">{{ $message }}</p>@enderror
                                <div class="grid grid-cols-3 gap-1.5">
                                    @foreach ([\App\Enums\TaskStatus::Proses, \App\Enums\TaskStatus::Tunda, \App\Enums\TaskStatus::Selesai] as $status)
                                        <button type="button" :disabled="busy" x-on:click="busy = true; await $wire.updateTask({{ $task->id }}, '{{ $status->value }}', await briefLocate()); busy = false"
                                            @class(['btn btn-sm border', 'border-transparent '.$status->badgeClass() => $task->status === $status, 'border-line bg-white text-muted' => $task->status !== $status])>{{ $status->label() }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </section>
        @endif

        {{-- Laporan terkirim: terlihat oleh pengirim laporan (bawahan) maupun atasan --}}
        @if ($recipient->reported_at)
            <section class="card flex flex-col gap-2" data-test="report-sent">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="card-title">{{ $isRecipient ? 'Laporan yang Anda kirim' : 'Laporan' }}</h2>
                    <span class="text-[11px] text-muted">{{ $recipient->reported_at->format('d/m/Y H.i') }}</span>
                </div>
                <p class="text-sm whitespace-pre-line">{{ $recipient->report_note ?: 'Tanpa catatan.' }}</p>
                @php $attachedTasks = $recipient->tasks->whereNotNull('file_path'); @endphp
                @if ($recipient->report_file_path || $attachedTasks->isNotEmpty())
                    <div class="flex flex-col gap-1.5 rounded-xl bg-surface p-2.5">
                        <span class="text-[11px] font-bold text-muted uppercase">Lampiran terkirim</span>
                        @if ($recipient->report_file_path)
                            <a href="{{ route('file', ['laporan', $recipient->id]) }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-navy underline"><x-icon name="clip" size="13" />{{ $recipient->report_file_name }} <span class="font-normal text-muted no-underline">· laporan</span></a>
                        @endif
                        @foreach ($attachedTasks as $task)
                            <a href="{{ route('file', ['tugas', $task->id]) }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-navy underline"><x-icon name="clip" size="13" />{{ $task->file_name }} <span class="font-normal text-muted no-underline">· {{ str($task->title)->limit(40) }}</span></a>
                        @endforeach
                    </div>
                @endif
                @if ($recipient->verification_status)
                    <div class="text-xs text-muted">Diverifikasi {{ $recipient->verifier?->name }} · {{ $recipient->verified_at?->format('d/m H.i') }}{{ $recipient->verification_note ? ' — '.$recipient->verification_note : '' }}</div>
                @else
                    <span class="chip w-fit bg-amber-100 text-amber-800">Menunggu verifikasi atasan</span>
                @endif
            </section>
        @endif

        @if ($isRecipient && $recipient->verification_status !== 'approved')
            <section class="card flex flex-col gap-2" x-data="{ busy: false }">
                <h2 class="card-title">{{ $recipient->reported_at ? 'Kirim ulang laporan' : 'Kirim laporan ke atasan' }}</h2>
                @if ($recipient->verification_status === 'revision')
                    <div class="rounded-lg bg-rose-50 p-2.5 text-xs text-rose-700"><b>Catatan revisi:</b> {{ $recipient->verification_note }}</div>
                @endif
                <textarea wire:model="reportNote" rows="3" class="field" placeholder="Ringkasan hasil pekerjaan / pesan tambahan"></textarea>
                <label class="flex flex-col gap-1">
                    <span class="text-[11px] font-semibold text-muted">Lampiran laporan (opsional)</span>
                    <input type="file" wire:model="reportFile" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" class="text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-surface file:px-3 file:py-1.5 file:text-xs file:font-semibold">
                </label>
                <span wire:loading wire:target="reportFile" class="text-[11px] font-semibold text-navy">Mengunggah…</span>
                @if ($reportFile && ! $errors->has('reportFile'))
                    <span class="text-[11px] font-semibold text-amber-700">File siap: {{ $reportFile->getClientOriginalName() }} — tekan "Kirim laporan" untuk mengirim.</span>
                @endif
                @if ($recipient->report_file_path)<p class="text-[11px] text-muted">Kosongkan bila tetap memakai lampiran sebelumnya ({{ $recipient->report_file_name }}).</p>@endif
                @error('reportFile')<p class="error">{{ $message }}</p>@enderror
                <button type="button" :disabled="busy" x-on:click="busy = true; await $wire.sendReport(await briefLocate()); busy = false" wire:loading.attr="disabled" wire:target="reportFile" class="btn-ok">Kirim laporan</button>
            </section>
        @endif

        @if ($isSender && $recipient->reported_at && ! $recipient->verification_status)
            <section class="card flex flex-col gap-2 border-gold">
                <h2 class="card-title">Verifikasi laporan</h2>
                <textarea wire:model="verificationNote" rows="2" class="field" placeholder="Catatan (wajib bila revisi)"></textarea>
                @error('verificationNote')<p class="error">{{ $message }}</p>@enderror
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" wire:click="verify('revision')" class="btn-outline !border-rose-300 !text-rose-700">Perlu revisi</button>
                    <button type="button" wire:click="verify('approved')" class="btn-ok">Terima</button>
                </div>
            </section>
        @endif

        @if ($forwarded->isNotEmpty())
            <section class="card flex flex-col gap-2">
                <h2 class="card-title">Diteruskan ke bawahan</h2>
                @foreach ($forwarded as $child)
                    @foreach ($child->recipients as $r)
                        <a href="{{ route('recipient.show', $r) }}" wire:navigate class="flex items-center justify-between gap-2 rounded-xl border border-line p-2.5 hover:bg-surface">
                            <div class="flex flex-col"><span class="text-sm font-semibold">{{ $r->toUser?->name ?? '—' }}</span><span class="text-[11px] text-muted">{{ $r->toPosition->name }} · {{ $r->doneCount() }}/{{ $r->tasks->count() }} tugas</span></div>
                            <span class="chip {{ $r->stateClass() }}">{{ $r->stateLabel() }}</span>
                        </a>
                    @endforeach
                @endforeach
            </section>
        @endif

        <section class="card flex flex-col gap-2">
            <h2 class="card-title">Pesan</h2>
            @forelse ($recipient->messages as $m)
                <div @class(['max-w-[85%] rounded-xl px-3 py-2 text-sm', 'self-end bg-info-soft' => $m->user_id === auth()->id(), 'bg-surface' => $m->user_id !== auth()->id()])>
                    <div class="text-[10px] font-bold text-muted">{{ $m->user->name }} · {{ $m->created_at->format('d/m H.i') }}</div>{{ $m->body }}
                </div>
            @empty
                <x-empty>Belum ada pesan.</x-empty>
            @endforelse
            @if ($isRecipient || $isSender)
                <form wire:submit="sendMessage" class="flex gap-2">
                    <input type="text" wire:model="message" class="field" placeholder="Tulis pesan tambahan…">
                    <button class="btn-primary shrink-0"><x-icon name="message" size="16" /></button>
                </form>
                @error('message')<p class="error">{{ $message }}</p>@enderror
            @endif
        </section>
    </main>
</div>
