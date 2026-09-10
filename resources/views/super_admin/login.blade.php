<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — Super Admin Sekarya</title>
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
                <h1 class="mt-6 text-3xl font-extrabold leading-tight">Konsol<br>Super Admin</h1>
                <p class="mt-2 text-sm text-slate-300">Kelola verifikasi, transfer, pengguna, dan pengelola dalam satu tempat.</p>
            </div>
            <ul class="space-y-2.5 text-sm text-slate-200">
                <li class="flex items-center gap-2.5"><span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0" style="background: rgba(249,115,22,.25); color: #FDBA74;">✓</span> Antrean paling lama menunggu di depan</li>
                <li class="flex items-center gap-2.5"><span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0" style="background: rgba(249,115,22,.25); color: #FDBA74;">✓</span> Setiap baca NIK tercatat di audit</li>
                <li class="flex items-center gap-2.5"><span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0" style="background: rgba(249,115,22,.25); color: #FDBA74;">✓</span> Dana hanya ditahan setelah mutasi cocok</li>
            </ul>
        </div>

        <div class="p-7 sm:p-10">
            <p class="text-xs uppercase tracking-widest font-bold" style="color: var(--accent);">Sekarya · Pengelola</p>
            <h2 class="mt-1 text-2xl font-extrabold" style="color: var(--brand);">Selamat datang kembali</h2>
            <p class="mt-1 text-sm text-slate-500">Hanya akun <span class="font-semibold">super_admin</span> yang bisa masuk.</p>

            @if ($errors->any())
                <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('super_admin.login.attempt') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-xs font-bold text-slate-600 mb-1.5">EMAIL</label>
                    <input id="email" name="email" type="email" required value="{{ old('email') }}" autofocus autocomplete="username"
                        placeholder="super@sekarya.com" class="field">
                </div>
                <div>
                    <label for="password" class="block text-xs font-bold text-slate-600 mb-1.5">KATA SANDI</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                        placeholder="••••••••••••" class="field">
                </div>
                <button class="w-full rounded-xl py-3 text-sm font-bold text-white transition-all hover:brightness-110 active:brightness-95"
                        style="background: linear-gradient(135deg, var(--accent), #DD5F0A); box-shadow: 0 10px 24px -8px rgba(249,115,22,.7);">
                    Masuk ke Konsol
                </button>
            </form>
            <p class="mt-5 text-center text-xs text-slate-400">Tidak ada pendaftaran di sini — akun lahir dari baris perintah & menu pengelola.</p>
        </div>
    </div>
</body>
</html>
