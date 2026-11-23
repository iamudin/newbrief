<?php

namespace App\Livewire\Admin;

use App\Models\Holiday;
use App\Models\Setting;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts::admin')]
class Settings extends Component
{
    use WithFileUploads;

    public $logo;

    /** @var array<string, string> */
    public array $values = [];

    public string $holidayDate = '';

    public string $holidayName = '';

    public function mount(): void
    {
        foreach (array_keys(Setting::DEFAULTS) as $key) {
            $this->values[$key] = (string) Setting::get($key);
        }
    }

    public function save(): void
    {
        $time = ['required', 'date_format:H:i'];
        $this->validate([
            'values.agency_name' => 'required|string|max:150',
            'values.government_name' => 'required|string|max:150',
            'values.regency' => 'nullable|string|max:100',
            'logo' => 'nullable|image|max:1024',
            'values.deadline_eselon3' => $time,
            'values.deadline_eselon4' => [...$time, 'after:values.deadline_eselon3'],
            'values.deadline_staff' => [...$time, 'after:values.deadline_eselon4'],
            'values.workday_start' => $time,
            'values.report_deadline' => $time,
            'values.verify_deadline' => $time,
            'values.workday_end' => $time,
            'values.office_name' => 'required|string|max:150',
            'values.office_latitude' => 'required|numeric|between:-90,90',
            'values.office_longitude' => 'required|numeric|between:-180,180',
            'values.office_radius' => 'required|integer|between:20,5000',
        ], attributes: [
            'values.deadline_eselon4' => 'batas Eselon 4',
            'values.deadline_staff' => 'batas Staff',
            'values.office_radius' => 'radius kantor',
        ]);

        if ($this->logo) {
            $dir = public_path('uploads');
            is_dir($dir) || mkdir($dir, 0755, true);
            $name = 'logo-instansi-'.now()->timestamp.'.'.$this->logo->getClientOriginalExtension();
            copy($this->logo->getRealPath(), $dir.'/'.$name);
            $this->values['agency_logo'] = 'uploads/'.$name;
            $this->reset('logo');
        }

        foreach ($this->values as $key => $value) {
            Setting::put($key, $value);
        }

        $this->dispatch('toast', message: 'Pengaturan disimpan.');
    }

    public function addHoliday(): void
    {
        $this->validate(['holidayDate' => 'required|date|unique:holidays,date', 'holidayName' => 'required|string|max:150'], attributes: ['holidayDate' => 'tanggal', 'holidayName' => 'keterangan']);
        Holiday::query()->create(['date' => $this->holidayDate, 'name' => $this->holidayName]);
        $this->reset('holidayDate', 'holidayName');
    }

    public function deleteHoliday(int $id): void
    {
        Holiday::query()->whereKey($id)->delete();
    }

    public function render()
    {
        return view('livewire.admin.settings', [
            'holidays' => Holiday::query()->whereYear('date', '>=', now()->year)->orderBy('date')->get(),
        ])->title('Pengaturan');
    }
}
