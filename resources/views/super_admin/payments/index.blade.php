@extends('super_admin.layout')

@section('title', 'Antrean transfer')
@section('header', 'Konfirmasi Transfer')

@section('content')
<div class="anim-rise">
    <h3 class="text-xl font-extrabold" style="color: #163C68;">Antrean konfirmasi transfer</h3>
    <p class="mt-1 text-sm text-slate-500">Bawaan <span class="font-mono font-bold">awaiting_confirmation</span>, urut <span class="font-mono font-bold">reported_at</span> — paling lama menunggu di depan.</p>
</div>

<form method="GET" class="anim-rise ad-1 mt-4 card p-4 flex flex-wrap items-end gap-3 text-sm">
    <div>
        <label class="label">Status tagihan</label>
        <select name="status" class="field w-auto! min-w-[14rem]">
            @foreach (['awaiting_confirmation','pending','held','released','refunded','cancelled'] as $s)
                <option value="{{ $s }}" @selected($filterStatus === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn btn-accent">Saring</button>
</form>

<div class="anim-rise ad-2 mt-4 card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[720px]">
            <thead><tr class="table-head">
                <th>Dilaporkan</th><th class="th-c">Task</th><th class="th-c">Nominal</th><th class="th-c">Status</th><th class="th-c">Pembayar</th><th class="th-c">Aksi</th>
            </tr></thead>
            <tbody>
            @forelse ($queue as $p)
                @php
                    $tone = match($p->status->value) {
                        'held' => 'green', 'released' => 'navy', 'pending' => 'amber',
                        'refunded', 'cancelled' => 'slate', default => 'orange',
                    };
                @endphp
                <tr class="table-row">
                    <td class="whitespace-nowrap text-slate-500 text-xs">{{ $p->reported_at ?? $p->created_at }}</td>
                    <td class="td-c">
                        <p class="font-medium"><span class="marq" title="{{ $p->task?->title }}"><span class="marq-in">{{ $p->task?->title }}</span></span></p>
                        <p class="font-mono text-xs text-slate-400">{{ $p->task?->task_number }}</p>
                    </td>
                    <td class="td-c whitespace-nowrap font-extrabold" style="color: #163C68;">Rp{{ number_format($p->amount, 0, ',', '.') }}</td>
                    <td class="td-c">@include('super_admin.partials.badge', ['text' => $p->status->value, 'tone' => $tone])</td>
                    <td class="td-c text-xs text-slate-500"><span class="marq" title="{{ $p->payer?->email }}"><span class="marq-in">{{ $p->payer?->email }}</span></span></td>
                    <td class="td-c">
                        <a href="{{ route('super_admin.payments.show', $p->ulid) }}" class="btn btn-navy py-2! px-4! text-xs!">Buka →</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">@include('super_admin.partials.empty', ['title' => 'Antrean kosong', 'hint' => 'Tidak ada laporan transfer yang menunggu.'])</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('super_admin.partials.pager', ['paginator' => $queue])
@endsection
