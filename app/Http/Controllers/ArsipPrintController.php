<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use App\Services\ArsipService;
use App\Services\DispositionPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Cetak lembar disposisi (PDF) yang sudah digabung dengan file surat. */
class ArsipPrintController extends Controller
{
    public function __invoke(Request $request, Letter $letter, DispositionPdfService $pdf): Response
    {
        abort_unless(ArsipService::canView($request->user(), $letter), 403);
        abort_unless($letter->disposed_at !== null, 404, 'Surat belum didisposisi.');

        $result = $pdf->build($letter);
        $filename = 'disposisi-'.str($letter->agenda_number.'-'.$letter->letter_number)->slug().'.pdf';

        return response($result['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('unduh') ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            'X-Disposisi-Merged' => $result['merged'] ? '1' : '0',
        ]);
    }
}
