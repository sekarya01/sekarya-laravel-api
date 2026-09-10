@extends('super_admin.layout')

@section('title', 'Jejak audit')
@section('header', 'Jejak Audit')

@section('content')
<div class="anim-rise">
    <h3 class="text-xl font-extrabold" style="color: #163C68;">Jejak audit</h3>
    <p class="mt-1 text-sm text-slate-500">Append-only — tidak ada <span class="font-mono font-bold">updated_at</span>. API belum punya endpoint baca; dasbor ini membaca langsung dari basis data.</p>
</div>

<form method="GET" class="anim-rise ad-1 mt-4 card p-4 flex flex-wrap items-end gap-3 text-sm">
    <div class="flex-1 min-w-[12rem]">
        <label class="label">Tindakan</label>
        <select name="action" class="field">
            <option value="">Semua tindakan</option>
            @foreach ($actions as $a)
                <option value="{{ $a->value }}" @selected($filterAction === $a->value)>{{ $a->value }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn btn-accent">Saring</button>
</form>

<div class="anim-rise ad-2 mt-4 card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[760px]">
            <thead><tr class="table-head">
                <th>Waktu</th><th>Pelaku</th><th>Tindakan</th><th>Subjek</th><th>IP</th><th>Alasan</th>
            </tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr class="table-row">
                    <td class="whitespace-nowrap text-slate-500 text-xs">{{ $log->created_at }}</td>
                    <td class="font-medium text-xs max-w-[180px] truncate">{{ $log->admin?->email }}</td>
                    <td>@include('super_admin.partials.badge', ['text' => $log->action->value, 'tone' => 'navy'])</td>
                    <td class="font-mono text-xs text-slate-500 whitespace-nowrap">{{ $log->subject_type }} #{{ $log->subject_id }}</td>
                    <td class="font-mono text-xs text-slate-400">{{ $log->ip ?? '—' }}</td>
                    <td class="text-xs text-slate-500 max-w-[220px] truncate" title="{{ $log->reason }}">{{ $log->reason ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6">@include('super_admin.partials.empty', ['title' => 'Belum ada jejak', 'hint' => 'Jejak muncul setelah ada keputusan pengelola.'])</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('super_admin.partials.pager', ['paginator' => $logs])
@endsection
