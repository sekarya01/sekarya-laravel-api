@extends('super_admin.layout')

@section('title', 'Kode undangan mitra')
@section('header', 'Kode Undangan Mitra')

@section('content')
<div class="anim-rise flex flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
        <h3 class="text-xl font-extrabold" style="color: #163C68;">Kode undangan mitra</h3>
        <p class="mt-1 text-sm text-slate-500">Kode 8 karakter acak (huruf kecil + KAPITAL + angka + karakter unik), disimpan sebagai <span class="font-mono font-bold">hash</span> di server. Mati bila <strong>kuota habis</strong> atau <strong>tanggal lewat</strong>.</p>
    </div>
    <div class="flex gap-2 shrink-0">
        <button type="button" data-open-drawer="filterDrawer" class="btn btn-navy">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"/></svg>
            Filter
        </button>
        <button type="button" data-open-drawer="createDrawer" class="btn btn-accent">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Generate kode
        </button>
    </div>
</div>

<div class="anim-rise ad-2 mt-4 card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[760px]">
            <thead><tr class="table-head">
                <th>Kode</th><th>Catatan</th><th class="th-c">Terpakai</th><th class="th-c">Kedaluwarsa</th><th class="th-c">Status</th><th class="th-c">Aksi</th>
            </tr></thead>
            <tbody>
            @forelse ($codes as $c)
                <tr class="table-row">
                    <td>
                        <p class="font-mono font-bold" style="color: #163C68;">{{ $c->prefix }}······</p>
                        <p class="text-xs text-slate-400">oleh {{ $c->creator?->email ?? 'sistem' }} · {{ $c->created_at?->format('d M Y H:i') }}</p>
                    </td>
                    <td class="text-slate-500 text-xs"><span class="marq" title="{{ $c->note ?? '—' }}"><span class="marq-in">{{ $c->note ?? '—' }}</span></span></td>
                    <td class="td-c whitespace-nowrap font-bold" style="color: #163C68;">{{ $c->used_count }}/{{ $c->max_uses }}</td>
                    <td class="td-c whitespace-nowrap text-xs text-slate-500">{{ $c->expires_at?->format('d M Y H:i') ?? 'tanpa batas' }}</td>
                    <td class="td-c">
                        @if ($c->isUsable())
                            @include('super_admin.partials.badge', ['text' => 'bisa dipakai', 'tone' => 'green'])
                        @elseif (! $c->is_active)
                            @include('super_admin.partials.badge', ['text' => 'nonaktif', 'tone' => 'red'])
                        @elseif ($c->isDateExpired())
                            @include('super_admin.partials.badge', ['text' => 'kedaluwarsa', 'tone' => 'amber'])
                        @else
                            @include('super_admin.partials.badge', ['text' => 'kuota habis', 'tone' => 'slate'])
                        @endif
                    </td>
                    <td class="td-c">
                        <a href="{{ route('super_admin.worker_invites.show', $c->getKey()) }}" class="btn btn-navy py-2! px-4! text-xs!">Buka →</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">@include('super_admin.partials.empty', ['title' => 'Belum ada kode', 'hint' => 'Tekan Generate kode — kode 8 karakter dibuat server dan tampil sekali.'])</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('super_admin.partials.pages', ['paginator' => $codes])

{{-- Drawer filter --}}
<div id="filterDrawer" class="rdrawer" aria-hidden="true">
    <div class="rdrawer-bg" data-close-drawer></div>
    <aside class="rdrawer-panel sidebar-bg" role="dialog" aria-modal="true" aria-label="Saring kode">
        <div class="flex items-center gap-3 px-6 pt-6">
            <div class="flex-1 min-w-0">
                <h4 class="font-extrabold text-white leading-tight">Saring kode</h4>
                <p class="text-xs text-slate-300/80">Kosongkan = tampil semua</p>
            </div>
            <button class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-300 hover:text-white hover:bg-white/10 transition-all shrink-0" data-close-drawer aria-label="Tutup panel">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="GET" data-guard class="px-6 py-5 space-y-4 text-sm flex-1">
            <div>
                <label class="label-dark" for="f-usable">Status</label>
                <select id="f-usable" name="usable" class="field-dark">
                    <option value="" @selected($filterUsable === '')>Semua</option>
                    <option value="1" @selected($filterUsable === '1')>Bisa dipakai</option>
                    <option value="0" @selected($filterUsable === '0')>Mati / habis</option>
                </select>
            </div>
            <button class="btn btn-accent w-full py-3!">Terapkan</button>
        </form>
    </aside>
</div>

{{-- Drawer generate: masa hidup diisi dulu, kode dibuat server saat dikirim --}}
<div id="createDrawer" class="rdrawer" aria-hidden="true">
    <div class="rdrawer-bg" data-close-drawer></div>
    <aside class="rdrawer-panel sidebar-bg" role="dialog" aria-modal="true" aria-label="Generate kode undangan">
        <div class="flex items-center gap-3 px-6 pt-6">
            <span class="w-11 h-11 rounded-2xl flex items-center justify-center text-white text-xl font-extrabold shrink-0" style="background: linear-gradient(135deg, #F97316, #C2570B); box-shadow: 0 8px 20px -6px rgba(249,115,22,.7);">+</span>
            <div class="flex-1 min-w-0">
                <h4 class="font-extrabold text-white leading-tight">Generate kode</h4>
                <p class="text-xs text-slate-300/80">Kode 8 karakter dibuat otomatis</p>
            </div>
            <button class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-300 hover:text-white hover:bg-white/10 transition-all shrink-0" data-close-drawer aria-label="Tutup panel">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" action="{{ route('super_admin.worker_invites.store') }}" class="px-6 py-5 space-y-4 text-sm flex-1">
            @csrf
            <p class="text-xs leading-relaxed text-slate-300/90">Masa hidup dua pintu — yang tercapai lebih dulu yang menutup. Kode aslinya <strong class="text-orange-300">tampil sekali</strong> sesudah dibuat.</p>
            <div>
                <label class="label-dark" for="c-max">Jumlah max hit (berapa kali boleh dipakai)</label>
                <input id="c-max" name="max_uses" type="number" required min="1" max="100000" value="{{ old('max_uses', 10) }}" class="field-dark">
            </div>
            <div>
                <label class="label-dark" for="c-exp">Tanggal kedaluwarsa (opsional)</label>
                <input id="c-exp" name="expires_at" type="datetime-local" value="{{ old('expires_at') }}" class="field-dark">
            </div>
            <div>
                <label class="label-dark" for="c-note">Catatan (opsional)</label>
                <input id="c-note" name="note" maxlength="255" value="{{ old('note') }}" class="field-dark" placeholder="cth. Batch perekrutan Bandung">
            </div>
            <button class="btn btn-accent w-full py-3!">Generate & tampilkan kode</button>
        </form>
        <p class="px-6 pb-6 text-xs text-slate-400/70">Esc untuk menutup · klik latar untuk menutup</p>
    </aside>
</div>

<script>
    @if (session('open_modal') === 'create-invite')
        setRdrawer('createDrawer', true);
    @endif
</script>
@endsection
