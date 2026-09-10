@props(['paginator'])
@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $window = [];
    foreach ([1, 2, $current - 1, $current, $current + 1, $last - 1, $last] as $p) {
        if ($p >= 1 && $p <= $last) {
            $window[] = $p;
        }
    }
    $window = array_values(array_unique($window));
    sort($window);
@endphp
@if ($paginator->hasPages())
    <div class="mt-5 flex flex-wrap items-center justify-center gap-2 text-sm">
        @if ($paginator->onFirstPage())
            <span class="pg-btn pg-disabled">← Sebelumnya</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="pg-btn" rel="prev">← Sebelumnya</a>
        @endif

        @php $prev = 0; @endphp
        @foreach ($window as $p)
            @if ($p - $prev > 1)
                <span class="pg-dots">…</span>
            @endif
            @if ($p === $current)
                <span class="pg-btn pg-current" aria-current="page">{{ $p }}</span>
            @else
                <a href="{{ $paginator->url($p) }}" class="pg-btn">{{ $p }}</a>
            @endif
            @php $prev = $p; @endphp
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="pg-btn" rel="next">Berikutnya →</a>
        @else
            <span class="pg-btn pg-disabled">Berikutnya →</span>
        @endif
    </div>
    <p class="mt-2 text-center text-xs text-slate-400">
        Halaman {{ $current }} dari {{ $last }} · {{ number_format($paginator->total(), 0, ',', '.') }} data
    </p>
@else
    <p class="mt-5 text-center text-xs text-slate-400">Semua data sudah tampil.</p>
@endif
