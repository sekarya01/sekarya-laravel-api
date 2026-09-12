<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Buat Kata Sandi Baru — Sekarya</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @else
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    @endif
    <style>
        body { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif; }
        .field { width: 100%; border-radius: .8rem; border: 1px solid #D7E0EC; background: #FBFCFE;
            padding: .7rem 2.75rem .7rem 1rem; font-size: .9rem; }
        .field:focus { outline: none; border-color: #F97316; background: #fff; box-shadow: 0 0 0 3px rgba(249,115,22,.15); }
        .eye { position: absolute; right: .75rem; top: 50%; transform: translateY(-50%);
            padding: .25rem; border-radius: 999px; color: #94A3B8; }
        .eye.on { color: #163C68; }
    </style>
</head>
<body class="min-h-screen bg-white flex items-center justify-center p-4 sm:p-6 text-slate-800">
    <main class="w-full max-w-sm rounded-3xl border border-slate-200 bg-white p-8 shadow-xl shadow-slate-200/60">
        <div class="flex items-center justify-center">
            <span class="text-3xl font-extrabold tracking-tight" style="color: #163C68;">Sekarya</span>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="#F97316" fill-rule="evenodd" aria-hidden="true"><path d="M12 22s7-6 7-12a7 7 0 1 0-14 0c0 6 7 12 7 12Z M12 12.7 A2.7 2.7 0 1 0 12 7.3 A2.7 2.7 0 1 0 12 12.7 Z"/></svg>
        </div>

        <h1 class="mt-6 text-center text-xl font-extrabold" style="color: #163C68;">Buat kata sandi baru</h1>
        <p class="mt-1 text-center text-sm text-slate-500">{{ $email }}</p>

        @if ($errors->any())
            <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('password.reset.store') }}" class="mt-6 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">
            <div>
                <label for="password" class="block text-xs font-bold text-slate-600 mb-1.5">KATA SANDI BARU</label>
                <div class="relative">
                    <input id="password" name="password" type="password" required autofocus autocomplete="new-password"
                        placeholder="Min. 8 karakter, ada angka" class="field">
                    <button type="button" class="eye" data-eye="password" aria-label="Tampilkan kata sandi">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12 C5 7 8 5 12 5 C16 5 19 7 22 12 C19 17 16 19 12 19 C8 19 5 17 2 12 Z M12 15 A3 3 0 1 0 12 9 A3 3 0 1 0 12 15 Z"/></svg>
                    </button>
                </div>
            </div>
            <div>
                <label for="password_confirmation" class="block text-xs font-bold text-slate-600 mb-1.5">KONFIRMASI KATA SANDI</label>
                <div class="relative">
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                        placeholder="Ulangi kata sandi baru" class="field">
                    <button type="button" class="eye" data-eye="password_confirmation" aria-label="Tampilkan konfirmasi kata sandi">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12 C5 7 8 5 12 5 C16 5 19 7 22 12 C19 17 16 19 12 19 C8 19 5 17 2 12 Z M12 15 A3 3 0 1 0 12 9 A3 3 0 1 0 12 15 Z"/></svg>
                    </button>
                </div>
            </div>
            <button class="w-full rounded-xl py-3 text-sm font-bold text-white"
                    style="background: #F97316;">
                Perbarui Password
            </button>
        </form>
        <p class="mt-5 text-center text-xs text-slate-400">Tidak meminta reset? Abaikan halaman ini.</p>
    </main>
    <script>
        // Intip kata sandi seperti aplikasi mobile: ikon menyala navy (#163C68)
        // saat terlihat, abu (#94A3B8) saat tersembunyi.
        document.querySelectorAll('[data-eye]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = document.getElementById(btn.dataset.eye);
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.classList.toggle('on', show);
            });
        });
    </script>
</body>
</html>
