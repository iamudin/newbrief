<?php

namespace App\Livewire\Admin;

use App\Enums\Level;
use App\Models\Position;
use App\Models\User;
use App\Services\AssignmentService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Data Pegawai: profil pegawai + penempatan jabatan (mutasi). */
#[Layout('layouts::admin')]
class Employees extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $level = '';

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $nip = '';

    public string $email = '';

    public string $phone = '';

    public string $gender = '';

    public string $rank = '';

    public string $password = '';

    public ?int $positionId = null;

    public ?int $historyId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->reset('editingId', 'name', 'nip', 'email', 'phone', 'gender', 'rank', 'password', 'positionId');
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $user = User::query()->with('currentAssignment')->findOrFail($id);
        $this->editingId = $id;
        $this->fill([
            'name' => $user->name, 'nip' => $user->nip, 'email' => (string) $user->email,
            'phone' => (string) $user->phone, 'gender' => (string) $user->gender, 'rank' => (string) $user->rank, 'password' => '',
            'positionId' => $user->currentAssignment?->position_id,
        ]);
        $this->showForm = true;
    }

    public function save(AssignmentService $assignments): void
    {
        $this->validate([
            'name' => 'required|string|max:150',
            'nip' => ['required', 'string', 'max:30', Rule::unique('users', 'nip')->ignore($this->editingId)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($this->editingId)],
            'phone' => 'nullable|string|max:30',
            'gender' => 'nullable|in:L,P',
            'rank' => 'nullable|string|max:100',
            'password' => [$this->editingId ? 'nullable' : 'required', 'string', Password::defaults()],
            'positionId' => 'nullable|exists:positions,id',
        ], attributes: ['positionId' => 'jabatan']);

        $attributes = ['name' => $this->name, 'nip' => $this->nip, 'email' => $this->email ?: null, 'phone' => $this->phone ?: null, 'gender' => $this->gender ?: null, 'rank' => $this->rank ?: null];

        if ($this->password !== '') {
            $attributes['password'] = $this->password;
            $attributes['must_change_password'] = true; // password dari admin bersifat sementara
        }

        $user = User::query()->updateOrCreate(['id' => $this->editingId], $attributes);

        if ($this->positionId) {
            $assignments->assign($user->load('currentAssignment'), Position::query()->with('currentAssignment')->findOrFail($this->positionId));
        } elseif ($this->editingId) {
            $assignments->release($user->load('currentAssignment'));
        }

        $this->showForm = false;
        $this->dispatch('toast', message: 'Data pegawai disimpan.');
    }

    public function render()
    {
        $positions = Position::query()->with('currentAssignment.user')->get()->sortBy(fn ($p) => [$p->level->rank(), $p->parent_id, $p->sort_order]);

        return view('livewire.admin.employees', [
            'employees' => User::query()
                ->where('is_admin', false)
                ->with('currentAssignment.position.parent')
                ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('nip', 'like', "%{$this->search}%")))
                ->when($this->level, fn ($q) => $q->whereHas('currentAssignment.position', fn ($q) => $q->where('level', $this->level)))
                ->orderBy('name')
                ->paginate(15),
            'positions' => $positions,
            'history' => $this->historyId ? User::query()->with(['assignments' => fn ($q) => $q->with('position')->latest('started_at')])->find($this->historyId) : null,
            'levels' => Level::cases(),
        ])->title('Data Pegawai');
    }
}
