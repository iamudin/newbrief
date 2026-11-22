@props(['percent' => 0, 'tone' => 'bg-ok'])
<div class="h-2 overflow-hidden rounded-full bg-line"><div class="h-2 rounded-full {{ $tone }}" style="width: {{ max(0, min(100, $percent)) }}%"></div></div>
