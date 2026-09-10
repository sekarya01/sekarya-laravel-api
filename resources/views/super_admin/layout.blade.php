<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Super Admin') — Sekarya Console</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @else
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    @endif
    <style>
        :root {
            --brand: #163C68;
            --brand-deep: #0C2745;
            --brand-ink: #0A1E35;
            --accent: #F97316;
            --accent-hover: #EA680C;
            --accent-soft: #FFF1E4;
            --page: #F1F4F9;
        }
        html { scroll-behavior: smooth; }
        body { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif; background: var(--page); }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #C3CEDD; border-radius: 8px; border: 2px solid var(--page); }
        .no-scrollbar { scrollbar-width: none; }
        .no-scrollbar::-webkit-scrollbar { display: none; }

        /* Animations */
        @keyframes rise { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes toastIn { from { opacity: 0; transform: translateX(24px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes glowPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(249,115,22,.35); } 50% { box-shadow: 0 0 0 8px rgba(249,115,22,0); } }
        .anim-rise { animation: rise .55s cubic-bezier(.22,.8,.32,1) both; }
        .ad-1 { animation-delay: .05s; } .ad-2 { animation-delay: .12s; } .ad-3 { animation-delay: .19s; }
        .ad-4 { animation-delay: .26s; } .ad-5 { animation-delay: .33s; } .ad-6 { animation-delay: .4s; }

        /* Sidebar */
        .sidebar-bg { background: linear-gradient(178deg, var(--brand-ink) 0%, var(--brand-deep) 48%, var(--brand) 135%); }
        .navlink { display: flex; align-items: center; gap: .75rem; padding: .625rem .75rem; border-radius: .75rem;
            color: #B9C8DC; font-size: .875rem; font-weight: 500; position: relative;
            transition: all .22s ease; border: 1px solid transparent; }
        .navlink:hover { color: #fff; background: rgba(255,255,255,.08); transform: translateX(4px); }
        .navlink svg { width: 1.25rem; height: 1.25rem; flex-shrink: 0; opacity: .85; }
        .navlink-active { color: #fff !important; background: rgba(255,255,255,.12); border-color: rgba(255,255,255,.08); }
        .navlink-active::before { content: ''; position: absolute; left: -1rem; top: 20%; height: 60%; width: 4px;
            border-radius: 0 4px 4px 0; background: var(--accent); box-shadow: 0 0 12px rgba(249,115,22,.8); }
        .navlink-active svg { opacity: 1; color: var(--accent); }

        /* Mobile pills */
        .mnav { display: inline-flex; align-items: center; gap: .5rem; padding: .5rem .875rem; border-radius: 999px;
            font-size: .8125rem; font-weight: 600; color: #3D546F; background: #fff; border: 1px solid #D7E0EC;
            white-space: nowrap; transition: all .2s ease; }
        .mnav svg { width: 1rem; height: 1rem; }
        .mnav-active { background: var(--brand); border-color: var(--brand); color: #fff;
            box-shadow: 0 4px 12px -4px rgba(22,60,104,.6); }

        /* Components */
        .card { background: #fff; border-radius: 1rem; border: 1px solid #E4EAF2;
            box-shadow: 0 1px 2px rgba(16,42,76,.05), 0 8px 24px -12px rgba(16,42,76,.12);
            transition: transform .25s ease, box-shadow .25s ease; }
        .card-lift:hover { transform: translateY(-4px); box-shadow: 0 2px 4px rgba(16,42,76,.06), 0 16px 32px -12px rgba(16,42,76,.22); }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; font-weight: 600;
            font-size: .875rem; border-radius: .75rem; padding: .625rem 1rem; transition: all .2s ease; cursor: pointer; }
        .btn:active { transform: scale(.97); }
        .btn-accent { background: var(--accent); color: #fff; box-shadow: 0 6px 16px -6px rgba(249,115,22,.6); }
        .btn-accent:hover { background: var(--accent-hover); box-shadow: 0 8px 20px -6px rgba(249,115,22,.7); transform: translateY(-2px); }
        .btn-navy { background: var(--brand); color: #fff; box-shadow: 0 6px 16px -8px rgba(22,60,104,.7); }
        .btn-navy:hover { background: #1D4D86; transform: translateY(-2px); }
        .btn-success { background: #047857; color: #fff; box-shadow: 0 6px 16px -8px rgba(4,120,87,.6); }
        .btn-success:hover { background: #065F46; transform: translateY(-2px); }
        .btn-danger { background: #B91C1C; color: #fff; box-shadow: 0 6px 16px -8px rgba(185,28,28,.6); }
        .btn-danger:hover { background: #991B1B; transform: translateY(-2px); }
        .btn-warn { background: #B45309; color: #fff; }
        .btn-warn:hover { background: #92400E; transform: translateY(-2px); }
        .btn-ghost { background: #fff; color: var(--brand); border: 1px solid #D7E0EC; }
        .btn-ghost:hover { border-color: var(--brand); background: #F2F6FB; }
        .field { width: 100%; border-radius: .75rem; border: 1px solid #D7E0EC; background: #FBFCFE;
            padding: .625rem 1rem; font-size: .875rem; transition: all .2s ease; }
        .field:focus { outline: none; border-color: var(--accent); background: #fff;
            box-shadow: 0 0 0 4px rgba(249,115,22,.15); }
        .label { display: block; font-size: .75rem; font-weight: 700; color: #3D546F; margin-bottom: .5rem; }
        .table-head th { font-size: .75rem; text-transform: uppercase; letter-spacing: .08em; color: #7A8DA6;
            font-weight: 700; padding: .75rem 1.125rem; background: #F7FAFD; white-space: nowrap; text-align: left; }
        .table-row { transition: background .15s ease; }
        .table-row:hover { background: #FFF7EF; }
        .table-row td { padding: 1rem 1.125rem; border-top: 1px solid #EDF1F7; vertical-align: middle; }
        .table-head th.th-c, .table-row td.td-c { text-align: center; }
        /* Marquee halus untuk teks panjang di sel tengah: ellipsis biasa,
           berjalan saat di-hover dan hanya bila teksnya benar-benar meluber. */
        .marq { display: inline-block; max-width: 11rem; overflow: hidden; white-space: nowrap;
            vertical-align: bottom; text-overflow: ellipsis; }
        .marq-in { display: inline-block; }
        .marq.marq-go { text-overflow: clip; }
        .marq.marq-go .marq-in { animation: marqScroll 5s linear infinite; }
        @keyframes marqScroll { 0%, 12% { transform: translateX(0); } 88%, 100% { transform: translateX(calc(11rem - 100%)); } }
        .toast { animation: toastIn .35s cubic-bezier(.22,.8,.32,1) both; }
        .toast.hide { opacity: 0; transform: translateX(24px); transition: all .3s ease; }
        .stat-icon { width: 2.75rem; height: 2.75rem; border-radius: .875rem; display: flex; align-items: center;
            justify-content: center; flex-shrink: 0; }
        .stat-icon svg { width: 1.5rem; height: 1.5rem; }
        .needs-attention { animation: glowPulse 2.4s ease-in-out infinite; }
    </style>
</head>
<body class="text-slate-800 min-h-screen antialiased">
@php
    $nav = [
        ['route' => 'super_admin.dashboard', 'match' => 'super_admin.dashboard', 'label' => 'Dasbor',
         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>'],
        ['route' => 'super_admin.verifications.index', 'match' => 'super_admin.verifications.*', 'label' => 'Verifikasi',
         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>'],
        ['route' => 'super_admin.payments.index', 'match' => 'super_admin.payments.*', 'label' => 'Transfer',
         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>'],
        ['route' => 'super_admin.users.index', 'match' => 'super_admin.users.*', 'label' => 'Pengguna',
         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>'],
        ['route' => 'super_admin.admins.index', 'match' => 'super_admin.admins.*', 'label' => 'Pengelola',
         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/>'],
        ['route' => 'super_admin.audit.index', 'match' => 'super_admin.audit.*', 'label' => 'Audit',
         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>'],
    ];
@endphp
<div class="flex min-h-screen">
    <aside class="sidebar-bg w-64 shrink-0 text-white hidden lg:flex flex-col sticky top-0 h-screen">
        <div class="px-4 pt-6 pb-4">
            <div class="flex items-center gap-3 px-1">
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center font-black text-2xl text-white shrink-0"
                     style="background: linear-gradient(135deg, var(--accent), #C2570B); box-shadow: 0 8px 20px -6px rgba(249,115,22,.7);">S</div>
                <div>
                    <p class="text-xs uppercase tracking-widest text-slate-300/80">Sekarya</p>
                    <h1 class="text-lg font-bold leading-tight">Super Admin</h1>
                </div>
            </div>
            @auth('admin_web')
                <div class="mt-4 flex items-center gap-3 rounded-xl bg-white/10 border border-white/10 px-3 py-2">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0" style="background: var(--accent);">
                        {{ strtoupper(substr(auth('admin_web')->user()->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold truncate">{{ auth('admin_web')->user()->name }}</p>
                        <p class="text-xs text-slate-300/80 truncate">{{ auth('admin_web')->user()->email }}</p>
                    </div>
                </div>
            @endauth
        </div>

        <nav class="flex-1 px-4 py-2 space-y-2">
            @foreach ($nav as $item)
                <a href="{{ route($item['route']) }}"
                   class="navlink {{ request()->routeIs($item['match']) ? 'navlink-active' : '' }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">{!! $item['icon'] !!}</svg>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="p-4">
            <form method="POST" action="{{ route('super_admin.logout') }}">
                @csrf
                <button class="navlink w-full text-red-200/90! hover:bg-red-500/20! hover:text-red-100!">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                    Keluar
                </button>
            </form>
            <p class="mt-4 text-center text-xs text-slate-400/70">Sekarya Console · v1</p>
        </div>
    </aside>

    <div class="flex-1 flex flex-col min-w-0">
        <header class="sticky top-0 z-20 backdrop-blur bg-white/85 border-b border-[#E4EAF2]">
            <div class="flex items-center gap-4 px-4 sm:px-6 lg:px-8 h-16">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="lg:hidden w-10 h-10 rounded-xl flex items-center justify-center font-black text-lg text-white shrink-0"
                         style="background: linear-gradient(135deg, var(--accent), #C2570B);">S</div>
                    <div class="min-w-0">
                        <p class="text-xs uppercase tracking-widest font-bold" style="color: var(--accent);">Konsol Pengelola</p>
                        <h2 class="text-base sm:text-lg font-bold truncate" style="color: var(--brand);">@yield('header', 'Dasbor')</h2>
                    </div>
                </div>
                <div class="ml-auto flex items-center gap-2">
                    @auth('admin_web')
                        <span class="lg:hidden inline-flex items-center gap-2 text-xs font-semibold text-slate-600 bg-slate-100 rounded-full pl-1 pr-3 py-1 max-w-[10rem]">
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-[.625rem] font-bold text-white shrink-0" style="background: var(--accent);">
                                {{ strtoupper(substr(auth('admin_web')->user()->name, 0, 1)) }}
                            </span>
                            <span class="truncate">{{ auth('admin_web')->user()->name }}</span>
                        </span>
                    @endauth
                    <span class="hidden sm:inline-flex items-center gap-2 text-xs font-medium text-slate-500 bg-slate-100 rounded-full px-3 py-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        {{ now()->translatedFormat('l, d M Y') }}
                    </span>
                </div>
            </div>
            <nav class="lg:hidden px-4 pb-3 flex gap-2 overflow-x-auto no-scrollbar">
                @foreach ($nav as $item)
                    <a href="{{ route($item['route']) }}"
                       class="mnav {{ request()->routeIs($item['match']) ? 'mnav-active' : '' }}">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">{!! $item['icon'] !!}</svg>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
        </header>

        <main class="flex-1 px-4 sm:px-6 lg:px-8 py-6 max-w-6xl w-full mx-auto">
            @yield('content')
        </main>

        <footer class="px-6 pb-8 text-center text-xs text-slate-400 lg:hidden">
            <form method="POST" action="{{ route('super_admin.logout') }}" class="inline">
                @csrf
                <button class="font-semibold text-red-500 hover:underline">Keluar dari konsol</button>
            </form>
        </footer>
        <footer class="px-6 pb-6 text-center text-xs text-slate-400 hidden lg:block">
            Setiap pembacaan data sensitif tercatat di jejak audit.
        </footer>
    </div>
</div>

<div class="fixed top-4 right-4 z-50 space-y-2 w-[calc(100%-2rem)] max-w-sm">
    @if (session('status'))
        <div class="toast card border-emerald-200! flex items-start gap-3 p-4" data-toast style="border-left: 4px solid #059669;">
            <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
            </div>
            <p class="text-sm font-medium text-slate-700">{{ session('status') }}</p>
        </div>
    @endif
    @if ($errors->any())
        <div class="toast card border-red-200! p-4" data-toast style="border-left: 4px solid #DC2626;">
            <p class="text-sm font-bold text-red-800 mb-1">Perlu perhatian</p>
            <ul class="list-disc pl-5 text-sm text-red-700 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>

<script>
    document.querySelectorAll('[data-toast]').forEach(el => {
        setTimeout(() => { el.classList.add('hide'); setTimeout(() => el.remove(), 350); }, 4500);
    });
    // Marquee hanya untuk teks yang meluber; teks pendek tetap diam.
    document.addEventListener('mouseover', e => {
        const m = e.target.closest('.marq');
        if (!m || m.classList.contains('marq-go')) return;
        if (m.scrollWidth > m.clientWidth + 4) m.classList.add('marq-go');
    });
    document.addEventListener('mouseout', e => {
        const m = e.target.closest('.marq');
        if (m && !m.contains(e.relatedTarget)) m.classList.remove('marq-go');
    });
</script>
</body>
</html>
