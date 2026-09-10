@extends('super_admin.layout')

@section('title', 'Moderasi pengguna')
@section('header', 'Moderasi Pengguna')

@section('content')
<div class="anim-rise">
    <h3 class="text-xl font-extrabold" style="color: #163C68;">Moderasi pengguna</h3>
    <p class="mt-1 text-sm text-slate-500"><span class="font-mono font-bold">email</span> adalah pencocokan persis (terindeks) — bukan pencarian sebagian. Tidak ada LIKE di proyek ini.</p>
</div>

<form method="GET" class="anim-rise ad-1 mt-4 card p-4 flex flex-wrap items-end gap-3 text-sm">
    <div class="flex-1 min-w-[14rem]">
        <label class="label">Email persis</label>
        <input name="email" value="{{ $filterEmail }}" placeholder="cth. budi@sekarya.test" class="field">
    </div>
    <div>
        <label class="label">Status</label>
        <select name="status" class="field !w-auto min-w-[12rem]">
            <option value="">Semua status</option>
            @foreach (['active' => 'green', 'pending_verification' => 'amber', 'suspended' => 'orange', 'banned' => 'red'] as $s => $t)
                <option value="{{ $s }}" @selected($filterStatus === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn btn-accent">Cari</button>
</form>

<div class="anim-rise ad-2 mt-4 card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[640px]">
            <thead><tr class="table-head">
                <th>Pengguna</th><th>Status</th><th>Terdaftar</th><th class="!text-right">Aksi</th>
            </tr></thead>
            <tbody>
            @forelse ($queue as $u)
                @php
                    $tone = match($u->status->value) {
                        'active' => 'green', 'banned' => 'red', 'suspended' => 'orange', default => 'amber',
                    };
                @endphp
                <tr class="table-row">
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-white text-xs shrink-0" style="background: linear-gradient(135deg, #163C68, #2A5E9E);">
                                {{ strtoupper(substr($u->name, 0, 1)) }}
                            </div>
                            <div class="min-w-0"><p class="font-bold truncate">{{ $u->name }}</p><p class="text-xs text-slate-400 truncate">{{ $u->email }}</p></div>
                        </div>
                    </td>
                    <td>@include('super_admin.partials.badge', ['text' => $u->status->value, 'tone' => $tone])</td>
                    <td class="whitespace-nowrap text-slate-500 text-xs">{{ $u->created_at }}</td>
                    <td class="!text-right">
                        <a href="{{ route('super_admin.users.show', $u->ulid) }}" class="btn btn-navy !py-2 !px-3.5 !text-xs">Buka →</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4">@include('super_admin.partials.empty', ['title' => 'Tidak ada hasil', 'hint' => 'Alamat yang tidak terdaftar mengembalikan daftar kosong — bukan galat.'])</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('super_admin.partials.pager', ['paginator' => $queue])
@endsection
