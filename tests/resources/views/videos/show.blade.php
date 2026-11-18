@extends('layouts.app')

@section('title', $video->title.' · '.config('app.name'))

@push('head')
<meta property="og:title" content="{{ $video->title }}">
@if ($video->has_thumbnail)<meta property="og:image" content="{{ route('videos.thumb', $video) }}">@endif
<style>
    .player { background: #000; border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow); }
    .player video { display: block; width: 100%; max-height: 72vh; background: #000; }
    .info { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-top: 18px; flex-wrap: wrap; }
    .title-input { font: inherit; font-size: 22px; font-weight: 700; color: var(--text); background: transparent;
                   border: 1px solid transparent; border-radius: 8px; padding: 2px 6px; margin-left: -7px; width: 100%; }
    .title-input:hover { border-color: var(--border); }
    .title-input:focus { outline: none; border-color: var(--accent); background: var(--surface); }
    .meta { margin-top: 4px; }
    .actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .grow { flex: 1; min-width: 240px; }
    .saved { font-size: 13px; color: var(--ok); opacity: 0; transition: opacity .3s; margin-left: 6px; }
    .saved.on { opacity: 1; }
</style>
@endpush

@section('content')
    <div class="player">
        <video id="player" controls playsinline preload="metadata"
               @if ($video->has_thumbnail) poster="{{ route('videos.thumb', $video) }}" @endif>
            <source src="{{ route('videos.file', $video) }}" type="{{ $video->mime }}">
        </video>
    </div>

    <div class="info">
        <div class="grow">
            <input class="title-input" id="title" value="{{ $video->title }}" maxlength="150" aria-label="Video title">
            <div class="meta muted">
                {{ $video->created_at->format('M j, Y g:i A') }} · {{ $video->durationLabel() }} · {{ $video->sizeLabel() }} ·
                <span id="views">{{ $video->views }}</span> {{ Str::plural('view', $video->views) }}
                <span class="saved" id="saved">Saved ✓</span>
            </div>
        </div>
        <div class="actions">
            <button class="btn btn-primary" id="copy">Copy link</button>
            <a class="btn" href="{{ route('videos.download', $video) }}">Download</a>
            <form method="post" action="{{ route('videos.destroy', $video) }}" onsubmit="return confirm('Delete this video permanently?')">
                @csrf @method('delete')
                <button class="btn btn-danger">Delete</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const shareUrl = @json(route('videos.show', $video));
    const player = document.getElementById('player');

    // Browser-recorded WebM files often report duration = Infinity; force the browser to compute it so seeking works.
    player.addEventListener('loadedmetadata', () => {
        if (player.duration === Infinity) {
            player.currentTime = 1e101;
            player.addEventListener('timeupdate', function reset() {
                player.removeEventListener('timeupdate', reset);
                player.currentTime = 0;
            });
        }
    });

    // Count a view the first time it's played.
    let counted = false;
    player.addEventListener('play', async () => {
        if (counted) return; counted = true;
        try {
            const r = await fetch(@json(route('videos.view', $video)), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
            const d = await r.json(); document.getElementById('views').textContent = d.views;
        } catch (e) {}
    });

    document.getElementById('copy').addEventListener('click', async (e) => {
        try { await navigator.clipboard.writeText(shareUrl); } catch (err) { prompt('Copy this link:', shareUrl); return; }
        e.target.textContent = 'Copied ✓'; setTimeout(() => e.target.textContent = 'Copy link', 1500);
    });

    // Rename on blur / Enter.
    const title = document.getElementById('title');
    let lastTitle = title.value;
    async function saveTitle() {
        const v = title.value.trim();
        if (!v) { title.value = lastTitle; return; }
        if (v === lastTitle) return;
        const r = await fetch(@json(route('videos.update', $video)), {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ title: v }),
        });
        if (r.ok) {
            lastTitle = v; document.title = v + ' · ' + @json(config('app.name'));
            const s = document.getElementById('saved'); s.classList.add('on'); setTimeout(() => s.classList.remove('on'), 1500);
        }
    }
    title.addEventListener('blur', saveTitle);
    title.addEventListener('keydown', e => { if (e.key === 'Enter') title.blur(); if (e.key === 'Escape') { title.value = lastTitle; title.blur(); } });
</script>
@endpush
