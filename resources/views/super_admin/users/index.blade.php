@extends('super_admin.layout')

@section('title', 'Moderasi pengguna')
@section('header', 'Moderasi Pengguna')

@section('content')
@php
    $activeFilters = ($filterEmail !== '' ? 1 : 0)
        + (old('ulid') ? 1 : 0)
        + ($filterStatus !== '' ? 1 : 0)
        + ($filterGender !== '' ? 1 : 0)
        + ($filterReady !== '' ? 1 : 0);
@endphp
<div class="anim-rise flex flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
        <h3 class="text-xl font-extrabold" style="color: #163C68;">Moderasi pengguna</h3>
        <p class="mt-1 text-sm text-slate-500">Semua pencocokan <strong>persis dan terindeks</strong> — bukan pencarian sebagian. Tidak ada LIKE di proyek ini. Untuk ULID, langsung lompat ke detailnya.</p>
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
    <div class="flex-1 min-w-[14rem]">
        <label class="label" for="u-email">Email persis</label>
        <input id="u-email" name="email" value="{{ $filterEmail }}" placeholder="cth. budi@sekarya.test" class="field">
    </div>
    <div class="min-w-[12rem]">
        <label class="label" for="u-ulid">Lompat via ULID</label>
        <input id="u-ulid" name="ulid" value="{{ old('ulid') }}" placeholder="01K…" maxlength="26" class="field font-mono">
    </div>
    <button type="submit" class="btn btn-accent">Cari</button>
</div>

<div class="anim-rise ad-2 mt-4 card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[640px]">
            <thead><tr class="table-head">
                <th>Pengguna</th><th class="th-c">Status</th><th class="th-c">Terdaftar</th><th class="th-c">Aksi</th>
            </tr></thead>
            <tbody>
            @forelse ($queue as $u)
                @php
                    $tone = match($u->status->value) {
                        'active' => 'green', 'banned' => 'red', 'suspended' => 'orange', default => 'amber',
                    };
                @endphp
                <tr class="table-row">
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-white text-xs shrink-0" style="background: linear-gradient(135deg, #163C68, #2A5E9E);">
                                {{ strtoupper(substr($u->name, 0, 1)) }}
                            </div>
                            <div class="min-w-0"><p class="font-bold"><span class="marq" title="{{ $u->name }}"><span class="marq-in">{{ $u->name }}</span></span></p><p class="text-xs text-slate-400"><span class="marq" title="{{ $u->email }}"><span class="marq-in">{{ $u->email }}</span></span></p>@if ($u->readyToWork())<p class="text-xs font-bold" style="color: #F97316;">● siap kerja</p>@endif</div>
                        </div>
                    </td>
                    <td class="td-c">@include('super_admin.partials.badge', ['text' => $u->status->value, 'tone' => $tone])</td>
                    <td class="td-c whitespace-nowrap text-slate-500 text-xs">{{ $u->created_at }}</td>
                    <td class="td-c">
                        <a href="{{ route('super_admin.users.show', $u->ulid) }}" class="btn btn-navy py-2! px-4! text-xs!">Buka →</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4">@include('super_admin.partials.empty', ['title' => 'Tidak ada hasil', 'hint' => 'Alamat yang tidak terdaftar mengembalikan daftar kosong — bukan galat.'])</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('super_admin.partials.pages', ['paginator' => $queue])

<div id="filterDrawer" class="rdrawer" aria-hidden="true">
    <div class="rdrawer-bg" data-close-drawer></div>
    <aside class="rdrawer-panel sidebar-bg" role="dialog" aria-modal="true" aria-label="Filter pengguna">
        <div class="flex items-center gap-3 px-6 pt-6">
            <span class="w-11 h-11 rounded-2xl flex items-center justify-center text-white shrink-0" style="background: linear-gradient(135deg, #F97316, #C2570B);">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"/></svg>
            </span>
            <div class="flex-1 min-w-0">
                <h4 class="font-extrabold text-white leading-tight">Filter pengguna</h4>
                <p class="text-xs text-slate-300/80">Pencarian teks tetap di halaman</p>
            </div>
            <button type="button" class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-300 hover:text-white hover:bg-white/10 transition-all shrink-0" data-close-drawer aria-label="Tutup filter">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="px-6 py-5 space-y-4 text-sm flex-1">
            <div>
                <label class="label-dark" for="f-status">Status</label>
                <select id="f-status" name="status" class="field-dark">
                    <option value="">Semua status</option>
                    @foreach (['active', 'pending_verification', 'suspended', 'banned'] as $s)
                        <option value="{{ $s }}" @selected($filterStatus === $s)>{{ $s }}</option>
                    @endforeach
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
            <div>
                <label class="label-dark" for="f-ready">Kesiapan kerja</label>
                <select id="f-ready" name="ready" class="field-dark">
                    <option value="">Semua</option>
                    <option value="yes" @selected($filterReady === 'yes')>Siap saja</option>
                    <option value="no" @selected($filterReady === 'no')>Belum siap</option>
                </select>
            </div>
        </div>
        <div class="px-6 pb-6 flex gap-2">
            <button type="submit" class="btn btn-accent flex-1">Terapkan</button>
            <a href="{{ route('super_admin.users.index') }}" class="btn btn-ghost flex-1 !bg-white/10 !text-white !border-white/20 hover:!bg-white/20">Reset</a>
        </div>
    </aside>
</div>
</form>
@endsection
