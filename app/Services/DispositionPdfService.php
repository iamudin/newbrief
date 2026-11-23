<?php

namespace App\Services;

use App\Models\Letter;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Throwable;

/**
 * Lembar disposisi PDF yang digabung dengan file surat (seperti e-office: dompdf + FPDI).
 *  - PDF surat diimpor halaman per halaman.
 *  - PDF versi baru (≥1.5, kompresi xref) yang tidak didukung parser gratis FPDI
 *    dikonversi dulu ke PDF 1.4 dengan Ghostscript, lalu diimpor ulang.
 *  - Surat berupa gambar (JPG/PNG) ditempatkan pada halaman A4.
 */
class DispositionPdfService
{
    /** @var array<int, string> */
    private array $temporary = [];

    /** @return array{content: string, merged: bool, warning: ?string} */
    public function build(Letter $letter): array
    {
        try {
            $sheet = $this->tempFile($this->renderSheet($letter), 'pdf');
            $pdf = new Fpdi;
            $this->appendPdf($pdf, $sheet);

            $source = Storage::disk('local')->path($letter->file_path);
            $warning = null;

            if (! is_file($source)) {
                $warning = 'File surat tidak ditemukan di penyimpanan.';
            } elseif ($letter->isPdf()) {
                $warning = $this->appendLetterPdf($pdf, $source);
            } else {
                $this->appendImage($pdf, $source);
            }

            if ($warning) {
                $this->appendNotice($pdf, $warning);
            }

            return ['content' => $pdf->Output('S'), 'merged' => $warning === null, 'warning' => $warning];
        } finally {
            foreach ($this->temporary as $file) {
                @unlink($file);
            }
            $this->temporary = [];
        }
    }

    public function renderSheet(Letter $letter): string
    {
        $letter->loadMissing('dispositions.toPosition', 'dispositions.toUser', 'dispositions.fromPosition', 'disposer', 'parafUser', 'verifierPosition');
        $logo = Setting::get('agency_logo') ?: 'images/logo-brief.png';

        return Pdf::loadView('arsip.pdf-sheet', [
            'letter' => $letter,
            'agency' => Setting::get('agency_name'),
            'government' => Setting::get('government_name'),
            'regency' => Setting::get('regency'),
            'logoPath' => is_file(public_path($logo)) ? public_path($logo) : null,
            'kadis' => $letter->disposer,
            'firstLevel' => $letter->dispositions->whereNull('parent_id')->values(),
            'followUps' => $letter->dispositions->whereNotNull('parent_id')->values(),
        ])->setPaper('a4')->output();
    }

    private function appendLetterPdf(Fpdi $pdf, string $source): ?string
    {
        try {
            $this->appendPdf($pdf, $source);

            return null;
        } catch (Throwable) {
            // Parser gratis FPDI hanya mendukung PDF ≤ 1.4 → konversi dengan Ghostscript.
        }

        $converted = $this->downgradeWithGhostscript($source);

        if (! $converted) {
            return 'File surat (PDF) tidak dapat digabung otomatis karena formatnya tidak didukung. Silakan unduh file surat secara terpisah.';
        }

        try {
            $this->appendPdf($pdf, $converted);

            return null;
        } catch (Throwable) {
            return 'File surat (PDF) tidak dapat digabung otomatis. Silakan unduh file surat secara terpisah.';
        }
    }

    private function appendPdf(Fpdi $pdf, string $file): void
    {
        $pages = $pdf->setSourceFile($file);

        for ($page = 1; $page <= $pages; $page++) {
            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template);
        }
    }

    private function appendImage(Fpdi $pdf, string $file): void
    {
        [$width, $height] = getimagesize($file) ?: [210, 297];
        $landscape = $width > $height;
        $pdf->AddPage($landscape ? 'L' : 'P', 'A4');

        $pageW = $landscape ? 297 : 210;
        $pageH = $landscape ? 210 : 297;
        $margin = 8;
        $scale = min(($pageW - 2 * $margin) / $width, ($pageH - 2 * $margin) / $height);
        $w = $width * $scale;
        $h = $height * $scale;

        $pdf->Image($file, ($pageW - $w) / 2, ($pageH - $h) / 2, $w, $h);
    }

    private function appendNotice(Fpdi $pdf, string $message): void
    {
        $pdf->AddPage('P', 'A4');
        $pdf->SetFont('Helvetica', 'B', 13);
        $pdf->SetXY(20, 40);
        $pdf->MultiCell(170, 7, 'Lampiran file surat');
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->SetX(20);
        $pdf->MultiCell(170, 6, iconv('UTF-8', 'windows-1252//TRANSLIT', $message) ?: $message);
    }

    private function downgradeWithGhostscript(string $source): ?string
    {
        $binary = config('services.ghostscript.path', 'gs');
        $target = $this->tempPath('pdf');

        try {
            $result = Process::timeout(60)->run([
                $binary, '-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.4', '-dNOPAUSE', '-dQUIET', '-dBATCH', '-dSAFER',
                '-sOutputFile='.$target, $source,
            ]);
        } catch (Throwable) {
            return null;
        }

        return $result->successful() && is_file($target) && filesize($target) > 0 ? $target : null;
    }

    private function tempFile(string $content, string $extension): string
    {
        $path = $this->tempPath($extension);
        file_put_contents($path, $content);

        return $path;
    }

    private function tempPath(string $extension): string
    {
        $dir = storage_path('app/temp');
        is_dir($dir) || mkdir($dir, 0755, true);

        return $this->temporary[] = $dir.'/disposisi-'.bin2hex(random_bytes(6)).'.'.$extension;
    }
}
