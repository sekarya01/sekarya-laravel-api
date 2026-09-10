@extends('super_admin.layout')

@section('title', 'Dasbor')

@section('content')
<h2 class="text-2xl font-semibold">Ringkasan</h2>
<p class="mt-1 text-sm text-slate-500">Angka antrean yang menunggu tindakan manusia. Paling lama menunggu harus dikerjakan dulu.</p>

<div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
    <a href="{{ route('super_admin.verifications.index') }}" class="rounded-xl bg-white p-5 shadow hover:ring-2 hover:ring-slate-900">
        <p class="text-xs uppercase tracking-wide text-slate-500">Verifikasi menunggu</p>
        <p class="mt-1 text-3xl font-semibold">{{ $pendingVerifications }}</p>
    </a>
    <a href="{{ route('super_admin.payments.index') }}" class="rounded-xl bg-white p-5 shadow hover:ring-2 hover:ring-slate-900">
        <p class="text-xs uppercase tracking-wide text-slate-500">Transfer menunggu</p>
        <p class="mt-1 text-3xl font-semibold">{{ $awaitingPayments }}</p>
    </a>
    <div class="rounded-xl bg-white p-5 shadow">
        <p class="text-xs uppercase tracking-wide text-slate-500">Task open</p>
        <p class="mt-1 text-3xl font-semibold">{{ $openTasks }}</p>
    </div>
    <div class="rounded-xl bg-white p-5 shadow">
        <p class="text-xs uppercase tracking-wide text-slate-500">Pengguna aktif</p>
        <p class="mt-1 text-3xl font-semibold">{{ $activeUsers }}</p>
    </div>
    <a href="{{ route('super_admin.admins.index') }}" class="rounded-xl bg-white p-5 shadow hover:ring-2 hover:ring-slate-900">
        <p class="text-xs uppercase tracking-wide text-slate-500">Pengelola</p>
        <p class="mt-1 text-3xl font-semibold">{{ $totalAdmins }}</p>
    </a>
</div>

<div class="mt-8 rounded-xl bg-white p-5 shadow">
    <div class="flex items-center justify-between">
        <h3 class="font-semibold">Keputusan terakhir</h3>
        <a href="{{ route('super_admin.audit.index') }}" class="text-sm text-slate-600 underline">Lihat jejak audit</a>
    </div>
    <div class="mt-3 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
            <tr class="text-left text-slate-500">
                <th class="py-2 pr-4">Waktu</th>
                <th class="py-2 pr-4">Pelaku</th>
                <th class="py-2 pr-4">Tindakan</th>
                <th class="py-2 pr-4">Subjek</th>
                <th class="py-2">Alasan</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($recentAudits as $log)
                <tr class="border-t">
                    <td class="py-2 pr-4 whitespace-nowrap">{{ $log->created_at }}</td>
                    <td class="py-2 pr-4">{{ $log->admin?->email }}</td>
                    <td class="py-2 pr-4 font-mono text-xs">{{ $log->action->value }}</td>
                    <td class="py-2 pr-4 font-mono text-xs">{{ $log->subject_type }} #{{ $log->subject_id }}</td>
                    <td class="py-2">{{ \Illuminate\Support\Str::limit((string) $log->reason, 60) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-4 text-slate-500">Belum ada jejak.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
