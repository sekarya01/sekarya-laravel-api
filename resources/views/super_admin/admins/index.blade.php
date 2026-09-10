@extends('super_admin.layout')

@section('title', 'Akun pengelola')
@section('header', 'Akun Pengelola')

@section('content')
<div class="anim-rise flex flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
        <h3 class="text-xl font-extrabold" style="color: #163C68;">Akun pengelola</h3>
        <p class="mt-1 text-sm text-slate-500">Tepat satu <span class="font-mono font-bold">super_admin</span> (dijaga basis data). Peran baru selalu <span class="font-mono font-bold">admin</span>. Hapus = soft delete, email tetap terpakai.</p>
    </div>
    <button class="btn btn-accent shrink-0" data-open-create>
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Buat pengelola
    </button>
</div>

<div class="anim-rise ad-1 mt-4 card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[620px]">
            <thead><tr class="table-head">
                <th>Pengelola</th><th class="th-c">Peran</th><th class="th-c">Status</th><th class="th-c">Login terakhir</th><th class="th-c">Aksi</th>
            </tr></thead>
            <tbody>
            @forelse ($admins as $a)
                <tr class="table-row">
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-white text-xs shrink-0"
                                 style="background: {{ $a->isSuperAdmin() ? 'linear-gradient(135deg, #F97316, #C2570B)' : 'linear-gradient(135deg, #163C68, #2A5E9E)' }};">
                                {{ strtoupper(substr($a->name, 0, 1)) }}
                            </div>
                            <div class="min-w-0"><p class="font-bold">{{ $a->name }}</p><p class="text-xs text-slate-400"><span class="marq" title="{{ $a->email }}"><span class="marq-in">{{ $a->email }}</span></span></p></div>
                        </div>
                    </td>
                    <td class="td-c">@include('super_admin.partials.badge', ['text' => $a->role->value, 'tone' => $a->isSuperAdmin() ? 'orange' : 'navy'])</td>
                    <td class="td-c">@include('super_admin.partials.badge', ['text' => $a->status->value, 'tone' => $a->status->isActive() ? 'green' : 'red'])</td>
                    <td class="td-c whitespace-nowrap text-slate-500 text-xs">{{ $a->last_login_at ?? 'belum pernah' }}</td>
                    <td class="td-c">
                        @if (! $a->isSuperAdmin())
                            <form method="POST" action="{{ route('super_admin.admins.destroy', $a->ulid) }}" onsubmit="return confirm('Hapus {{ $a->email }}? Tokennya dicabut, barisnya tetap ada untuk jejak audit.')">
                                @csrf
                                @method('DELETE')
                                <button class="text-xs font-bold text-red-600 hover:text-red-800 hover:underline transition-all">Hapus</button>
                            </form>
                        @else
                            @include('super_admin.partials.badge', ['text' => 'dilindungi', 'tone' => 'slate'])
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">@include('super_admin.partials.empty', ['title' => 'Tidak ada pengelola', 'hint' => 'Buat akun admin pertama lewat tombol Buat pengelola.'])</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('super_admin.partials.pager', ['paginator' => $admins])

<div id="createDrawer" aria-hidden="true">
    <div class="drawer-bg" data-close-create></div>
    <aside id="createPanel" class="sidebar-bg" role="dialog" aria-modal="true" aria-label="Buat pengelola baru">
        <div class="flex items-center gap-3 px-6 pt-6">
            <span class="w-11 h-11 rounded-2xl flex items-center justify-center text-white text-xl font-extrabold shrink-0" style="background: linear-gradient(135deg, #F97316, #C2570B); box-shadow: 0 8px 20px -6px rgba(249,115,22,.7);">+</span>
            <div class="flex-1 min-w-0">
                <h4 class="font-extrabold text-white leading-tight">Buat pengelola baru</h4>
                <p class="text-xs text-slate-300/80">Selalu berperan admin</p>
            </div>
            <button class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-300 hover:text-white hover:bg-white/10 transition-all shrink-0" data-close-create aria-label="Tutup panel">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="px-6 py-5 overflow-y-auto flex-1">
            <p class="text-xs leading-relaxed text-slate-300/90">Sandi min. 12 karakter. Tidak ada field peran — selalu <span class="font-mono font-bold text-orange-300">admin</span>.</p>
            <form method="POST" action="{{ route('super_admin.admins.store') }}" class="mt-4 space-y-4 text-sm">
                @csrf
                <div>
                    <label class="label-dark" for="adm-name">Nama lengkap</label>
                    <input id="adm-name" name="name" required minlength="3" maxlength="100" value="{{ old('name') }}" class="field-dark" placeholder="cth. Verifikator Dua">
                </div>
                <div>
                    <label class="label-dark" for="adm-email">Email</label>
                    <input id="adm-email" name="email" type="email" required maxlength="255" value="{{ old('email') }}" class="field-dark" placeholder="verif2@sekarya.com">
                </div>
                <div>
                    <label class="label-dark" for="adm-pass">Kata sandi</label>
                    <input id="adm-pass" name="password" type="password" required minlength="12" class="field-dark" placeholder="min. 12 karakter">
                </div>
                <div>
                    <label class="label-dark" for="adm-pass2">Konfirmasi sandi</label>
                    <input id="adm-pass2" name="password_confirmation" type="password" required minlength="12" class="field-dark" placeholder="ulangi sandi">
                </div>
                <button class="btn btn-accent w-full py-3!">+ Buat admin</button>
            </form>
        </div>
        <p class="px-6 pb-6 text-xs text-slate-400/70">Esc untuk menutup · klik latar untuk menutup</p>
    </aside>
</div>

<style>
    /* Drawer kanan: tutup = geser kiri→kanan keluar, buka = geser kanan→kiri masuk. */
    #createDrawer { position: fixed; inset: 0; z-index: 50; visibility: hidden; transition: visibility 0s .45s; }
    #createDrawer.open { visibility: visible; transition-delay: 0s; }
    #createDrawer .drawer-bg { position: absolute; inset: 0; background: rgba(10, 30, 53, .6);
        backdrop-filter: blur(4px); opacity: 0; transition: opacity .35s ease; }
    #createDrawer.open .drawer-bg { opacity: 1; }
    #createPanel { position: absolute; top: 0; right: 0; height: 100%; width: 100%; max-width: 26rem;
        display: flex; flex-direction: column; transform: translateX(105%);
        transition: transform .42s cubic-bezier(.22,.8,.32,1);
        box-shadow: -24px 0 48px -16px rgba(10, 30, 53, .5); }
    #createDrawer.open #createPanel { transform: translateX(0); }
    .label-dark { display: block; font-size: .75rem; font-weight: 700; color: #B9C8DC; margin-bottom: .5rem; }
    .field-dark { width: 100%; border-radius: .75rem; border: 1px solid rgba(255,255,255,.16);
        background: rgba(255,255,255,.08); color: #fff; padding: .625rem 1rem; font-size: .875rem;
        transition: all .2s ease; }
    .field-dark::placeholder { color: rgba(255,255,255,.35); }
    .field-dark:focus { outline: none; border-color: #F97316; background: rgba(255,255,255,.12);
        box-shadow: 0 0 0 4px rgba(249,115,22,.25); }
</style>
<script>
    const createDrawer = document.getElementById('createDrawer');
    function openCreate() {
        createDrawer.classList.add('open');
        createDrawer.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('adm-name')?.focus(), 200);
    }
    function closeCreate() {
        createDrawer.classList.remove('open');
        createDrawer.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.umodal.open')) document.body.style.overflow = '';
    }
    document.querySelectorAll('[data-open-create]').forEach(b => b.addEventListener('click', openCreate));
    createDrawer.querySelectorAll('[data-close-create]').forEach(c => c.addEventListener('click', closeCreate));
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && createDrawer.classList.contains('open')) closeCreate();
    });
    @if (session('open_modal') === 'create-admin')
        openCreate();
    @endif
</script>
@endsection
