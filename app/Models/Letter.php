<?php

namespace App\Models;

use App\Models\Concerns\HasWhatsappMessages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Surat masuk E-Arsip. */
class Letter extends Model
{
    use HasWhatsappMessages;

    protected $guarded = ['id'];

    /** @var array<string, string> */
    public const NATURES = [
        'biasa' => 'Biasa',
        'segera' => 'Segera',
        'sangat_segera' => 'Sangat Segera',
        'rahasia' => 'Rahasia',
    ];

    /**
     * Pilihan "harapan" disposisi (mengikuti referensi e-office).
     * Item dengan 'prefix' meminta isian nama unit/jabatan.
     *
     * @var array<string, array<int, array{text:string, prefix?:string}>>
     */
    public const EXPECTATIONS = [
        'Tindak lanjut & pelaksanaan' => [
            ['text' => 'Tindak lanjuti sesuai ketentuan/prosedur'],
            ['text' => 'Selesaikan segera'],
            ['text' => 'Pelajari dan laporkan'],
            ['text' => 'Proses sesuai aturan'],
        ],
        'Koordinasi & diskusi' => [
            ['text' => 'Koordinasikan dengan', 'prefix' => 'Koordinasikan dengan '],
            ['text' => 'Bicarakan dengan saya'],
            ['text' => 'Minta pertimbangan/saran teknis dari', 'prefix' => 'Minta pertimbangan/saran teknis dari '],
        ],
        'Penyiapan dokumen & balasan' => [
            ['text' => 'Siapkan konsep balasan'],
            ['text' => 'Siapkan bahan/data'],
            ['text' => 'Agendakan'],
        ],
        'Kehadiran & perwakilan' => [
            ['text' => 'Hadir/Wakili saya'],
            ['text' => 'Dampingi saya'],
        ],
        'Informasi & pengarsipan' => [
            ['text' => 'Untuk diketahui (U.d.k.)'],
            ['text' => 'File / Arsipkan'],
        ],
    ];

    protected function casts(): array
    {
        return [
            'letter_date' => 'date',
            'received_date' => 'date',
            'paraf_at' => 'datetime',
            'disposed_at' => 'datetime',
            'expectations' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function operatorPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'operator_position_id');
    }

    public function verifierPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'verifier_position_id');
    }

    public function kadisPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'kadis_position_id');
    }

    public function parafUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paraf_by');
    }

    public function disposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disposed_by');
    }

    public function dispositions(): HasMany
    {
        return $this->hasMany(LetterDisposition::class)->orderBy('id');
    }

    public function stage(): string
    {
        return match (true) {
            $this->paraf_at === null => 'paraf',
            $this->disposed_at === null => 'kadis',
            default => 'disposed',
        };
    }

    public function stageLabel(): string
    {
        return [
            'paraf' => 'Menunggu paraf',
            'kadis' => 'Menunggu disposisi Kadis',
            'disposed' => 'Sudah didisposisi',
        ][$this->stage()];
    }

    public function stageClass(): string
    {
        return [
            'paraf' => 'bg-amber-100 text-amber-800',
            'kadis' => 'bg-sky-100 text-sky-700',
            'disposed' => 'bg-emerald-100 text-emerald-700',
        ][$this->stage()];
    }

    public function natureLabel(): string
    {
        return self::NATURES[$this->nature] ?? $this->nature;
    }

    public function natureClass(): string
    {
        return [
            'biasa' => 'bg-slate-100 text-slate-600',
            'segera' => 'bg-amber-100 text-amber-800',
            'sangat_segera' => 'bg-rose-100 text-rose-700',
            'rahasia' => 'bg-navy text-gold',
        ][$this->nature] ?? 'bg-slate-100 text-slate-600';
    }

    public function isPdf(): bool
    {
        return str_ends_with(mb_strtolower($this->file_name), '.pdf');
    }
}
