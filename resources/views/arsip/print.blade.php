<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Lembar Disposisi {{ $letter->agenda_number }}</title>
    <style>
        @page { size: A4; margin: 18mm 16mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Times New Roman', serif; font-size: 13px; color: #000; margin: 0; padding: 24px; }
        .sheet { max-width: 780px; margin: 0 auto; }
        .kop { display: flex; align-items: center; gap: 14px; border-bottom: 3px double #000; padding-bottom: 8px; }
        .kop img { height: 64px; }
        .kop h1 { margin: 0; font-size: 17px; text-transform: uppercase; text-align: center; flex: 1; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        td, th { border: 1.5px solid #000; padding: 6px 8px; vertical-align: top; }
        .title { text-align: center; font-weight: bold; font-size: 16px; letter-spacing: 1px; }
        .box { display: inline-block; width: 13px; height: 13px; border: 1px solid #000; text-align: center; line-height: 11px; font-size: 11px; margin-right: 4px; font-family: 'DejaVu Sans', Arial, sans-serif; }
        .muted { color: #444; font-size: 11px; }
        .sign { margin-top: 28px; width: 260px; margin-left: auto; text-align: center; }
        .toolbar { max-width: 780px; margin: 0 auto 14px; display: flex; gap: 8px; font-family: Arial, sans-serif; }
        .toolbar button { padding: 8px 14px; border-radius: 8px; border: 0; background: #0E2B4B; color: #fff; font-weight: bold; cursor: pointer; }
        @media print { .toolbar { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()">Cetak / Simpan PDF</button><button onclick="window.close()" style="background:#5B6474">Tutup</button></div>
    <div class="sheet">
        <div class="kop">
            <img src="/images/logo-brief.png" alt="">
            <h1>{{ $agency }}</h1>
        </div>

        <table>
            <tr><td colspan="2" class="title">LEMBAR DISPOSISI</td></tr>
            <tr>
                <td style="width:50%">
                    Surat dari : {{ $letter->sender }}<br>
                    No. Surat : {{ $letter->letter_number }}<br>
                    Tgl. Surat : {{ $letter->letter_date->translatedFormat('d F Y') }}
                </td>
                <td>
                    Diterima Tgl : {{ $letter->received_date->translatedFormat('d F Y') }}<br>
                    No. Agenda : {{ $letter->agenda_number }}<br>
                    Sifat :
                    @foreach (\App\Models\Letter::NATURES as $key => $label)
                        <span style="white-space:nowrap"><span class="box">{{ $letter->nature === $key ? '✔' : '' }}</span>{{ $label }}</span>
                    @endforeach
                </td>
            </tr>
            <tr><td colspan="2" style="height:70px">Hal : {{ $letter->subject }}</td></tr>
            <tr>
                <td>
                    <b>Diteruskan kepada Sdr. :</b><br>
                    @foreach ($kadisTargets as $p)
                        <div><span class="box">{{ in_array($p->id, $firstLevel, true) ? '✔' : '' }}</span>{{ $p->name }}</div>
                    @endforeach
                </td>
                <td>
                    <b>Dengan hormat harap :</b><br>
                    @php $chosen = collect($letter->expectations ?? []); @endphp
                    @foreach (collect(\App\Models\Letter::EXPECTATIONS)->flatten(1) as $item)
                        @php
                            $match = isset($item['prefix']) ? $chosen->first(fn ($e) => str_starts_with($e, rtrim($item['text']))) : ($chosen->contains($item['text']) ? $item['text'] : null);
                        @endphp
                        <div><span class="box">{{ $match ? '✔' : '' }}</span>{{ $match ?? $item['text'] }}</div>
                    @endforeach
                </td>
            </tr>
            <tr><td colspan="2" style="height:80px"><b>Catatan :</b><br>{{ $letter->disposition_note }}</td></tr>
        </table>

        <div class="sign">
            Kepala Dinas,<br><br><br><br>
            <b><u>{{ $letter->disposer?->name }}</u></b><br>
            <span class="muted">{{ $letter->disposed_at->translatedFormat('d F Y H.i') }}</span>
        </div>

        @if ($followUps->isNotEmpty())
            <table>
                <tr><th colspan="4" style="text-align:left">Riwayat disposisi lanjutan</th></tr>
                <tr><th style="width:25%">Dari</th><th style="width:25%">Kepada</th><th>Catatan / balasan</th><th style="width:16%">Status</th></tr>
                @foreach ($followUps as $d)
                    <tr>
                        <td>{{ $d->fromPosition->name }}<br><span class="muted">{{ $d->created_at->format('d/m/Y H.i') }}</span></td>
                        <td>{{ $d->toPosition->name }}<br><span class="muted">{{ $d->toUser?->name }}</span></td>
                        <td>{{ $d->note }}@if ($d->reply)<br><i>Balasan: {{ $d->reply }}</i>@endif</td>
                        <td>{{ $d->stateLabel() }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        <p class="muted" style="margin-top:16px">Diparaf: {{ $letter->parafUser?->name }} ({{ $letter->verifierPosition?->name }}) · {{ $letter->paraf_at?->format('d/m/Y H.i') }} — dicetak dari BRIEF E-Arsip {{ now()->format('d/m/Y H.i') }}</p>
    </div>
</body>
</html>
