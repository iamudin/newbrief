<?php

namespace App\Http\Controllers;

use App\Models\Direction;
use App\Models\DirectionRecipient;
use App\Models\Letter;
use App\Models\Task;
use App\Services\ArsipService;
use App\Services\DirectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function __invoke(Request $request, string $type, int $id): StreamedResponse
    {
        $user = $request->user();

        if ($type === 'surat') {
            $letter = Letter::query()->findOrFail($id);
            abort_unless(ArsipService::canView($user, $letter), 403);
            abort_unless(Storage::disk('local')->exists($letter->file_path), 404);

            // Tampil langsung (inline) agar bisa dipratinjau; ?unduh=1 untuk mengunduh.
            return $request->boolean('unduh')
                ? Storage::disk('local')->download($letter->file_path, $letter->file_name)
                : Storage::disk('local')->response($letter->file_path, $letter->file_name, [
                    'Content-Disposition' => HeaderUtils::makeDisposition('inline', $letter->file_name, Str::ascii($letter->file_name) ?: 'surat'),
                ]);
        }

        [$path, $name, $recipients] = match ($type) {
            'arahan' => (function () use ($id) {
                $direction = Direction::query()->findOrFail($id);

                return [$direction->attachment_path, $direction->attachment_name, $direction->recipients()->with('direction', 'toPosition')->get()];
            })(),
            'tugas' => (function () use ($id) {
                $task = Task::query()->with('recipient.direction', 'recipient.toPosition')->findOrFail($id);

                return [$task->file_path, $task->file_name, collect([$task->recipient])];
            })(),
            'laporan' => (function () use ($id) {
                $recipient = DirectionRecipient::query()->with('direction', 'toPosition')->findOrFail($id);

                return [$recipient->report_file_path, $recipient->report_file_name, collect([$recipient])];
            })(),
        };

        abort_unless($path && Storage::disk('local')->exists($path), 404);
        abort_unless($recipients->contains(fn (DirectionRecipient $r) => DirectionService::canView($user, $r)), 403);

        return Storage::disk('local')->download($path, $name);
    }
}
