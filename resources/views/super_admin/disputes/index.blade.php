@extends('super_admin.layout')

@section('title', 'Antrean sengketa')
@section('header', 'Sengketa Mitra')

@section('content')
<div class="anim-rise flex flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
        <h3 class="text-xl font-extrabold" style="color: #163C68;">Antrean sengketa</h3>
        <p class="mt-1 text-sm text-slate-500">Satu baris = hasil kerja SATU mitra yang disengketakan pemberi kerja. Dana mitra itu tertahan sampai diputuskan — paling lama menunggu di depan.</p>
    </div>
    <div class="flex gap-2 shrink-0">
        @foreach (['open' => 'Terbuka', 'resolved' => 'Diputuskan'] as $value => $label)
            <a href="{{ route('super_admin.disputes.index', ['status' => $value]) }}"
               class="btn {{ $filterStatus === $value ? 'btn-navy' : 'btn-ghost' }} py-2! text-xs!">{{ $label }}</a>
        @endforeach
    </div>
</div>

<div class="anim-rise ad-2 mt-4 card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[820px]">
            <thead><tr class="table-head">
                <th>Diajukan</th><th class="th-c">Tugas</th><th class="th-c">Pemberi kerja → Mitra</th><th class="th-c">Kategori</th><th class="th-c">Upah</th><th class="th-c">Tanggapan</th><th class="th-c">Aksi</th>
            </tr></thead>
            <tbody>
            @forelse ($queue as $d)
                <tr class="table-row">
                    <td class="whitespace-nowrap text-slate-500 text-xs">{{ $d->created_at }}</td>
                    <td class="td-c">
                        <p class="font-medium"><span class="marq" title="{{ $d->task?->title }}"><span class="marq-in">{{ $d->task?->title }}</span></span></p>
                        <p class="text-xs text-slate-400 font-mono">#{{ $d->task?->task_number }}</p>
                    </td>
                    <td class="td-c text-xs">
                        <p class="font-medium">{{ $d->task?->poster?->name }}</p>
                        <p class="text-slate-400">→ {{ $d->activity?->worker?->name ?? 'tiket lama (per task)' }}</p>
                    </td>
                    <td class="td-c">@include('super_admin.partials.badge', ['text' => $d->category?->label() ?? '—', 'tone' => 'orange'])</td>
                    <td class="td-c whitespace-nowrap font-extrabold" style="color: #163C68;">
                        {{ $d->activity ? 'Rp'.number_format((int) $d->activity->agreed_amount, 0, ',', '.') : '—' }}
                    </td>
                    <td class="td-c">
                        @if ($d->status->isOpen())
                            @include('super_admin.partials.badge', $d->worker_responded_at ? ['text' => 'sudah', 'tone' => 'green'] : ['text' => 'belum', 'tone' => 'slate'])
                        @else
                            @include('super_admin.partials.badge', ['text' => $d->resolution?->value === 'release' ? 'komplain ditolak' : 'komplain diterima', 'tone' => $d->resolution?->value === 'release' ? 'green' : 'red'])
                        @endif
                    </td>
                    <td class="td-c">
                        <a href="{{ route('super_admin.disputes.show', $d->ulid) }}" class="btn btn-navy py-2! px-4! text-xs!">Buka →</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">@include('super_admin.partials.empty', ['title' => 'Antrean kosong', 'hint' => 'Tidak ada sengketa yang menunggu keputusan.'])</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('super_admin.partials.pages', ['paginator' => $queue])
@endsection
