<?php

use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\User;
use App\Services\ArsipService;
use App\Services\DispositionPdfService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

beforeEach(function () {
    Storage::fake('local');
    $this->org = makeOrg();
    $this->org['kadis']->update(['rank' => 'Pembina Utama Muda (IV/c)']);
    $opPos = Position::query()->create(['name' => 'Operator E-Arsip', 'level' => 'arsip', 'parent_id' => $this->org['positions']['e4']->id]);
    $this->operator = User::factory()->create();
    PositionAssignment::query()->create(['position_id' => $opPos->id, 'user_id' => $this->operator->id, 'started_at' => today()->subMonth()]);
});

/** PDF sumber dengan jumlah halaman tertentu (dibuat FPDF → PDF 1.3, didukung FPDI). */
function samplePdf(int $pages): UploadedFile
{
    $pdf = new Fpdi;
    for ($i = 1; $i <= $pages; $i++) {
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 14);
        $pdf->Text(20, 20, "Halaman surat {$i}");
    }

    return UploadedFile::fake()->createWithContent('surat.pdf', $pdf->Output('S'));
}

function disposedLetter(array $org, User $operator, UploadedFile $file)
{
    $service = app(ArsipService::class);
    $letter = $service->store($operator, ['letter_number' => '800/77', 'letter_date' => today()->toDateString(), 'received_date' => today()->toDateString(), 'sender' => 'Sekretariat Daerah', 'subject' => 'Undangan rapat koordinasi', 'nature' => 'segera'], $file);
    $service->paraf($org['e4'], $letter);
    $service->dispose($org['kadis'], $letter->fresh(), [$org['positions']['e3']->id], ['Selesaikan segera', 'Koordinasikan dengan Bappeda'], 'Laporkan hasilnya');

    return $letter->fresh();
}

function pageCount(string $content): int
{
    $path = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($path, $content);

    return (new Fpdi)->setSourceFile($path);
}

it('merges the disposition sheet with every page of the letter PDF', function () {
    $letter = disposedLetter($this->org, $this->operator, samplePdf(3));

    $result = app(DispositionPdfService::class)->build($letter);

    expect($result['merged'])->toBeTrue()
        ->and(pageCount($result['content']))->toBe(1 + 3);
});

it('places an image letter on its own page after the sheet', function () {
    $letter = disposedLetter($this->org, $this->operator, UploadedFile::fake()->image('surat.jpg', 800, 1100));

    $result = app(DispositionPdfService::class)->build($letter);

    expect($result['merged'])->toBeTrue()->and(pageCount($result['content']))->toBe(2);
});

it('renders the e-office style sheet content', function () {
    $letter = disposedLetter($this->org, $this->operator, samplePdf(1));
    $html = view('arsip.pdf-sheet', [
        'letter' => $letter->load('dispositions.toPosition', 'dispositions.toUser', 'dispositions.fromPosition', 'disposer'),
        'agency' => 'Dinas Penanaman Modal dan PTSP', 'government' => 'Pemerintah Kabupaten Bengkalis', 'regency' => 'Bengkalis', 'logoPath' => null,
        'kadis' => $letter->disposer, 'firstLevel' => $letter->dispositions->whereNull('parent_id'), 'followUps' => collect(),
    ])->render();

    expect($html)->toContain('LEMBAR DISPOSISI')
        ->toContain('PEMERINTAH KABUPATEN BENGKALIS')
        ->toContain('Diteruskan kepada Sdr')
        ->toContain($this->org['e3']->name.' - Kabid')
        ->toContain('Koordinasikan dengan Bappeda')
        ->toContain('KEPALA DINAS PENANAMAN MODAL DAN PTSP')
        ->toContain('Pembina Utama Muda (IV/c)')
        ->toContain('NIP. '.$this->org['kadis']->nip);
});

it('serves the merged PDF to people involved and falls back with a notice for unreadable PDFs', function () {
    $letter = disposedLetter($this->org, $this->operator, UploadedFile::fake()->createWithContent('rusak.pdf', 'bukan pdf'));

    $response = $this->actingAs($this->org['e3'])->get(route('arsip.print', $letter));
    $response->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Disposisi-Merged', '0');
    expect(pageCount($response->getContent()))->toBe(2); // lembar disposisi + halaman pemberitahuan

    $this->actingAs($this->org['e3'])->get(route('arsip.print', $letter).'?unduh=1')
        ->assertHeader('Content-Disposition', 'attachment; filename="disposisi-001sm'.now()->year.'-80077.pdf"');
    $this->actingAs($this->org['staff'])->get(route('arsip.print', $letter))->assertForbidden();
});
