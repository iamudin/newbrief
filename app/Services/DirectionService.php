<?php

namespace App\Services;

use App\Enums\Level;
use App\Enums\TaskStatus;
use App\Models\DailyCheckin;
use App\Models\Direction;
use App\Models\DirectionRecipient;
use App\Models\Duty;
use App\Models\FieldAssignment;
use App\Models\Initiative;
use App\Models\Position;
use App\Models\Reprimand;
use App\Models\Task;
use App\Models\TaskMessage;
use App\Models\User;
use App\Notifications\BriefNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DirectionService
{
    public function __construct(private LocationService $locations) {}

    /* ------------------------------------------------------------------
     | Query riwayat — melekat ke jabatan; khusus staff juga ke orangnya.
     * ------------------------------------------------------------------ */

    public static function inbox(User $user): Builder
    {
        $position = $user->position();

        return DirectionRecipient::query()
            ->where('to_position_id', $position?->id ?? 0)
            ->when($position?->level === Level::Staff, fn (Builder $q) => $q->where('to_user_id', $user->id));
    }

    public static function outbox(User $user): Builder
    {
        return Direction::query()->where('from_position_id', $user->position()?->id ?? 0);
    }

    /* ------------------------------------------------------------------
     | Otorisasi
     * ------------------------------------------------------------------ */

    public static function isRecipient(User $user, DirectionRecipient $recipient): bool
    {
        $position = $user->position();

        if (! $position || $recipient->to_position_id !== $position->id) {
            return false;
        }

        return $position->level !== Level::Staff || $recipient->to_user_id === $user->id;
    }

    public static function isSender(User $user, DirectionRecipient $recipient): bool
    {
        return $user->position()?->id === $recipient->direction->from_position_id;
    }

    public static function canView(User $user, DirectionRecipient $recipient): bool
    {
        if ($user->is_admin || self::isRecipient($user, $recipient) || self::isSender($user, $recipient)) {
            return true;
        }

        $position = $user->position();

        return $position !== null && ($position->level === Level::Kadis || $position->isAncestorOf($recipient->toPosition));
    }

    private function authorize(bool $allowed): void
    {
        if (! $allowed) {
            throw new AuthorizationException('Anda tidak berhak melakukan aksi ini.');
        }
    }

    /* ------------------------------------------------------------------
     | Membuat arahan / meneruskan instruksi
     * ------------------------------------------------------------------ */

    /**
     * @param  array<int, array{position_id:int, items?: array<int, array{title:string, source?:string, duty_id?:int|null, initiative_id?:int|null}>}>  $recipients
     */
    public function send(
        User $sender,
        string $type,
        string $content,
        array $recipients,
        ?string $title = null,
        ?int $parentId = null,
        ?UploadedFile $attachment = null,
        ?array $coords = null,
    ): Direction {
        $position = $sender->position();
        $this->authorize($position !== null && in_array($position->level, [Level::Kadis, Level::Eselon3, Level::Eselon4], true));

        $allowedChildren = $position->subordinates()->with('currentAssignment.user')->get()->keyBy('id');
        $recipients = collect($recipients)->filter(fn ($r) => $allowedChildren->has((int) $r['position_id']));

        if ($recipients->isEmpty()) {
            throw ValidationException::withMessages(['recipients' => 'Pilih minimal satu bawahan langsung penerima.']);
        }

        if ($position->level !== Level::Kadis && $recipients->every(fn ($r) => empty($r['items']))) {
            throw ValidationException::withMessages(['recipients' => 'Tambahkan minimal satu item checklist untuk penerima.']);
        }

        if ($parentId !== null) {
            $parentAllowed = self::inbox($sender)->whereHas('direction', fn ($q) => $q->whereKey($parentId))->exists();
            $this->authorize($parentAllowed);
        }

        $direction = DB::transaction(function () use ($sender, $position, $type, $content, $title, $parentId, $attachment, $recipients, $allowedChildren) {
            $direction = Direction::query()->create([
                'date' => today()->toDateString(),
                'parent_id' => $parentId,
                'from_position_id' => $position->id,
                'from_user_id' => $sender->id,
                'type' => $position->level === Level::Kadis ? $type : 'task',
                'title' => $title,
                'content' => $content,
                'attachment_path' => $attachment?->store('brief/arahan', 'local'),
                'attachment_name' => $attachment?->getClientOriginalName(),
            ]);

            foreach ($recipients as $row) {
                $target = $allowedChildren[(int) $row['position_id']];
                $recipient = $direction->recipients()->create([
                    'to_position_id' => $target->id,
                    'to_user_id' => $target->holder()?->id,
                ]);

                foreach (array_values($row['items'] ?? []) as $i => $item) {
                    $this->createTaskItem($recipient, $target, $item, $i);
                }
            }

            return $direction;
        });

        $this->locations->record($sender, $coords, 'direction');

        foreach ($direction->recipients()->with('toUser')->get() as $recipient) {
            $recipient->toUser?->notify(new BriefNotification(
                $direction->typeLabel().' baru',
                $sender->name.': '.str($content)->limit(80),
                route('recipient.show', $recipient),
                'direction',
            )->withWhatsApp($recipient, 'instruksi'));
        }

        return $direction;
    }

    /** @param array{title:string, source?:string, duty_id?:int|null, initiative_id?:int|null} $item */
    private function createTaskItem(DirectionRecipient $recipient, Position $target, array $item, int $order): void
    {
        $source = $item['source'] ?? 'manual';
        $dutyId = null;
        $initiativeId = null;
        $title = trim((string) ($item['title'] ?? ''));

        if ($source === 'duty' && ! empty($item['duty_id'])) {
            $duty = Duty::query()->where('position_id', $target->id)->find($item['duty_id']);
            $dutyId = $duty?->id;
            $title = $duty?->title ?? $title;
        }

        if ($source === 'initiative' && ! empty($item['initiative_id'])) {
            $initiative = Initiative::query()->where('position_id', $target->id)->where('status', 'approved')->find($item['initiative_id']);
            $initiativeId = $initiative?->id;
            $title = $initiative?->title ?? $title;
        }

        if ($title === '') {
            return;
        }

        $recipient->tasks()->create([
            'title' => $title,
            'source' => $dutyId ? 'duty' : ($initiativeId ? 'initiative' : 'manual'),
            'duty_id' => $dutyId,
            'initiative_id' => $initiativeId,
            'status' => TaskStatus::Pending,
            'sort_order' => $order,
        ]);
    }

    /* ------------------------------------------------------------------
     | Aksi penerima
     * ------------------------------------------------------------------ */

    public function markRead(User $user, DirectionRecipient $recipient): void
    {
        if (self::isRecipient($user, $recipient) && $recipient->read_at === null) {
            $recipient->update(['read_at' => now()]);
        }
    }

    public function confirm(User $user, DirectionRecipient $recipient, ?array $coords = null): void
    {
        $this->authorize(self::isRecipient($user, $recipient));

        if ($recipient->confirmed_at !== null) {
            return;
        }

        $recipient->update(['confirmed_at' => now(), 'confirmed_by' => $user->id, 'read_at' => $recipient->read_at ?? now()]);
        $this->locations->record($user, $coords, 'direction');

        $recipient->direction->fromUser?->notify(new BriefNotification(
            'Konfirmasi SIAP',
            $user->name.' menyatakan SIAP atas '.mb_strtolower($recipient->direction->typeLabel()),
            route('recipient.show', $recipient),
            'confirm',
        ));
    }

    public function checkIn(User $user, ?array $coords = null): DailyCheckin
    {
        $position = $user->position();
        $this->authorize($position !== null && in_array($position->level, Level::compliable(), true));

        $log = $this->locations->record($user, $coords, 'siap');

        return DailyCheckin::query()->firstOrCreate(
            ['date' => today()->toDateString(), 'user_id' => $user->id],
            [
                'position_id' => $position->id,
                'checked_in_at' => now(),
                'latitude' => $log?->latitude,
                'longitude' => $log?->longitude,
                'location_zone' => $log?->zone,
            ],
        );
    }

    public function updateTask(User $user, Task $task, string $status, ?string $note = null, ?UploadedFile $file = null, ?array $coords = null): void
    {
        $recipient = $task->recipient;
        $this->authorize(self::isRecipient($user, $recipient));

        $log = $this->locations->record($user, $coords, 'task');

        $data = [
            'status' => TaskStatus::from($status),
            'note' => $note,
            'status_updated_at' => now(),
            'updated_by' => $user->id,
            'location_zone' => $log?->zone ?? $task->location_zone,
        ];

        if ($file) {
            $data['file_path'] = $file->store('brief/tugas', 'local');
            $data['file_name'] = $file->getClientOriginalName();
        }

        $task->update($data);

        if ($recipient->confirmed_at === null) {
            $recipient->update(['confirmed_at' => now(), 'confirmed_by' => $user->id]);
        }
    }

    /** Simpan lampiran bukti untuk satu item tugas tanpa mengubah statusnya. */
    public function attachTaskFile(User $user, Task $task, UploadedFile $file): void
    {
        $this->authorize(self::isRecipient($user, $task->recipient));

        $old = $task->file_path;
        $task->update([
            'file_path' => $file->store('brief/tugas', 'local'),
            'file_name' => $file->getClientOriginalName(),
            'updated_by' => $user->id,
        ]);

        if ($old) {
            Storage::disk('local')->delete($old);
        }
    }

    public function report(User $user, DirectionRecipient $recipient, ?string $note = null, ?UploadedFile $file = null, ?array $coords = null): void
    {
        $this->authorize(self::isRecipient($user, $recipient));

        $data = [
            'report_note' => $note,
            'reported_at' => now(),
            'reported_by' => $user->id,
            'verification_status' => null,
            'verification_note' => null,
            'verified_at' => null,
            'verified_by' => null,
            'confirmed_at' => $recipient->confirmed_at ?? now(),
            'confirmed_by' => $recipient->confirmed_by ?? $user->id,
        ];

        if ($file) {
            if ($recipient->report_file_path) {
                Storage::disk('local')->delete($recipient->report_file_path);
            }
            $data['report_file_path'] = $file->store('brief/laporan', 'local');
            $data['report_file_name'] = $file->getClientOriginalName();
        }

        $recipient->update($data);
        $this->locations->record($user, $coords, 'report');

        $recipient->direction->fromPosition->holder()?->notify(new BriefNotification(
            'Laporan masuk',
            $user->name.' mengirim laporan ('.$recipient->doneCount().'/'.$recipient->tasks->count().' selesai)',
            route('recipient.show', $recipient),
            'report',
        ));
    }

    public function verify(User $user, DirectionRecipient $recipient, string $status, ?string $note = null): void
    {
        $this->authorize(self::isSender($user, $recipient) && $recipient->reported_at !== null);

        if (! in_array($status, ['approved', 'revision'], true)) {
            throw ValidationException::withMessages(['status' => 'Status verifikasi tidak dikenal.']);
        }

        $recipient->update([
            'verification_status' => $status,
            'verification_note' => $note,
            'verified_at' => now(),
            'verified_by' => $user->id,
        ]);

        $recipient->toUser?->notify(new BriefNotification(
            $status === 'approved' ? 'Laporan diterima' : 'Laporan perlu revisi',
            $user->name.($note ? ': '.str($note)->limit(80) : ' telah memverifikasi laporan Anda.'),
            route('recipient.show', $recipient),
            $status === 'approved' ? 'success' : 'warning',
        ));
    }

    public function message(User $user, DirectionRecipient $recipient, string $body): TaskMessage
    {
        $this->authorize(self::isRecipient($user, $recipient) || self::isSender($user, $recipient));

        $message = $recipient->messages()->create(['user_id' => $user->id, 'body' => $body]);

        $other = self::isRecipient($user, $recipient) ? $recipient->direction->fromPosition->holder() : $recipient->toUser;
        $other?->notify(new BriefNotification('Pesan baru', $user->name.': '.str($body)->limit(80), route('recipient.show', $recipient), 'message'));

        return $message;
    }

    /* ------------------------------------------------------------------
     | Inisiatif, dinas luar, teguran
     * ------------------------------------------------------------------ */

    public function proposeInitiative(User $user, string $title, ?string $description = null): Initiative
    {
        $position = $user->position();
        $this->authorize($position !== null && in_array($position->level, [Level::Eselon4, Level::Staff, Level::Eselon3], true));

        $initiative = Initiative::query()->create([
            'position_id' => $position->id,
            'user_id' => $user->id,
            'title' => $title,
            'description' => $description,
            'status' => 'pending',
        ]);

        $position->parent?->holder()?->notify(new BriefNotification(
            'Usulan tugas inisiatif',
            $user->name.': '.str($title)->limit(80),
            route('members'),
            'initiative',
        ));

        return $initiative;
    }

    public function reviewInitiative(User $user, Initiative $initiative, bool $approve, ?string $note = null): void
    {
        $this->authorize($initiative->status === 'pending' && $initiative->position->parent_id === $user->position()?->id);

        $initiative->update([
            'status' => $approve ? 'approved' : 'rejected',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        $initiative->user->notify(new BriefNotification(
            $approve ? 'Inisiatif disetujui' : 'Inisiatif ditolak',
            $initiative->title,
            null,
            $approve ? 'success' : 'warning',
        ));
    }

    public function canSupervise(User $user, Position $target): bool
    {
        $position = $user->position();

        return $position !== null && ($position->level === Level::Kadis || $position->isAncestorOf($target));
    }

    public function setFieldDuty(User $user, Position $target, bool $onDuty, ?string $destination = null): void
    {
        $this->authorize($this->canSupervise($user, $target));
        $holder = $target->holder();

        if (! $holder) {
            return;
        }

        if (! $onDuty) {
            FieldAssignment::query()->where('user_id', $holder->id)->whereDate('date', today())->delete();

            return;
        }

        FieldAssignment::query()->updateOrCreate(
            ['date' => today()->toDateString(), 'user_id' => $holder->id],
            ['position_id' => $target->id, 'set_by' => $user->id, 'destination' => $destination],
        );

        $holder->notify(new BriefNotification('Dinas luar hari ini', 'Anda ditetapkan dinas luar'.($destination ? ' ke '.$destination : '').' oleh '.$user->name, null, 'info'));
    }

    public function reprimand(User $user, Position $target, string $message): Reprimand
    {
        $this->authorize($this->canSupervise($user, $target));
        $holder = $target->holder();

        if (! $holder) {
            throw ValidationException::withMessages(['target' => 'Jabatan ini belum memiliki pemegang.']);
        }

        // Satu teguran per pegawai per hari (cegah spam notifikasi/WhatsApp).
        if (Reprimand::query()->where('to_user_id', $holder->id)->whereDate('date', today())->exists()) {
            throw ValidationException::withMessages(['reprimandMessage' => 'Pegawai ini sudah ditegur hari ini.']);
        }

        $reprimand = Reprimand::query()->create([
            'date' => today()->toDateString(),
            'from_user_id' => $user->id,
            'to_user_id' => $holder->id,
            'to_position_id' => $target->id,
            'message' => $message,
        ]);

        $holder->notify((new BriefNotification('Teguran dari '.$user->name, $message, route('home'), 'warning'))->withWhatsApp($reprimand, 'teguran'));

        return $reprimand;
    }
}
