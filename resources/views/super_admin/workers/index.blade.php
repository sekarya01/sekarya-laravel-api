@extends('super_admin.layout')

@section('title', 'Direktori pekerja')
@section('header', 'Direktori Pekerja')

@section('content')
<div class="anim-rise">
    <h3 class="text-xl font-extrabold" style="color: #163C68;">Direktori pekerja</h3>
    <p class="mt-1 text-sm text-slate-500">Sisi <span class="font-mono font-bold">user_workers</span> — hanya akun <strong>aktif</strong>, terbaru siap bekerja dulu. <span class="font-mono font-bold">ready_to_work</span> adalah penanda per baris, disaring hanya kalau diminta.</p>
</div>

<form method="GET" class="anim-rise ad-1 mt-4 card p-4 flex flex-wrap items-end gap-3 text-sm">
    <div>
        <label class="label" for="w-ready">Kesiapan</label>
        <select id="w-ready" name="ready_to_work" class="field w-auto! min-w-[10rem]">
            <option value="" @selected($filterReadyToWork === '')>Semua</option>
            <option value="1" @selected($filterReadyToWork === '1')>Siap kerja saja</option>
            <option value="0" @selected($filterReadyToWork === '0')>Belum siap saja</option>
        </select>
    </div>
    <div>
        <label class="label" for="w-gender">Jenis kelamin</label>
        <select id="w-gender" name="gender" class="field w-auto! min-w-[10rem]">
            <option value="">Semua</option>
            <option value="male" @selected($filterGender === 'male')>Laki-laki</option>
            <option value="female" @selected($filterGender === 'female')>Perempuan</option>
        </select>
    </div>
    <div>
        <label class="label" for="w-city">Kota kerja</label>
        <input id="w-city" name="city" value="{{ $filterCity }}" placeholder="cth. Jakarta" maxlength="80" class="field w-auto! min-w-[11rem]">
    </div>
    <div>
        <label class="label" for="w-province">Provinsi</label>
        <input id="w-province" name="province" value="{{ $filterProvince }}" placeholder="cth. DKI Jakarta" maxlength="80" class="field w-auto! min-w-[11rem]">
    </div>
    <button class="btn btn-accent">Saring</button>
</form>

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
@endsection
