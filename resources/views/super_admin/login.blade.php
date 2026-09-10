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
</head>
<body class="bg-slate-100 flex items-center justify-center min-h-screen p-6">
<div class="w-full max-w-md rounded-xl bg-white p-8 shadow">
    <p class="text-xs uppercase tracking-widest text-slate-500">Sekarya · Pengelola</p>
    <h1 class="mt-1 text-2xl font-semibold">Masuk Super Admin</h1>
    <p class="mt-1 text-sm text-slate-500">Hanya akun <span class="font-medium">super_admin</span>. Tidak ada pendaftaran di sini.</p>
    @if ($errors->any())
        <div class="mt-4 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <form method="POST" action="{{ route('super_admin.login.attempt') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label for="email" class="text-sm font-medium">Email</label>
            <input id="email" name="email" type="email" required value="{{ old('email') }}" autofocus
                class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
        </div>
        <div>
            <label for="password" class="text-sm font-medium">Kata sandi</label>
            <input id="password" name="password" type="password" required
                class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
        </div>
        <button class="w-full rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Masuk</button>
    </form>
</div>
</body>
</html>
