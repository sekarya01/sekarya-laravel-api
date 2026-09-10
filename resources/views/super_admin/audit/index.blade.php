@extends('super_admin.layout')

@section('title', 'Jejak audit')

@section('content')
<h2 class="text-2xl font-semibold">Jejak audit</h2>
<p class="mt-1 text-sm text-slate-500">Append-only — tidak ada <span class="font-mono">updated_at</span>. API belum punya endpoint baca; dasbor ini membaca langsung dari basis data.</p>

<form method="GET" class="mt-4 flex gap-2 text-sm">
    <select name="action" class="rounded border border-slate-300 px-3 py-2">
        <option value="">Semua tindakan</option>
        @foreach ($actions as $a)
            <option value="{{ $a->value }}" @selected($filterAction === $a->value)>{{ $a->value }}</option>
        @endforeach
    </select>
    <button class="rounded bg-slate-900 px-4 py-2 text-white">Saring</button>
</form>

<div class="mt-4 overflow-x-auto rounded-xl bg-white shadow">
    <table class="w-full text-sm">
        <thead>
        <tr class="text-left text-slate-500">
            <th class="px-4 py-3">Waktu</th>
            <th class="px-4 py-3">Pelaku</th>
            <th class="px-4 py-3">Tindakan</th>
            <th class="px-4 py-3">Subjek</th>
            <th class="px-4 py-3">IP</th>
            <th class="px-4 py-3">Alasan</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($logs as $log)
            <tr class="border-t">
                <td class="px-4 py-3 whitespace-nowrap">{{ $log->created_at }}</td>
                <td class="px-4 py-3">{{ $log->admin?->email }}</td>
                <td class="px-4 py-3 font-mono text-xs">{{ $log->action->value }}</td>
                <td class="px-4 py-3 font-mono text-xs">{{ $log->subject_type }} #{{ $log->subject_id }}</td>
                <td class="px-4 py-3 font-mono text-xs">{{ $log->ip ?? '—' }}</td>
                <td class="px-4 py-3">{{ $log->reason ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-6 text-slate-500">Belum ada jejak.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4 flex gap-2 text-sm">
    @if ($logs->previousCursor())
        <a href="{{ $logs->previousPageUrl() }}" class="rounded border px-3 py-2 bg-white">← Sebelumnya</a>
    @endif
    @if ($logs->hasMorePages())
        <a href="{{ $logs->nextPageUrl() }}" class="rounded border px-3 py-2 bg-white">Berikutnya →</a>
    @endif
</div>
@endsection
