<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Super Admin') — Sekarya</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @else
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    @endif
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen">
<div class="flex min-h-screen">
    <aside class="w-64 shrink-0 bg-slate-900 text-slate-100 flex flex-col">
        <div class="px-5 py-5 border-b border-white/10">
            <p class="text-xs uppercase tracking-widest text-slate-400">Sekarya</p>
            <h1 class="text-lg font-semibold">Super Admin</h1>
            @auth('admin_web')
                <p class="mt-1 text-xs text-slate-400">{{ auth('admin_web')->user()->email }}</p>
            @endauth
        </div>
        <nav class="flex-1 px-3 py-4 space-y-1 text-sm">
            <a href="{{ route('super_admin.dashboard') }}" class="block rounded px-3 py-2 hover:bg-white/10">Dasbor</a>
            <a href="{{ route('super_admin.verifications.index') }}" class="block rounded px-3 py-2 hover:bg-white/10">Verifikasi identitas</a>
            <a href="{{ route('super_admin.payments.index') }}" class="block rounded px-3 py-2 hover:bg-white/10">Konfirmasi transfer</a>
            <a href="{{ route('super_admin.users.index') }}" class="block rounded px-3 py-2 hover:bg-white/10">Moderasi pengguna</a>
            <a href="{{ route('super_admin.admins.index') }}" class="block rounded px-3 py-2 hover:bg-white/10">Akun pengelola</a>
            <a href="{{ route('super_admin.audit.index') }}" class="block rounded px-3 py-2 hover:bg-white/10">Jejak audit</a>
        </nav>
        <div class="p-4 border-t border-white/10">
            <form method="POST" action="{{ route('super_admin.logout') }}">
                @csrf
                <button class="w-full rounded bg-white/10 px-3 py-2 text-sm hover:bg-white/20">Keluar</button>
            </form>
        </div>
    </aside>
    <main class="flex-1 p-6 lg:p-10">
        @if (session('status'))
            <div class="mb-4 rounded border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>
</div>
</body>
</html>
