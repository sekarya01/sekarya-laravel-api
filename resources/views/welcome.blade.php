<!DOCTYPE html>
<html class="scroll-smooth" lang="id"><script>document.documentElement.classList.add("js")</script>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sekarya — Butuh bantuan harian? Pasang tugas, pilih mitra terpercaya.</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        navy: '#163C68',
        'navy-dark': '#0A1E35',
        'navy-hover': '#0F2C4D',
        orange: '#F97316',
        'orange-text': '#EA580C',
        success: '#10B981',
        'success-ink': '#047857',
        'success-tint': '#E7F8F1',
        canvas: '#F8F9FF',
        tint: '#EFF4FF',
        'tint-line': '#DCE9FF',
        warm: '#FFF3EA',
        'warm-line': '#FED7AA',
        'warm-ink': '#C2410C',
        ink: '#0B1C30',
        'ink-2': '#43474F',
        muted: '#64748B',
        line: '#E2E8F0',
        outline: '#C3C6D0'
      },
      fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
      borderRadius: { card: '1rem', panel: '0.75rem', hero: '1.5rem' },
      boxShadow: {
        card: '0 4px 16px rgba(22,60,104,0.05)',
        raised: '0 8px 24px -4px rgba(22,60,104,0.10)',
        cta: '0 8px 20px -4px rgba(249,115,22,0.35)',
        header: '0 4px 12px rgba(22,60,104,0.15)'
      }
    }
  }
}
</script>
<style>
  html,body{margin:0;padding:0}
  body{overscroll-behavior:none;-webkit-font-smoothing:antialiased}
  .msr{font-family:'Material Symbols Rounded';font-weight:normal;font-style:normal;line-height:1;letter-spacing:normal;text-transform:none;display:inline-block;white-space:nowrap;word-wrap:normal;direction:ltr;font-feature-settings:'liga';-webkit-font-feature-settings:'liga';font-variation-settings:'FILL' 0,'wght' 500,'GRAD' 0,'opsz' 24}
  ::-webkit-scrollbar{display:none}
  summary{list-style:none}
  summary::-webkit-details-marker{display:none}
  #faq .faq-item:not([open]):hover{border-color:#C3C6D0}
  #faq .faq-item[open]{border-left-width:4px;border-left-color:#F97316;background:#F8F9FF;padding-left:calc(1.5rem - 3px)}
  details[open] .chev{transform:rotate(180deg)}
  .chev{transition:transform .2s ease}
  .guide-radio{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
  .guide-panel{display:none}
  .guide-btn{color:#43474F;font-weight:500;cursor:pointer}
  .guide-btn:hover{color:#163C68}
  #tab-seeker:checked ~ div .guide-btn[for="tab-seeker"],
  #tab-mitra:checked ~ div .guide-btn[for="tab-mitra"]{background:#163C68;color:#fff;font-weight:600}
  #tab-seeker:checked ~ .guide-seeker{display:block}
  #tab-mitra:checked ~ .guide-mitra{display:block}
  .glow{position:absolute;border-radius:9999px;pointer-events:none;filter:blur(120px)}
  .nav-link{position:relative;font-size:14px;font-weight:600;color:#43474F;transition:color .2s ease}
  .nav-link:hover{color:#163C68}
  .nav-link.active{color:#163C68;font-weight:800}
  .nav-link::after{content:"";position:absolute;left:0;right:0;bottom:-6px;height:2px;border-radius:9999px;background:#F97316;transform:scaleX(0);transform-origin:left;transition:transform .28s cubic-bezier(.4,0,.2,1)}
  .nav-link:hover::after,.nav-link.active::after{transform:scaleX(1)}

  /* 1. reveal on scroll — hanya aktif bila JS aktif (.js di <html>) */
  .js .reveal{opacity:0;transform:translateY(24px);transition:opacity .7s cubic-bezier(.22,1,.36,1),transform .7s cubic-bezier(.22,1,.36,1);will-change:opacity,transform}
  .js .reveal.in{opacity:1;transform:none}
  .js .reveal-x{opacity:0;transform:translateX(-28px);transition:opacity .75s cubic-bezier(.22,1,.36,1),transform .75s cubic-bezier(.22,1,.36,1)}
  .js .reveal-x.in{opacity:1;transform:none}
  .js .reveal-phone{opacity:0;transform:translateY(34px) scale(.97);transition:opacity .9s cubic-bezier(.22,1,.36,1),transform .9s cubic-bezier(.22,1,.36,1)}
  .js .reveal-phone.in{opacity:1;transform:none}

  /* 2. hero stagger + floating pills */
  .js .hero-el{opacity:0;transform:translateY(18px);animation:heroIn .8s cubic-bezier(.22,1,.36,1) forwards}
  @keyframes heroIn{to{opacity:1;transform:none}}
  .float-a{animation:floatA 6s ease-in-out infinite}
  .float-b{animation:floatA 7.5s ease-in-out infinite .8s}
  @keyframes floatA{0%,100%{transform:translateY(0)}50%{transform:translateY(-9px)}}

  /* 4. mockup hidup */
  .dot-pulse{animation:dotPulse 1.9s ease-in-out infinite}
  @keyframes dotPulse{0%,100%{box-shadow:0 0 0 0 rgba(249,115,22,.55)}70%{box-shadow:0 0 0 7px rgba(249,115,22,0)}}
  .skel{background:linear-gradient(90deg,#EFF4FF 25%,#F8F9FF 37%,#EFF4FF 63%);background-size:400% 100%;animation:skel 1.4s ease infinite;border-radius:.5rem}
  @keyframes skel{0%{background-position:100% 0}100%{background-position:0 0}}
  /* skeleton overlay: tampil lalu fade-out saat masuk viewport */
  .js .mock-skel{opacity:1;transition:opacity .55s ease}
  .js .mock-live.ready .mock-skel{opacity:0;pointer-events:none}
  .mock-skel{position:absolute;inset:0;z-index:30;background:#fff;padding:1.25rem}

  /* 5. cara kerja line draw */
  .step-line{position:absolute;top:24px;height:2px;background:repeating-linear-gradient(90deg,#C3C6D0 0 6px,transparent 6px 12px);transform:scaleX(0);transform-origin:left;transition:transform 1s cubic-bezier(.4,0,.2,1)}
  .step-line.in{transform:scaleX(1)}

  /* 6. faq smooth expand */
  .faq-body{display:grid;grid-template-rows:0fr;transition:grid-template-rows .34s cubic-bezier(.4,0,.2,1),opacity .3s ease;opacity:0}
  details[open] .faq-body{grid-template-rows:1fr;opacity:1}
  .faq-body>div{overflow:hidden}

  /* 7. hover */
  .lift{transition:transform .28s cubic-bezier(.22,1,.36,1),box-shadow .28s ease}
  .lift:hover{transform:translateY(-4px);box-shadow:0 14px 32px -6px rgba(22,60,104,.16)}
  .press{transition:transform .18s ease,filter .2s ease,background-color .2s ease}
  .press:hover{transform:translateY(-1px)}
  .press:active{transform:scale(.975)}
  .cta-glow:hover{filter:brightness(1.06);box-shadow:0 12px 28px -6px rgba(249,115,22,.5)}
  .guide-btn{transition:background-color .25s ease,color .25s ease}

  @media (prefers-reduced-motion:reduce){
    .reveal,.reveal-x,.reveal-phone{opacity:1!important;transform:none!important;transition:none!important}
    .hero-el{animation:none;opacity:1;transform:none}
    .float-a,.float-b,.dot-pulse,.skel{animation:none}
    .step-line{transform:scaleX(1);transition:none}
    *{scroll-behavior:auto!important}
  }
</style>
</head>
<body class="bg-canvas font-sans text-ink antialiased">

<!-- ============ NAV ============ -->
<header id="nav" class="sticky top-0 left-0 w-full z-50 bg-white/95 backdrop-blur-md border-b border-line transition-shadow">
  <div class="h-20 max-w-7xl mx-auto px-6 flex items-center justify-between gap-6">
    <a href="#top" class="flex items-center" aria-label="Sekarya">
      <img src="{{ asset('images/sekarya-wordmark.svg') }}" alt="Sekarya" class="h-8 w-auto">
    </a>
    <nav class="hidden lg:flex items-center gap-8">
      <a class="nav-link" href="#tentang">Tentang</a>
      <a class="nav-link" href="#cara-kerja">Cara Kerja</a>
      <a class="nav-link" href="#fitur">Fitur</a>
      <a class="nav-link" href="#mitra">Jadi Mitra</a>
      <a class="nav-link" href="#panduan">Panduan</a>
      <a class="nav-link" href="#faq">FAQ</a>
    </nav>
  </div>
</header>

<main id="top" class="w-full overflow-x-hidden">

<!-- ============ HERO ============ -->
<section class="relative w-full bg-navy text-white overflow-hidden">
  <div class="glow -top-32 -left-32 w-[520px] h-[520px] bg-navy/50"></div>
  <div class="glow top-1/3 -right-24 w-[560px] h-[560px] bg-navy/40"></div>
  <div class="relative w-full max-w-7xl mx-auto px-6 py-20 lg:py-28 grid grid-cols-1 lg:grid-cols-12 gap-16 items-center">
    <div class="lg:col-span-7 flex flex-col items-start min-w-0">
      <span class="hero-el inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/10 border border-white/15 backdrop-blur-md mb-8" style="animation-delay:.05s">
        <span class="w-2 h-2 rounded-full bg-success animate-pulse"></span>
        <span class="msr text-[18px] text-success">android</span>
        <span class="text-xs font-semibold text-slate-200 tracking-wide">Kini di Android</span>
      </span>
      <h1 class="hero-el text-4xl sm:text-5xl lg:text-[54px] lg:leading-[1.12] font-extrabold text-white tracking-tight mb-6 max-w-xl" style="animation-delay:.15s">Butuh bantuan harian? Pasang tugas, pilih mitra terpercaya.</h1>
      <p class="hero-el text-lg text-slate-300 max-w-lg mb-9 leading-relaxed" style="animation-delay:.25s">Dana kamu ditahan aman dan baru cair setelah kamu setujui hasilnya.</p>
      <div class="hero-el flex flex-wrap items-center gap-4 mb-8" style="animation-delay:.35s">
        <a href="#download" class="group inline-flex items-center justify-center gap-3.5 w-[220px] h-[52px] rounded-full bg-orange text-white shadow-cta hover:bg-orange-text active:scale-[0.98] transition-all">
          <svg class="w-6 h-6 shrink-0" viewBox="30 336.7 120.9 129.2" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path fill="#FFC400" d="M119.2 421.2c15.3-8.4 27-14.8 28-15.3 3.2-1.7 6.5-6.2 0-9.7-2.1-1.1-13.4-7.3-28-15.3l-20.1 20.2 20.1 20.1z"/><path fill="#FF3A44" d="M99.1 401.1l-64.2 64.7c1.5.2 3.2-.2 5.2-1.3 4.2-2.3 48.8-26.7 79.1-43.3l-20.1-20.1z"/><path fill="#00E676" d="M99.1 401.1l20.1-20.2c0 0-74.6-40.7-79.1-43.1-1.7-1-3.6-1.3-5.3-1l64.3 64.3z"/><path fill="#00A0FF" d="M99.1 401.1l-64.3-64.3c-2.6.6-4.8 2.9-4.8 7.6v113.8c0 4.3 1.7 7.4 4.9 7.7l64.2-64.8z"/></svg>
          <span class="flex flex-col text-left leading-tight">
            <span class="text-[10px] uppercase tracking-wider opacity-90 font-semibold">Temukan di</span>
            <span class="text-base font-extrabold">Google Play</span>
          </span>
          <span class="msr text-[20px] ml-1 group-hover:translate-x-0.5 transition-transform">arrow_forward</span>
        </a>
        <div class="inline-flex items-center justify-center gap-3 w-[220px] h-[52px] rounded-full bg-white/5 border border-white/10 text-slate-400 cursor-not-allowed">
          <span class="msr text-[22px]">smartphone</span>
          <span class="flex flex-col text-left leading-tight">
            <span class="text-[10px] uppercase tracking-wider font-semibold text-slate-400">App Store</span>
            <span class="text-sm font-semibold text-slate-300">Segera hadir</span>
          </span>
        </div>
      </div>
      <div class="hero-el flex items-center gap-2.5 text-slate-300 text-sm" style="animation-delay:.45s">
        <span class="msr text-[18px] text-success">verified_user</span>
        <span>100% bebas biaya pendaftaran mitra &amp; pembuat tugas.</span>
      </div>
    </div>

    <!-- phone mockup -->
    <div class="lg:col-span-5 relative flex justify-center items-center">
      <div class="float-a absolute -top-4 -left-4 z-30 p-3.5 rounded-hero bg-white text-ink shadow-raised border border-line flex items-center gap-3">
        <span class="w-10 h-10 rounded-panel bg-tint text-navy flex items-center justify-center shrink-0"><span class="msr text-[22px]">shield</span></span>
        <span class="flex flex-col text-left leading-tight">
          <span class="text-xs font-bold text-ink">Dana ditahan aman</span>
          <span class="text-[11px] text-muted">Sekarya Escrow</span>
        </span>
      </div>
      <div class="float-b absolute top-[76%] -right-4 z-30 p-3 rounded-hero bg-white text-ink shadow-raised border border-line flex items-center gap-3">
        <span class="w-10 h-10 rounded-panel bg-success-tint text-success-ink flex items-center justify-center shrink-0"><span class="msr text-[22px]">verified_user</span></span>
        <span class="flex flex-col text-left leading-tight">
          <span class="text-xs font-bold text-ink">Mitra terverifikasi KTP</span>
          <span class="text-[10px] text-success-ink font-bold uppercase tracking-wider">Diperiksa pengelola</span>
        </span>
      </div>
      <div class="float-a absolute -bottom-3 -left-2 z-30 p-3 rounded-hero bg-white text-ink shadow-raised border border-line flex items-center gap-3">
        <span class="relative flex -space-x-2 shrink-0">
          <span class="w-8 h-8 rounded-full bg-navy text-white ring-2 ring-white flex items-center justify-center text-[10px] font-bold">AR</span>
          <span class="w-8 h-8 rounded-full bg-tint text-navy ring-2 ring-white flex items-center justify-center text-[10px] font-bold">DP</span>
          <span class="w-8 h-8 rounded-full bg-navy text-white ring-2 ring-white flex items-center justify-center text-[10px] font-bold">+1</span>
        </span>
        <span class="flex flex-col text-left leading-tight">
          <span class="text-xs font-bold text-ink">3 mitra melamar</span>
          <span class="text-[11px] text-muted">Tugas baru tayang</span>
        </span>
      </div>

      <div class="hero-el relative w-[300px] sm:w-[370px] lg:w-[400px] rounded-[48px] p-3 bg-navy-dark border-[4px] border-navy/60 shadow-raised z-20" style="animation-delay:.3s">
        <div class="w-full rounded-[38px] bg-white overflow-hidden flex flex-col text-ink h-[620px] sm:h-[720px] lg:h-[760px] mock-live relative"><div class="mock-skel"><div class="skel h-3 w-16 mb-4"></div><div class="skel h-4 w-32 mb-2"></div><div class="skel h-3 w-24 mb-5"></div><div class="skel h-24 w-full mb-3"></div><div class="skel h-3 w-20 mb-2"></div><div class="skel h-16 w-full"></div></div>
          <div class="bg-navy h-14 px-4 flex items-center justify-between shrink-0">
            <img src="{{ asset('images/sekarya-wordmark-white.svg') }}" alt="Sekarya" class="h-5 w-auto">
            <div class="flex items-center gap-1">
              <span class="inline-flex items-center gap-1 bg-white/12 rounded-full pl-2 pr-2.5 py-1"><span class="msr text-[13px] text-orange-text">location_on</span><span class="text-[8px] font-semibold text-white">Bandung</span></span>
              <span class="relative flex items-center justify-center w-11 h-11 text-white"><span class="msr text-[20px]">notifications</span><span class="absolute top-2.5 right-2.5 w-2 h-2 rounded-full bg-orange"></span></span>
            </div>
          </div>
          <div class="bg-canvas px-4 pt-4 pb-5 flex-1 overflow-hidden">
            <div class="flex items-center gap-2">
              <h2 class="text-[18px] leading-[25px] font-bold text-ink flex-1 tracking-[-0.015em]">Butuh bantuan apa hari ini?</h2>
              <span class="inline-flex items-center gap-1 bg-tint text-navy rounded-full h-7 px-3 shadow-card shrink-0"><span class="msr text-[13px]">info</span><span class="text-[10px] font-semibold">Cara kerja</span></span>
            </div>
            <p class="text-[11px] leading-[18px] text-ink-2 mt-2">Ceritakan yang sulit kamu kerjakan — Kami bantu selesaikan.</p>

            <div class="mt-4 rounded-[24px] bg-navy p-5 text-white shadow-raised">
              <div class="flex items-center gap-2">
                <span class="msr text-[16px] text-white/80">account_balance_wallet</span>
                <span class="text-[11px] font-semibold flex-1">Saldo SekaryaPay</span>
                <span class="inline-flex items-center gap-1.5 bg-success/15 rounded-full px-2.5 h-6"><span class="w-1.5 h-1.5 rounded-full bg-success"></span><span class="text-[8px] font-bold text-success">Aktif &amp; Aman</span></span>
              </div>
              <div class="text-[26px] leading-[33px] font-extrabold tracking-[-0.02em] mt-2" data-count="850000" data-prefix="Rp">Rp850.000</div>
              <div class="flex gap-3 mt-4">
                <span class="flex-1 h-11 rounded-full bg-orange text-white text-[11px] font-bold flex items-center justify-center gap-1.5"><span class="msr text-[15px]">add</span>Isi Saldo</span>
                <span class="flex-1 h-11 rounded-full bg-white/12 border border-white/20 text-white text-[11px] font-bold flex items-center justify-center gap-1.5"><span class="msr text-[15px]">history</span>Riwayat</span>
              </div>
            </div>

            <div class="flex items-end justify-between mt-6">
              <h3 class="text-[15px] leading-[21px] font-bold text-ink">Sedang berjalan</h3>
              <span class="text-[11px] font-semibold text-orange-text">Lihat semua</span>
            </div>
            <div class="mt-3 bg-white rounded-2xl border border-line shadow-card p-4">
              <div class="flex items-center justify-between gap-2">
                <span class="inline-flex items-center gap-1.5 bg-tint text-navy text-[10px] font-semibold rounded-full h-7 px-3 whitespace-nowrap"><span class="msr text-[13px]">plumbing</span>Pipa &amp; Air</span>
                <span class="inline-flex items-center gap-1.5 bg-warm text-warm-ink text-[10px] font-bold rounded-full h-7 px-3 whitespace-nowrap"><span class="w-1.5 h-1.5 rounded-full bg-current"></span>Sedang Dikerjakan</span>
              </div>
              <h4 class="text-[13px] leading-[18px] font-bold text-ink mt-3">Perbaikan Pipa Wastafel Bocor</h4>
              <div class="flex items-center gap-2 text-[11px] text-ink-2 mt-2"><span class="msr text-[13px] text-muted">location_on</span>Jl. R.E. Martadinata, Bandung</div>
              <div class="flex items-center gap-2 text-[11px] text-ink-2 mt-1"><span class="msr text-[13px] text-muted">schedule</span>Estimasi selesai 14:30 WIB</div>
              <div class="bg-tint rounded-xl p-3 flex items-center justify-between mt-3">
                <div class="flex items-center gap-2.5">
                  <span class="w-9 h-9 rounded-full bg-tint-line text-navy font-extrabold text-[11px] flex items-center justify-center">AS</span>
                  <div><div class="text-[11px] font-bold text-ink">Asep Sunandar</div><div class="text-[10px] text-ink-2">★ 4.9 · Tiba 15 mnt lagi</div></div>
                </div>
                <div class="text-right"><div class="text-[9px] font-medium text-muted">Biaya Jasa</div><div class="text-[14px] font-extrabold text-navy">Rp150.000</div></div>
              </div>
              <div class="flex justify-end mt-3"><span class="text-[11px] font-bold text-orange-text inline-flex items-center">Pantau Progres<span class="msr text-[15px]">chevron_right</span></span></div>
            </div>

            <h3 class="text-[15px] leading-[21px] font-bold text-ink mt-6">Mulai cepat</h3>
            <p class="text-[11px] leading-[18px] text-ink-2 mt-1">Pilih yang paling mirip, detailnya bisa kamu ubah.</p>
            <div class="grid grid-cols-3 gap-3 mt-3">
              <div class="bg-white border border-line rounded-2xl shadow-card p-3 flex flex-col items-center"><span class="w-11 h-11 rounded-xl bg-tint text-navy flex items-center justify-center mb-2"><span class="msr text-[16px]">cleaning_services</span></span><span class="text-[10px] font-bold text-ink-2 leading-tight text-center min-h-[2rem] flex items-center">Bersih Rumah</span></div>
              <div class="bg-white border border-line rounded-2xl shadow-card p-3 flex flex-col items-center"><span class="w-11 h-11 rounded-xl bg-tint text-navy flex items-center justify-center mb-2"><span class="msr text-[16px]">plumbing</span></span><span class="text-[10px] font-bold text-ink-2 leading-tight text-center min-h-[2rem] flex items-center">Tukang &amp; Pipa</span></div>
              <div class="bg-white border border-line rounded-2xl shadow-card p-3 flex flex-col items-center"><span class="w-11 h-11 rounded-xl bg-tint text-navy flex items-center justify-center mb-2"><span class="msr text-[16px]">local_shipping</span></span><span class="text-[10px] font-bold text-ink-2 leading-tight text-center min-h-[2rem] flex items-center">Pindahan</span></div>
              <div class="bg-white border border-line rounded-2xl shadow-card p-3 flex flex-col items-center"><span class="w-11 h-11 rounded-xl bg-tint text-navy flex items-center justify-center mb-2"><span class="msr text-[16px]">yard</span></span><span class="text-[10px] font-bold text-ink-2 leading-tight text-center min-h-[2rem] flex items-center">Taman Kebun</span></div>
              <div class="bg-white border border-line rounded-2xl shadow-card p-3 flex flex-col items-center"><span class="w-11 h-11 rounded-xl bg-tint text-navy flex items-center justify-center mb-2"><span class="msr text-[16px]">local_laundry_service</span></span><span class="text-[10px] font-bold text-ink-2 leading-tight text-center min-h-[2rem] flex items-center">Cuci Setrika</span></div>
              <div class="bg-white border border-line rounded-2xl shadow-card p-3 flex flex-col items-center"><span class="w-11 h-11 rounded-xl bg-tint text-navy flex items-center justify-center mb-2"><span class="msr text-[16px]">grid_view</span></span><span class="text-[10px] font-bold text-ink-2 leading-tight text-center min-h-[2rem] flex items-center">Lainnya</span></div>
            </div>
          </div>
          <div class="w-full h-14 bg-white border-t border-line px-6 flex items-center justify-around text-muted shrink-0">
            <span class="flex flex-col items-center text-navy"><span class="msr text-[18px]">home</span><span class="text-[8px] font-bold">Beranda</span></span>
            <span class="flex flex-col items-center"><span class="msr text-[18px]">assignment</span><span class="text-[8px] font-medium">Tugas</span></span>
            <span class="flex flex-col items-center"><span class="msr text-[18px]">person</span><span class="text-[8px] font-medium">Akun</span></span>
          </div>
          <div class="w-full h-2.5 bg-white flex justify-center items-center pb-1"><span class="w-20 h-1 rounded-full bg-line"></span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ TENTANG ============ -->
<section id="tentang" class="w-full bg-canvas py-24 px-6 border-b border-line">
  <div class="max-w-7xl mx-auto">
    <div class="text-center max-w-2xl mx-auto mb-16 reveal">
      <span class="text-xs font-bold uppercase tracking-wider text-navy bg-tint px-3 py-1 rounded-full inline-block mb-3">Satu Aplikasi, Dua Peran</span>
      <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight text-ink">Mau dibantu atau mau bekerja, semuanya di Sekarya.</h2>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-20 reveal">
      <div class="bg-white rounded-hero border border-line p-8 sm:p-10 lift shadow-card flex flex-col justify-between">
        <div class="flex flex-col flex-1">
          <span class="w-12 h-12 rounded-hero bg-tint text-navy flex items-center justify-center mb-6 shrink-0"><span class="msr text-[26px]">search</span></span>
          <h3 class="text-2xl font-bold text-ink mb-3 leading-tight">Cari Bantuan</h3>
          <p class="text-ink-2 text-sm leading-relaxed mb-6">Pasang tugas, lalu pilih mitra terverifikasi dari rating dan badge KTP-nya. Bayar aman langsung dari saldo.</p>
          <ul class="space-y-3.5 mb-8">
            <li class="flex items-center gap-3 text-sm text-ink-2 font-medium"><span class="msr text-[20px] text-success shrink-0">check_circle</span> Pasang tugas dalam semenit</li>
            <li class="flex items-center gap-3 text-sm text-ink-2 font-medium"><span class="msr text-[20px] text-success shrink-0">check_circle</span> Pilih mitra dari rating &amp; badge KTP</li>
            <li class="flex items-center gap-3 text-sm text-ink-2 font-medium"><span class="msr text-[20px] text-success shrink-0">check_circle</span> Bayar aman dari saldo</li>
          </ul>
        </div>
        <div class="border border-tint-line rounded-panel bg-tint p-4">
          <div class="flex items-center justify-between mb-2">
            <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-orange"></span><span class="text-xs font-bold text-ink-2 uppercase tracking-wider">Tugas Aktif</span></span>
            <span class="px-2.5 py-0.5 rounded-full bg-white text-navy text-[10px] font-bold border border-tint-line">3 penawaran masuk</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-sm font-bold text-ink">Cuci Toren Air</span>
            <span class="text-sm font-extrabold text-navy">Rp 120.000</span>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-hero border border-line p-8 sm:p-10 lift shadow-card flex flex-col justify-between">
        <div class="flex flex-col flex-1">
          <span class="w-12 h-12 rounded-hero bg-tint text-navy flex items-center justify-center mb-6 shrink-0"><span class="msr text-[26px]">handyman</span></span>
          <h3 class="text-2xl font-bold text-ink mb-3 leading-tight">Cari Kerja</h3>
          <p class="text-ink-2 text-sm leading-relaxed mb-6">Temukan tugas di kotamu, lamar dengan satu ketukan, dan upah cair ke saldo begitu tugas kamu disetujui.</p>
          <ul class="space-y-3.5 mb-8">
            <li class="flex items-center gap-3 text-sm text-ink-2 font-medium"><span class="msr text-[20px] text-success shrink-0">check_circle</span> Temukan kerjaan di kotamu</li>
            <li class="flex items-center gap-3 text-sm text-ink-2 font-medium"><span class="msr text-[20px] text-success shrink-0">check_circle</span> Lamar dengan satu ketukan</li>
            <li class="flex items-center gap-3 text-sm text-ink-2 font-medium"><span class="msr text-[20px] text-success shrink-0">check_circle</span> Upah cair ke saldo setelah disetujui</li>
          </ul>
        </div>
        <div class="border border-tint-line rounded-panel bg-tint p-4">
          <div class="flex items-center justify-between mb-2">
            <span class="flex items-center gap-1.5 text-navy"><span class="msr text-[16px]">assignment</span><span class="text-xs font-bold uppercase tracking-wider">Tugas Terbaru</span></span>
            <span class="px-2.5 py-0.5 rounded-full bg-white text-muted text-[10px] font-bold border border-tint-line">Depok</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-sm font-bold text-ink">Perbaikan Instalasi Listrik</span>
            <span class="text-sm font-extrabold text-navy">Rp 150.000</span>
          </div>
        </div>
      </div>
    </div>

    <div>
      <div class="text-center mb-10">
        <h3 class="font-bold text-xl md:text-2xl text-ink mb-2">Kategori Jasa Terpopuler</h3>
        <p class="text-ink-2 text-sm">Beragam kebutuhan tugas harian, dari laundry sampai pindahan.</p>
      </div>
      <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 md:gap-4 reveal">
        <div class="bg-white border border-line rounded-hero p-4 flex flex-col items-center justify-center text-center shadow-card hover:-translate-y-0.5 hover:border-navy/40 transition-all cursor-pointer group"><span class="w-11 h-11 rounded-panel bg-tint text-navy flex items-center justify-center mb-2.5 group-hover:bg-navy group-hover:text-white transition-colors"><span class="msr text-[20px]">local_laundry_service</span></span><span class="text-xs font-semibold text-ink-2 leading-tight flex items-center justify-center min-h-[2rem]">Mencuci</span></div>
        <div class="bg-white border border-line rounded-hero p-4 flex flex-col items-center justify-center text-center shadow-card hover:-translate-y-0.5 hover:border-navy/40 transition-all cursor-pointer group"><span class="w-11 h-11 rounded-panel bg-tint text-navy flex items-center justify-center mb-2.5 group-hover:bg-navy group-hover:text-white transition-colors"><span class="msr text-[20px]">cleaning_services</span></span><span class="text-xs font-semibold text-ink-2 leading-tight flex items-center justify-center min-h-[2rem]">Membersihkan Rumah</span></div>
        <div class="bg-white border border-line rounded-hero p-4 flex flex-col items-center justify-center text-center shadow-card hover:-translate-y-0.5 hover:border-navy/40 transition-all cursor-pointer group"><span class="w-11 h-11 rounded-panel bg-tint text-navy flex items-center justify-center mb-2.5 group-hover:bg-navy group-hover:text-white transition-colors"><span class="msr text-[20px]">pets</span></span><span class="text-xs font-semibold text-ink-2 leading-tight flex items-center justify-center min-h-[2rem]">Menjaga Hewan</span></div>
        <div class="bg-white border border-line rounded-hero p-4 flex flex-col items-center justify-center text-center shadow-card hover:-translate-y-0.5 hover:border-navy/40 transition-all cursor-pointer group"><span class="w-11 h-11 rounded-panel bg-tint text-navy flex items-center justify-center mb-2.5 group-hover:bg-navy group-hover:text-white transition-colors"><span class="msr text-[20px]">local_shipping</span></span><span class="text-xs font-semibold text-ink-2 leading-tight flex items-center justify-center min-h-[2rem]">Mengantarkan Barang</span></div>
        <div class="bg-white border border-line rounded-hero p-4 flex flex-col items-center justify-center text-center shadow-card hover:-translate-y-0.5 hover:border-navy/40 transition-all cursor-pointer group"><span class="w-11 h-11 rounded-panel bg-tint text-navy flex items-center justify-center mb-2.5 group-hover:bg-navy group-hover:text-white transition-colors"><span class="msr text-[20px]">build</span></span><span class="text-xs font-semibold text-ink-2 leading-tight flex items-center justify-center min-h-[2rem]">Tukang &amp; Perbaikan</span></div>
        <div class="bg-white border border-line rounded-hero p-4 flex flex-col items-center justify-center text-center shadow-card hover:-translate-y-0.5 hover:border-navy/40 transition-all cursor-pointer group"><span class="w-11 h-11 rounded-panel bg-tint text-navy flex items-center justify-center mb-2.5 group-hover:bg-navy group-hover:text-white transition-colors"><span class="msr text-[20px]">yard</span></span><span class="text-xs font-semibold text-ink-2 leading-tight flex items-center justify-center min-h-[2rem]">Berkebun</span></div>
        <div class="bg-white border border-line rounded-hero p-4 flex flex-col items-center justify-center text-center shadow-card hover:-translate-y-0.5 hover:border-navy/40 transition-all cursor-pointer group"><span class="w-11 h-11 rounded-panel bg-tint text-navy flex items-center justify-center mb-2.5 group-hover:bg-navy group-hover:text-white transition-colors"><span class="msr text-[20px]">inventory_2</span></span><span class="text-xs font-semibold text-ink-2 leading-tight flex items-center justify-center min-h-[2rem]">Bantu Pindahan</span></div>
        <div class="bg-white border border-line rounded-hero p-4 flex flex-col items-center justify-center text-center shadow-card hover:-translate-y-0.5 hover:border-navy/40 transition-all cursor-pointer group"><span class="w-11 h-11 rounded-panel bg-tint text-navy flex items-center justify-center mb-2.5 group-hover:bg-navy group-hover:text-white transition-colors"><span class="msr text-[20px]">grid_view</span></span><span class="text-xs font-semibold text-ink-2 leading-tight flex items-center justify-center min-h-[2rem]">Lainnya</span></div>
      </div>
    </div>
  </div>
</section>

<!-- ============ CARA KERJA ============ -->
<section id="cara-kerja" class="w-full bg-white py-24 px-6 border-b border-line">
  <div class="max-w-7xl mx-auto">
    <div class="text-center max-w-2xl mx-auto mb-16 reveal">
      <span class="text-xs font-bold uppercase tracking-wider text-navy bg-tint px-3 py-1 rounded-full inline-block mb-3">Proses Mudah &amp; Transparan</span>
      <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight text-ink">Dari butuh sampai beres dalam 5 langkah</h2>
    </div>

    <div class="relative flex flex-col md:flex-row items-start justify-between gap-8 md:gap-4 mb-20 reveal">
      <span class="step-line hidden md:block absolute top-7 left-10 right-10"></span>
      <div class="relative z-10 flex flex-col items-center md:items-start text-center md:text-left flex-1">
        <div class="relative mb-5"><span class="w-14 h-14 rounded-hero bg-tint border border-tint-line flex items-center justify-center text-navy"><span class="msr text-[24px]">account_balance_wallet</span></span><span class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-orange text-white text-[11px] font-bold flex items-center justify-center">1</span></div>
        <h4 class="text-base font-bold text-ink mb-1.5">Isi saldo</h4>
        <p class="text-xs text-ink-2 leading-relaxed">Isi saldo SekaryaPay lewat transfer bank atau e-wallet.</p>
      </div>
      <div class="relative z-10 flex flex-col items-center md:items-start text-center md:text-left flex-1">
        <div class="relative mb-5"><span class="w-14 h-14 rounded-hero bg-tint border border-tint-line flex items-center justify-center text-navy"><span class="msr text-[24px]">assignment</span></span><span class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-orange text-white text-[11px] font-bold flex items-center justify-center">2</span></div>
        <h4 class="text-base font-bold text-ink mb-1.5">Pasang tugas</h4>
        <p class="text-xs text-ink-2 leading-relaxed">Tulis detail pekerjaan, lokasi, jadwal, dan budget kamu.</p>
      </div>
      <div class="relative z-10 flex flex-col items-center md:items-start text-center md:text-left flex-1">
        <div class="relative mb-5"><span class="w-14 h-14 rounded-hero bg-tint border border-tint-line flex items-center justify-center text-navy"><span class="msr text-[24px]">handshake</span></span><span class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-orange text-white text-[11px] font-bold flex items-center justify-center">3</span></div>
        <h4 class="text-base font-bold text-ink mb-1.5">Pilih mitra</h4>
        <p class="text-xs text-ink-2 leading-relaxed">Pilih mitra dari rating, ulasan, dan badge KTP-nya.</p>
      </div>
      <div class="relative z-10 flex flex-col items-center md:items-start text-center md:text-left flex-1">
        <div class="relative mb-5"><span class="w-14 h-14 rounded-hero bg-tint border border-tint-line flex items-center justify-center text-navy"><span class="msr text-[24px]">add_a_photo</span></span><span class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-orange text-white text-[11px] font-bold flex items-center justify-center">4</span></div>
        <h4 class="text-base font-bold text-ink mb-1.5">Mitra bekerja &amp; kirim foto bukti</h4>
        <p class="text-xs text-ink-2 leading-relaxed">Pekerjaan dituntaskan, mitra mengunggah foto buktinya.</p>
      </div>
      <div class="relative z-10 flex flex-col items-center md:items-start text-center md:text-left flex-1">
        <div class="relative mb-5"><span class="w-14 h-14 rounded-hero bg-tint border border-tint-line flex items-center justify-center text-navy"><span class="msr text-[24px]">verified</span></span><span class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-orange text-white text-[11px] font-bold flex items-center justify-center">5</span></div>
        <h4 class="text-base font-bold text-ink mb-1.5">Setujui &amp; beri nilai</h4>
        <p class="text-xs text-ink-2 leading-relaxed">Konfirmasi puas, dana diteruskan ke mitra, lalu beri ulasan.</p>
      </div>
    </div>

    <div class="relative bg-navy rounded-hero p-8 md:p-12 reveal text-white overflow-hidden shadow-raised border border-white/10">
      <span class="glow -top-24 -right-24 w-80 h-80 bg-navy/60"></span>
      <div class="relative z-10">
        <div class="max-w-2xl mb-10">
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-success/10 text-success text-xs font-semibold uppercase tracking-wider mb-4 border border-success/20"><span class="w-2 h-2 rounded-full bg-success animate-pulse"></span> Sekarya Escrow</span>
          <h3 class="text-2xl md:text-3xl font-extrabold tracking-tight leading-snug">Uang kamu tidak bergerak sebelum kamu puas</h3>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
          <div class="lg:col-span-8 grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="flex flex-col">
              <span class="w-11 h-11 rounded-panel bg-white/10 border border-white/10 flex items-center justify-center text-success mb-3.5"><span class="msr text-[22px]">shield</span></span>
              <h4 class="text-sm font-bold mb-1.5">Dana ditahan</h4>
              <p class="text-xs text-slate-300 leading-relaxed">Budget dipotong dari saldo dan ditahan sampai kamu setujui.</p>
            </div>
            <div class="flex flex-col">
              <span class="w-11 h-11 rounded-panel bg-white/10 border border-white/10 flex items-center justify-center text-success mb-3.5"><span class="msr text-[22px]">badge</span></span>
              <h4 class="text-sm font-bold mb-1.5">Mitra terverifikasi</h4>
              <p class="text-xs text-slate-300 leading-relaxed">Email + KTP diperiksa pengelola sebelum dapat mengambil tugas.</p>
            </div>
            <div class="flex flex-col">
              <span class="w-11 h-11 rounded-panel bg-white/10 border border-white/10 flex items-center justify-center text-success mb-3.5"><span class="msr text-[22px]">gavel</span></span>
              <h4 class="text-sm font-bold mb-1.5">Sengketa ditengahi</h4>
              <p class="text-xs text-slate-300 leading-relaxed">Tolak hasil bila tidak sesuai; dana tetap aman dan admin menengahi.</p>
            </div>
          </div>
          <div class="lg:col-span-4">
            <div class="rounded-hero p-6 bg-success-tint border border-success/30 text-ink">
              <div class="flex items-center gap-3 mb-4">
                <span class="w-12 h-12 rounded-panel bg-white border border-success/30 flex items-center justify-center text-success-ink"><span class="msr text-[26px]">verified_user</span></span>
                <div>
                  <div class="text-sm font-bold leading-tight">Dana Aman</div>
                  <span class="text-[10px] uppercase tracking-wider text-success-ink font-extrabold">Terlindungi</span>
                </div>
              </div>
              <p class="text-xs text-ink-2 mb-4 leading-relaxed">Dana terlindungi escrow otomatis. Diteruskan ke mitra hanya setelah konfirmasi puas dari kamu.</p>
              <div class="flex items-center gap-2 pt-3 border-t border-success/20 text-success-ink text-xs font-semibold"><span class="msr text-[16px]">check_circle</span><span>Dijamin sampai tugas selesai disetujui</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ FITUR ============ -->
<section id="fitur" class="w-full bg-canvas py-24 px-6 border-b border-line">
  <div class="max-w-7xl mx-auto">
    <div class="text-center max-w-2xl mx-auto mb-16 reveal">
      <span class="text-xs font-bold uppercase tracking-wider text-navy bg-tint px-3 py-1 rounded-full inline-block mb-3">Fitur Unggulan</span>
      <h2 class="text-3xl md:text-4xl font-extrabold text-ink tracking-tight mb-4">Dirancang praktis, transparan, dan terpercaya.</h2>
      <p class="text-ink-2 text-sm">Nikmati kemudahan mengelola tugas harian dan mencari kerja, dengan fitur yang menjaga uang dan reputasimu.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 reveal">
      <!-- large: pantau pengerjaan -->
      <div class="lg:col-span-2 bg-white border border-line rounded-hero p-8 lift shadow-card flex flex-col justify-between">
        <div class="flex flex-col mb-6">
          <span class="w-12 h-12 rounded-hero bg-tint text-navy flex items-center justify-center mb-5"><span class="msr text-[26px]">directions_run</span></span>
          <h3 class="text-2xl font-bold text-ink mb-2">Pantau pengerjaan</h3>
          <p class="text-ink-2 text-sm leading-relaxed">Lihat status tugas dan foto bukti pekerjaan secara langsung, tanpa rasa cemas.</p>
        </div>
        <div class="border border-tint-line rounded-panel p-4 bg-tint">
          <div class="flex flex-col space-y-3 mb-4">
            <div class="flex items-center gap-3">
              <span class="w-6 h-6 rounded-full bg-success text-white flex items-center justify-center shrink-0"><span class="msr text-[14px]">check</span></span>
              <div class="flex items-center justify-between flex-1"><span class="text-xs font-semibold text-ink">Menuju lokasi</span><span class="text-[10px] text-muted">09:15 WIB</span></div>
            </div>
            <div class="flex items-center gap-3">
              <span class="w-6 h-6 rounded-full bg-success text-white flex items-center justify-center shrink-0"><span class="msr text-[14px]">check</span></span>
              <div class="flex items-center justify-between flex-1"><span class="text-xs font-semibold text-ink">Sudah sampai</span><span class="text-[10px] text-muted">09:30 WIB</span></div>
            </div>
            <div class="flex items-center gap-3">
              <span class="w-6 h-6 rounded-full bg-navy text-white flex items-center justify-center shrink-0 animate-pulse"><span class="msr text-[14px]">play_arrow</span></span>
              <div class="flex items-center justify-between flex-1"><span class="text-xs font-bold text-navy">Sedang dikerjakan</span><span class="px-1.5 py-0.5 rounded bg-white text-navy text-[9px] font-bold border border-tint-line">Aktif</span></div>
            </div>
            <div class="flex items-center gap-3">
              <span class="w-6 h-6 rounded-full bg-white border border-tint-line text-muted flex items-center justify-center shrink-0"><span class="msr text-[14px]">hourglass_top</span></span>
              <div class="flex items-center justify-between flex-1"><span class="text-xs font-medium text-muted">Selesai</span><span class="text-[10px] text-muted">Menunggu</span></div>
            </div>
          </div>
          <div class="pt-3 border-t border-tint-line flex items-center justify-between">
            <span class="text-xs font-bold text-ink flex items-center gap-1.5"><span class="msr text-[16px] text-navy">photo_camera</span> 3 Foto Bukti</span>
            <div class="flex items-center gap-2">
              <span class="w-8 h-8 rounded-lg bg-white border border-line flex items-center justify-center text-muted text-[10px] font-bold">B1</span>
              <span class="w-8 h-8 rounded-lg bg-white border border-line flex items-center justify-center text-muted text-[10px] font-bold">B2</span>
              <span class="w-8 h-8 rounded-lg bg-white border border-line flex items-center justify-center text-muted text-[10px] font-bold">B3</span>
            </div>
          </div>
        </div>
      </div>

      <!-- large: harga referensi -->
      <div class="lg:col-span-2 bg-white border border-line rounded-hero p-8 lift shadow-card flex flex-col justify-between">
        <div class="flex flex-col mb-6">
          <span class="w-12 h-12 rounded-hero bg-tint text-navy flex items-center justify-center mb-5"><span class="msr text-[26px]">payments</span></span>
          <h3 class="text-2xl font-bold text-ink mb-2">Harga referensi</h3>
          <p class="text-ink-2 text-sm leading-relaxed">Panduan tarif wajar dari riwayat pasar, supaya budget tugasmu pas dan adil.</p>
        </div>
        <div class="border border-tint-line rounded-panel p-4 bg-tint">
          <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-bold text-ink uppercase tracking-wide">Budget Tugas</span>
            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-white text-success-ink text-[10px] font-bold border border-tint-line"><span class="w-1.5 h-1.5 rounded-full bg-success"></span> Sesuai Standar</span>
          </div>
          <div class="flex items-center justify-between bg-white border border-line rounded-panel px-3.5 py-2.5 mb-3">
            <span class="text-sm font-extrabold text-ink">Rp 100.000</span>
            <span class="msr text-[18px] text-navy">edit</span>
          </div>
          <div class="mb-3 flex items-center gap-1.5 text-muted text-xs"><span class="msr text-[15px] text-navy">auto_awesome</span><span>Biasanya Rp80rb – Rp150rb untuk Mencuci</span></div>
          <div class="space-y-1.5">
            <div class="w-full bg-tint-line rounded-full h-2 relative">
              <span class="block bg-orange h-2 rounded-full" style="width:45%"></span>
              <span class="absolute top-1/2 -mt-2 w-4 h-4 rounded-full bg-navy border-2 border-white shadow-card" style="left:calc(45% - 8px)"></span>
            </div>
            <div class="flex justify-between text-[10px] text-muted font-medium">
              <span>Min Rp80rb</span><span class="font-bold text-navy">Rata-rata Rp115rb</span><span>Maks Rp150rb</span>
            </div>
          </div>
        </div>
      </div>

      <!-- small: chat -->
      <div class="bg-white border border-line rounded-hero p-6 lift shadow-card flex flex-col justify-between">
        <div class="mb-4">
          <span class="w-10 h-10 rounded-panel bg-tint text-navy flex items-center justify-center mb-3"><span class="msr text-[20px]">message</span></span>
          <h4 class="text-base font-bold text-ink mb-1 leading-snug">Chat per tugas</h4>
          <p class="text-xs text-muted leading-relaxed">Koordinasi detail langsung tanpa sebar nomor pribadi.</p>
        </div>
        <div class="border border-tint-line rounded-panel p-3 bg-tint space-y-2 mt-auto">
          <div class="bg-white border border-line rounded-lg rounded-tl-none p-2 max-w-[85%]"><p class="text-[10px] text-ink-2 leading-tight">Halo kak, alat pembersih sudah disiapkan?</p></div>
          <div class="bg-navy text-white rounded-lg rounded-tr-none p-2 max-w-[85%] ml-auto text-right"><p class="text-[10px] leading-tight">Sudah siap Mas, silakan ke lokasi.</p></div>
        </div>
      </div>

      <!-- small: notifikasi -->
      <div class="bg-white border border-line rounded-hero p-6 lift shadow-card flex flex-col justify-between">
        <div class="mb-4">
          <span class="w-10 h-10 rounded-panel bg-tint text-navy flex items-center justify-center mb-3"><span class="msr text-[20px]">notifications</span></span>
          <h4 class="text-base font-bold text-ink mb-1 leading-snug">Notifikasi real-time</h4>
          <p class="text-xs text-muted leading-relaxed">Update penawaran dan status pekerjaan langsung seketika.</p>
        </div>
        <div class="border border-tint-line rounded-panel p-3 bg-tint mt-auto">
          <div class="flex items-start gap-2">
            <span class="w-2 h-2 rounded-full bg-success mt-1 shrink-0 animate-pulse"></span>
            <div class="flex-1">
              <div class="flex items-center justify-between"><span class="text-[11px] font-bold text-ink">Penawaran baru</span><span class="text-[9px] text-muted">Baru saja</span></div>
              <p class="text-[10px] text-ink-2 leading-tight mt-0.5">Seorang mitra melamar tugas "Cuci Toren Air".</p>
            </div>
          </div>
        </div>
      </div>

      <!-- small: rating -->
      <div class="bg-white border border-line rounded-hero p-6 lift shadow-card flex flex-col justify-between">
        <div class="mb-4">
          <span class="w-10 h-10 rounded-panel bg-tint text-navy flex items-center justify-center mb-3"><span class="msr text-[20px]">star</span></span>
          <h4 class="text-base font-bold text-ink mb-1 leading-snug">Rating dua arah</h4>
          <p class="text-xs text-muted leading-relaxed">Reputasi adil bagi pembuat tugas dan mitra.</p>
        </div>
        <div class="border border-tint-line rounded-panel p-3 bg-tint mt-auto">
          <div class="flex items-center justify-between mb-1.5">
            <span class="flex items-center gap-0.5 text-amber-500">
              <span class="msr text-[15px]">star</span><span class="msr text-[15px]">star</span><span class="msr text-[15px]">star</span><span class="msr text-[15px]">star</span><span class="msr text-[15px]">star</span>
            </span>
            <span class="text-xs font-extrabold text-ink">4.9 / 5.0</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="px-1.5 py-0.5 rounded bg-white text-navy text-[9px] font-bold border border-tint-line">Mitra Andal</span>
            <span class="text-[9px] text-success-ink font-bold">Tepat waktu</span>
          </div>
        </div>
      </div>

      <!-- small: tarik saldo -->
      <div class="bg-white border border-line rounded-hero p-6 lift shadow-card flex flex-col justify-between">
        <div class="mb-4">
          <span class="w-10 h-10 rounded-panel bg-tint text-navy flex items-center justify-center mb-3"><span class="msr text-[20px]">account_balance_wallet</span></span>
          <h4 class="text-base font-bold text-ink mb-1 leading-snug">Tarik saldo ke rekening</h4>
          <p class="text-xs text-muted leading-relaxed">Cairkan upah ke rekening bank atau e-wallet kamu.</p>
        </div>
        <div class="border border-tint-line rounded-panel p-3 bg-tint mt-auto">
          <div class="flex items-center justify-between mb-2">
            <div><span class="text-[9px] text-muted block leading-none">Saldo Tersedia</span><span class="text-xs font-extrabold text-ink">Rp 450.000</span></div>
            <span class="px-2.5 py-1 rounded-full bg-navy text-white text-[10px] font-bold">Tarik</span>
          </div>
          <div class="flex items-center gap-1 text-success-ink text-[9px] font-semibold"><span class="msr text-[12px]">check_circle</span><span>Ke rekening atas namamu</span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ JADI MITRA ============ -->
<section id="mitra" class="w-full bg-white py-24 px-6 border-b border-line">
  <div class="max-w-7xl mx-auto">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-center">
      <div class="lg:col-span-7 flex flex-col items-start min-w-0 reveal-x">
        <span class="text-xs font-bold uppercase tracking-wider text-navy bg-tint px-3 py-1 rounded-full mb-3">Untuk Pekerja</span>
        <h2 class="text-3xl md:text-4xl font-extrabold text-ink tracking-tight mb-4">Dapat kerjaan di kotamu</h2>
        <p class="text-ink-2 text-base mb-8 leading-relaxed">Gunakan keahlianmu untuk penghasilan harian tambahan, fleksibel dan transparan tanpa biaya pendaftaran.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8 w-full">
          <div class="p-4 rounded-hero bg-canvas border border-line flex items-center gap-3 text-sm font-semibold text-ink-2"><span class="w-9 h-9 rounded-panel bg-tint text-navy flex items-center justify-center shrink-0"><span class="msr text-[20px]">touch_app</span></span><span>Lamar tugas dengan satu ketukan</span></div>
          <div class="p-4 rounded-hero bg-canvas border border-line flex items-center gap-3 text-sm font-semibold text-ink-2"><span class="w-9 h-9 rounded-panel bg-tint text-navy flex items-center justify-center shrink-0"><span class="msr text-[20px]">account_balance_wallet</span></span><span>Upah pasti cair setelah disetujui</span></div>
          <div class="p-4 rounded-hero bg-canvas border border-line flex items-center gap-3 text-sm font-semibold text-ink-2"><span class="w-9 h-9 rounded-panel bg-tint text-navy flex items-center justify-center shrink-0"><span class="msr text-[20px]">star</span></span><span>Bangun reputasi dan rating</span></div>
          <div class="p-4 rounded-hero bg-canvas border border-line flex items-center gap-3 text-sm font-semibold text-ink-2"><span class="w-9 h-9 rounded-panel bg-tint text-navy flex items-center justify-center shrink-0"><span class="msr text-[20px]">savings</span></span><span>Tarik saldo ke rekening</span></div>
        </div>

        <div class="w-full bg-canvas rounded-hero p-6 border border-line mb-4">
          <span class="text-xs font-bold uppercase tracking-wider text-muted mb-4 block">Alur Pendaftaran Mitra</span>
          <div class="space-y-3.5">
            <div class="flex items-center gap-3"><span class="w-6 h-6 rounded-full bg-orange text-white flex items-center justify-center text-xs font-bold shrink-0">1</span><span class="text-xs sm:text-sm font-medium text-ink-2">Download &amp; daftar</span></div>
            <div class="flex items-center gap-3"><span class="w-6 h-6 rounded-full bg-orange text-white flex items-center justify-center text-xs font-bold shrink-0">2</span><span class="text-xs sm:text-sm font-medium text-ink-2">Verifikasi email</span></div>
            <div class="flex items-center gap-3"><span class="w-6 h-6 rounded-full bg-orange text-white flex items-center justify-center text-xs font-bold shrink-0">3</span><span class="text-xs sm:text-sm font-semibold text-ink">Masukkan kode undangan mitra</span></div>
            <div class="flex items-center gap-3"><span class="w-6 h-6 rounded-full bg-orange text-white flex items-center justify-center text-xs font-bold shrink-0">4</span><span class="text-xs sm:text-sm font-medium text-ink-2">Lengkapi profil &amp; verifikasi KTP</span></div>
            <div class="flex items-center gap-3"><span class="w-6 h-6 rounded-full bg-orange text-white flex items-center justify-center text-xs font-bold shrink-0">5</span><span class="text-xs sm:text-sm font-medium text-ink-2">Mulai melamar</span></div>
          </div>
        </div>
      </div>

      <!-- phone mockup: worker home -->
      <div class="lg:col-span-5 flex justify-center relative min-w-0">
        <div class="reveal-phone relative w-[300px] sm:w-[370px] lg:w-[400px] rounded-[48px] p-3 bg-navy-dark border-[4px] border-navy/60 shadow-raised z-20">
          <div class="w-full rounded-[38px] bg-white overflow-hidden flex flex-col text-ink h-[620px] sm:h-[720px] lg:h-[760px] mock-live relative"><div class="mock-skel"><div class="skel h-3 w-16 mb-4"></div><div class="skel h-4 w-32 mb-2"></div><div class="skel h-3 w-24 mb-5"></div><div class="skel h-24 w-full mb-3"></div><div class="skel h-3 w-20 mb-2"></div><div class="skel h-16 w-full"></div></div>
          <div class="bg-navy h-14 px-4 flex items-center justify-between shrink-0">
            <img src="{{ asset('images/sekarya-wordmark-white.svg') }}" alt="Sekarya" class="h-5 w-auto">
            <div class="flex items-center gap-1">
              <span class="inline-flex items-center gap-1 bg-white/12 rounded-full pl-2 pr-2.5 py-1"><span class="msr text-[13px] text-orange-text">location_on</span><span class="text-[8px] font-semibold text-white">Bandung</span></span>
              <span class="relative flex items-center justify-center w-11 h-11 text-white"><span class="msr text-[20px]">notifications</span><span class="absolute top-2.5 right-2.5 w-2 h-2 rounded-full bg-orange"></span></span>
            </div>
          </div>
          <div class="bg-canvas px-4 pt-4 pb-5 flex-1 overflow-hidden">
            <div class="flex items-center">
              <div class="flex-1">
                <h2 class="text-[15px] leading-[21px] font-bold text-navy">Halo, Rani! 👋</h2>
                <p class="text-[10px] leading-[13px] text-ink-2 mt-1.5">Siap dapat penghasilan tambahan hari ini?</p>
              </div>
              <span class="w-11 h-11 rounded-full bg-tint text-navy font-bold text-[11px] flex items-center justify-center ml-3">RA</span>
            </div>

            <div class="mt-4 rounded-[24px] bg-navy p-5 text-white shadow-raised">
              <div class="flex items-center gap-2 pb-2">
                <span class="w-8 h-8 rounded-xl bg-white/12 flex items-center justify-center shrink-0"><span class="msr text-[15px] text-white/80">account_balance_wallet</span></span>
                <span class="text-[10px] font-semibold text-white/80 flex-1">Pendapatan minggu ini</span>
                <span class="inline-flex items-center text-[10px] font-semibold text-white/80">Rincian<span class="msr text-[13px]">chevron_right</span></span>
              </div>
              <div class="pb-3">
                <div class="text-[26px] leading-[33px] font-extrabold tracking-[-0.02em]" data-count="1250000" data-prefix="Rp">Rp1.250.000</div>
                <div class="inline-flex items-center gap-1 text-[10px] text-success mt-0.5"><span class="msr text-[11px]">trending_up</span>18% dari pekan lalu</div>
              </div>
              <div class="flex gap-3">
                <div class="flex-1 bg-white rounded-2xl p-3">
                  <span class="w-9 h-9 rounded-xl bg-success-tint text-success flex items-center justify-center"><span class="msr text-[15px]">task_alt</span></span>
                  <div class="text-[15px] font-extrabold text-ink mt-2" data-count="14">14</div>
                  <div class="text-[9px] font-bold uppercase tracking-wide text-muted">Kerja selesai</div>
                </div>
                <div class="flex-1 bg-white rounded-2xl p-3">
                  <span class="w-9 h-9 rounded-xl bg-warm text-amber-500 flex items-center justify-center"><span class="msr text-[15px]">star</span></span>
                  <div class="text-[15px] font-extrabold text-ink mt-2" data-count="4.9" data-dec="1" data-sep=",">4,9</div>
                  <div class="text-[9px] font-bold uppercase tracking-wide text-muted">Reputasi Mitra</div>
                </div>
              </div>
            </div>

            <h3 class="text-[15px] leading-[21px] font-bold text-navy mt-6">Tugas di sekitarmu</h3>
            <div class="flex items-center gap-2 mt-3">
              <span class="flex-1 h-12 rounded-full bg-white border border-line shadow-card flex items-center gap-2 px-4 text-muted text-[10px]"><span class="msr text-[16px]">search</span>Cari peluang kerja di sekitarmu…</span>
              <span class="w-12 h-12 rounded-full bg-tint text-navy flex items-center justify-center shrink-0"><span class="msr text-[16px]">tune</span></span>
            </div>

            <div class="mt-3 bg-white rounded-2xl border border-line shadow-card p-4">
              <div class="flex items-center justify-between gap-2">
                <span class="inline-flex items-center gap-1.5 bg-tint text-navy text-[10px] font-semibold rounded-full h-7 px-3 whitespace-nowrap"><span class="msr text-[13px]">plumbing</span>Pipa &amp; Air</span>
                <span class="text-[9px] text-ink-2 bg-tint rounded-full h-6 px-2.5 flex items-center whitespace-nowrap">10 mnt lalu</span>
              </div>
              <h4 class="text-[13px] leading-[18px] font-bold text-ink mt-3">Perbaikan Pipa Wastafel Bocor</h4>
              <div class="flex items-center gap-2 text-[11px] text-ink-2 mt-2"><span class="msr text-[13px] text-muted">location_on</span>Dago, Bandung</div>
              <div class="flex items-center gap-2 text-[11px] text-ink-2 mt-1"><span class="msr text-[13px] text-muted">event</span>Hari ini, 15:00 WIB</div>
              <div class="bg-tint rounded-xl p-3 flex items-center justify-between mt-3">
                <div class="flex items-center gap-2.5">
                  <span class="w-9 h-9 rounded-full bg-tint-line text-navy font-extrabold text-[11px] flex items-center justify-center">HW</span>
                  <div><div class="text-[11px] font-bold text-ink">Hendra Wijaya</div><div class="text-[10px] text-ink-2">Pemberi kerja terverifikasi</div></div>
                </div>
                <div class="text-right"><div class="text-[9px] font-medium text-muted">Budget/pekerja</div><div class="text-[14px] font-extrabold text-navy">Rp150.000</div></div>
              </div>
              <div class="flex justify-end mt-3"><span class="text-[11px] font-bold text-orange-text inline-flex items-center">Ajukan Penawaran<span class="msr text-[15px]">chevron_right</span></span></div>
            </div>

            <div class="mt-3 bg-white rounded-2xl border border-line shadow-card p-4">
              <div class="flex items-center justify-between gap-2">
                <span class="inline-flex items-center gap-1.5 bg-tint text-navy text-[10px] font-semibold rounded-full h-7 px-3 whitespace-nowrap"><span class="msr text-[13px]">cleaning_services</span>Kebersihan</span>
                <span class="text-[9px] text-ink-2 bg-tint rounded-full h-6 px-2.5 flex items-center whitespace-nowrap">25 mnt lalu</span>
              </div>
              <h4 class="text-[13px] leading-[18px] font-bold text-ink mt-3">Deep Cleaning Kamar Mandi &amp; Keramik</h4>
              <div class="flex items-center gap-2 text-[11px] text-ink-2 mt-2"><span class="msr text-[13px] text-muted">location_on</span>Cibiru, Bandung</div>
              <div class="flex items-center gap-2 text-[11px] text-ink-2 mt-1"><span class="msr text-[13px] text-muted">event</span>Besok, 09:00 WIB</div>
              <div class="bg-tint rounded-xl p-3 flex items-center justify-between mt-3">
                <div class="flex items-center gap-2.5">
                  <span class="w-9 h-9 rounded-full bg-tint-line text-navy font-extrabold text-[11px] flex items-center justify-center">SA</span>
                  <div><div class="text-[11px] font-bold text-ink">Siti Aminah</div><div class="text-[10px] text-ink-2">Pemberi kerja terverifikasi</div></div>
                </div>
                <div class="text-right"><div class="text-[9px] font-medium text-muted">Budget/pekerja</div><div class="text-[14px] font-extrabold text-navy">Rp120.000</div></div>
              </div>
              <div class="flex justify-end mt-3"><span class="text-[11px] font-bold text-orange-text inline-flex items-center">Ajukan Penawaran<span class="msr text-[15px]">chevron_right</span></span></div>
            </div>

            <div class="mt-4 bg-tint rounded-2xl p-4 flex gap-3">
              <span class="w-9 h-9 rounded-xl bg-white text-navy flex items-center justify-center shrink-0"><span class="msr text-[16px]">lightbulb</span></span>
              <div>
                <div class="text-[11px] font-bold text-ink">Tips Mitra Sekarya</div>
                <p class="text-[10px] leading-[15px] text-ink-2 mt-1">Mitra yang membalas pesanan dalam waktu kurang dari 15 menit mendapatkan lebih banyak peluang kerja!</p>
              </div>
            </div>
          </div>
          <div class="w-full h-14 bg-white border-t border-line px-6 flex items-center justify-around text-muted shrink-0">
            <span class="flex flex-col items-center text-navy"><span class="msr text-[18px]">home</span><span class="text-[8px] font-bold">Beranda</span></span>
            <span class="flex flex-col items-center"><span class="msr text-[18px]">assignment</span><span class="text-[8px] font-medium">Kerjaan</span></span>
            <span class="flex flex-col items-center"><span class="msr text-[18px]">person</span><span class="text-[8px] font-medium">Akun</span></span>
          </div>
          <div class="w-full h-2.5 bg-white flex justify-center items-center pb-1"><span class="w-20 h-1 rounded-full bg-line"></span></div>
        </div>
        </div>
        <div class="float-b absolute -bottom-4 -right-2 sm:-right-4 z-30 p-3.5 rounded-hero bg-white text-ink shadow-raised border border-line max-w-[240px]">
          <div class="flex items-center gap-2 mb-1.5">
            <span class="w-7 h-7 rounded-lg bg-success-tint text-success-ink flex items-center justify-center shrink-0"><span class="msr text-[16px]">payments</span></span>
            <div><p class="text-xs font-bold leading-tight">Upah masuk ke saldo</p><span class="text-[9px] text-muted">10 menit lalu</span></div>
          </div>
          <p class="text-[10px] text-ink-2 leading-snug"><span class="font-bold text-success-ink">Rp 150.000</span> masuk dari tugas "Cuci AC Bintaro".</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ PANDUAN ============ -->
<section id="panduan" class="w-full bg-canvas py-24 px-6 border-b border-line">
  <div class="max-w-7xl mx-auto">
    <div class="text-center max-w-2xl mx-auto mb-10 reveal">
      <span class="text-xs font-bold uppercase tracking-wider text-navy bg-tint px-3 py-1 rounded-full inline-block mb-3">Panduan Pengguna</span>
      <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight text-ink mb-3">Panduan singkat</h2>
      <p class="text-ink-2 text-sm leading-relaxed">Langkah praktis pakai Sekarya supaya transaksimu lancar dan aman.</p>
    </div>

    <input type="radio" name="panduan" id="tab-seeker" class="guide-radio" checked>
    <input type="radio" name="panduan" id="tab-mitra" class="guide-radio">
    <div class="flex justify-center mb-12">
      <div class="inline-grid grid-cols-2 p-1 rounded-full bg-tint border border-tint-line gap-1">
        <label for="tab-seeker" class="guide-btn text-center px-6 py-2 rounded-full text-xs transition-colors">Pemberi Kerja</label>
        <label for="tab-mitra" class="guide-btn text-center px-6 py-2 rounded-full text-xs transition-colors">Mitra</label>
      </div>
    </div>

    <div class="guide-panel guide-seeker">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 reveal">
      <div class="bg-white rounded-hero p-6 sm:p-7 border border-line lift shadow-card flex flex-col justify-between">
        <div class="flex flex-col">
          <div class="flex items-center justify-between mb-4"><span class="w-8 h-8 rounded-full bg-tint text-navy font-bold text-xs flex items-center justify-center">01</span><span class="text-[10px] font-semibold text-muted uppercase tracking-wider">Langkah 1</span></div>
          <h3 class="text-base font-bold text-ink mb-2">Isi saldo</h3>
          <p class="text-ink-2 text-xs leading-relaxed mb-6">Pilih nominal, transfer via bank atau e-wallet, lalu tunggu konfirmasi dari pengelola.</p>
        </div>
        <div class="p-3 rounded-panel bg-tint border border-tint-line mt-auto">
          <div class="flex items-center justify-between mb-2"><span class="text-[11px] font-bold text-ink">SekaryaPay</span><span class="text-[9px] px-1.5 py-0.5 rounded bg-white text-muted font-semibold border border-tint-line">Menunggu transfer</span></div>
          <div class="grid grid-cols-3 gap-1.5 text-center">
            <span class="p-1.5 rounded-full bg-white border border-tint-line text-[10px] font-medium text-ink-2">100k</span>
            <span class="p-1.5 rounded-full bg-navy text-white text-[10px] font-bold">250k</span>
            <span class="p-1.5 rounded-full bg-white border border-tint-line text-[10px] font-medium text-ink-2">500k</span>
          </div>
        </div>
      </div>
      <div class="bg-white rounded-hero p-6 sm:p-7 border border-line lift shadow-card flex flex-col justify-between">
        <div class="flex flex-col">
          <div class="flex items-center justify-between mb-4"><span class="w-8 h-8 rounded-full bg-tint text-navy font-bold text-xs flex items-center justify-center">02</span><span class="text-[10px] font-semibold text-muted uppercase tracking-wider">Langkah 2</span></div>
          <h3 class="text-base font-bold text-ink mb-2">Pasang tugas</h3>
          <p class="text-ink-2 text-xs leading-relaxed mb-6">Unggah foto, pilih kategori, tentukan lokasi, jadwal, dan budget tugasmu.</p>
        </div>
        <div class="p-3 rounded-panel bg-tint border border-tint-line mt-auto space-y-1.5">
          <div class="flex items-center justify-between text-[10px]"><span class="text-muted">Kategori:</span><span class="font-bold text-ink">Bersih Rumah</span></div>
          <div class="flex items-center justify-between text-[10px]"><span class="text-muted">Lokasi:</span><span class="font-bold text-ink">Bintaro</span></div>
          <div class="flex items-center justify-between text-[10px] pt-1 border-t border-tint-line"><span class="text-muted">Budget:</span><span class="font-bold text-navy">Rp 120.000</span></div>
        </div>
      </div>
      <div class="bg-white rounded-hero p-6 sm:p-7 border border-line lift shadow-card flex flex-col justify-between">
        <div class="flex flex-col">
          <div class="flex items-center justify-between mb-4"><span class="w-8 h-8 rounded-full bg-tint text-navy font-bold text-xs flex items-center justify-center">03</span><span class="text-[10px] font-semibold text-muted uppercase tracking-wider">Langkah 3</span></div>
          <h3 class="text-base font-bold text-ink mb-2">Pilih mitra</h3>
          <p class="text-ink-2 text-xs leading-relaxed mb-6">Lihat rating, ulasan, dan badge KTP terverifikasi sebelum kamu pilih mitra.</p>
        </div>
        <div class="p-3 rounded-panel bg-tint border border-tint-line mt-auto">
          <div class="flex items-center justify-between mb-1.5">
            <div class="flex items-center gap-1"><span class="text-[11px] font-bold text-ink">Budi S.</span><span class="flex items-center text-amber-500"><span class="msr text-[13px]">star</span><span class="text-[10px] font-bold">4.9</span></span></div>
            <span class="text-[9px] px-1 py-0.5 rounded bg-success-tint text-success-ink font-bold">KTP valid</span>
          </div>
          <div class="flex items-center justify-between pt-1 border-t border-tint-line"><span class="text-[9px] text-muted">Budget tugas</span><span class="text-[11px] font-extrabold text-navy">Rp 120.000</span></div>
        </div>
      </div>
      <div class="bg-white rounded-hero p-6 sm:p-7 border border-line lift shadow-card flex flex-col justify-between">
        <div class="flex flex-col">
          <div class="flex items-center justify-between mb-4"><span class="w-8 h-8 rounded-full bg-tint text-navy font-bold text-xs flex items-center justify-center">04</span><span class="text-[10px] font-semibold text-muted uppercase tracking-wider">Langkah 4</span></div>
          <h3 class="text-base font-bold text-ink mb-2">Setujui hasil</h3>
          <p class="text-ink-2 text-xs leading-relaxed mb-6">Cek foto bukti pekerjaan, setujui pelepasan dana, lalu beri nilai mitra.</p>
        </div>
        <div class="p-3 rounded-panel bg-tint border border-tint-line mt-auto space-y-2">
          <div class="flex items-center justify-between text-[10px]"><span class="text-ink-2 flex items-center gap-1"><span class="msr text-[13px] text-navy">photo_camera</span> Foto bukti (3)</span><span class="flex items-center text-amber-500"><span class="msr text-[13px]">star</span><span class="msr text-[13px]">star</span><span class="msr text-[13px]">star</span><span class="msr text-[13px]">star</span><span class="msr text-[13px]">star</span></span></div>
          <span class="w-full py-2 rounded-full bg-orange text-white font-bold text-[10px] flex items-center justify-center gap-1"><span class="msr text-[12px]">check_circle</span> Setujui &amp; Cairkan</span>
        </div>
      </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
        <div class="bg-tint border border-tint-line rounded-hero p-4 flex items-center gap-3.5 text-xs text-ink-2">
          <span class="w-9 h-9 rounded-panel bg-white border border-tint-line flex items-center justify-center shrink-0 text-navy"><span class="msr text-[20px]">lightbulb</span></span>
          <p class="leading-relaxed"><span class="font-bold text-ink">Tips:</span> Tulis detail tugas sejelas mungkin agar mitra paham dan tidak perlu bolak-balik.</p>
        </div>
        <div class="bg-warm border border-warm-line rounded-hero p-4 flex items-center gap-3.5 text-xs text-warm-ink">
          <span class="w-9 h-9 rounded-panel bg-white border border-warm-line flex items-center justify-center shrink-0 text-orange-text"><span class="msr text-[20px]">warning</span></span>
          <p class="leading-relaxed"><span class="font-bold">Penting:</span> Jangan setujui pekerjaan yang belum selesai atau tidak sesuai kesepakatan awal.</p>
        </div>
      </div>
    </div>

    <div class="guide-panel guide-mitra">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 reveal">
      <div class="bg-white rounded-hero p-6 sm:p-7 border border-line lift shadow-card flex flex-col justify-between">
        <div class="flex flex-col">
          <div class="flex items-center justify-between mb-4"><span class="w-8 h-8 rounded-full bg-tint text-navy font-bold text-xs flex items-center justify-center">01</span><span class="text-[10px] font-semibold text-muted uppercase tracking-wider">Langkah 1</span></div>
          <h3 class="text-base font-bold text-ink mb-2">Siap menerima kerja</h3>
          <p class="text-ink-2 text-xs leading-relaxed mb-6">Nyalakan status siap kerja di Beranda mitra agar tugas di kotamu mulai muncul.</p>
        </div>
        <div class="p-3 rounded-panel bg-tint border border-tint-line mt-auto flex items-center justify-between">
          <span class="text-[11px] font-bold text-ink">Siap menerima kerja</span>
          <span class="w-11 h-6 rounded-full bg-orange flex items-center justify-end pr-0.5"><span class="w-5 h-5 rounded-full bg-white"></span></span>
        </div>
      </div>
      <div class="bg-white rounded-hero p-6 sm:p-7 border border-line lift shadow-card flex flex-col justify-between">
        <div class="flex flex-col">
          <div class="flex items-center justify-between mb-4"><span class="w-8 h-8 rounded-full bg-tint text-navy font-bold text-xs flex items-center justify-center">02</span><span class="text-[10px] font-semibold text-muted uppercase tracking-wider">Langkah 2</span></div>
          <h3 class="text-base font-bold text-ink mb-2">Lamar tugas</h3>
          <p class="text-ink-2 text-xs leading-relaxed mb-6">Baca detail tugas dan budget yang dipasang, lalu lamar dengan satu ketukan.</p>
        </div>
        <div class="p-3 rounded-panel bg-tint border border-tint-line mt-auto flex items-center justify-between">
          <span class="text-[11px] font-extrabold text-navy">Rp 120.000</span>
          <span class="px-2.5 py-1 rounded-full bg-orange text-white text-[10px] font-bold">Lamar</span>
        </div>
      </div>
      <div class="bg-white rounded-hero p-6 sm:p-7 border border-line lift shadow-card flex flex-col justify-between">
        <div class="flex flex-col">
          <div class="flex items-center justify-between mb-4"><span class="w-8 h-8 rounded-full bg-tint text-navy font-bold text-xs flex items-center justify-center">03</span><span class="text-[10px] font-semibold text-muted uppercase tracking-wider">Langkah 3</span></div>
          <h3 class="text-base font-bold text-ink mb-2">Kerjakan tugas</h3>
          <p class="text-ink-2 text-xs leading-relaxed mb-6">Tandai tiap tahap — menuju lokasi, sampai, dikerjakan — lalu unggah foto bukti.</p>
        </div>
        <div class="p-3 rounded-panel bg-tint border border-tint-line mt-auto space-y-1.5">
          <div class="flex items-center gap-2 text-[10px]"><span class="w-5 h-5 rounded-full bg-success text-white flex items-center justify-center"><span class="msr text-[12px]">check</span></span><span class="text-ink-2 font-semibold">Menuju lokasi</span></div>
          <div class="flex items-center gap-2 text-[10px]"><span class="w-5 h-5 rounded-full bg-navy text-white flex items-center justify-center"><span class="msr text-[12px]">play_arrow</span></span><span class="text-navy font-bold">Sedang dikerjakan</span></div>
        </div>
      </div>
      <div class="bg-white rounded-hero p-6 sm:p-7 border border-line lift shadow-card flex flex-col justify-between">
        <div class="flex flex-col">
          <div class="flex items-center justify-between mb-4"><span class="w-8 h-8 rounded-full bg-tint text-navy font-bold text-xs flex items-center justify-center">04</span><span class="text-[10px] font-semibold text-muted uppercase tracking-wider">Langkah 4</span></div>
          <h3 class="text-base font-bold text-ink mb-2">Upah cair</h3>
          <p class="text-ink-2 text-xs leading-relaxed mb-6">Setelah pemberi kerja setujui, upah otomatis masuk ke saldo dan siap ditarik.</p>
        </div>
        <div class="p-3 rounded-panel bg-success-tint border border-success/30 mt-auto">
          <span class="text-[9px] text-success-ink block leading-none mb-1">Upah masuk ke saldo</span>
          <span class="text-sm font-extrabold text-ink">+ Rp 120.000</span>
        </div>
      </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
        <div class="bg-tint border border-tint-line rounded-hero p-4 flex items-center gap-3.5 text-xs text-ink-2">
          <span class="w-9 h-9 rounded-panel bg-white border border-tint-line flex items-center justify-center shrink-0 text-navy"><span class="msr text-[20px]">lightbulb</span></span>
          <p class="leading-relaxed"><span class="font-bold text-ink">Tips:</span> Cek detail tugas dan jadwal sebelum melamar agar kamu kerja tepat waktu.</p>
        </div>
        <div class="bg-warm border border-warm-line rounded-hero p-4 flex items-center gap-3.5 text-xs text-warm-ink">
          <span class="w-9 h-9 rounded-panel bg-white border border-warm-line flex items-center justify-center shrink-0 text-orange-text"><span class="msr text-[20px]">warning</span></span>
          <p class="leading-relaxed"><span class="font-bold">Penting:</span> Unggah foto bukti setelah tugas tuntas — upah cair setelah pemberi kerja setujui.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ FAQ ============ -->
<section id="faq" class="w-full bg-white py-24 px-6 border-b border-line">
  <div class="max-w-6xl mx-auto">
    <div class="text-center max-w-2xl mx-auto mb-16 reveal">
      <span class="text-xs font-bold uppercase tracking-wider text-navy bg-tint px-3 py-1 rounded-full inline-block mb-3">Pertanyaan Umum</span>
      <h2 class="text-3xl md:text-4xl font-extrabold text-ink tracking-tight mb-3">Sering ditanyakan</h2>
      <p class="text-ink-2 text-sm">Jawaban atas hal-hal yang paling sering ditanyakan seputar Sekarya.</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start reveal">
      <details name="faq" class="faq-item border border-line rounded-hero p-6 bg-white shadow-card transition-colors">
        <summary class="flex items-start justify-between gap-4 cursor-pointer list-none">
          <h3 class="text-base font-bold text-ink">Apakah Sekarya gratis?</h3>
          <span class="msr chev text-navy shrink-0 text-[20px]">expand_more</span>
        </summary>
        <div class="faq-body"><div><p class="text-xs sm:text-sm text-ink-2 leading-relaxed mt-3">Pendaftaran akun gratis tanpa biaya langganan. Pemberi kerja hanya membayar sesuai budget tugas, dan mitra dikenakan potongan admin transparan yang kecil saat menarik saldo.</p></div></div>
      </details>
      <details name="faq" class="faq-item border border-line rounded-hero p-6 bg-white shadow-card transition-colors">
        <summary class="flex items-start justify-between gap-4 cursor-pointer list-none">
          <h3 class="text-base font-bold text-ink">Apakah uang saya aman?</h3>
          <span class="msr chev text-muted shrink-0 text-[20px]">expand_more</span>
        </summary>
        <div class="faq-body"><div><p class="text-xs sm:text-sm text-ink-2 leading-relaxed mt-3">Dana kamu ditahan aman di Sekarya Escrow dan baru diteruskan ke mitra setelah kamu memeriksa foto bukti dan menyetujui hasilnya.</p></div></div>
      </details>
      <details name="faq" class="faq-item border border-line rounded-hero p-6 bg-white shadow-card transition-colors">
        <summary class="flex items-start justify-between gap-4 cursor-pointer list-none">
          <h3 class="text-base font-bold text-ink">Bagaimana cara jadi mitra?</h3>
          <span class="msr chev text-muted shrink-0 text-[20px]">expand_more</span>
        </summary>
        <div class="faq-body"><div><p class="text-xs sm:text-sm text-ink-2 leading-relaxed mt-3">Download aplikasi Sekarya, verifikasi email dan nomor HP, masukkan kode undangan mitra kotamu, lalu lengkapi verifikasi KTP untuk mulai melamar tugas.</p></div></div>
      </details>
      <details name="faq" class="faq-item border border-line rounded-hero p-6 bg-white shadow-card transition-colors">
        <summary class="flex items-start justify-between gap-4 cursor-pointer list-none">
          <h3 class="text-base font-bold text-ink">Bisa satu tugas untuk banyak mitra?</h3>
          <span class="msr chev text-muted shrink-0 text-[20px]">expand_more</span>
        </summary>
        <div class="faq-body"><div><p class="text-xs sm:text-sm text-ink-2 leading-relaxed mt-3">Ya. Untuk kebutuhan seperti pindahan rumah atau pembersihan skala besar, kamu bisa memasang tugas dengan kuota beberapa mitra sekaligus.</p></div></div>
      </details>
      <details name="faq" class="faq-item border border-line rounded-hero p-6 bg-white shadow-card transition-colors">
        <summary class="flex items-start justify-between gap-4 cursor-pointer list-none">
          <h3 class="text-base font-bold text-ink">Bagaimana jika hasil tidak memuaskan?</h3>
          <span class="msr chev text-muted shrink-0 text-[20px]">expand_more</span>
        </summary>
        <div class="faq-body"><div><p class="text-xs sm:text-sm text-ink-2 leading-relaxed mt-3">Jangan setujui hasilnya dulu. Kamu bisa minta mitra memperbaiki atau mengajukan sengketa agar pengelola Sekarya menengahi dan memeriksa bukti secara objektif.</p></div></div>
      </details>
      <details name="faq" class="faq-item border border-line rounded-hero p-6 bg-white shadow-card transition-colors">
        <summary class="flex items-start justify-between gap-4 cursor-pointer list-none">
          <h3 class="text-base font-bold text-ink">Bisa membatalkan tugas?</h3>
          <span class="msr chev text-muted shrink-0 text-[20px]">expand_more</span>
        </summary>
        <div class="faq-body"><div><p class="text-xs sm:text-sm text-ink-2 leading-relaxed mt-3">Tugas bisa dibatalkan sesuai ketentuan. Jika dibatalkan, dana yang ditahan langsung kembali utuh 100% ke saldo akunmu.</p></div></div>
      </details>
      <details name="faq" class="faq-item border border-line rounded-hero p-6 bg-white shadow-card transition-colors">
        <summary class="flex items-start justify-between gap-4 cursor-pointer list-none">
          <h3 class="text-base font-bold text-ink">Bagaimana menarik saldo?</h3>
          <span class="msr chev text-muted shrink-0 text-[20px]">expand_more</span>
        </summary>
        <div class="faq-body"><div><p class="text-xs sm:text-sm text-ink-2 leading-relaxed mt-3">Mitra maupun pemberi kerja bisa menarik saldo SekaryaPay kapan saja ke rekening bank nasional dan dompet digital yang kamu daftarkan.</p></div></div>
      </details>
      <details name="faq" class="faq-item border border-line rounded-hero p-6 bg-white shadow-card transition-colors">
        <summary class="flex items-start justify-between gap-4 cursor-pointer list-none">
          <h3 class="text-base font-bold text-ink">Kapan versi iOS?</h3>
          <span class="msr chev text-muted shrink-0 text-[20px]">expand_more</span>
        </summary>
        <div class="faq-body"><div><p class="text-xs sm:text-sm text-ink-2 leading-relaxed mt-3">Saat ini Sekarya fokus memberi pengalaman terbaik di Android. Versi iOS sedang dikembangkan dan akan segera hadir di App Store.</p></div></div>
      </details>
    </div>
  </div>
</section>

<!-- ============ DOWNLOAD BANNER ============ -->
<section id="download" class="w-full bg-canvas py-20 px-6">
  <div class="max-w-6xl mx-auto rounded-hero bg-navy text-white p-8 md:p-14 relative overflow-hidden shadow-raised border border-white/10">
    <span class="glow -right-20 -bottom-20 w-96 h-96 bg-navy/60"></span>
    <span class="glow -left-16 -top-16 w-72 h-72 bg-orange/20"></span>
    <div class="relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">
      <div class="lg:col-span-7 flex flex-col items-start min-w-0 reveal-x">
        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/10 border border-white/15 text-xs font-semibold uppercase tracking-wider mb-4"><span class="w-2 h-2 rounded-full bg-success animate-pulse"></span> Download Sekarang</span>
        <h2 class="text-3xl md:text-4xl font-extrabold text-white tracking-tight leading-tight mb-4">Satu aplikasi untuk memberi kerja dan mencari kerja</h2>
        <p class="text-slate-300 text-sm leading-relaxed mb-8 max-w-lg">Mulai selesaikan urusan harianmu, atau dapatkan penghasilan tambahan yang fleksibel, hari ini.</p>
        <div class="flex flex-wrap items-center gap-4">
          <a href="https://play.google.com" rel="noopener noreferrer" target="_blank" class="inline-flex items-center justify-center gap-3.5 w-[220px] h-[52px] rounded-full bg-orange text-white shadow-cta hover:bg-orange-text active:scale-[0.98] transition-all">
            <svg class="w-6 h-6 shrink-0" viewBox="30 336.7 120.9 129.2" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path fill="#FFC400" d="M119.2 421.2c15.3-8.4 27-14.8 28-15.3 3.2-1.7 6.5-6.2 0-9.7-2.1-1.1-13.4-7.3-28-15.3l-20.1 20.2 20.1 20.1z"/><path fill="#FF3A44" d="M99.1 401.1l-64.2 64.7c1.5.2 3.2-.2 5.2-1.3 4.2-2.3 48.8-26.7 79.1-43.3l-20.1-20.1z"/><path fill="#00E676" d="M99.1 401.1l20.1-20.2c0 0-74.6-40.7-79.1-43.1-1.7-1-3.6-1.3-5.3-1l64.3 64.3z"/><path fill="#00A0FF" d="M99.1 401.1l-64.3-64.3c-2.6.6-4.8 2.9-4.8 7.6v113.8c0 4.3 1.7 7.4 4.9 7.7l64.2-64.8z"/></svg>
            <span class="flex flex-col text-left leading-tight"><span class="text-[10px] uppercase tracking-wider font-semibold opacity-90">Temukan di</span><span class="text-sm font-extrabold">Google Play</span></span>
          </a>
          <div class="inline-flex items-center justify-center gap-3 w-[220px] h-[52px] rounded-full bg-white/5 border border-white/10 text-slate-400 cursor-not-allowed">
            <span class="msr text-[22px]">smartphone</span>
            <span class="flex flex-col text-left leading-tight"><span class="text-[10px] uppercase tracking-wider font-semibold text-slate-400">App Store</span><span class="text-sm font-semibold text-slate-300">Segera hadir</span></span>
          </div>
        </div>
      </div>
      <div class="lg:col-span-5 flex justify-center lg:justify-end relative min-w-0">
        <div class="w-[250px] sm:w-[315px] lg:w-[345px] rounded-t-[44px] p-2.5 bg-navy-dark border-[3px] border-navy/60 shadow-raised -mb-14">
          <div class="w-full rounded-t-[36px] bg-white overflow-hidden">
            <div class="w-full rounded-t-[36px] bg-white overflow-hidden flex flex-col text-ink mock-live relative"><div class="mock-skel"><div class="skel h-3 w-16 mb-4"></div><div class="skel h-4 w-32 mb-2"></div><div class="skel h-3 w-24 mb-5"></div><div class="skel h-24 w-full mb-3"></div><div class="skel h-3 w-20 mb-2"></div><div class="skel h-16 w-full"></div></div>
            <div class="bg-navy h-14 px-4 flex items-center justify-between shrink-0">
              <img src="{{ asset('images/sekarya-wordmark-white.svg') }}" alt="Sekarya" class="h-5 w-auto">
              <div class="flex items-center gap-1">
                <span class="inline-flex items-center gap-1 bg-white/12 rounded-full pl-2 pr-2.5 py-1"><span class="msr text-[13px] text-orange-text">location_on</span><span class="text-[8px] font-semibold text-white">Bandung</span></span>
                <span class="relative flex items-center justify-center w-11 h-11 text-white"><span class="msr text-[20px]">notifications</span><span class="absolute top-2.5 right-2.5 w-2 h-2 rounded-full bg-orange"></span></span>
              </div>
            </div>
            <div class="bg-canvas px-4 pt-4 pb-6">
              <div class="flex items-center gap-2">
                <h2 class="text-[18px] leading-[25px] font-bold text-ink flex-1 tracking-[-0.015em]">Butuh bantuan apa hari ini?</h2>
                <span class="inline-flex items-center gap-1 bg-tint text-navy rounded-full h-7 px-3 shadow-card shrink-0"><span class="msr text-[13px]">info</span><span class="text-[10px] font-semibold">Cara kerja</span></span>
              </div>
              <p class="text-[11px] leading-[18px] text-ink-2 mt-2">Ceritakan yang sulit kamu kerjakan — Kami bantu selesaikan.</p>
              <div class="mt-4 rounded-[24px] bg-navy p-5 text-white shadow-raised">
                <div class="flex items-center gap-2">
                  <span class="msr text-[16px] text-white/80">account_balance_wallet</span>
                  <span class="text-[11px] font-semibold flex-1">Saldo SekaryaPay</span>
                  <span class="inline-flex items-center gap-1.5 bg-success/15 rounded-full px-2.5 h-6"><span class="w-1.5 h-1.5 rounded-full bg-success"></span><span class="text-[8px] font-bold text-success">Aktif &amp; Aman</span></span>
                </div>
                <div class="text-[26px] leading-[33px] font-extrabold tracking-[-0.02em] mt-2" data-count="850000" data-prefix="Rp">Rp850.000</div>
                <div class="flex gap-3 mt-4">
                  <span class="flex-1 h-11 rounded-full bg-orange text-white text-[11px] font-bold flex items-center justify-center gap-1.5"><span class="msr text-[15px]">add</span>Isi Saldo</span>
                  <span class="flex-1 h-11 rounded-full bg-white/12 border border-white/20 text-white text-[11px] font-bold flex items-center justify-center gap-1.5"><span class="msr text-[15px]">history</span>Riwayat</span>
                </div>
              </div>
              <div class="flex items-end justify-between mt-6">
                <h3 class="text-[15px] leading-[21px] font-bold text-ink">Sedang berjalan</h3>
                <span class="text-[11px] font-semibold text-orange-text">Lihat semua</span>
              </div>
            </div>
          </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

</main>

<!-- ============ FOOTER ============ -->
<footer class="w-full bg-navy text-slate-300 pt-16 pb-12 border-t border-white/10">
  <div class="max-w-7xl mx-auto px-6">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10">
      <div class="flex flex-col items-start">
        <div class="flex items-center mb-4">
          <img src="{{ asset('images/sekarya-wordmark-white.svg') }}" alt="Sekarya" class="h-8 w-auto">
        </div>
        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Marketplace jasa harian Indonesia</span>
        <p class="text-slate-400 text-xs leading-relaxed">Menghubungkan orang yang butuh bantuan harian dengan mitra pekerja terpercaya di sekitar rumah, dengan dana yang ditahan aman.</p>
      </div>
      <div class="flex flex-col">
        <h4 class="text-xs font-bold text-white uppercase tracking-wider mb-4">Navigasi</h4>
        <ul class="space-y-2.5 text-xs">
          <li><a class="text-slate-400 hover:text-white transition-colors" href="#tentang">Tentang</a></li>
          <li><a class="text-slate-400 hover:text-white transition-colors" href="#cara-kerja">Cara Kerja</a></li>
          <li><a class="text-slate-400 hover:text-white transition-colors" href="#fitur">Fitur</a></li>
          <li><a class="text-slate-400 hover:text-white transition-colors" href="#mitra">Jadi Mitra</a></li>
          <li><a class="text-slate-400 hover:text-white transition-colors" href="#panduan">Panduan</a></li>
          <li><a class="text-slate-400 hover:text-white transition-colors" href="#faq">FAQ</a></li>
        </ul>
      </div>
      <div class="flex flex-col">
        <h4 class="text-xs font-bold text-white uppercase tracking-wider mb-4">Legal &amp; Bantuan</h4>
        <ul class="space-y-2.5 text-xs">
          <li><a class="text-slate-400 hover:text-white transition-colors" href="#">Kebijakan Privasi</a></li>
          <li><a class="text-slate-400 hover:text-white transition-colors" href="#">Syarat &amp; Ketentuan</a></li>
          <li><a class="text-slate-400 hover:text-white transition-colors" href="#">Pusat Bantuan</a></li>
        </ul>
      </div>
      <div class="flex flex-col">
        <h4 class="text-xs font-bold text-white uppercase tracking-wider mb-4">Ikuti Kami</h4>
        <div class="flex items-center gap-3 mb-6">
          <a aria-label="Instagram" href="#" class="w-9 h-9 rounded-full bg-white/10 hover:bg-navy flex items-center justify-center text-white transition-colors"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg></a>
          <a aria-label="TikTok" href="#" class="w-9 h-9 rounded-full bg-white/10 hover:bg-navy flex items-center justify-center text-white transition-colors"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.97v7.54c0 1.9-.38 3.86-1.52 5.37-1.47 2.01-3.9 3.09-6.38 3.07-2.32-.01-4.63-.98-6.14-2.72-1.78-2.02-2.34-4.9-1.54-7.46.72-2.39 2.66-4.29 5.09-4.94 1.25-.34 2.58-.31 3.85.04v4.23c-.7-.3-1.52-.39-2.27-.27-1.07.16-2.02.86-2.47 1.84-.52 1.09-.4 2.45.29 3.42.66.93 1.83 1.47 2.97 1.37 1.25-.09 2.37-.95 2.69-2.16.14-.52.19-1.07.19-1.61V.02z"/></svg></a>
          <a aria-label="Facebook" href="#" class="w-9 h-9 rounded-full bg-white/10 hover:bg-navy flex items-center justify-center text-white transition-colors"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M9 8H6v4h3v12h5V12h3.642L18 8h-4V6.333C14 5.374 14.5 5 15.5 5H18V0h-3.808C10.595 0 9 1.583 9 4.615V8z"/></svg></a>
        </div>
        <div class="p-3 rounded-panel bg-white/5 border border-white/10 flex items-center gap-2.5 text-xs text-slate-300">
          <span class="msr text-[18px] text-success">verified_user</span>
          <span>Dana ditahan aman dengan Sekarya Escrow</span>
        </div>
      </div>
    </div>
    <div class="border-t border-white/10 mt-12 pt-8 flex flex-col md:flex-row justify-between items-center text-xs text-slate-400 gap-4">
      <p>© 2026 Sekarya.</p>
    </div>
  </div>
</footer>

<script>
  // NAV shadow on scroll
  const nav = document.getElementById('nav');
  addEventListener('scroll', () => {
    nav.style.boxShadow = window.scrollY > 8 ? '0 4px 12px rgba(22,60,104,0.15)' : 'none';
  }, { passive: true });

  // NAV: penanda opsi aktif mengikuti seksi yang sedang dilihat (scroll-spy)
  const links = [...document.querySelectorAll('#nav .nav-link')];
  const secs = links.map((l) => document.getElementById(l.getAttribute('href').slice(1)));
  function spy() {
    const y = window.scrollY + 96;
    let idx = -1;
    secs.forEach((s, i) => { if (s && s.offsetTop <= y) idx = i; });
    links.forEach((l, i) => l.classList.toggle('active', i === idx));
  }
  addEventListener('scroll', spy, { passive: true });
  addEventListener('resize', spy);
  spy();
</script>

<script>
(function(){
  var io=new IntersectionObserver(function(es){
    es.forEach(function(e){ if(!e.isIntersecting) return;
      var t=e.target;
      if(t.classList.contains('mock-live')){ setTimeout(function(){t.classList.add('ready')},700); io.unobserve(t); return; }
      if(t.hasAttribute('data-count')){ count(t); io.unobserve(t); return; }
      t.classList.add('in'); io.unobserve(t);
    });
  },{threshold:.12,rootMargin:'0px 0px -6% 0px'});

  function fmt(v,dec,sep,prefix){
    var s = dec ? v.toFixed(dec) : String(Math.round(v));
    if(dec){ var p=s.split('.'); s=p[0].replace(/\B(?=(\d{3})+(?!\d))/g,'.')+sep+p[1]; }
    else { s=s.replace(/\B(?=(\d{3})+(?!\d))/g,'.'); }
    return prefix+s;
  }
  function count(el){
    var to=parseFloat(el.dataset.count), dec=parseInt(el.dataset.dec||0), sep=el.dataset.sep||'.', pre=el.dataset.prefix||'', st=null, dur=1400;
    function step(ts){ if(!st)st=ts; var p=Math.min((ts-st)/dur,1), e=1-Math.pow(1-p,3);
      el.textContent=fmt(to*e,dec,sep,pre); if(p<1) requestAnimationFrame(step); else el.textContent=fmt(to,dec,sep,pre); }
    requestAnimationFrame(step);
  }

  document.querySelectorAll('.reveal,.reveal-x,.reveal-phone,.step-line,[data-count],.mock-live').forEach(function(el){ io.observe(el); });
})();
</script>
</body>
</html>
