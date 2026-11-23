@props(['message' => null, 'label' => null, 'compact' => false])
{{-- Status notifikasi WhatsApp: terkirim / antre / gagal / tidak dikirim (+ alasan) --}}
@if ($message)
    @php
        $icon = ['sent' => '✓', 'failed' => '✕', 'skipped' => '–'][$message->status] ?? '⏳';
        $title = trim(($label ? $label.': ' : '').$message->statusLabel().' · '.$message->created_at->format('d/m H.i')
            .($message->sent_at ? ' (terkirim '.$message->sent_at->format('H.i').')' : '')
            .($message->error ? ' — '.$message->error : ''));
    @endphp
    <span {{ $attributes->merge(['class' => 'chip '.$message->statusClass()]) }} title="{{ $title }}" data-wa-status="{{ $message->status }}">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm5.2 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.5-3.9-4.7-4.1-.1-.2-1.1-1.5-1.1-2.9s.7-2 1-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.3.5-.4.4c-.1.2-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.3 2.4 1.5.3.1.5.1.6-.1l.9-1c.2-.3.4-.2.6-.1l1.9.9c.3.1.5.2.5.3.1.2.1.7-.1 1.3Z"/></svg>
        @unless ($compact){{ $label ? $label.' · ' : '' }}@endunless{{ $message->statusLabel() }} {{ $icon }}
    </span>
    @if ($message->status !== 'sent' && $message->error && ! $compact)
        <span class="block text-[10px] text-muted">{{ $message->error }}</span>
    @endif
@endif
