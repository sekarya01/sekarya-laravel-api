@extends('super_admin.layout')

@section('title', 'Direktori pekerja')
@section('header', 'Direktori Pekerja')

@section('content')
@php
    $activeFilters = ($filterReadyToWork !== '' ? 1 : 0)
        + ($filterGender !== '' ? 1 : 0)
        + ($filterCity !== '' ? 1 : 0)
        + ($filterProvince !== '' ? 1 : 0);
@endphp
<div class="anim-rise flex flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
        <h3 class="text-xl font-extrabold" style="color: #163C68;">Direktori pekerja</h3>
        <p class="mt-1 text-sm text-slate-500">Sisi <span class="font-mono font-bold">user_workers</span> — hanya akun <strong>aktif</strong>, terbaru siap bekerja dulu. <span class="font-mono font-bold">ready_to_work</span> adalah penanda per baris, disaring hanya kalau diminta.</p>
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
<div class="anim-rise ad-1 mt-4 card p-4 flex flex-wrap items-end gap-3 text-sm">
    <div class="flex-1 min-w-[12rem]">
        <label class="label" for="w-city">Kota kerja</label>
        <input id="w-city" name="city" value="{{ $filterCity }}" placeholder="cth. Jakarta" maxlength="80" class="field">
    </div>
    <div class="flex-1 min-w-[12rem]">
        <label class="label" for="w-province">Provinsi</label>
        <input id="w-province" name="province" value="{{ $filterProvince }}" placeholder="cth. DKI Jakarta" maxlength="80" class="field">
    </div>
    <button type="submit" class="btn btn-accent">Cari</button>
</div>

<div class="anim-rise ad-2 mt-4 card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[760px]">
            <thead><tr class="table-head">
                <th>Pekerja</th><th class="th-c">Wilayah kerja</th><th class="th-c">Rating</th><th class="th-c">Selesai</th><th class="th-c">KTP</th><th class="th-c">Siap</th><th class="th-c">Aksi</th>
            </tr></thead>
            <tbody>
            @forelse ($workers as $w)
                @php
                    $u = $w->user;
                    $addr = $w->resolvedAddress();
                    $ready = $u?->readyToWork() ?? false;
                @endphp
                <tr class="table-row">
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-white text-xs shrink-0" style="background: linear-gradient(135deg, #163C68, #2A5E9E);">
                                {{ strtoupper(substr($w->resolvedName() ?? '?', 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="font-bold"><span class="marq" title="{{ $w->resolvedName() }}"><span class="marq-in">{{ $w->resolvedName() }}</span></span></p>
                                <p class="text-xs text-slate-400">{{ $u?->gender?->label() ?? '—' }} · {{ $w->age() !== null ? $w->age().' thn' : '—' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="td-c text-xs text-slate-500">{{ $addr['city'] ?? '—' }}{{ $addr['province'] ? ', '.$addr['province'] : '' }}</td>
                    <td class="td-c whitespace-nowrap font-bold" style="color: #163C68;">{{ number_format((float) $w->worker_rating_avg, 1) }} <span class="font-medium text-slate-400 text-xs">({{ $w->worker_rating_count }})</span></td>
                    <td class="td-c font-bold">{{ $w->tasks_completed }}</td>
                    <td class="td-c">
                        @if (($u?->identity_verified_count ?? 0) > 0)
                            @include('super_admin.partials.badge', ['text' => 'terverifikasi', 'tone' => 'green'])
                        @else
                            @include('super_admin.partials.badge', ['text' => 'belum', 'tone' => 'slate'])
                        @endif
                    </td>
                    <td class="td-c">@include('super_admin.partials.badge', ['text' => $ready ? 'siap kerja' : 'belum siap', 'tone' => $ready ? 'orange' : 'slate'])</td>
                    <td class="td-c">
                        <a href="{{ route('super_admin.workers.show', $w) }}" class="btn btn-navy py-2! px-4! text-xs!">Buka →</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">@include('super_admin.partials.empty', ['title' => 'Belum ada pekerja', 'hint' => 'Profil pekerja dibuat saat pengguna pertama kali bekerja atau mengisi profil pekerjanya.'])</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('super_admin.partials.pages', ['paginator' => $workers])

<div id="filterDrawer" class="rdrawer" aria-hidden="true">
    <div class="rdrawer-bg" data-close-drawer></div>
    <aside class="rdrawer-panel sidebar-bg" role="dialog" aria-modal="true" aria-label="Filter pekerja">
        <div class="flex items-center gap-3 px-6 pt-6">
            <span class="w-11 h-11 rounded-2xl flex items-center justify-center text-white shrink-0" style="background: linear-gradient(135deg, #F97316, #C2570B);">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"/></svg>
            </span>
            <div class="flex-1 min-w-0">
                <h4 class="font-extrabold text-white leading-tight">Filter pekerja</h4>
                <p class="text-xs text-slate-300/80">Kota & provinsi tetap di halaman</p>
            </div>
            <button type="button" class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-300 hover:text-white hover:bg-white/10 transition-all shrink-0" data-close-drawer aria-label="Tutup filter">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="px-6 py-5 space-y-4 text-sm flex-1">
            <div>
                <label class="label-dark" for="f-ready">Kesiapan</label>
                <select id="f-ready" name="ready_to_work" class="field-dark">
                    <option value="" @selected($filterReadyToWork === '')>Semua</option>
                    <option value="1" @selected($filterReadyToWork === '1')>Siap kerja saja</option>
                    <option value="0" @selected($filterReadyToWork === '0')>Belum siap saja</option>
                </select>
            </div>
            <div>
                <label class="label-dark" for="f-gender">Jenis kelamin</label>
                <select id="f-gender" name="gender" class="field-dark">
                    <option value="">Semua</option>
                    <option value="male" @selected($filterGender === 'male')>Laki-laki</option>
                    <option value="female" @selected($filterGender === 'female')>Perempuan</option>
                </select>
            </div>
        </div>
        <div class="px-6 pb-6 flex gap-2">
            <button type="submit" class="btn btn-accent flex-1">Terapkan</button>
            <a href="{{ route('super_admin.workers.index') }}" class="btn btn-ghost flex-1 !bg-white/10 !text-white !border-white/20 hover:!bg-white/20">Reset</a>
        </div>
    </aside>
</div>
</form>
@endsection
