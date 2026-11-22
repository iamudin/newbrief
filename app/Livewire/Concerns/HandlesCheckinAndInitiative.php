<?php

namespace App\Livewire\Concerns;

use App\Models\DailyCheckin;
use App\Models\Initiative;
use App\Services\DirectionService;

trait HandlesCheckinAndInitiative
{
    public string $initiativeTitle = '';

    public string $initiativeDescription = '';

    public function checkIn(?array $coords, DirectionService $service): void
    {
        $checkin = $service->checkIn(auth()->user(), $coords);
        $this->dispatch('toast', message: 'SIAP tercatat pukul '.$checkin->checked_in_at->format('H.i').'.');
    }

    public function proposeInitiative(DirectionService $service): void
    {
        $this->validate([
            'initiativeTitle' => 'required|string|max:200',
            'initiativeDescription' => 'nullable|string|max:1000',
        ], attributes: ['initiativeTitle' => 'uraian tugas']);

        $service->proposeInitiative(auth()->user(), $this->initiativeTitle, $this->initiativeDescription ?: null);
        $this->reset('initiativeTitle', 'initiativeDescription');
        $this->dispatch('toast', message: 'Usulan inisiatif dikirim ke atasan.');
    }

    protected function todayCheckin(): ?DailyCheckin
    {
        return DailyCheckin::query()->where('user_id', auth()->id())->whereDate('date', today())->first();
    }

    protected function myInitiatives()
    {
        return Initiative::query()->where('user_id', auth()->id())->where('position_id', auth()->user()->position()->id)->latest()->limit(8)->get();
    }
}
