@extends('layouts.app')

@section('title', 'My videos · '.config('app.name'))

@push('head')
<style>
    .head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-bottom: 20px; }
    .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 18px; }
    .vcard { overflow: hidden; display: flex; flex-direction: column; }
    .thumb { position: relative; aspect-ratio: 16/9; background: var(--surface-2); display: block; }
    .thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .thumb .dur { position: absolute; right: 8px; bottom: 8px; background: rgba(0,0,0,.72); color: #fff;
                  font-size: 12px; font-weight: 600; padding: 2px 7px; border-radius: 6px; }
    .thumb .noimg { position: absolute; inset: 0; display: grid; place-items: center; font-size: 30px; color: var(--muted); }
    .vbody { padding: 12px 14px 14px; display: flex; flex-direction: column; gap: 4px; flex: 1; }
    .vtitle { font-weight: 600; text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .vtitle:hover { color: var(--accent); }
    .vmeta { font-size: 13px; }
    .vactions { margin-top: 8px; }
    .vactions .btn { padding: 6px 12px; font-size: 13px; }
    .empty { text-align: center; padding: 70px 20px; }
    .empty p { max-width: 420px; margin: 10px auto 24px; }
</style>
@endpush

@section('content')
    <div class="head">
        <h1>My videos</h1>
        <span class="muted">{{ $videos->count() }} {{ Str::plural('video', $videos->count()) }}</span>
    </div>

    @if ($videos->isEmpty())
        <div class="card empty">
            <h2 style="margin:0">No videos yet</h2>
            <p class="muted">Record your screen, camera, and voice. You'll get a link you can paste into a proposal, email, or chat.</p>
            <a class="btn btn-rec btn-lg" href="{{ route('videos.record') }}">● Record your first video</a>
        </div>
    @else
        <div class="grid">
            @foreach ($videos as $video)
                <div class="card vcard">
                    <a class="thumb" href="{{ route('videos.show', $video) }}">
                        @if ($video->has_thumbnail)
                            <img src="{{ route('videos.thumb', $video) }}" alt="" loading="lazy">
                        @else
                            <span class="noimg">▶</span>
                        @endif
                        <span class="dur">{{ $video->durationLabel() }}</span>
                    </a>
                    <div class="vbody">
                        <a class="vtitle" href="{{ route('videos.show', $video) }}" title="{{ $video->title }}">{{ $video->title }}</a>
                        <div class="vmeta muted">{{ $video->created_at->diffForHumans() }} · {{ $video->views }} {{ Str::plural('view', $video->views) }}</div>
                        <div class="vactions">
                            <button class="btn copy" data-url="{{ route('videos.show', $video) }}">Copy link</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection

@push('scripts')
<script>
    document.querySelectorAll('.copy').forEach(btn => btn.addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(btn.dataset.url); } catch (e) { prompt('Copy this link:', btn.dataset.url); return; }
        const old = btn.textContent; btn.textContent = 'Copied ✓';
        setTimeout(() => btn.textContent = old, 1500);
    }));
</script>
@endpush
