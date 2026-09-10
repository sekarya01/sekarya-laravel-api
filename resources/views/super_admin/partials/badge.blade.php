@props(['text' => '', 'tone' => 'slate'])
@php
$tones = [
    'navy' => 'background:#E4EDF7;color:#163C68;box-shadow:inset 0 0 0 1px #BFD2E8;',
    'orange' => 'background:#FFF1E4;color:#C2570B;box-shadow:inset 0 0 0 1px #FBD9B6;',
    'green' => 'background:#E7F6EF;color:#047857;box-shadow:inset 0 0 0 1px #B9E4CF;',
    'red' => 'background:#FDECEC;color:#B91C1C;box-shadow:inset 0 0 0 1px #F5C2C2;',
    'amber' => 'background:#FFF6E0;color:#92400E;box-shadow:inset 0 0 0 1px #F3DFAE;',
    'purple' => 'background:#F1EAFE;color:#6D28D9;box-shadow:inset 0 0 0 1px #D9C8F8;',
    'slate' => 'background:#EEF2F7;color:#52627A;box-shadow:inset 0 0 0 1px #D7E0EC;',
];
@endphp
<span class="inline-flex items-center rounded-full px-2.5 py-1 text-[.7rem] font-bold whitespace-nowrap" style="{{ $tones[$tone] ?? $tones['slate'] }}">{{ $text }}</span>
