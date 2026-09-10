@extends('super_admin.layout')

@section('title', $user->email)

@section('content')
<a href="{{ route('super_admin.users.index') }}" class="text-sm underline">← Kembali ke daftar</a>
<h2 class="mt-2 text-2xl font-semibold">{{ $user->name }}</h2>
<p class="text-sm text-slate-500">{{ $user->email }} · <span class="font-mono">{{ $user->status->value }}</span></p>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <div class="rounded-xl bg-white p-5 shadow">
        <h3 class="font-semibold">Profil</h3>
        <dl class="mt-2 space-y-1 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">ULID</dt><dd class="font-mono text-xs">{{ $user->ulid }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Telepon</dt><dd>{{ $user->phone ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Kota</dt><dd>{{ $user->city ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Email terverifikasi</dt><dd>{{ $user->email_verified_at ?? 'belum' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Dibuat</dt><dd>{{ $user->created_at }}</dd></div>
        </dl>
    </div>
    <div class="rounded-xl bg-white p-5 shadow">
        <h3 class="font-semibold">Verifikasi terakhir</h3>
        @forelse ($user->verifications as $v)
            <div class="mt-2 border-t pt-2 text-sm">
                <span class="font-mono text-xs">{{ $v->type->value }} · {{ $v->status->value }}</span>
                <span class="text-slate-500">— {{ $v->submitted_at }}</span>
            </div>
        @empty
            <p class="mt-2 text-sm text-slate-500">Belum pernah mengajukan verifikasi.</p>
        @endforelse
    </div>
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-3">
    <form method="POST" action="{{ route('super_admin.users.suspend', $user->ulid) }}" class="rounded-xl bg-white p-5 shadow">
        @csrf
        <h3 class="font-semibold">Tangguhkan</h3>
        <p class="text-sm text-slate-500">Mencabut seluruh tokennya.</p>
        <textarea name="reason" required minlength="10" maxlength="1000" rows="3" class="mt-2 w-full rounded border border-slate-300 px-3 py-2 text-sm"></textarea>
        <button class="mt-3 w-full rounded bg-amber-600 px-4 py-2 text-sm text-white">Tangguhkan</button>
    </form>
    <form method="POST" action="{{ route('super_admin.users.ban', $user->ulid) }}" class="rounded-xl bg-white p-5 shadow">
        @csrf
        <h3 class="font-semibold">Blokir</h3>
        <p class="text-sm text-slate-500">Mencabut seluruh tokennya.</p>
        <textarea name="reason" required minlength="10" maxlength="1000" rows="3" class="mt-2 w-full rounded border border-slate-300 px-3 py-2 text-sm"></textarea>
        <button class="mt-3 w-full rounded bg-red-700 px-4 py-2 text-sm text-white">Blokir</button>
    </form>
    <form method="POST" action="{{ route('super_admin.users.reinstate', $user->ulid) }}" class="rounded-xl bg-white p-5 shadow">
        @csrf
        <h3 class="font-semibold">Pulihkan</h3>
        <p class="text-sm text-slate-500">Akun yang belum verifikasi email kembali ke <span class="font-mono">pending_verification</span>.</p>
        <button class="mt-3 w-full rounded bg-emerald-700 px-4 py-2 text-sm text-white">Pulihkan</button>
    </form>
</div>
@endsection
