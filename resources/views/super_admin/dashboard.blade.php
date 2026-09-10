@extends('super_admin.layout')

@section('title', 'Dasbor')
@section('header', 'Ringkasan Operasional')

@section('content')
<div class="anim-rise">
    <div class="rounded-2xl p-5 sm:p-6 text-white relative overflow-hidden" style="background: linear-gradient(120deg, #0A1E35 0%, #163C68 60%, #1E4E85 100%);">
        <div class="absolute -right-10 -top-16 w-64 h-64 rounded-full opacity-25" style="background: #F97316; filter: blur(70px);"></div>
        <div class="relative">
            <p class="text-[.7rem] uppercase tracking-[.2em] font-bold text-orange-200">Halo, Super Admin</p>
            <h3 class="mt-1 text-xl sm:text-2xl font-extrabold">Ada {{ $pendingVerifications + $awaitingPayments }} antrean menunggu tindakanmu.</h3>
            <p class="mt-1 text-sm text-slate-300">Kerjakan yang paling lama menunggu dulu — mereka yang paling lama tertahan.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('super_admin.verifications.index') }}" class="btn btn-accent !text-[.8rem]">Buka verifikasi →</a>
                <a href="{{ route('super_admin.payments.index') }}" class="btn !text-[.8rem] bg-white/15 text-white border border-white/20 hover:bg-white/25">Buka transfer →</a>
            </div>
        </div>
    </div>
</div>

@php
$stats = [
    ['label' => 'Verifikasi menunggu', 'value' => $pendingVerifications, 'href' => route('super_admin.verifications.index'),
     'bg' => '#FFF1E4', 'fg' => '#F97316', 'alert' => $pendingVerifications > 0,
     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>'],
    ['label' => 'Transfer menunggu', 'value' => $awaitingPayments, 'href' => route('super_admin.payments.index'),
     'bg' => '#E4EDF7', 'fg' => '#163C68', 'alert' => $awaitingPayments > 0,
     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>'],
    ['label' => 'Task open', 'value' => $openTasks, 'href' => null,
     'bg' => '#E7F6EF', 'fg' => '#047857', 'alert' => false,
     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0"/>'],
    ['label' => 'Pengguna aktif', 'value' => $activeUsers, 'href' => route('super_admin.users.index'),
     'bg' => '#F1EAFE', 'fg' => '#6D28D9', 'alert' => false,
     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>'],
    ['label' => 'Pengelola', 'value' => $totalAdmins, 'href' => route('super_admin.admins.index'),
     'bg' => '#E4EDF7', 'fg' => '#163C68', 'alert' => false,
     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/>'],
];
@endphp

<div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
    @foreach ($stats as $i => $s)
        @php $tag = $s['href'] ? 'a' : 'div'; @endphp
        <{{ $tag }} @if($s['href']) href="{{ $s['href'] }}" @endif
            class="card card-lift anim-rise ad-{{ $i + 1 }} p-5 flex items-center gap-4 {{ $s['alert'] ? 'needs-attention' : '' }}">
            <div class="stat-icon" style="background: {{ $s['bg'] }}; color: {{ $s['fg'] }};">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">{!! $s['icon'] !!}</svg>
            </div>
            <div class="min-w-0">
                <p class="text-[.7rem] uppercase tracking-wider font-bold text-slate-400">{{ $s['label'] }}</p>
                <p class="text-3xl font-extrabold leading-none mt-1" style="color: #163C68;">{{ $s['value'] }}</p>
            </div>
        </{{ $tag }}>
    @endforeach
</div>

<div class="mt-5 card anim-rise ad-4 overflow-hidden">
    <div class="flex items-center justify-between px-5 sm:px-6 pt-5">
        <div>
            <h3 class="font-extrabold text-lg" style="color: #163C68;">Keputusan terakhir</h3>
            <p class="text-xs text-slate-400">Jejak append-only — tidak bisa disunting.</p>
        </div>
        <a href="{{ route('super_admin.audit.index') }}" class="btn btn-ghost !py-2 !text-xs">Semua jejak →</a>
    </div>
    <div class="mt-3 overflow-x-auto">
        <table class="w-full text-sm min-w-[640px]">
            <thead><tr class="table-head">
                <th>Waktu</th><th>Pelaku</th><th>Tindakan</th><th>Subjek</th><th>Alasan</th>
            </tr></thead>
            <tbody>
            @forelse ($recentAudits as $log)
                <tr class="table-row">
                    <td class="whitespace-nowrap text-slate-500 text-xs">{{ $log->created_at }}</td>
                    <td class="font-medium">{{ $log->admin?->email }}</td>
                    <td>@include('super_admin.partials.badge', ['text' => $log->action->value, 'tone' => 'navy'])</td>
                    <td class="font-mono text-xs text-slate-500">{{ $log->subject_type }} #{{ $log->subject_id }}</td>
                    <td class="text-slate-500 text-xs max-w-[220px] truncate">{{ $log->reason ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">@include('super_admin.partials.empty', ['title' => 'Belum ada jejak', 'hint' => 'Setiap keputusanmu akan tercatat di sini.'])</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
