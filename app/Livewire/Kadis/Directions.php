<?php

namespace App\Livewire\Kadis;

use App\Models\Direction;
use App\Services\DirectionService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts::app')]
class Directions extends Component
{
    use WithFileUploads, WithPagination;

    public string $generalTitle = 'Arahan Umum Harian';

    public string $generalContent = '';

    public $generalFile;

    /** @var array<int, string> */
    public array $priority = [];

    /** @var array<int, mixed> */
    public array $priorityFiles = [];

    public function send(DirectionService $service): void
    {
        $eselon3 = auth()->user()->position()->children;

        $this->validate([
            'generalTitle' => 'nullable|string|max:150',
            'generalContent' => 'nullable|string|max:5000',
            'generalFile' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'priority.*' => 'nullable|string|max:3000',
            'priorityFiles.*' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ], attributes: ['generalContent' => 'arahan umum', 'priority.*' => 'arahan prioritas', 'priorityFiles.*' => 'lampiran']);

        $priorities = collect($this->priority)->map(fn ($t) => trim((string) $t))->filter();

        if (trim($this->generalContent) === '' && $priorities->isEmpty()) {
            $this->addError('generalContent', 'Isi arahan umum atau minimal satu arahan prioritas.');

            return;
        }

        $sent = 0;

        if (trim($this->generalContent) !== '') {
            $service->send(auth()->user(), 'general', $this->generalContent, $eselon3->map(fn ($p) => ['position_id' => $p->id])->all(), $this->generalTitle ?: null, null, $this->generalFile);
            $sent++;
        }

        foreach ($priorities as $positionId => $content) {
            if ($eselon3->contains('id', (int) $positionId)) {
                $service->send(auth()->user(), 'priority', $content, [['position_id' => (int) $positionId]], 'Arahan Prioritas', null, $this->priorityFiles[$positionId] ?? null);
                $sent++;
            }
        }

        $this->reset('generalContent', 'generalFile', 'priority', 'priorityFiles');
        $this->dispatch('toast', message: $sent.' arahan terkirim ke Eselon 3.');
    }

    public function render()
    {
        $position = auth()->user()->position();

        return view('livewire.kadis.directions', [
            'eselon3' => $position->children()->with('currentAssignment.user')->get(),
            'history' => Direction::query()->where('from_position_id', $position->id)
                ->with(['recipients.toPosition', 'fromUser'])
                ->latest('date')->latest('id')
                ->paginate(10),
        ])->title('Arahan');
    }
}
