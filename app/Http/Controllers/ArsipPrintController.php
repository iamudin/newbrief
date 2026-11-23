<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use App\Models\Setting;
use App\Services\ArsipService;
use Illuminate\Http\Request;

/** Lembar disposisi siap cetak (gunakan "Simpan sebagai PDF" di dialog cetak browser). */
class ArsipPrintController extends Controller
{
    public function __invoke(Request $request, Letter $letter)
    {
        abort_unless(ArsipService::canView($request->user(), $letter), 403);
        abort_unless($letter->disposed_at !== null, 404, 'Surat belum didisposisi.');

        $letter->load('dispositions.toPosition', 'dispositions.toUser', 'dispositions.fromPosition', 'disposer', 'kadisPosition', 'parafUser', 'verifierPosition');

        return view('arsip.print', [
            'letter' => $letter,
            'agency' => Setting::get('agency_name'),
            'kadisTargets' => $letter->kadisPosition?->subordinates()->get() ?? collect(),
            'firstLevel' => $letter->dispositions->whereNull('parent_id')->pluck('to_position_id')->all(),
            'followUps' => $letter->dispositions->whereNotNull('parent_id'),
        ]);
    }
}
