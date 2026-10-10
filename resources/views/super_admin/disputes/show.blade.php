@extends('super_admin.layout')

@section('title', 'Sengketa '.$dispute->ulid)
@section('header', 'Detail Sengketa')

@section('content')
@php
    $task = $dispute->task;
    $activity = $dispute->activity;
    $photoUrl = fn (string $path): string => \Illuminate\Support\Facades\Storage::disk('public')->url($path);
    $rupiah = fn (?int $n): string => 'Rp'.number_format((int) $n, 0, ',', '.');
@endphp
<div class="anim-rise flex flex-wrap items-center gap-3">
    <a href="{{ route('super_admin.disputes.index') }}" class="btn btn-ghost py-2! text-xs!">← Kembali ke antrean</a>
    @include('super_admin.partials.badge', ['text' => $dispute->status->value, 'tone' => $dispute->status->isOpen() ? 'orange' : 'green'])
    @include('super_admin.partials.badge', ['text' => $dispute->category?->label() ?? 'tanpa kategori', 'tone' => 'amber'])
</div>

<div class="anim-rise mt-3 rounded-2xl p-5 sm:p-6 text-white relative overflow-hidden" style="background: linear-gradient(120deg, #0A1E35, #163C68 70%);">
    <div class="absolute -right-8 -top-12 w-56 h-56 rounded-full opacity-25" style="background: #F97316; filter: blur(60px);"></div>
    <p class="relative text-xs uppercase tracking-widest font-bold text-orange-200">Upah mitra yang disengketakan</p>
    <p class="relative mt-1 text-3xl sm:text-4xl font-extrabold">{{ $activity ? $rupiah($activity->agreed_amount) : '—' }}</p>
    <p class="relative mt-1 text-sm text-slate-200">{{ $task?->title }} <span class="font-mono text-xs text-slate-400">#{{ $task?->task_number }}</span></p>
    <p class="relative mt-1 text-xs text-slate-300">{{ $task?->poster?->name }} → {{ $activity?->worker?->name ?? 'semua mitra yang disengketakan (tiket lama)' }}</p>
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <div class="anim-rise ad-1 card p-5 sm:p-6" style="border-top: 4px solid #F97316;">
        <h4 class="font-extrabold" style="color: #163C68;">Keluhan pemberi kerja</h4>
        <p class="mt-1 text-xs text-slate-400">{{ $task?->poster?->name }} · {{ $dispute->created_at }}</p>
        <p class="mt-3 text-sm leading-relaxed whitespace-pre-line">{{ $dispute->reason }}</p>
        @if ($dispute->evidence_photos)
            <div class="mt-3 grid grid-cols-3 gap-2">
                @foreach ($dispute->evidence_photos as $p)
                    <a href="{{ $photoUrl($p) }}" target="_blank" rel="noopener"><img src="{{ $photoUrl($p) }}" alt="Bukti pemberi kerja" class="aspect-square w-full rounded-xl object-cover"></a>
                @endforeach
            </div>
        @else
            <p class="mt-3 text-xs text-slate-400">Tanpa foto bukti.</p>
        @endif
    </div>

    <div class="anim-rise ad-2 card p-5 sm:p-6" style="border-top: 4px solid #163C68;">
        <h4 class="font-extrabold" style="color: #163C68;">Tanggapan mitra</h4>
        @if ($dispute->worker_responded_at)
            <p class="mt-1 text-xs text-slate-400">{{ $activity?->worker?->name }} · {{ $dispute->worker_responded_at }}</p>
            <p class="mt-3 text-sm leading-relaxed whitespace-pre-line">{{ $dispute->worker_response }}</p>
            @if ($dispute->worker_evidence_photos)
                <div class="mt-3 grid grid-cols-3 gap-2">
                    @foreach ($dispute->worker_evidence_photos as $p)
                        <a href="{{ $photoUrl($p) }}" target="_blank" rel="noopener"><img src="{{ $photoUrl($p) }}" alt="Bukti mitra" class="aspect-square w-full rounded-xl object-cover"></a>
                    @endforeach
                </div>
            @endif
        @else
            <p class="mt-3 text-sm text-slate-500">Mitra belum menanggapi. Tanggapan opsional — keputusan boleh diambil tanpanya.</p>
        @endif
    </div>
</div>

@if ($activity)
<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <div class="anim-rise ad-2 card p-5 sm:p-6">
        <h4 class="font-extrabold" style="color: #163C68;">Bukti pengerjaan mitra</h4>
        <p class="mt-3 text-sm leading-relaxed whitespace-pre-line">{{ $activity->worker_note ?? '—' }}</p>
        @if ($activity->proof_photos)
            <div class="mt-3 grid grid-cols-3 gap-2">
                @foreach ($activity->proof_photos as $p)
                    <a href="{{ $photoUrl($p) }}" target="_blank" rel="noopener"><img src="{{ $photoUrl($p) }}" alt="Bukti pengerjaan" class="aspect-square w-full rounded-xl object-cover"></a>
                @endforeach
            </div>
        @endif
    </div>
    <div class="anim-rise ad-3 card p-5 sm:p-6">
        <h4 class="font-extrabold" style="color: #163C68;">Waktu — untuk keluhan terlambat</h4>
        <dl class="mt-3 grid grid-cols-2 gap-y-2 text-sm">
            @foreach ([
                'Jadwal mulai' => $task?->needed_at,
                'Batas selesai' => $task?->end_at,
                'Berangkat' => $activity->departed_at,
                'Tiba (diakui pemberi kerja)' => $activity->arrived_at,
                'Mulai bekerja' => $activity->started_at,
                'Hasil diserahkan' => $activity->submitted_at,
                'Disengketakan' => $activity->rejected_at,
            ] as $label => $time)
                <dt class="text-slate-500">{{ $label }}</dt><dd class="font-medium text-right">{{ $time ?? '—' }}</dd>
            @endforeach
        </dl>
    </div>
</div>
@endif

@if ($dispute->status->isOpen())
<form method="POST" action="{{ route('super_admin.disputes.resolve', $dispute->ulid) }}" class="anim-rise ad-3 mt-4 card p-5 sm:p-6" style="border-top: 4px solid #163C68;">
    @csrf
    <h4 class="font-extrabold" style="color: #163C68;">Keputusan</h4>
    <p class="mt-1 text-sm text-slate-500">Hanya berlaku untuk mitra ini — mitra lain di tugas yang sama tidak tersentuh.</p>
    <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <label class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm cursor-pointer">
            <input type="radio" name="resolution" value="release" required @checked(old('resolution') === 'release')>
            <span class="font-bold text-emerald-800">Tolak komplain</span>
            <span class="block mt-1 text-xs text-emerald-900">Hasil mitra diterima — upah {{ $activity ? $rupiah($activity->agreed_amount) : '' }} diteruskan ke saldo mitra.</span>
        </label>
        <label class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm cursor-pointer">
            <input type="radio" name="resolution" value="refund" required @checked(old('resolution') === 'refund')>
            <span class="font-bold text-red-800">Terima komplain</span>
            <span class="block mt-1 text-xs text-red-900">Hasil mitra tidak diterima — upah {{ $activity ? $rupiah($activity->agreed_amount) : '' }} dikembalikan ke saldo pemberi kerja.</span>
        </label>
    </div>
    <label class="label mt-4 block text-sm font-bold" for="note">Keterangan untuk kedua pihak</label>
    <textarea id="note" name="note" required minlength="10" maxlength="500" rows="3" class="field mt-1" placeholder="Jam tiba tercatat 3 jam lewat jadwal dan tidak ada kabar di chat.">{{ old('note') }}</textarea>
    <p class="mt-1 text-xs text-slate-400">Dikirim lewat notifikasi ke pemberi kerja dan mitra, dan tercatat di jejak audit.</p>
    <button class="btn btn-navy w-full mt-4">Putuskan sengketa</button>
</form>
@else
<div class="anim-rise ad-3 mt-4 card p-5 sm:p-6" style="border-top: 4px solid {{ $dispute->resolution?->value === 'release' ? '#059669' : '#DC2626' }};">
    <h4 class="font-extrabold" style="color: #163C68;">{{ $dispute->resolution?->label() }}</h4>
    <p class="mt-1 text-xs text-slate-400">{{ $dispute->resolver?->name ?? 'pengelola' }} · {{ $dispute->resolved_at }}</p>
    <p class="mt-3 text-sm leading-relaxed whitespace-pre-line">{{ $dispute->admin_note ?? '—' }}</p>
</div>
@endif
@endsection
