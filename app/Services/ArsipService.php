<?php

namespace App\Services;

use App\Enums\Level;
use App\Models\Letter;
use App\Models\LetterDisposition;
use App\Models\Position;
use App\Models\User;
use App\Notifications\BriefNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Alur E-Arsip surat masuk (mengikuti referensi e-office, disesuaikan struktur jabatan BRIEF):
 *  1. Operator E-Arsip menginput surat masuk.
 *  2. Atasan langsung operator (Kasubag Umum) memaraf & meneruskan ke Kepala Dinas.
 *  3. Kepala Dinas mendisposisi ke Eselon 3 dengan "harapan" + catatan.
 *  4. Penerima membaca, membalas, meneruskan ke bawahan langsungnya, lalu menandai selesai.
 * Semua disposisi melekat pada jabatan, sehingga pejabat pengganti mewarisi riwayatnya (kecuali staff).
 */
class ArsipService
{
    /* ------------------------------------------------------------------
     | Peran & otorisasi
     * ------------------------------------------------------------------ */

    public static function isOperator(User $user): bool
    {
        return $user->level() === Level::Arsip;
    }

    public static function isVerifier(User $user, Letter $letter): bool
    {
        return $letter->verifier_position_id !== null && $user->position()?->id === $letter->verifier_position_id;
    }

    public static function isKadis(User $user): bool
    {
        return $user->level() === Level::Kadis;
    }

    /** Operator yang menginput surat atau rekan operator pada jabatan yang sama. */
    public static function isOwnerOperator(User $user, Letter $letter): bool
    {
        return self::isOperator($user) && $user->position()?->id === $letter->operator_position_id;
    }

    public static function inbox(User $user): Builder
    {
        $position = $user->position();

        return LetterDisposition::query()
            ->where('to_position_id', $position?->id ?? 0)
            ->when($position?->level === Level::Staff, fn (Builder $q) => $q->where('to_user_id', $user->id));
    }

    public static function isDispositionRecipient(User $user, LetterDisposition $disposition): bool
    {
        $position = $user->position();

        if (! $position || $disposition->to_position_id !== $position->id) {
            return false;
        }

        return $position->level !== Level::Staff || $disposition->to_user_id === $user->id;
    }

    public static function canView(User $user, Letter $letter): bool
    {
        if ($user->is_admin || self::isKadis($user) || self::isOwnerOperator($user, $letter) || self::isVerifier($user, $letter)) {
            return true;
        }

        $position = $user->position();

        if (! $position) {
            return false;
        }

        // Penerima disposisi, atau atasan dari penerima disposisi.
        $recipientPositions = $letter->dispositions()->with('toPosition')->get();

        return $recipientPositions->contains(fn (LetterDisposition $d) => self::isDispositionRecipient($user, $d) || $position->isAncestorOf($d->toPosition));
    }

    public static function canEdit(User $user, Letter $letter): bool
    {
        return $letter->paraf_at === null && (self::isOwnerOperator($user, $letter) || self::isVerifier($user, $letter));
    }

    private function authorize(bool $allowed): void
    {
        if (! $allowed) {
            throw new AuthorizationException('Anda tidak berhak melakukan aksi ini.');
        }
    }

    /** Jumlah pekerjaan E-Arsip yang menunggu pengguna (untuk badge menu). */
    public static function pendingCount(User $user): int
    {
        $position = $user->position();

        if (! $position) {
            return 0;
        }

        $count = Letter::query()->where('verifier_position_id', $position->id)->whereNull('paraf_at')->count();

        if ($position->level === Level::Kadis) {
            $count += Letter::query()->whereNotNull('paraf_at')->whereNull('disposed_at')->count();
        }

        return $count + self::inbox($user)->whereNull('completed_at')->count();
    }

    /* ------------------------------------------------------------------
     | 1. Input surat (Operator E-Arsip)
     * ------------------------------------------------------------------ */

    public static function nextAgendaNumber(?int $year = null): string
    {
        $year ??= (int) now()->year;
        $last = Letter::query()->where('agenda_number', 'like', "%/SM/{$year}")->count();

        return str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT)."/SM/{$year}";
    }

    /** @param array{letter_number:string, letter_date:string, received_date:string, sender:string, subject:string, nature:string} $data */
    public function store(User $user, array $data, UploadedFile $file): Letter
    {
        $position = $user->position();
        $this->authorize(self::isOperator($user));

        $letter = DB::transaction(fn () => Letter::query()->create([
            ...$data,
            'agenda_number' => self::nextAgendaNumber(),
            'file_path' => $file->store('brief/surat', 'local'),
            'file_name' => $file->getClientOriginalName(),
            'created_by' => $user->id,
            'operator_position_id' => $position->id,
            'verifier_position_id' => $position->parent_id,
        ]));

        $letter->verifierPosition?->holder()?->notify((new BriefNotification(
            'Surat masuk perlu paraf',
            "No. Agenda {$letter->agenda_number}\nDari: {$letter->sender}\nNo. surat: {$letter->letter_number}\nPerihal: ".str($letter->subject)->limit(120),
            route('arsip.show', $letter),
            'info',
        ))->withWhatsApp());

        return $letter;
    }

    public function update(User $user, Letter $letter, array $data, ?UploadedFile $file = null): void
    {
        $this->authorize(self::canEdit($user, $letter));

        if ($file) {
            Storage::disk('local')->delete($letter->file_path);
            $data['file_path'] = $file->store('brief/surat', 'local');
            $data['file_name'] = $file->getClientOriginalName();
        }

        $letter->update($data);
    }

    public function delete(User $user, Letter $letter): void
    {
        $this->authorize(self::canEdit($user, $letter));
        Storage::disk('local')->delete($letter->file_path);
        $letter->delete();
    }

    /* ------------------------------------------------------------------
     | 2. Paraf & teruskan ke Kepala Dinas
     * ------------------------------------------------------------------ */

    public function paraf(User $user, Letter $letter, ?string $note = null): void
    {
        $this->authorize(self::isVerifier($user, $letter) && $letter->paraf_at === null);

        $kadis = Position::query()->level(Level::Kadis)->with('currentAssignment.user')->first();

        if (! $kadis?->holder()) {
            throw ValidationException::withMessages(['paraf' => 'Jabatan Kepala Dinas belum terisi.']);
        }

        $letter->update([
            'paraf_at' => now(),
            'paraf_by' => $user->id,
            'paraf_note' => $note,
            'kadis_position_id' => $kadis->id,
        ]);

        $kadis->holder()->notify((new BriefNotification(
            'Surat masuk untuk didisposisi',
            "Diparaf {$user->name}\nNo. Agenda {$letter->agenda_number} · {$letter->natureLabel()}\nDari: {$letter->sender}\nPerihal: ".str($letter->subject)->limit(120),
            route('arsip.show', $letter),
            'info',
        ))->withWhatsApp());

        $letter->creator->notify(new BriefNotification('Surat diparaf', "No. {$letter->letter_number} diteruskan ke Kepala Dinas", route('arsip.show', $letter), 'success'));
    }

    /* ------------------------------------------------------------------
     | 3. Disposisi Kepala Dinas
     * ------------------------------------------------------------------ */

    /**
     * @param  array<int, int>  $positionIds
     * @param  array<int, string>  $expectations
     */
    public function dispose(User $user, Letter $letter, array $positionIds, array $expectations, ?string $note = null): void
    {
        $kadis = $user->position();
        $this->authorize(self::isKadis($user) && $letter->paraf_at !== null && $letter->disposed_at === null);

        $targets = $kadis->subordinates()->with('currentAssignment.user')->whereIn('id', $positionIds)->get();

        if ($targets->isEmpty()) {
            throw ValidationException::withMessages(['dispositionTargets' => 'Pilih minimal satu pejabat penerima disposisi.']);
        }

        $expectations = $this->cleanExpectations($expectations);

        DB::transaction(function () use ($user, $kadis, $letter, $targets, $expectations, $note) {
            $letter->update([
                'disposed_at' => now(),
                'disposed_by' => $user->id,
                'expectations' => $expectations,
                'disposition_note' => $note,
                'kadis_position_id' => $kadis->id,
            ]);

            foreach ($targets as $target) {
                $letter->dispositions()->create([
                    'from_position_id' => $kadis->id,
                    'from_user_id' => $user->id,
                    'to_position_id' => $target->id,
                    'to_user_id' => $target->holder()?->id,
                    'expectations' => $expectations,
                    'note' => $note,
                ]);
            }
        });

        $this->notifyRecipients($letter->dispositions()->whereNull('parent_id')->with('toUser')->get(), $letter, $user);
    }

    /** Kadis memperbarui disposisi selama belum ada penerima yang meneruskan ke bawahan. */
    public function resetDisposition(User $user, Letter $letter): void
    {
        $this->authorize(self::isKadis($user) && $letter->disposed_at !== null);

        if ($letter->dispositions()->whereNotNull('parent_id')->exists()) {
            throw ValidationException::withMessages(['reset' => 'Disposisi sudah diteruskan ke bawahan, tidak dapat diperbarui.']);
        }

        DB::transaction(function () use ($letter) {
            $letter->dispositions()->delete();
            $letter->update(['disposed_at' => null, 'disposed_by' => null, 'expectations' => null, 'disposition_note' => null]);
        });
    }

    /* ------------------------------------------------------------------
     | 4. Tindak lanjut penerima disposisi
     * ------------------------------------------------------------------ */

    public function markRead(User $user, Letter $letter): void
    {
        self::inbox($user)->where('letter_id', $letter->id)->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function reply(User $user, LetterDisposition $disposition, string $reply): void
    {
        $this->authorize(self::isDispositionRecipient($user, $disposition));

        $disposition->update(['reply' => $reply, 'replied_at' => now(), 'read_at' => $disposition->read_at ?? now()]);

        $disposition->fromPosition->holder()?->notify(new BriefNotification(
            'Balasan disposisi',
            $user->name.': '.str($reply)->limit(80),
            route('arsip.show', $disposition->letter_id),
            'message',
        ));
    }

    /**
     * @param  array<int, int>  $positionIds
     * @param  array<int, string>  $expectations
     */
    public function forward(User $user, LetterDisposition $disposition, array $positionIds, ?string $note = null, array $expectations = []): void
    {
        $this->authorize(self::isDispositionRecipient($user, $disposition));
        $position = $user->position();

        $already = $disposition->children()->pluck('to_position_id');
        $targets = $position->subordinates()->with('currentAssignment.user')->whereIn('id', $positionIds)->whereNotIn('id', $already)->get();

        if ($targets->isEmpty()) {
            throw ValidationException::withMessages(['forwardTargets' => 'Pilih minimal satu bawahan yang belum menerima disposisi ini.']);
        }

        $expectations = $this->cleanExpectations($expectations);

        $created = DB::transaction(function () use ($user, $position, $disposition, $targets, $note, $expectations) {
            $disposition->update(['forwarded_at' => now(), 'read_at' => $disposition->read_at ?? now()]);

            return $targets->map(fn (Position $target) => $disposition->children()->create([
                'letter_id' => $disposition->letter_id,
                'from_position_id' => $position->id,
                'from_user_id' => $user->id,
                'to_position_id' => $target->id,
                'to_user_id' => $target->holder()?->id,
                'expectations' => $expectations ?: null,
                'note' => $note,
            ]));
        });

        $this->notifyRecipients($created->each->load('toUser'), $disposition->letter, $user);
    }

    public function complete(User $user, LetterDisposition $disposition, bool $done = true): void
    {
        $this->authorize(self::isDispositionRecipient($user, $disposition));
        $disposition->update(['completed_at' => $done ? now() : null, 'read_at' => $disposition->read_at ?? now()]);
    }

    /* ------------------------------------------------------------------ */

    /** @param array<int, string> $expectations */
    private function cleanExpectations(array $expectations): array
    {
        return collect($expectations)->map(fn ($e) => trim((string) $e))->filter()->unique()->values()->all();
    }

    private function notifyRecipients($dispositions, Letter $letter, User $from): void
    {
        foreach ($dispositions as $disposition) {
            $disposition->toUser?->notify((new BriefNotification(
                'Disposisi surat masuk',
                "Dari {$from->name}\nSurat: {$letter->sender} ({$letter->natureLabel()})\nPerihal: ".str($letter->subject)->limit(120).($disposition->note ? "\nCatatan: ".str($disposition->note)->limit(150) : ''),
                route('arsip.show', $letter),
                'direction',
            ))->withWhatsApp());
        }
    }
}
