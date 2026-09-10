@extends('super_admin.layout')

@section('title', 'Detail verifikasi #'.$verification->id)

@section('content')
<a href="{{ route('super_admin.verifications.index') }}" class="text-sm underline">← Kembali ke antrean</a>
<h2 class="mt-2 text-2xl font-semibold">Verifikasi #{{ $verification->id }} — {{ $verification->type->value }}</h2>

<div class="mt-2 rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
    Membuka halaman ini <strong>mencatat</strong> <span class="font-mono">verification.viewed</span> ke jejak audit
    (siapa, kapan, dari IP mana). NIK / nomor rekening di bawah hanya ada di halaman detail ini — tidak pernah di daftar.
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <div class="rounded-xl bg-white p-5 shadow">
        <h3 class="font-semibold">Status</h3>
        <dl class="mt-2 space-y-1 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Status</dt><dd class="font-mono">{{ $verification->status->value }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Diajukan</dt><dd>{{ $verification->submitted_at }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Dinilai</dt><dd>{{ $verification->reviewed_at ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Penilai</dt><dd>{{ $verification->reviewer?->email ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Foto KTP</dt><dd>{{ $verification->id_card_photo_path ? 'ada' : 'tidak ada' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Foto selfie</dt><dd>{{ $verification->selfie_photo_path ? 'ada' : 'tidak ada' }}</dd></div>
            @if ($verification->face_match_score !== null)
                <div class="flex justify-between"><dt class="text-slate-500">Face match</dt><dd>{{ $verification->face_match_score }}</dd></div>
            @endif
            @if ($verification->rejection_reason)
                <div><dt class="text-slate-500">Alasan penolakan</dt><dd class="mt-1 rounded bg-slate-100 p-2">{{ $verification->rejection_reason }}</dd></div>
            @endif
            @if ($verification->revoked_reason)
                <div><dt class="text-slate-500">Alasan pencabutan</dt><dd class="mt-1 rounded bg-slate-100 p-2">{{ $verification->revoked_reason }}</dd></div>
            @endif
        </dl>
    </div>
    <div class="rounded-xl bg-white p-5 shadow">
        <h3 class="font-semibold">Data dokumen (terbaca utuh — keputusan pemilik proyek)</h3>
        @if ($verification->type->value === 'identity')
            <dl class="mt-2 space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Nama di dokumen</dt><dd class="font-medium">{{ $verification->name_on_document ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Tgl lahir</dt><dd>{{ $verification->birth_date_on_document ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">NIK</dt><dd class="font-mono font-semibold">{{ $verification->document_number_enc ?? '—' }}</dd></div>
            </dl>
        @else
            <dl class="mt-2 space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Bank</dt><dd>{{ $verification->bank_code ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Atas nama</dt><dd>{{ $verification->account_holder_name ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">No. rekening</dt><dd class="font-mono font-semibold">{{ $verification->account_number_enc ?? '—' }}</dd></div>
            </dl>
        @endif
        <h3 class="mt-4 font-semibold">Pemilik</h3>
        <dl class="mt-2 space-y-1 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Nama</dt><dd>{{ $verification->user?->name }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Email</dt><dd>{{ $verification->user?->email }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Status akun</dt><dd class="font-mono">{{ $verification->user?->status->value }}</dd></div>
        </dl>
    </div>
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-3">
    <form method="POST" action="{{ route('super_admin.verifications.approve', $verification) }}" class="rounded-xl bg-white p-5 shadow">
        @csrf
        <h3 class="font-semibold">Setujui</h3>
        <p class="text-sm text-slate-500">Badge terverifikasi pemiliknya menyala.</p>
        <button class="mt-3 w-full rounded bg-emerald-700 px-4 py-2 text-sm text-white">Setujui</button>
    </form>
    <form method="POST" action="{{ route('super_admin.verifications.reject', $verification) }}" class="rounded-xl bg-white p-5 shadow">
        @csrf
        <h3 class="font-semibold">Tolak</h3>
        <p class="text-sm text-slate-500">Alasan wajib (min. 10 karakter) — dibaca penggunanya.</p>
        <textarea name="reason" required minlength="10" maxlength="500" rows="3" class="mt-2 w-full rounded border border-slate-300 px-3 py-2 text-sm" placeholder="Foto KTP tidak terbaca, silakan unggah ulang.">{{ old('reason') }}</textarea>
        <button class="mt-3 w-full rounded bg-red-700 px-4 py-2 text-sm text-white">Tolak</button>
    </form>
    <form method="POST" action="{{ route('super_admin.verifications.revoke', $verification) }}" class="rounded-xl bg-white p-5 shadow">
        @csrf
        <h3 class="font-semibold">Cabut</h3>
        <p class="text-sm text-slate-500">Untuk verifikasi yang ternyata palsu. Alasan wajib.</p>
        <textarea name="reason" required minlength="10" maxlength="500" rows="3" class="mt-2 w-full rounded border border-slate-300 px-3 py-2 text-sm" placeholder="Identitas terbukti palsu berdasarkan ..."></textarea>
        <button class="mt-3 w-full rounded bg-slate-900 px-4 py-2 text-sm text-white">Cabut</button>
    </form>
</div>
@endsection
