@extends('super_admin.layout')

@section('title', 'Detail kode undangan')
@section('header', 'Detail Kode Undangan')

@section('content')
<div class="anim-rise">
    <a href="{{ route('super_admin.worker_invites.index') }}" class="text-sm font-bold hover:underline" style="color: #163C68;">← Kembali ke daftar kode</a>
</div>

{{-- Kode tampil terus — dibagikan ke calon mitra kapan saja --}}
<div class="anim-rise ad-1 mt-4 card overflow-hidden" style="border-left: 4px solid #F97316;">
    <div class="p-5 sm:p-6">
        <p class="text-xs uppercase tracking-widest font-bold" style="color: #C2570B;">Kode undangan — bagikan ke calon mitra</p>
        <div class="mt-3 flex flex-wrap items-center gap-3">
            <code id="plainCode" class="font-mono text-2xl sm:text-3xl font-extrabold tracking-widest px-4 py-2 rounded-xl" style="background: #FFF1E4; color: #0A1E35;">{{ $code->displayCode() }}</code>
            @if ($code->code_plain)
            <button type="button" id="copyCode" class="btn btn-navy text-xs!">Salin kode</button>
            @endif
        </div>
        @if ($code->isArchived())
        <p class="mt-3 text-xs leading-relaxed rounded-xl p-3" style="background: #FFF6E0; color: #92400E;">Kode ini dibuat sebelum sistem menyimpan kode aslinya — isinya <strong>tak bisa dipulihkan</strong> (yang tersimpan hanya hash). Nonaktifkan di bawah lalu tekan <a href="{{ route('super_admin.worker_invites.index') }}" class="font-bold underline">Generate kode</a> untuk penggantinya.</p>
        @else
        <p class="mt-2 text-xs text-slate-500">Kode ini tampil terus di sini. Kalau bocor ke publik, nonaktifkan di bawah lalu terbitkan yang baru.</p>
        @endif
    </div>
</div>
<script>
    document.getElementById('copyCode')?.addEventListener('click', async (e) => {
        const code = document.getElementById('plainCode')?.textContent?.trim() ?? '';
        try { await navigator.clipboard.writeText(code); } catch (_) {
            const ta = document.createElement('textarea');
            ta.value = code; document.body.appendChild(ta); ta.select();
            document.execCommand('copy'); ta.remove();
        }
        const btn = e.currentTarget;
        btn.textContent = 'Tersalin ✓';
        setTimeout(() => { btn.textContent = 'Salin kode'; }, 2000);
    });
</script>

<div class="anim-rise ad-2 mt-4 card p-5 sm:p-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs uppercase tracking-widest font-bold text-slate-400">Kode {{ $code->displayCode() }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $code->note ?? 'Tanpa catatan' }} · {{ $code->areaLabel() }} · dibuat {{ $code->created_at?->format('d M Y H:i') }} oleh {{ $code->creator?->email ?? 'sistem' }}</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @if ($code->isUsable())
                    @include('super_admin.partials.badge', ['text' => 'bisa dipakai', 'tone' => 'green'])
                @elseif (! $code->is_active)
                    @include('super_admin.partials.badge', ['text' => 'nonaktif', 'tone' => 'red'])
                @elseif ($code->isDateExpired())
                    @include('super_admin.partials.badge', ['text' => 'kedaluwarsa', 'tone' => 'amber'])
                @else
                    @include('super_admin.partials.badge', ['text' => 'kuota habis', 'tone' => 'slate'])
                @endif
            </div>
        </div>
        @if ($code->is_active)
        <form method="POST" action="{{ route('super_admin.worker_invites.deactivate', $code->getKey()) }}" onsubmit="return confirm('Nonaktifkan kode ini? Pekerja yang sudah masuk lewat kode ini tetap mitra.')">
            @csrf
            <button class="btn btn-danger text-xs!">Nonaktifkan kode</button>
        </form>
        @endif
    </div>
    <div class="mt-4 grid gap-3 sm:grid-cols-3 text-sm">
        <div class="rounded-xl p-4" style="background: #F7FAFD;">
            <p class="text-xs uppercase tracking-wider font-bold text-slate-400">Terpakai</p>
            <p class="text-2xl font-extrabold" style="color: #163C68;">{{ $code->used_count }}/{{ $code->max_uses }}</p>
        </div>
        <div class="rounded-xl p-4" style="background: #F7FAFD;">
            <p class="text-xs uppercase tracking-wider font-bold text-slate-400">Kedaluwarsa</p>
            <p class="text-2xl font-extrabold" style="color: #163C68;">{{ $code->expires_at?->format('d M Y') ?? '—' }}</p>
            <p class="text-xs text-slate-400">{{ $code->expires_at?->format('H:i') ?? 'tanpa batas tanggal' }}</p>
        </div>
        <div class="rounded-xl p-4" style="background: #F7FAFD;">
            <p class="text-xs uppercase tracking-wider font-bold text-slate-400">Pekerja masuk</p>
            <p class="text-2xl font-extrabold" style="color: #163C68;">{{ $code->redemptions_count }}</p>
        </div>
    </div>
</div>

<div class="anim-rise ad-3 mt-4 card overflow-hidden">
    <div class="px-5 sm:px-6 pt-5">
        <h3 class="font-extrabold text-lg" style="color: #163C68;">Pekerja yang memakai kode ini</h3>
        <p class="text-xs text-slate-400">Verifikasi identitasnya di antrean verifikasi — kode hanya membuka pintu, bukan menyatakan lolos.</p>
    </div>
    <div class="mt-3 overflow-x-auto">
        <table class="w-full text-sm min-w-[680px]">
            <thead><tr class="table-head">
                <th>Pekerja</th><th>Kontak</th><th class="th-c">KTP</th><th class="th-c">Waktu pakai</th><th class="th-c">Aksi</th>
            </tr></thead>
            <tbody>
            @forelse ($redemptions as $r)
                @php $u = $r->user; @endphp
                <tr class="table-row">
                    <td class="font-bold">{{ $u?->name ?? 'akun dihapus' }}</td>
                    <td class="text-xs text-slate-500"><span class="marq" title="{{ $u?->email }} · {{ $u?->phone }}"><span class="marq-in">{{ $u?->email }} · {{ $u?->phone }}</span></span></td>
                    <td class="td-c">
                        @if (($u?->identity_verified_count ?? 0) > 0)
                            @include('super_admin.partials.badge', ['text' => 'terverifikasi', 'tone' => 'green'])
                        @else
                            @include('super_admin.partials.badge', ['text' => 'belum', 'tone' => 'amber'])
                        @endif
                    </td>
                    <td class="td-c whitespace-nowrap text-xs text-slate-500">{{ $r->created_at?->format('d M Y H:i') }}</td>
                    <td class="td-c">
                        @if ($u?->workerProfile)
                            <a href="{{ route('super_admin.workers.show', $u->workerProfile) }}" class="text-xs font-bold hover:underline" style="color: #163C68;">Verifikasi →</a>
                        @else
                            <span class="text-xs text-slate-400">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">@include('super_admin.partials.empty', ['title' => 'Belum ada yang memakai', 'hint' => 'Bagikan kode 8 karakternya ke calon mitra.'])</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('super_admin.partials.pages', ['paginator' => $redemptions])
@endsection
