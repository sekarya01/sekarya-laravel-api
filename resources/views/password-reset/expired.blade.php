<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tautan Kedaluwarsa — Sekarya</title>
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

        <div class="mx-auto mt-6 flex h-16 w-16 items-center justify-center rounded-full" style="background: #FEF2F2;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h1 class="mt-4 text-xl font-extrabold" style="color: #163C68;">Tautan kedaluwarsa</h1>
        <p class="mt-2 text-sm text-slate-500">Tautan ini sudah <span class="font-semibold">kedaluwarsa</span> (berlaku 60 menit) atau <span class="font-semibold">sudah terpakai</span>. Minta tautan baru dari aplikasi Sekarya lewat menu lupa kata sandi.</p>
    </main>
</body>
</html>
