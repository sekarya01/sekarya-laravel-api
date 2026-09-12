<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kata Sandi Diperbarui — Sekarya</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @else
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    @endif
    <style>
        :root { --brand: #163C68; --brand-deep: #0C2745; --brand-ink: #0A1E35; --accent: #F97316; }
        body { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif; }
        @keyframes rise { from { opacity: 0; transform: translateY(18px) scale(.99); } to { opacity: 1; transform: translateY(0) scale(1); } }
        @keyframes pop { 0% { transform: scale(.4); opacity: 0; } 60% { transform: scale(1.12); } 100% { transform: scale(1); opacity: 1; } }
        @keyframes drift { 0%, 100% { transform: translate(0,0) scale(1); } 50% { transform: translate(30px,-20px) scale(1.08); } }
        .anim-rise { animation: rise .6s cubic-bezier(.22,.8,.32,1) both; }
        .anim-pop { animation: pop .5s cubic-bezier(.22,.8,.32,1) both .15s; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 sm:p-6" style="background: linear-gradient(140deg, var(--brand-ink) 0%, var(--brand-deep) 45%, var(--brand) 100%);">
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full opacity-20" style="background: var(--accent); filter: blur(110px); animation: drift 9s ease-in-out infinite;"></div>
        <div class="absolute -bottom-32 -left-24 w-[28rem] h-[28rem] rounded-full opacity-20" style="background: #3B82F6; filter: blur(120px); animation: drift 11s ease-in-out infinite reverse;"></div>
    </div>

    <div class="anim-rise relative w-full max-w-md rounded-3xl overflow-hidden shadow-2xl bg-white p-8 sm:p-10 text-center">
        <div class="anim-pop mx-auto w-16 h-16 rounded-full flex items-center justify-center" style="background: #ECFDF5;">
            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke-width="2.5" style="color: #059669;"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
        </div>
        <p class="mt-6 text-xs uppercase tracking-widest font-bold" style="color: var(--accent);">Sekarya · Reset Kata Sandi</p>
        <h1 class="mt-1 text-2xl font-extrabold" style="color: var(--brand);">Kata sandi diperbarui</h1>
        <p class="mt-2 text-sm text-slate-500">Tautan reset tadi sudah hangus dan tidak bisa dipakai lagi. Sesi di perangkat lain juga sudah dikeluarkan. Silakan masuk kembali di aplikasi Sekarya dengan kata sandi baru Anda.</p>
    </div>
</body>
</html>
