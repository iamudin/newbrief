@props(['recipient', 'withTasks' => true])
@php
    $attachedTasks = $withTasks && $recipient->relationLoaded('tasks') ? $recipient->tasks->whereNotNull('file_path') : collect();
    $direction = $recipient->relationLoaded('direction') ? $recipient->direction : null;
@endphp
@if ($recipient->report_file_path || $attachedTasks->isNotEmpty() || $direction?->attachment_path)
    <div {{ $attributes->merge(['class' => 'mt-2 flex flex-wrap gap-1.5']) }} x-on:click.stop>
        @if ($direction?->attachment_path)
            <a href="{{ route('file', ['arahan', $direction->id]) }}" class="chip max-w-full bg-info-soft text-navy" title="Lampiran arahan dari atasan"><x-icon name="clip" size="11" /><span class="truncate">{{ $direction->attachment_name }}</span></a>
        @endif
        @if ($recipient->report_file_path)
            <a href="{{ route('file', ['laporan', $recipient->id]) }}" class="chip max-w-full bg-ok-soft text-ok-dark" title="Lampiran laporan terkirim"><x-icon name="clip" size="11" /><span class="truncate">Laporan: {{ $recipient->report_file_name }}</span></a>
        @endif
        @foreach ($attachedTasks as $task)
            <a href="{{ route('file', ['tugas', $task->id]) }}" class="chip max-w-full bg-gold-soft text-[#7A5A00]" title="{{ $task->title }}"><x-icon name="clip" size="11" /><span class="truncate">{{ $task->file_name }}</span></a>
        @endforeach
    </div>
@endif
