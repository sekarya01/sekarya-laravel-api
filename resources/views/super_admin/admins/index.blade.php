@extends('super_admin.layout')

@section('title', 'Akun pengelola')
@section('header', 'Akun Pengelola')

@section('content')
<div class="anim-rise">
    <h3 class="text-xl font-extrabold" style="color: #163C68;">Akun pengelola</h3>
    <p class="mt-1 text-sm text-slate-500">Tepat satu <span class="font-mono font-bold">super_admin</span> (dijaga basis data). Peran baru selalu <span class="font-mono font-bold">admin</span>. Hapus = soft delete, email tetap terpakai.</p>
</div>

<div class="anim-rise ad-1 mt-4 card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[620px]">
            <thead><tr class="table-head">
                <th>Pengelola</th><th>Peran</th><th>Status</th><th>Login terakhir</th><th class="!text-right">Aksi</th>
            </tr></thead>
            <tbody>
            @forelse ($admins as $a)
                <tr class="table-row">
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-white text-xs shrink-0"
                                 style="background: {{ $a->isSuperAdmin() ? 'linear-gradient(135deg, #F97316, #C2570B)' : 'linear-gradient(135deg, #163C68, #2A5E9E)' }};">
                                {{ strtoupper(substr($a->name, 0, 1)) }}
                            </div>
                            <div class="min-w-0"><p class="font-bold truncate">{{ $a->name }}</p><p class="text-xs text-slate-400 truncate">{{ $a->email }}</p></div>
                        </div>
                    </td>
                    <td>@include('super_admin.partials.badge', ['text' => $a->role->value, 'tone' => $a->isSuperAdmin() ? 'orange' : 'navy'])</td>
                    <td>@include('super_admin.partials.badge', ['text' => $a->status->value, 'tone' => $a->status->isActive() ? 'green' : 'red'])</td>
                    <td class="whitespace-nowrap text-slate-500 text-xs">{{ $a->last_login_at ?? 'belum pernah' }}</td>
                    <td class="!text-right">
                        @if (! $a->isSuperAdmin())
                            <form method="POST" action="{{ route('super_admin.admins.destroy', $a->ulid) }}" onsubmit="return confirm('Hapus {{ $a->email }}? Tokennya dicabut, barisnya tetap ada untuk jejak audit.')">
                                @csrf
                                @method('DELETE')
                                <button class="text-xs font-bold text-red-600 hover:text-red-800 hover:underline transition-all">Hapus</button>
                            </form>
                        @else
                            @include('super_admin.partials.badge', ['text' => 'dilindungi', 'tone' => 'slate'])
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">@include('super_admin.partials.empty', ['title' => 'Tidak ada pengelola', 'hint' => 'Buat akun admin pertama lewat formulir di bawah.'])</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('super_admin.partials.pager', ['paginator' => $admins])

<div class="anim-rise ad-2 mt-5 card p-5 sm:p-6 max-w-xl" style="border-top: 4px solid #F97316;">
    <h4 class="font-extrabold text-lg" style="color: #163C68;">Buat pengelola baru</h4>
    <p class="mt-1 text-sm text-slate-500">Sandi min. 12 karakter. Tidak ada field peran — selalu <span class="font-mono font-bold">admin</span>.</p>
    <form method="POST" action="{{ route('super_admin.admins.store') }}" class="mt-4 space-y-3.5 text-sm">
        @csrf
        <div>
            <label class="label" for="adm-name">Nama lengkap</label>
            <input id="adm-name" name="name" required minlength="3" maxlength="100" value="{{ old('name') }}" class="field" placeholder="cth. Verifikator Dua">
        </div>
        <div>
            <label class="label" for="adm-email">Email</label>
            <input id="adm-email" name="email" type="email" required maxlength="255" value="{{ old('email') }}" class="field" placeholder="verif2@sekarya.com">
        </div>
        <div class="grid gap-3.5 sm:grid-cols-2">
            <div>
                <label class="label" for="adm-pass">Kata sandi</label>
                <input id="adm-pass" name="password" type="password" required minlength="12" class="field" placeholder="min. 12 karakter">
            </div>
            <div>
                <label class="label" for="adm-pass2">Konfirmasi sandi</label>
                <input id="adm-pass2" name="password_confirmation" type="password" required minlength="12" class="field" placeholder="ulangi sandi">
            </div>
        </div>
        <button class="btn btn-accent w-full sm:w-auto">+ Buat admin</button>
    </form>
</div>
@endsection
