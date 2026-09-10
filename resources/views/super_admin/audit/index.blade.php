@extends('super_admin.layout')

@section('title', 'Jejak audit')
@section('header', 'Jejak Audit')

@section('content')
@php
    $activeFilters = ($filterAction !== '' ? 1 : 0);
@endphp
<div class="anim-rise flex flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
        <h3 class="text-xl font-extrabold" style="color: #163C68;">Jejak audit</h3>
        <p class="mt-1 text-sm text-slate-500">Append-only — tidak ada <span class="font-mono font-bold">updated_at</span>. API belum punya endpoint baca; dasbor ini membaca langsung dari basis data.</p>
    </div>
    <button type="button" data-open-drawer="filterDrawer" class="btn btn-navy shrink-0">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"/></svg>
        Filter
        @if ($activeFilters > 0)
            <span class="ml-1 inline-flex min-w-6 h-6 px-1.5 items-center justify-center rounded-full text-xs font-bold text-white" style="background: var(--accent);">{{ $activeFilters }}</span>
        @endif
    </button>
</div>

<form method="GET" data-guard>
<div class="anim-rise ad-2 mt-4 card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[760px]">
            <thead><tr class="table-head">
                <th>Waktu</th><th class="th-c">Pelaku</th><th class="th-c">Tindakan</th><th class="th-c">Subjek</th><th class="th-c">IP</th><th>Alasan</th>
            </tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr class="table-row">
                    <td class="whitespace-nowrap text-slate-500 text-xs">{{ $log->created_at }}</td>
                    <td class="td-c font-medium text-xs"><span class="marq" title="{{ $log->admin?->email }}"><span class="marq-in">{{ $log->admin?->email }}</span></span></td>
                    <td class="td-c">@include('super_admin.partials.badge', ['text' => $log->action->value, 'tone' => 'navy'])</td>
                    <td class="td-c font-mono text-xs text-slate-500 whitespace-nowrap">{{ $log->subject_type }} #{{ $log->subject_id }}</td>
                    <td class="td-c font-mono text-xs text-slate-400">{{ $log->ip ?? '—' }}</td>
                    <td class="text-xs text-slate-500"><span class="marq" title="{{ $log->reason }}"><span class="marq-in">{{ $log->reason ?? '—' }}</span></span></td>
                </tr>
            @empty
                <tr><td colspan="6">@include('super_admin.partials.empty', ['title' => 'Belum ada jejak', 'hint' => 'Jejak muncul setelah ada keputusan pengelola.'])</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('super_admin.partials.pages', ['paginator' => $logs])

<div id="filterDrawer" class="rdrawer" aria-hidden="true">
    <div class="rdrawer-bg" data-close-drawer></div>
    <aside class="rdrawer-panel sidebar-bg" role="dialog" aria-modal="true" aria-label="Filter audit">
        <div class="flex items-center gap-3 px-6 pt-6">
            <span class="w-11 h-11 rounded-2xl flex items-center justify-center text-white shrink-0" style="background: linear-gradient(135deg, #F97316, #C2570B);">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"/></svg>
            </span>
            <div class="flex-1 min-w-0">
                <h4 class="font-extrabold text-white leading-tight">Filter audit</h4>
                <p class="text-xs text-slate-300/80">Kosongkan untuk melihat semua</p>
            </div>
            <button type="button" class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-300 hover:text-white hover:bg-white/10 transition-all shrink-0" data-close-drawer aria-label="Tutup filter">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="px-6 py-5 space-y-4 text-sm flex-1">
            <div>
                <label class="label-dark" for="f-action">Tindakan</label>
                <select id="f-action" name="action" class="field-dark">
                    <option value="">Semua tindakan</option>
                    @foreach ($actions as $a)
                        <option value="{{ $a->value }}" @selected($filterAction === $a->value)>{{ $a->value }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="px-6 pb-6 flex gap-2">
            <button type="submit" class="btn btn-accent flex-1">Terapkan</button>
            <a href="{{ route('super_admin.audit.index') }}" class="btn btn-ghost flex-1 !bg-white/10 !text-white !border-white/20 hover:!bg-white/20">Reset</a>
        </div>
    </aside>
</div>
</form>
@endsection
