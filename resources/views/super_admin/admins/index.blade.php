@extends('super_admin.layout')

@section('title', 'Akun pengelola')

@section('content')
<h2 class="text-2xl font-semibold">Akun pengelola</h2>
<p class="mt-1 text-sm text-slate-500">Tepat satu <span class="font-mono">super_admin</span> (dijaga basis data). Peran baru selalu <span class="font-mono">admin</span>. Hapus = soft delete, email tetap terpakai.</p>

<div class="mt-4 overflow-x-auto rounded-xl bg-white shadow">
    <table class="w-full text-sm">
        <thead>
        <tr class="text-left text-slate-500">
            <th class="px-4 py-3">Nama</th>
            <th class="px-4 py-3">Email</th>
            <th class="px-4 py-3">Peran</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3"></th>
        </tr>
        </thead>
        <tbody>
        @forelse ($admins as $a)
            <tr class="border-t">
                <td class="px-4 py-3">{{ $a->name }}</td>
                <td class="px-4 py-3">{{ $a->email }}</td>
                <td class="px-4 py-3 font-mono text-xs">{{ $a->role->value }}</td>
                <td class="px-4 py-3 font-mono text-xs">{{ $a->status->value }}</td>
                <td class="px-4 py-3 text-right">
                    @if (! $a->isSuperAdmin())
                        <form method="POST" action="{{ route('super_admin.admins.destroy', $a->ulid) }}" onsubmit="return confirm('Hapus {{ $a->email }}? Tokennya dicabut, barisnya tetap ada untuk jejak audit.')">
                            @csrf
                            @method('DELETE')
                            <button class="text-red-700 underline">Hapus</button>
                        </form>
                    @else
                        <span class="text-xs text-slate-400">dilindungi</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-6 text-slate-500">Tidak ada pengelola.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4 flex gap-2 text-sm">
    @if ($admins->previousCursor())
        <a href="{{ $admins->previousPageUrl() }}" class="rounded border px-3 py-2 bg-white">← Sebelumnya</a>
    @endif
    @if ($admins->hasMorePages())
        <a href="{{ $admins->nextPageUrl() }}" class="rounded border px-3 py-2 bg-white">Berikutnya →</a>
    @endif
</div>

<div class="mt-6 rounded-xl bg-white p-5 shadow max-w-xl">
    <h3 class="font-semibold">Buat pengelola baru</h3>
    <p class="text-sm text-slate-500">Sandi min. 12 karakter. Tidak ada field peran — selalu <span class="font-mono">admin</span>.</p>
    <form method="POST" action="{{ route('super_admin.admins.store') }}" class="mt-3 space-y-3 text-sm">
        @csrf
        <div>
            <label class="font-medium">Nama</label>
            <input name="name" required minlength="3" maxlength="100" value="{{ old('name') }}" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
        </div>
        <div>
            <label class="font-medium">Email</label>
            <input name="email" type="email" required maxlength="255" value="{{ old('email') }}" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="font-medium">Kata sandi</label>
                <input name="password" type="password" required minlength="12" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            </div>
            <div>
                <label class="font-medium">Konfirmasi</label>
                <input name="password_confirmation" type="password" required minlength="12" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            </div>
        </div>
        <button class="rounded bg-slate-900 px-4 py-2 text-white">Buat admin</button>
    </form>
</div>
@endsection
