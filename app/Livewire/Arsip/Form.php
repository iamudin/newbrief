<?php

namespace App\Livewire\Arsip;

use App\Models\Letter;
use App\Services\ArsipService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Input / ubah surat masuk oleh Operator E-Arsip (atau pemaraf sebelum diparaf). */
#[Layout('layouts::app')]
class Form extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?Letter $letter = null;

    public string $letter_number = '';

    public string $letter_date = '';

    public string $received_date = '';

    public string $sender = '';

    public string $subject = '';

    public string $nature = 'biasa';

    public $file;

    public function mount(?Letter $letter = null): void
    {
        if ($letter?->exists) {
            abort_unless(ArsipService::canEdit(auth()->user(), $letter), 403, 'Surat yang sudah diparaf tidak dapat diubah.');
            $this->letter = $letter;
            $this->fill([
                'letter_number' => $letter->letter_number,
                'letter_date' => $letter->letter_date->toDateString(),
                'received_date' => $letter->received_date->toDateString(),
                'sender' => $letter->sender,
                'subject' => $letter->subject,
                'nature' => $letter->nature,
            ]);
        } else {
            abort_unless(ArsipService::isOperator(auth()->user()), 403);
            $this->received_date = today()->toDateString();
        }
    }

    public function save(ArsipService $service)
    {
        $data = $this->validate([
            'letter_number' => 'required|string|max:150',
            'letter_date' => 'required|date|before_or_equal:received_date',
            'received_date' => 'required|date|before_or_equal:today',
            'sender' => 'required|string|max:200',
            'subject' => 'required|string|max:1000',
            'nature' => ['required', Rule::in(array_keys(Letter::NATURES))],
            'file' => [$this->letter ? 'nullable' : 'required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ], attributes: [
            'letter_number' => 'nomor surat', 'letter_date' => 'tanggal surat', 'received_date' => 'tanggal terima',
            'sender' => 'surat dari', 'subject' => 'perihal', 'nature' => 'sifat', 'file' => 'file surat',
        ]);
        unset($data['file']);

        if ($this->letter) {
            $service->update(auth()->user(), $this->letter, $data, $this->file);
            session()->flash('toast', 'Surat diperbarui.');

            return $this->redirectRoute('arsip.show', $this->letter, navigate: true);
        }

        $letter = $service->store(auth()->user(), $data, $this->file);
        session()->flash('toast', "Surat tersimpan dengan No. Agenda {$letter->agenda_number} dan diteruskan untuk paraf.");

        return $this->redirectRoute('arsip.show', $letter, navigate: true);
    }

    public function render()
    {
        $verifier = auth()->user()->position()?->parent;

        return view('livewire.arsip.form', [
            'agenda' => $this->letter?->agenda_number ?? ArsipService::nextAgendaNumber(),
            'verifier' => $this->letter?->verifierPosition ?? $verifier,
        ])->title($this->letter ? 'Ubah Surat Masuk' : 'Input Surat Masuk');
    }
}
