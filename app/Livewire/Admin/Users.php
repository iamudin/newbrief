<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Data User: akses login pegawai ke sistem. */
#[Layout('layouts::admin')]
class Users extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Locked]
    public ?int $editingId = null;

    public bool $showForm = false;

    public string $nip = '';

    public string $email = '';

    public string $password = '';

    public bool $isAdmin = false;

    public bool $isActive = true;

    public string $name = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->reset('editingId', 'nip', 'email', 'password', 'name');
        $this->isAdmin = true;
        $this->isActive = true;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $user = User::query()->findOrFail($id);
        $this->editingId = $id;
        $this->name = $user->name;
        $this->nip = $user->nip;
        $this->email = (string) $user->email;
        $this->password = '';
        $this->isAdmin = $user->is_admin;
        $this->isActive = $user->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => 'required|string|max:150',
            'nip' => ['required', 'string', 'max:30', Rule::unique('users', 'nip')->ignore($this->editingId)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($this->editingId)],
            'password' => [$this->editingId ? 'nullable' : 'required', 'string', Password::defaults()],
            'isAdmin' => 'boolean',
            'isActive' => 'boolean',
        ]);

        if ($this->editingId === auth()->id() && (! $this->isAdmin || ! $this->isActive)) {
            $this->addError('isAdmin', 'Anda tidak dapat mencabut akses admin/aktif akun sendiri.');

            return;
        }

        $attributes = ['name' => $data['name'], 'nip' => $data['nip'], 'email' => $data['email'] ?: null, 'is_admin' => $this->isAdmin, 'is_active' => $this->isActive];

        if ($this->password !== '') {
            // Password yang ditetapkan admin bersifat sementara → wajib diganti saat login.
            $attributes['password'] = $this->password;
            $attributes['must_change_password'] = $this->editingId !== auth()->id();
        }

        User::query()->updateOrCreate(['id' => $this->editingId], $attributes);
        $this->showForm = false;
        $this->dispatch('toast', message: 'Data user disimpan.');
    }

    public function resetPassword(int $id): void
    {
        $password = Str::password(10, symbols: false);
        // Mengganti hash password otomatis memutus sesi lama pengguna (middleware auth.session).
        User::query()->findOrFail($id)->update(['password' => $password, 'must_change_password' => true]);
        $this->dispatch('toast', message: 'Password baru: '.$password.' (sampaikan ke pegawai, hanya tampil sekali).');
    }

    public function toggleActive(int $id): void
    {
        abort_if($id === auth()->id(), 422);
        $user = User::query()->findOrFail($id);
        $user->update(['is_active' => ! $user->is_active]);
    }

    public function render()
    {
        return view('livewire.admin.users', [
            'users' => User::query()
                ->with('currentAssignment.position')
                ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('nip', 'like', "%{$this->search}%")))
                ->orderByDesc('is_admin')->orderBy('name')
                ->paginate(15),
        ])->title('Data User');
    }
}
