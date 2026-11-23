{{-- Lembar disposisi (PDF / dompdf) — tata letak mengikuti template e-office --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 36px; }
        body { font-family: 'Times New Roman', Times, serif; font-size: 13px; color: #000; }
        .kop { text-align: center; position: relative; min-height: 72px; }
        .kop img { position: absolute; left: 0; top: 0; height: 70px; }
        .kop h3 { margin: 6px 0 22px; padding: 0; font-size: 17px; line-height: 1.3; }
        .kop span { font-size: 16px; }
        hr.t { border: 0; border-top: 1px solid #000; margin: 0; }
        hr.b { border: 0; border-bottom: 2px solid #000; margin: 2px 0 0; }
        table { width: 100%; border-collapse: collapse; border: 2px solid #000; }
        td { border: 2px solid #000; vertical-align: top; }
        .check { display: inline-block; width: 12px; height: 12px; border: 1px solid #000; font-family: 'DejaVu Sans', sans-serif; font-size: 10px; line-height: 11px; text-align: center; }
        ul.checklist { margin: 0; padding: 0 0 0 10px; list-style: none; }
        ul.checklist li { margin-top: 2px; }
        ul.notes { list-style-type: disc; padding-left: 20px; margin: 4px 0 0; }
        ul.notes li { margin-top: 8px; }
        .ttd { margin-top: 70px; padding: 16px; font-size: 14px; }
        .muted { color: #555; font-size: 10px; }
    </style>
</head>
<body>
    <div class="kop">
        @if ($logoPath)<img src="{{ $logoPath }}" alt="">@endif
        <h3>
            {{ str($government)->upper() }}<br>
            <span>{{ str($agency)->upper() }}</span>
        </h3>
    </div>
    <hr class="t"><hr class="b">
    <br>

    <table style="border-bottom: none;">
        <tr>
            <td colspan="2" style="text-align:center; border-bottom:none;">
                <h3 style="line-height: normal; padding: 5px 0; margin: 0;">LEMBAR DISPOSISI</h3>
            </td>
        </tr>
        <tr>
            <td style="width:50%; border-bottom:none; padding-left:5px;">
                Surat dari : {{ $letter->sender }}<br>
                No. Surat : {{ $letter->letter_number }}<br>
                Tgl. Surat : {{ $letter->letter_date->translatedFormat('d F Y') }}
            </td>
            <td style="width:50%; border-bottom:none; padding:0 0 5px 5px;">
                Diterima Tgl : {{ $letter->received_date->translatedFormat('d F Y') }}<br>
                No. Agenda : {{ $letter->agenda_number }}<br>
                Sifat :<br>
                @foreach (['segera' => 'Segera', 'sangat_segera' => 'Sangat Segera', 'rahasia' => 'Rahasia', 'biasa' => 'Biasa'] as $key => $label)
                    <span class="check">{{ $letter->nature === $key ? '✔' : '' }}</span> {{ $label }}&nbsp;
                @endforeach
            </td>
        </tr>
    </table>

    <table style="border-top: none; border-bottom: none;">
        <tr>
            <td style="width:15%; height:100px; border-bottom:none; padding-left:5px;">Hal</td>
            <td style="border-bottom:none;">: {{ $letter->subject }}</td>
        </tr>
    </table>

    <table style="border-top: none;">
        <tr>
            <td style="width:50%; padding:0 0 10px 5px;">
                Diteruskan kepada Sdr :
                <ul class="checklist">
                    @forelse ($firstLevel as $d)
                        <li><span class="check">✔</span> {{ $d->toUser?->name ?? '-' }} - {{ $d->toPosition->name }}</li>
                    @empty
                        <li style="color:#888; font-style:italic;">-</li>
                    @endforelse
                </ul>
            </td>
            <td style="padding:0 0 10px 5px;">
                Dengan hormat harap :
                <ul class="checklist">
                    @forelse ((array) ($letter->expectations ?? []) as $item)
                        <li><span class="check">✔</span> {{ $item }}</li>
                    @empty
                        <li style="color:#888; font-style:italic;">-</li>
                    @endforelse
                </ul>
            </td>
        </tr>
        <tr>
            <td style="border-right:none; padding-left:5px;">
                Catatan :
                <ul class="notes">
                    <li>Kepala Dinas<br><i>{{ $letter->disposition_note ?: '-' }}</i></li>
                    @foreach ($firstLevel as $d)
                        @if ($d->reply)
                            <li>{{ $d->toPosition->name }}<br><i>{{ $d->reply }}</i></li>
                        @endif
                    @endforeach
                    @foreach ($followUps as $d)
                        <li>{{ $d->fromPosition->name }} &raquo; {{ $d->toPosition->name }}<br><i>{{ $d->note ?: '-' }}</i>@if ($d->reply)<br><i>Balasan: {{ $d->reply }}</i>@endif</li>
                    @endforeach
                </ul>
            </td>
            <td style="border-left:none; vertical-align:bottom;">
                <div class="ttd">
                    KEPALA {{ str($agency)->upper() }}<br>
                    @if ($regency)KABUPATEN {{ str($regency)->upper() }}<br>@endif
                    <br><br>
                    dto
                    <br><br><br>
                    {{ $kadis?->name ?? '' }}<br>
                    {{ $kadis?->rank ?? '' }}<br>
                    NIP. {{ $kadis?->nip ?? '' }}
                </div>
            </td>
        </tr>
    </table>
    <p class="muted">Didisposisi {{ $letter->disposed_at?->translatedFormat('d F Y H.i') }} · Diparaf {{ $letter->parafUser?->name }} ({{ $letter->verifierPosition?->name }}) {{ $letter->paraf_at?->format('d/m/Y H.i') }} · Dicetak dari BRIEF E-Arsip {{ now()->format('d/m/Y H.i') }}</p>
</body>
</html>
