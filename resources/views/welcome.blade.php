<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sekarya — Jasa Harian Tepercaya, Tawaran Terbuka, Bayar Aman</title>
    <meta name="description" content="Sekarya mempertemukan pemberi kerja dan pekerja jasa harian: cuci AC, bersih rumah, servis, dan lainnya. Tawaran transparan, dana ditahan aman, pekerja terverifikasi.">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @else
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    @endif
    <style>
        :root { --brand: #163C68; --brand-deep: #0C2745; --brand-ink: #0A1E35; --accent: #F97316; --accent-hover: #EA680C; }
        body { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif; }
        ::selection { background: #F97316; color: #fff; }

        @keyframes floaty { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
        @keyframes drift { 0%, 100% { transform: translate(0,0) scale(1); } 50% { transform: translate(36px,-24px) scale(1.08); } }
        @keyframes riseIn { from { opacity: 0; transform: translateY(28px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes popIn { from { opacity: 0; transform: scale(.92) translateY(12px); } to { opacity: 1; transform: none; } }
        .reveal { opacity: 0; }
        .reveal.visible { animation: riseIn .7s cubic-bezier(.22,.8,.32,1) both; }
        .anim-pop { animation: popIn .5s cubic-bezier(.22,.8,.32,1) both; }

        .btn-play { display: inline-flex; align-items: center; gap: .75rem; background: #0A1E35; color: #fff;
            border-radius: 1rem; padding: .8rem 1.4rem; transition: all .25s ease;
            box-shadow: 0 16px 32px -12px rgba(10,30,53,.6); border: 1px solid rgba(255,255,255,.12); }
        .btn-play:hover { transform: translateY(-3px); box-shadow: 0 22px 40px -12px rgba(10,30,53,.7); background: #163C68; }
        .card-hover { transition: transform .3s ease, box-shadow .3s ease; }
        .card-hover:hover { transform: translateY(-6px); box-shadow: 0 24px 48px -16px rgba(22,60,104,.25); }
        details.faq summary::-webkit-details-marker { display: none; }
        details.faq summary::marker { content: ''; }
        details.faq .chev { transition: transform .3s ease; }
        details.faq[open] .chev { transform: rotate(180deg); }
        details.faq[open] { border-color: #F97316; }
    </style>
</head>
<body class="text-slate-800 antialiased bg-white">

<!-- ══════════ NAVBAR ══════════ -->
<header class="fixed top-0 inset-x-0 z-50 backdrop-blur bg-white/85 border-b border-slate-100">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center gap-3">
        <a href="#beranda" class="flex items-center gap-2.5">
            <span class="w-10 h-10 rounded-xl flex items-center justify-center font-black text-lg text-white" style="background: linear-gradient(135deg, #F97316, #C2570B);">S</span>
            <span class="text-lg font-extrabold" style="color: #163C68;">Sekarya</span>
        </a>
        <nav class="hidden md:flex items-center gap-7 ml-8 text-sm font-semibold text-slate-600">
            <a href="#cara-kerja" class="hover:text-[#163C68] transition-colors">Cara Kerja</a>
            <a href="#fitur" class="hover:text-[#163C68] transition-colors">Fitur</a>
            <a href="#keamanan" class="hover:text-[#163C68] transition-colors">Keamanan</a>
            <a href="#faq" class="hover:text-[#163C68] transition-colors">FAQ</a>
        </nav>
        <div class="ml-auto flex items-center gap-2">
            <button id="navMenuBtn" class="md:hidden inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-bold border border-slate-200" style="color: #163C68;" aria-label="Buka menu">
                Menu
                <svg id="navMenuChev" class="w-4 h-4 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
            </button>
        </div>
    </div>
    <nav id="navMobile" class="md:hidden hidden border-t border-slate-100 bg-white px-4 py-3 space-y-1 text-sm font-semibold text-slate-700">
        <a href="#cara-kerja" class="block rounded-lg px-3 py-2.5 hover:bg-slate-50">Cara Kerja</a>
        <a href="#fitur" class="block rounded-lg px-3 py-2.5 hover:bg-slate-50">Fitur</a>
        <a href="#keamanan" class="block rounded-lg px-3 py-2.5 hover:bg-slate-50">Keamanan</a>
        <a href="#faq" class="block rounded-lg px-3 py-2.5 hover:bg-slate-50">FAQ</a>
    </nav>
</header>

<!-- ══════════ HERO ══════════ -->
<section id="beranda" class="relative overflow-hidden pt-28 pb-16 sm:pt-36 sm:pb-24" style="background: linear-gradient(160deg, #0A1E35 0%, #163C68 55%, #1E4E85 100%);">
    <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full opacity-25 pointer-events-none" style="background: #F97316; filter: blur(110px); animation: drift 9s ease-in-out infinite;"></div>
    <div class="absolute -bottom-32 -left-24 w-96 h-96 rounded-full opacity-20 pointer-events-none" style="background: #3B82F6; filter: blur(120px);"></div>

    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 grid lg:grid-cols-2 gap-12 items-center">
        <div class="reveal">
            <h1 class="mt-5 text-4xl sm:text-5xl lg:text-[3.4rem] font-extrabold text-white leading-[1.08]">
                Butuh tukang?<br>Buka lelang,<br><span style="color: #F97316;">pilih yang terbaik.</span>
            </h1>
            <p class="mt-5 text-slate-300 text-base sm:text-lg leading-relaxed max-w-lg">
                Posting pekerjaan dalam semenit, terima banyak tawaran harga, dan bayar dengan aman — dana cair ke pekerja hanya setelah Anda setujui hasilnya.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <!-- TODO: ganti href dengan URL Play Store saat aplikasi rilis -->
                <a href="#" class="btn-play">
                    <svg class="w-8 h-8" viewBox="0 0 24 24" fill="#F97316"><path d="M3.6 2.3c-.3.3-.5.8-.5 1.4v16.6c0 .6.2 1.1.5 1.4l.1.1 9.3-9.3v-.2L3.7 2.2l-.1.1z"/><path d="M16.6 15.9L13 12.3l-3.1 3.1 4.7 2.7c.8.5 1.9.5 2.7 0l-.7-.2z" opacity=".9"/><path d="M16.6 8.1l.7-.2c-.8-.5-1.9-.5-2.7 0L9.9 10.6l3.1 3.1 3.6-5.6z" opacity=".9"/><path d="M20.1 10.9c.6.6.6 1.6 0 2.2l-2.1 2.1-3.6-3.2 3.6-3.2 2.1 2.1z"/></svg>
                    <span class="text-left leading-tight">
                        <span class="block text-[.65rem] uppercase tracking-widest text-slate-400 font-bold">Dapatkan di</span>
                        <span class="block text-lg font-extrabold -mt-0.5">Google Play</span>
                    </span>
                </a>
                <a href="#cara-kerja" class="inline-flex items-center gap-2 rounded-2xl px-6 py-3.5 text-sm font-bold text-white border border-white/25 hover:bg-white/10 transition-all">
                    Lihat cara kerja ↓
                </a>
            </div>
            <div class="mt-8 flex gap-7 text-white">
                <div><p class="text-2xl font-extrabold"><span data-count="9">0</span></p><p class="text-xs text-slate-400">Kategori jasa</p></div>
                <div><p class="text-2xl font-extrabold"><span data-count="42">0</span></p><p class="text-xs text-slate-400">Keahlian</p></div>
                <div><p class="text-2xl font-extrabold">2<span class="text-[#F97316]">×</span></p><p class="text-xs text-slate-400">Penilaian dua arah</p></div>
            </div>
        </div>

        <!-- Mockup aplikasi -->
        <div class="reveal hidden sm:block" style="animation-delay: .15s;">
            <div class="relative mx-auto w-[300px]" style="animation: floaty 6s ease-in-out infinite;">
                <div class="rounded-[2.2rem] p-2.5 shadow-2xl" style="background: #060f1d;">
                    <div class="rounded-[1.7rem] overflow-hidden bg-[#F1F4F9] min-h-[560px] p-4 space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="w-8 h-8 rounded-xl flex items-center justify-center font-black text-white text-sm" style="background: linear-gradient(135deg, #F97316, #C2570B);">S</span>
                            <div><p class="text-xs font-extrabold" style="color: #163C68;">Halo, Budi 👋</p><p class="text-[.65rem] text-slate-400">Cari kerja di dekatmu</p></div>
                        </div>
                        <div class="rounded-2xl p-1.5 flex gap-1.5 text-[.65rem] font-bold">
                            <span class="flex-1 text-center rounded-xl bg-white py-2 shadow-sm" style="color: #163C68;">Terdekat</span>
                            <span class="flex-1 text-center rounded-xl py-2 text-slate-400">Terbaru</span>
                            <span class="flex-1 text-center rounded-xl py-2 text-slate-400">Keahlianku</span>
                        </div>
                        <div class="rounded-2xl bg-white p-3.5 shadow-sm border border-slate-100">
                            <div class="flex items-center justify-between">
                                <span class="text-[.6rem] font-bold px-2 py-0.5 rounded-full" style="background: #E4EDF7; color: #163C68;">CUCI AC</span>
                                <span class="text-[.6rem] text-slate-400">1,2 km</span>
                            </div>
                            <p class="mt-1.5 text-sm font-extrabold" style="color: #163C68;">Cuci AC 2 unit di rumah</p>
                            <p class="text-[.65rem] text-slate-400">Rp150rb+ · Jakarta · 3 pelamar</p>
                            <div class="mt-2.5 rounded-xl text-center text-xs font-bold text-white py-2" style="background: #F97316;">Ajukan Rp220rb</div>
                        </div>
                        <div class="rounded-2xl bg-white p-3.5 shadow-sm border border-slate-100 opacity-80">
                            <div class="flex items-center justify-between">
                                <span class="text-[.6rem] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">BERSIH RUMAH</span>
                                <span class="text-[.6rem] text-slate-400">2,8 km</span>
                            </div>
                            <p class="mt-1.5 text-sm font-extrabold" style="color: #163C68;">General cleaning 3 kamar</p>
                            <p class="text-[.65rem] text-slate-400">Rp200rb+ · Jakarta · 7 pelamar</p>
                        </div>
                        <div class="rounded-2xl p-3 flex items-center gap-2.5 text-white" style="background: linear-gradient(120deg, #0A1E35, #163C68);">
                            <span class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0" style="background: #F97316;">Rp</span>
                            <div><p class="text-[.65rem] font-bold">Dana Rp270rb ditahan aman</p><p class="text-[.6rem] text-slate-400">Cair setelah Anda setujui</p></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════ CARA KERJA ══════════ -->
<section id="cara-kerja" class="py-16 sm:py-24 bg-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="reveal text-center max-w-2xl mx-auto">
            <p class="text-xs font-bold uppercase tracking-widest" style="color: #F97316;">Cara kerja</p>
            <h2 class="mt-2 text-3xl sm:text-4xl font-extrabold" style="color: #163C68;">Dari butuh sampai beres dalam 4 langkah</h2>
        </div>
        <div class="mt-10 grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="reveal card-hover rounded-2xl border border-slate-100 bg-[#F7FAFD] p-6 relative">
                <span class="absolute top-4 right-5 text-4xl font-black text-slate-200">1</span>
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-white" style="background: #163C68;"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg></div>
                <h3 class="mt-4 font-extrabold" style="color: #163C68;">Posting pekerjaan</h3>
                <p class="mt-1.5 text-sm text-slate-500 leading-relaxed">Tulis kebutuhan, tentukan budget dan lokasi. Ada harga referensi dari pekerjaan sejenis.</p>
            </div>
            <div class="reveal card-hover rounded-2xl border border-slate-100 bg-[#F7FAFD] p-6 relative" style="animation-delay: .08s;">
                <span class="absolute top-4 right-5 text-4xl font-black text-slate-200">2</span>
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-white" style="background: #F97316;"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                <h3 class="mt-4 font-extrabold" style="color: #163C68;">Bandingkan tawaran</h3>
                <p class="mt-1.5 text-sm text-slate-500 leading-relaxed">Pekerja menawar dengan harga masing-masing. Bandingkan harga, rating, dan badge KTP.</p>
            </div>
            <div class="reveal card-hover rounded-2xl border border-slate-100 bg-[#F7FAFD] p-6 relative" style="animation-delay: .16s;">
                <span class="absolute top-4 right-5 text-4xl font-black text-slate-200">3</span>
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-white" style="background: #163C68;"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg></div>
                <h3 class="mt-4 font-extrabold" style="color: #163C68;">Transfer aman</h3>
                <p class="mt-1.5 text-sm text-slate-500 leading-relaxed">Lapor transfer Anda, pengelola memverifikasi mutasi, dana ditahan. Pekerja baru mulai.</p>
            </div>
            <div class="reveal card-hover rounded-2xl border border-slate-100 bg-[#F7FAFD] p-6 relative" style="animation-delay: .24s;">
                <span class="absolute top-4 right-5 text-4xl font-black text-slate-200">4</span>
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-white" style="background: #F97316;"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg></div>
                <h3 class="mt-4 font-extrabold" style="color: #163C68;">Setujui & nilai</h3>
                <p class="mt-1.5 text-sm text-slate-500 leading-relaxed">Hasil oke? Setujui — dana cair. Beri rating dua arah yang membangun reputasi.</p>
            </div>
        </div>
    </div>
</section>

<!-- ══════════ FITUR ══════════ -->
<section id="fitur" class="py-16 sm:py-24" style="background: #F1F4F9;">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="reveal text-center max-w-2xl mx-auto">
            <p class="text-xs font-bold uppercase tracking-widest" style="color: #F97316;">Kenapa Sekarya</p>
            <h2 class="mt-2 text-3xl sm:text-4xl font-extrabold" style="color: #163C68;">Dirancang agar kedua pihak tenang</h2>
        </div>
        <div class="mt-10 grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="reveal card-hover rounded-2xl bg-white border border-slate-100 p-6">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center" style="background: #FFF1E4; color: #F97316;"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div>
                <h3 class="mt-4 font-extrabold" style="color: #163C68;">Dana ditahan (escrow)</h3>
                <p class="mt-1.5 text-sm text-slate-500 leading-relaxed">Uang Anda dipegang setelah mutasi terverifikasi, dan cair ke pekerja hanya saat hasil disetujui. Sengketa? Dana tetap aman.</p>
            </div>
            <div class="reveal card-hover rounded-2xl bg-white border border-slate-100 p-6" style="animation-delay: .08s;">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center" style="background: #E4EDF7; color: #163C68;"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg></div>
                <h3 class="mt-4 font-extrabold" style="color: #163C68;">Pekerja terverifikasi KTP</h3>
                <p class="mt-1.5 text-sm text-slate-500 leading-relaxed">Badge identitas diberikan setelah pengelola memeriksa kecocokan data — bukan klaim sepihak.</p>
            </div>
            <div class="reveal card-hover rounded-2xl bg-white border border-slate-100 p-6" style="animation-delay: .16s;">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center" style="background: #FFF1E4; color: #F97316;"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg></div>
                <h3 class="mt-4 font-extrabold" style="color: #163C68;">Lelang transparan</h3>
                <p class="mt-1.5 text-sm text-slate-500 leading-relaxed">Satu pekerjaan bisa dilamar banyak orang. Anda pilih pemenang berdasar harga dan reputasi — bukan siapa cepat dia dapat.</p>
            </div>
            <div class="reveal card-hover rounded-2xl bg-white border border-slate-100 p-6">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center" style="background: #E4EDF7; color: #163C68;"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg></div>
                <h3 class="mt-4 font-extrabold" style="color: #163C68;">Cari yang dekat</h3>
                <p class="mt-1.5 text-sm text-slate-500 leading-relaxed">Filter jarak, kota, kategori, dan keahlian. Pekerjaan sekitar Anda, pekerja sekitar mereka.</p>
            </div>
            <div class="reveal card-hover rounded-2xl bg-white border border-slate-100 p-6" style="animation-delay: .08s;">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center" style="background: #FFF1E4; color: #F97316;"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg></div>
                <h3 class="mt-4 font-extrabold" style="color: #163C68;">Rating dua arah</h3>
                <p class="mt-1.5 text-sm text-slate-500 leading-relaxed">Pekerja dinilai sebagai pekerja, pemberi kerja dinilai sebagai pemberi kerja. Reputasi yang jujur di kedua sisi.</p>
            </div>
            <div class="reveal card-hover rounded-2xl bg-white border border-slate-100 p-6" style="animation-delay: .16s;">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center" style="background: #E4EDF7; color: #163C68;"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg></div>
                <h3 class="mt-4 font-extrabold" style="color: #163C68;">Satu akun dua peran</h3>
                <p class="mt-1.5 text-sm text-slate-500 leading-relaxed">Pagi memberi kerja, sore mencari kerja. Satu akun, dua reputasi yang terpisah rapi.</p>
            </div>
        </div>
    </div>
</section>

<!-- ══════════ KEAMANAN ══════════ -->
<section id="keamanan" class="py-16 sm:py-24 text-white relative overflow-hidden" style="background: linear-gradient(140deg, #0A1E35, #163C68 70%);">
    <div class="absolute -left-20 top-10 w-72 h-72 rounded-full opacity-20 pointer-events-none" style="background: #F97316; filter: blur(90px);"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 grid lg:grid-cols-2 gap-10 items-center">
        <div class="reveal">
            <p class="text-xs font-bold uppercase tracking-widest text-orange-300">Keamanan berlapis</p>
            <h2 class="mt-2 text-3xl sm:text-4xl font-extrabold">Uang Anda tidak bergerak sebelum Anda puas</h2>
            <ul class="mt-6 space-y-4 text-sm">
                <li class="flex gap-3"><span class="w-8 h-8 rounded-full flex items-center justify-center font-bold shrink-0" style="background: rgba(249,115,22,.2); color: #FDBA74;">1</span><span class="text-slate-200"><strong class="text-white">Verifikasi email + KTP.</strong> Akun aktif hanya setelah kode email benar; badge identitas setelah pengelola memeriksa.</span></li>
                <li class="flex gap-3"><span class="w-8 h-8 rounded-full flex items-center justify-center font-bold shrink-0" style="background: rgba(249,115,22,.2); color: #FDBA74;">2</span><span class="text-slate-200"><strong class="text-white">Mutasi dicek manusia.</strong> Laporan transfer Anda dicocokkan dengan mutasi rekening oleh pengelola, bukan sekadar diakui sistem.</span></li>
                <li class="flex gap-3"><span class="w-8 h-8 rounded-full flex items-center justify-center font-bold shrink-0" style="background: rgba(249,115,22,.2); color: #FDBA74;">3</span><span class="text-slate-200"><strong class="text-white">Cair saat disetujui.</strong> Dana dilepas ke pekerja hanya ketika seluruh hasil Anda setujui. Sengketa? Dana tetap ditahan.</span></li>
            </ul>
        </div>
        <div class="reveal grid grid-cols-2 gap-4" style="animation-delay: .12s;">
            <div class="rounded-2xl bg-white/10 border border-white/15 p-5 backdrop-blur"><p class="text-3xl font-extrabold text-white">100%</p><p class="mt-1 text-xs text-slate-300">pekerjaan tercatat jejaknya</p></div>
            <div class="rounded-2xl bg-white/10 border border-white/15 p-5 backdrop-blur"><p class="text-3xl font-extrabold" style="color: #F97316;">0</p><p class="mt-1 text-xs text-slate-300">dana cair tanpa persetujuan</p></div>
            <div class="rounded-2xl bg-white/10 border border-white/15 p-5 backdrop-blur"><p class="text-3xl font-extrabold text-white">2×</p><p class="mt-1 text-xs text-slate-300">verifikasi: email & identitas</p></div>
            <div class="rounded-2xl bg-white/10 border border-white/15 p-5 backdrop-blur"><p class="text-3xl font-extrabold text-white">8<span class="text-base"> jam</span></p><p class="mt-1 text-xs text-slate-300">batas sesi, status selalu dicek ulang</p></div>
        </div>
    </div>
</section>

<!-- ══════════ TESTIMONI ══════════ -->
<section class="py-16 sm:py-24 bg-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="reveal text-center max-w-2xl mx-auto">
            <p class="text-xs font-bold uppercase tracking-widest" style="color: #F97316;">Kata mereka</p>
            <h2 class="mt-2 text-3xl sm:text-4xl font-extrabold" style="color: #163C68;">Cerita dari kedua sisi</h2>
        </div>
        <div class="mt-10 grid md:grid-cols-3 gap-4">
            <figure class="reveal card-hover rounded-2xl border border-slate-100 bg-[#F7FAFD] p-6">
                <div class="text-[#F97316] tracking-widest">★★★★★</div>
                <blockquote class="mt-3 text-sm text-slate-600 leading-relaxed">"AC dua unit beres tiga jam. Saya pilih yang ratingnya 4,9 walau bukan termurah — ternyata sepadan."</blockquote>
                <figcaption class="mt-4 flex items-center gap-3"><span class="w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-bold" style="background: #163C68;">B</span><span><span class="block text-sm font-bold" style="color: #163C68;">Budi P.</span><span class="block text-xs text-slate-400">Pemberi kerja · Jakarta</span></span></figcaption>
            </figure>
            <figure class="reveal card-hover rounded-2xl border border-slate-100 bg-[#F7FAFD] p-6" style="animation-delay: .08s;">
                <div class="text-[#F97316] tracking-widest">★★★★★</div>
                <blockquote class="mt-3 text-sm text-slate-600 leading-relaxed">"Dana ditahan dulu justru bikin saya tenang — berarti pemberi kerjanya serius. Cair tepat setelah di-approve."</blockquote>
                <figcaption class="mt-4 flex items-center gap-3"><span class="w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-bold" style="background: #F97316;">S</span><span><span class="block text-sm font-bold" style="color: #163C68;">Siti R.</span><span class="block text-xs text-slate-400">Pekerja · Bandung</span></span></figcaption>
            </figure>
            <figure class="reveal card-hover rounded-2xl border border-slate-100 bg-[#F7FAFD] p-6" style="animation-delay: .16s;">
                <div class="text-[#F97316] tracking-widest">★★★★☆</div>
                <blockquote class="mt-3 text-sm text-slate-600 leading-relaxed">"Sempet ditolak karena foto KTP blur, alasannya jelas jadi tinggal foto ulang. Sekarang badge saya hijau."</blockquote>
                <figcaption class="mt-4 flex items-center gap-3"><span class="w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-bold" style="background: #163C68;">A</span><span><span class="block text-sm font-bold" style="color: #163C68;">Agus W.</span><span class="block text-xs text-slate-400">Pekerja · Surabaya</span></span></figcaption>
            </figure>
        </div>
    </div>
</section>

<!-- ══════════ FAQ ══════════ -->
<section id="faq" class="py-16 sm:py-24" style="background: #F1F4F9;">
    <div class="max-w-3xl mx-auto px-4 sm:px-6">
        <div class="reveal text-center">
            <p class="text-xs font-bold uppercase tracking-widest" style="color: #F97316;">FAQ</p>
            <h2 class="mt-2 text-3xl sm:text-4xl font-extrabold" style="color: #163C68;">Sering ditanyakan</h2>
        </div>
        <div class="mt-8 space-y-3">
            <details class="faq reveal rounded-2xl bg-white border border-slate-200 px-5 py-4 transition-colors">
                <summary class="flex items-center justify-between gap-3 cursor-pointer font-bold text-sm list-none" style="color: #163C68;">Apakah Sekarya gratis diunduh?<svg class="chev w-5 h-5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg></summary>
                <p class="mt-2 text-sm text-slate-500 leading-relaxed">Ya, aplikasi gratis diunduh dan akun gratis dibuat. Anda hanya membayar jasa yang disepakati — tanpa biaya tersembunyi.</p>
            </details>
            <details class="faq reveal rounded-2xl bg-white border border-slate-200 px-5 py-4 transition-colors">
                <summary class="flex items-center justify-between gap-3 cursor-pointer font-bold text-sm list-none" style="color: #163C68;">Bagaimana uang saya aman?<svg class="chev w-5 h-5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg></summary>
                <p class="mt-2 text-sm text-slate-500 leading-relaxed">Setelah Anda lapor transfer, pengelola mencocokkan mutasi lalu menahan dana. Pekerja mulai bekerja, dan dana cair hanya setelah Anda menyetujui hasilnya.</p>
            </details>
            <details class="faq reveal rounded-2xl bg-white border border-slate-100 px-5 py-4 transition-colors">
                <summary class="flex items-center justify-between gap-3 cursor-pointer font-bold text-sm list-none" style="color: #163C68;">Bagaimana cara jadi pekerja?<svg class="chev w-5 h-5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg></summary>
                <p class="mt-2 text-sm text-slate-500 leading-relaxed">Daftar, verifikasi email, lengkapi profil pekerja (nama, kontak, wilayah, keahlian), lalu ajukan verifikasi KTP. Setelah disetujui, badge siap-kerja menyala dan Anda bisa menawar pekerjaan.</p>
            </details>
            <details class="faq reveal rounded-2xl bg-white border border-slate-100 px-5 py-4 transition-colors">
                <summary class="flex items-center justify-between gap-3 cursor-pointer font-bold text-sm list-none" style="color: #163C68;">Bolehkah satu pekerjaan untuk banyak orang?<svg class="chev w-5 h-5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg></summary>
                <p class="mt-2 text-sm text-slate-500 leading-relaxed">Bisa. Tentukan berapa orang dibutuhkan — lelang tetap terbuka untuk semua pelamar, dan Anda memilih pemenang berdasar tawaran mereka.</p>
            </details>
            <details class="faq reveal rounded-2xl bg-white border border-slate-100 px-5 py-4 transition-colors">
                <summary class="flex items-center justify-between gap-3 cursor-pointer font-bold text-sm list-none" style="color: #163C68;">Bagaimana jika hasil tidak memuaskan?<svg class="chev w-5 h-5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg></summary>
                <p class="mt-2 text-sm text-slate-500 leading-relaxed">Tolak hasilnya — dana tetap ditahan dan status menjadi sengketa untuk ditengahi. Jangan setujui pekerjaan yang belum beres.</p>
            </details>
        </div>
    </div>
</section>

<!-- ══════════ CTA DOWNLOAD ══════════ -->
<section class="py-16 sm:py-24 bg-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="reveal relative overflow-hidden rounded-3xl p-8 sm:p-14 text-center text-white" style="background: linear-gradient(130deg, #0A1E35 0%, #163C68 60%, #1E4E85 100%);">
            <div class="absolute -top-20 left-1/3 w-80 h-80 rounded-full opacity-25 pointer-events-none" style="background: #F97316; filter: blur(100px);"></div>
            <h2 class="relative text-3xl sm:text-4xl font-extrabold">Siap kerja atau memberi kerja<br class="hidden sm:block"> hari ini juga?</h2>
            <p class="relative mt-3 text-slate-300 max-w-xl mx-auto">Download Sekarya di Android — satu akun untuk kedua peran. Gratis.</p>
            <div class="relative mt-8 flex justify-center">
                <!-- TODO: ganti href dengan URL Play Store saat aplikasi rilis -->
                <a href="#" class="btn-play !px-8 !py-4">
                    <svg class="w-9 h-9" viewBox="0 0 24 24" fill="#F97316"><path d="M3.6 2.3c-.3.3-.5.8-.5 1.4v16.6c0 .6.2 1.1.5 1.4l.1.1 9.3-9.3v-.2L3.7 2.2l-.1.1z"/><path d="M16.6 15.9L13 12.3l-3.1 3.1 4.7 2.7c.8.5 1.9.5 2.7 0l-.7-.2z" opacity=".9"/><path d="M16.6 8.1l.7-.2c-.8-.5-1.9-.5-2.7 0L9.9 10.6l3.1 3.1 3.6-5.6z" opacity=".9"/><path d="M20.1 10.9c.6.6.6 1.6 0 2.2l-2.1 2.1-3.6-3.2 3.6-3.2 2.1 2.1z"/></svg>
                    <span class="text-left leading-tight">
                        <span class="block text-[.65rem] uppercase tracking-widest text-slate-400 font-bold">Dapatkan di</span>
                        <span class="block text-xl font-extrabold -mt-0.5">Google Play</span>
                    </span>
                </a>
            </div>
            <p class="relative mt-4 text-xs text-slate-400">Versi iOS & Web menyusul.</p>
        </div>
    </div>
</section>

<!-- ══════════ FOOTER ══════════ -->
<footer class="text-slate-400 text-sm" style="background: #0A1E35;">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-10 flex flex-col sm:flex-row gap-4 items-center justify-between">
        <div class="flex items-center gap-2.5">
            <span class="w-9 h-9 rounded-xl flex items-center justify-center font-black text-white" style="background: linear-gradient(135deg, #F97316, #C2570B);">S</span>
            <div>
                <p class="text-base font-extrabold text-white leading-tight">Sekarya</p>
                <p class="text-xs">Marketplace jasa harian Indonesia.</p>
            </div>
        </div>
        <p class="text-xs">© 2026 Sekarya. Seluruh hak cipta dilindungi.</p>
    </div>
</footer>

<script>
    // Reveal saat scroll.
    const io = new IntersectionObserver(es => es.forEach(e => {
        if (e.isIntersecting) { e.target.classList.add('visible'); io.unobserve(e.target); }
    }), { threshold: .12 });
    document.querySelectorAll('.reveal').forEach(el => io.observe(el));

    // Angka statistik menghitung naik.
    const cio = new IntersectionObserver(es => es.forEach(e => {
        if (!e.isIntersecting) return;
        const el = e.target, target = +el.dataset.count, t0 = performance.now();
        const tick = t => {
            const p = Math.min((t - t0) / 1200, 1);
            el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
            if (p < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
        cio.unobserve(el);
    }), { threshold: .5 });
    document.querySelectorAll('[data-count]').forEach(el => cio.observe(el));

    // Menu mobile (teks + chevron, tanpa hamburger).
    const navBtn = document.getElementById('navMenuBtn');
    const navMobile = document.getElementById('navMobile');
    const navChev = document.getElementById('navMenuChev');
    navBtn?.addEventListener('click', () => {
        const open = navMobile.classList.toggle('hidden');
        navChev.style.transform = open ? '' : 'rotate(180deg)';
    });
    navMobile?.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
        navMobile.classList.add('hidden');
        navChev.style.transform = '';
    }));
</script>
</body>
</html>
