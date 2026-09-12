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
        :root { --brand: #163C68; --brand-deep: #0C2745; --brand-ink: #0A1E35; --accent: #F97316; --accent-hover: #EA680C; }
        body { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif; }
        @keyframes rise { from { opacity: 0; transform: translateY(18px) scale(.99); } to { opacity: 1; transform: translateY(0) scale(1); } }
        @keyframes floaty { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-12px); } }
        @keyframes drift { 0%, 100% { transform: translate(0,0) scale(1); } 50% { transform: translate(30px,-20px) scale(1.08); } }
        .anim-rise { animation: rise .6s cubic-bezier(.22,.8,.32,1) both; }
        .field { width: 100%; border-radius: .8rem; border: 1px solid #D7E0EC; background: #FBFCFE;
            padding: .7rem 1rem; font-size: .9rem; transition: all .2s ease; }
        .field:focus { outline: none; border-color: var(--accent); background: #fff; box-shadow: 0 0 0 3px rgba(249,115,22,.15); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 sm:p-6" style="background: linear-gradient(140deg, var(--brand-ink) 0%, var(--brand-deep) 45%, var(--brand) 100%);">
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full opacity-20" style="background: var(--accent); filter: blur(110px); animation: drift 9s ease-in-out infinite;"></div>
        <div class="absolute -bottom-32 -left-24 w-[28rem] h-[28rem] rounded-full opacity-20" style="background: #3B82F6; filter: blur(120px); animation: drift 11s ease-in-out infinite reverse;"></div>
    </div>

    <div class="anim-rise relative w-full max-w-4xl grid md:grid-cols-2 rounded-3xl overflow-hidden shadow-2xl bg-white">
        <div class="hidden md:flex flex-col justify-between p-8 text-white relative overflow-hidden" style="background: linear-gradient(170deg, var(--brand-ink), var(--brand) 120%);">
            <div class="absolute -bottom-16 -right-16 w-64 h-64 rounded-full opacity-15" style="background: var(--accent); filter: blur(60px);"></div>
            <div>
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center font-black text-2xl" style="background: linear-gradient(135deg, var(--accent), #C2570B); animation: floaty 5s ease-in-out infinite;">S</div>
                <h1 class="mt-6 text-3xl font-extrabold leading-tight">Buat kata<br>sandi baru</h1>
                <p class="mt-2 text-sm text-slate-300">Tautan ini hanya bisa dipakai satu kali dan berlaku 60 menit.</p>
            </div>
            <ul class="space-y-2.5 text-sm text-slate-200">
                <li class="flex items-center gap-2.5"><span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0" style="background: rgba(249,115,22,.25); color: #FDBA74;">✓</span> Minimal 8 karakter, huruf + angka</li>
                <li class="flex items-center gap-2.5"><span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0" style="background: rgba(249,115,22,.25); color: #FDBA74;">✓</span> Sesi di perangkat lain ikut keluar</li>
                <li class="flex items-center gap-2.5"><span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0" style="background: rgba(249,115,22,.25); color: #FDBA74;">✓</span> Tautan langsung hangus setelah dipakai</li>
            </ul>
        </div>

        <div class="p-7 sm:p-10">
            <p class="text-xs uppercase tracking-widest font-bold" style="color: var(--accent);">Sekarya · Reset Kata Sandi</p>
            <h2 class="mt-1 text-2xl font-extrabold" style="color: var(--brand);">Kata sandi baru</h2>
            <p class="mt-1 text-sm text-slate-500">Untuk <span class="font-semibold">{{ $email }}</span></p>

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
                    <input id="password" name="password" type="password" required autofocus autocomplete="new-password"
                        placeholder="Minimal 8 karakter, huruf + angka" class="field">
                </div>
                <div>
                    <label for="password_confirmation" class="block text-xs font-bold text-slate-600 mb-1.5">KONFIRMASI KATA SANDI</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                        placeholder="Ulangi kata sandi baru" class="field">
                </div>
                <button class="w-full rounded-xl py-3 text-sm font-bold text-white transition-all hover:brightness-110 active:brightness-95"
                        style="background: linear-gradient(135deg, var(--accent), #DD5F0A); box-shadow: 0 10px 24px -8px rgba(249,115,22,.7);">
                    Perbarui Password
                </button>
            </form>
            <p class="mt-5 text-center text-xs text-slate-400">Tidak meminta reset? Abaikan halaman ini — kata sandi Anda tidak berubah.</p>
        </div>
    </div>
</body>
</html>
