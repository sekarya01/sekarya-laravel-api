@props(['title' => 'Belum ada data', 'hint' => ''])
<div class="flex flex-col items-center justify-center py-12 text-center anim-fade">
    <div class="w-14 h-14 rounded-2xl flex items-center justify-center" style="background:#E4EDF7;color:#163C68;">
        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>
    </div>
    <p class="mt-3 font-bold" style="color:#163C68;">{{ $title }}</p>
    @if ($hint)
        <p class="mt-1 text-sm text-slate-500 max-w-sm">{{ $hint }}</p>
    @endif
</div>
