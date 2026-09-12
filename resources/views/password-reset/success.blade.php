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
        body { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-white flex items-center justify-center p-4 sm:p-6 text-slate-800">
    <main class="w-full max-w-sm rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-xl shadow-slate-200/60">
        <div class="flex items-center justify-center">
            <span class="text-3xl font-extrabold tracking-tight" style="color: #163C68;">Sekarya</span>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="#F97316" fill-rule="evenodd" aria-hidden="true"><path d="M12 22s7-6 7-12a7 7 0 1 0-14 0c0 6 7 12 7 12Z M12 12.7 A2.7 2.7 0 1 0 12 7.3 A2.7 2.7 0 1 0 12 12.7 Z"/></svg>
        </div>

        <div class="mx-auto mt-6 flex h-16 w-16 items-center justify-center rounded-full" style="background: #ECFDF5;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.75l6 6 9-13.5"/></svg>
        </div>
        <h1 class="mt-4 text-xl font-extrabold" style="color: #163C68;">Kata sandi diperbarui</h1>
        <p class="mt-2 text-sm text-slate-500">Tautan reset tadi sudah hangus dan tidak bisa dipakai lagi. Silakan masuk kembali di aplikasi Sekarya dengan kata sandi baru Anda.</p>
    </main>
</body>
</html>
