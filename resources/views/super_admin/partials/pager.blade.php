@props(['paginator'])
<div class="mt-5 flex items-center gap-2 text-sm">
    @if ($paginator->previousCursor())
        <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-ghost py-2!">← Sebelumnya</a>
    @endif
    @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-navy py-2!">Berikutnya →</a>
    @endif
    @if (! $paginator->previousCursor() && ! $paginator->hasMorePages())
        <span class="text-xs text-slate-400">Semua data sudah tampil.</span>
    @endif
</div>
