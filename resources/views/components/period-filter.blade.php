@props(['model' => 'period'])
<div class="flex gap-1 rounded-xl bg-[#E8EBF0] p-1">
    @foreach (\App\Services\WorkCalendar::periods() as $key => $label)
        <button type="button" wire:click="$set('{{ $model }}', '{{ $key }}')" @class(['tab', 'tab-active' => $this->{$model} === $key])>{{ $label }}</button>
    @endforeach
</div>
