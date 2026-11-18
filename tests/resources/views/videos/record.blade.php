@extends('layouts.app')

@section('title', 'New recording · '.config('app.name'))

@push('head')
<style>
    .rec-layout { display: grid; grid-template-columns: 1fr 340px; gap: 22px; align-items: start; }
    @media (max-width: 820px) { .rec-layout { grid-template-columns: 1fr; } }
    .stage { aspect-ratio: 16/9; background: var(--surface-2); border-radius: var(--radius); position: relative;
             overflow: hidden; display: grid; place-items: center; border: 1px solid var(--border); }
    .stage video.screen { width: 100%; height: 100%; object-fit: contain; background: #000; display: none; }
    .stage .hint { text-align: center; padding: 20px; max-width: 420px; }
    .stage .hint b { display: block; font-size: 18px; margin-bottom: 6px; }
    .bubble { position: absolute; left: 18px; bottom: 18px; width: 150px; height: 150px; border-radius: 50%;
              overflow: hidden; border: 3px solid #fff; box-shadow: 0 6px 24px rgba(0,0,0,.35); background: #222; display: none; }
    .bubble video { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
    .countdown { position: absolute; inset: 0; display: none; place-items: center; background: rgba(0,0,0,.55);
                 color: #fff; font-size: 110px; font-weight: 800; }
    .panel { padding: 18px; display: flex; flex-direction: column; gap: 14px; }
    .panel h2 { margin: 0; font-size: 17px; }
    .field label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; }
    .field select, .field input[type=text] { width: 100%; font: inherit; padding: 8px 10px; border-radius: 8px;
                    border: 1px solid var(--border); background: var(--surface); color: var(--text); }
    .toggle { display: flex; align-items: center; justify-content: space-between; gap: 10px; font-weight: 600; font-size: 14px; }
    .toggle input { width: 18px; height: 18px; accent-color: var(--accent); }
    .status { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 18px; }
    .dot { width: 12px; height: 12px; border-radius: 50%; background: var(--rec); animation: blink 1s infinite; }
    .paused .dot { animation: none; background: var(--muted); }
    @keyframes blink { 50% { opacity: .25; } }
    .row { display: flex; gap: 8px; }
    .row .btn { flex: 1; }
    .progress { height: 8px; border-radius: 99px; background: var(--surface-2); overflow: hidden; }
    .progress > div { height: 100%; width: 0; background: var(--accent); transition: width .2s; }
    .tip { font-size: 13px; background: var(--surface-2); border-radius: 10px; padding: 10px 12px; }
    .error { color: var(--danger); font-size: 14px; }
    [hidden] { display: none !important; }
</style>
@endpush

@section('content')
<div class="rec-layout">
    <div class="stage" id="stage">
        <div class="hint" id="hint">
            <b>Ready when you are</b>
            <span class="muted">Pick your camera and mic, then click <strong>Choose screen to share</strong>. Pick <strong>Entire screen</strong> so your face bubble shows up in the video.</span>
        </div>
        <video class="screen" id="screenPreview" muted playsinline></video>
        <div class="bubble" id="bubble"><video id="camPreview" muted playsinline autoplay></video></div>
        <div class="countdown" id="countdown"></div>
    </div>

    <div class="card panel">
        {{-- Setup --}}
        <div id="setup">
            <h2 style="margin-bottom:12px">Recording settings</h2>
            <div style="display:flex;flex-direction:column;gap:14px">
                <div class="field">
                    <label for="titleInput">Title (optional)</label>
                    <input type="text" id="titleInput" maxlength="150" placeholder="e.g. Video for Sarah – booking system fix">
                </div>
                <label class="toggle">Camera bubble <input type="checkbox" id="camOn" checked></label>
                <div class="field" id="camField">
                    <select id="camSelect" aria-label="Camera"></select>
                </div>
                <label class="toggle">Microphone <input type="checkbox" id="micOn" checked></label>
                <div class="field" id="micField">
                    <select id="micSelect" aria-label="Microphone"></select>
                </div>
                <label class="toggle">Include computer sound <input type="checkbox" id="sysAudio"></label>
                <button class="btn btn-primary btn-lg" id="shareBtn">1. Choose screen to share</button>
                <button class="btn btn-rec btn-lg" id="startBtn" hidden>● 2. Start recording</button>
                <button class="btn" id="changeBtn" hidden>Change screen</button>
                <div class="error" id="error" hidden></div>
                <div class="tip muted">Tip: while recording, use the floating bubble's <strong>Stop</strong> button, or the browser's “Stop sharing” bar.</div>
            </div>
        </div>

        {{-- Recording --}}
        <div id="recording" hidden>
            <div class="status" id="statusLine"><span class="dot"></span><span id="timer">0:00</span></div>
            <div class="row" style="margin-top:14px">
                <button class="btn" id="pauseBtn">Pause</button>
                <button class="btn btn-rec" id="stopBtn">■ Stop</button>
            </div>
            <button class="btn btn-danger" id="cancelBtn" style="margin-top:8px;width:100%">Cancel &amp; discard</button>
        </div>

        {{-- Uploading --}}
        <div id="uploading" hidden>
            <h2>Uploading…</h2>
            <div class="progress" style="margin:12px 0 6px"><div id="bar"></div></div>
            <div class="muted" id="upText">Starting…</div>
            <div id="upFail" hidden style="margin-top:12px">
                <div class="error">Upload failed. Your recording is still here — retry, or save it to your computer.</div>
                <div class="row" style="margin-top:10px">
                    <button class="btn btn-primary" id="retryBtn">Retry</button>
                    <button class="btn" id="saveLocalBtn">Download</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const ROUTES = {
        store: @json(route('videos.store')),
        base: @json(url('/videos')),
    };
    const CSRF = document.querySelector('meta[name=csrf-token]').content;
    const CHUNK_SIZE = 4 * 1024 * 1024; // 4 MB (server limit is 40 MB per request)

    const $ = id => document.getElementById(id);
    const els = {
        camOn: $('camOn'), micOn: $('micOn'), sysAudio: $('sysAudio'), camSelect: $('camSelect'), micSelect: $('micSelect'),
        camField: $('camField'), micField: $('micField'), camPreview: $('camPreview'), bubble: $('bubble'),
        screenPreview: $('screenPreview'), hint: $('hint'), countdown: $('countdown'), start: $('startBtn'),
        share: $('shareBtn'), change: $('changeBtn'),
        error: $('error'), setup: $('setup'), recording: $('recording'), uploading: $('uploading'),
        timer: $('timer'), statusLine: $('statusLine'), pause: $('pauseBtn'), stop: $('stopBtn'), cancel: $('cancelBtn'),
        bar: $('bar'), upText: $('upText'), upFail: $('upFail'), retry: $('retryBtn'), saveLocal: $('saveLocalBtn'),
        title: $('titleInput'),
    };

    let camStream = null, micStream = null, displayStream = null, audioCtx = null;
    let recorder = null, chunks = [], mimeType = '', thumbBlob = null, finalBlob = null;
    let pipWin = null, pipTimer = null, pipPause = null;
    let elapsedMs = 0, segmentStart = 0, tick = null, cancelled = false, busy = false;

    // ---------- helpers ----------
    const dlog = msg => { try { fetch(@json(url('/debug-log')), { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' }, body: JSON.stringify({ msg }) }); } catch (e) {} };
    window.addEventListener('error', e => dlog('JS error: ' + e.message + ' @' + e.lineno));
    window.addEventListener('unhandledrejection', e => dlog('Promise error: ' + (e.reason && (e.reason.name + ' ' + e.reason.message))));
    const showError = msg => { els.error.textContent = msg; els.error.hidden = !msg; if (msg) dlog('shown error: ' + msg); };
    const fmt = ms => { const s = Math.floor(ms / 1000); return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`; };
    const stopStream = s => s && s.getTracks().forEach(t => t.stop());
    const pickMime = () => [
        'video/mp4;codecs=avc1.42E01E,mp4a.40.2', 'video/mp4',
        'video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm',
    ].find(t => window.MediaRecorder && MediaRecorder.isTypeSupported(t)) || '';
    const currentElapsed = () => elapsedMs + (recorder && recorder.state === 'recording' ? performance.now() - segmentStart : 0);

    // ---------- device setup ----------
    async function fillDevices() {
        const devices = await navigator.mediaDevices.enumerateDevices();
        const fill = (sel, kind) => {
            const prev = sel.value;
            sel.innerHTML = '';
            devices.filter(d => d.kind === kind).forEach((d, i) => {
                const o = document.createElement('option');
                o.value = d.deviceId; o.textContent = d.label || `${kind === 'videoinput' ? 'Camera' : 'Microphone'} ${i + 1}`;
                sel.appendChild(o);
            });
            if (prev) sel.value = prev;
        };
        fill(els.camSelect, 'videoinput');
        fill(els.micSelect, 'audioinput');
    }

    async function startCamera() {
        stopStream(camStream); camStream = null;
        if (!els.camOn.checked) { els.bubble.style.display = 'none'; return; }
        try {
            camStream = await navigator.mediaDevices.getUserMedia({
                video: { deviceId: els.camSelect.value ? { exact: els.camSelect.value } : undefined, width: 640, height: 640 },
            });
            els.camPreview.srcObject = camStream;
            els.bubble.style.display = 'block';
            showError('');
        } catch (e) {
            els.bubble.style.display = 'none';
            showError('Camera not available: ' + e.message);
        }
    }

    async function startMic() {
        stopStream(micStream); micStream = null;
        if (!els.micOn.checked) return;
        micStream = await navigator.mediaDevices.getUserMedia({
            audio: { deviceId: els.micSelect.value ? { exact: els.micSelect.value } : undefined, echoCancellation: true, noiseSuppression: true },
        });
    }

    els.camOn.addEventListener('change', () => { els.camField.hidden = !els.camOn.checked; startCamera(); });
    els.micOn.addEventListener('change', () => { els.micField.hidden = !els.micOn.checked; });
    els.camSelect.addEventListener('change', startCamera);

    // ---------- floating camera bubble (Picture-in-Picture) ----------
    async function openBubble() {
        if (!camStream) return;
        if ('documentPictureInPicture' in window) {
            try {
                pipWin = await documentPictureInPicture.requestWindow({ width: 240, height: 290 });
                const d = pipWin.document;
                d.head.innerHTML = `<style>
                    html,body{margin:0;height:100%;background:#111;font-family:system-ui,sans-serif;color:#fff;overflow:hidden}
                    .w{height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px}
                    .c{width:200px;height:200px;border-radius:50%;overflow:hidden;border:3px solid #fff;position:relative}
                    video{width:100%;height:100%;object-fit:cover;transform:scaleX(-1)}
                    .cd{position:absolute;inset:0;display:grid;place-items:center;font-size:72px;font-weight:800;background:rgba(0,0,0,.5)}
                    .bar{display:flex;gap:6px;align-items:center}
                    .t{font-weight:700;font-variant-numeric:tabular-nums;min-width:44px}
                    button{font:inherit;font-weight:600;border:0;border-radius:8px;padding:5px 10px;cursor:pointer;background:#333;color:#fff}
                    .s{background:#e5484d}</style>`;
                d.body.innerHTML = `<div class="w"><div class="c"><video autoplay muted playsinline></video><div class="cd" hidden></div></div>
                    <div class="bar"><span class="t">0:00</span><button class="p">Pause</button><button class="s">■ Stop</button></div></div>`;
                d.querySelector('video').srcObject = camStream;
                pipTimer = d.querySelector('.t');
                pipPause = d.querySelector('.p');
                pipPause.addEventListener('click', togglePause);
                d.querySelector('.s').addEventListener('click', stopRecording);
                pipWin.addEventListener('pagehide', () => { pipWin = null; pipTimer = null; pipPause = null; });
                return;
            } catch (e) { pipWin = null; dlog('docPiP failed: ' + e.name + ' ' + e.message); }
        }
        // Fallback: plain video Picture-in-Picture (no controls inside).
        try { if (els.camPreview.readyState >= 1) await els.camPreview.requestPictureInPicture(); } catch (e) {}
    }

    function closeBubble() {
        if (pipWin) { pipWin.close(); pipWin = null; }
        if (document.pictureInPictureElement) document.exitPictureInPicture().catch(() => {});
    }

    function setCountdown(text) {
        els.countdown.textContent = text; els.countdown.style.display = text ? 'grid' : 'none';
        if (pipWin) { const cd = pipWin.document.querySelector('.cd'); cd.textContent = text; cd.hidden = !text; }
    }

    // ---------- recording ----------
    // Step 1 (its own click): pick the screen. Chrome lets one click open only one permission popup,
    // so the screen picker and the floating camera bubble each get their own button click.
    async function chooseScreen() {
        dlog('chooseScreen clicked, busy=' + busy + ', secure=' + isSecureContext);
        if (busy) return;
        showError('');
        mimeType = pickMime();
        if (!mimeType || !navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
            showError('This browser cannot record the screen. Please open this page in Chrome or Edge on a computer.');
            return;
        }
        stopStream(displayStream); displayStream = null;
        try {
            displayStream = await navigator.mediaDevices.getDisplayMedia({
                video: { frameRate: 30, width: { ideal: 1920 }, height: { ideal: 1080 } },
                audio: els.sysAudio.checked,
                selfBrowserSurface: 'exclude', surfaceSwitching: 'include', monitorTypeSurfaces: 'include',
            });
        } catch (e) {
            dlog('getDisplayMedia failed: ' + e.name + ' ' + e.message);
            showError(e.name === 'NotAllowedError'
                ? 'Screen sharing was cancelled or blocked. Click “Choose screen to share” and pick a screen.'
                : 'Could not start screen sharing: ' + e.message);
            return;
        }
        els.screenPreview.srcObject = displayStream;
        els.screenPreview.style.display = 'block';
        els.screenPreview.play().catch(() => {});
        els.hint.hidden = true;
        displayStream.getVideoTracks()[0].addEventListener('ended', () => {
            if (recorder || busy) stopRecording();
            else { displayStream = null; showReady(false); els.screenPreview.style.display = 'none'; els.hint.hidden = false; }
        });
        dlog('screen chosen: ' + (displayStream.getVideoTracks()[0].getSettings().displaySurface || '?') + ', mime=' + mimeType);
        showReady(true);
    }

    function showReady(ready) {
        els.share.hidden = ready; els.start.hidden = !ready; els.change.hidden = !ready;
    }

    // Step 2 (its own click): open the camera bubble, count down, record.
    async function startRecording() {
        dlog('startRecording clicked, busy=' + busy + ', screenActive=' + !!(displayStream && displayStream.active) + ', cam=' + !!camStream);
        if (busy) return;
        if (!displayStream || !displayStream.active) { showReady(false); return chooseScreen(); }
        busy = true;
        showError('');
        cancelled = false; chunks = []; thumbBlob = null; finalBlob = null; elapsedMs = 0;

        await openBubble();

        try { await startMic(); } catch (e) { showError('Microphone not available — recording without voice.'); }

        // Mix microphone + computer sound into one audio track.
        audioCtx = new AudioContext();
        const dest = audioCtx.createMediaStreamDestination();
        let hasAudio = false;
        [micStream, displayStream].forEach(s => {
            if (s && s.getAudioTracks().length) {
                audioCtx.createMediaStreamSource(new MediaStream(s.getAudioTracks())).connect(dest); hasAudio = true;
            }
        });

        const tracks = [...displayStream.getVideoTracks(), ...(hasAudio ? dest.stream.getAudioTracks() : [])];
        const stream = new MediaStream(tracks);

        els.bubble.style.display = 'none';

        els.setup.hidden = true; els.recording.hidden = false;
        window.addEventListener('beforeunload', warnLeave);

        for (const n of [3, 2, 1]) { setCountdown(String(n)); await new Promise(r => setTimeout(r, 1000)); if (cancelled) return; }
        setCountdown('');

        recorder = new MediaRecorder(stream, { mimeType, videoBitsPerSecond: 2_500_000 });
        recorder.ondataavailable = e => { if (e.data && e.data.size) chunks.push(e.data); };
        recorder.onstop = onRecorderStop;
        recorder.start(1000);
        dlog('recorder started, bubble=' + (pipWin ? 'docPiP' : (document.pictureInPictureElement ? 'videoPiP' : 'none')));
        segmentStart = performance.now();
        busy = false;
        tick = setInterval(updateTimer, 250);
        setTimeout(grabThumbnail, 1200);
    }

    function updateTimer() {
        const t = fmt(currentElapsed());
        els.timer.textContent = t;
        if (pipTimer) pipTimer.textContent = t;
    }

    function togglePause() {
        if (!recorder) return;
        if (recorder.state === 'recording') {
            recorder.pause(); elapsedMs += performance.now() - segmentStart;
            els.pause.textContent = 'Resume'; els.statusLine.classList.add('paused');
            if (pipPause) pipPause.textContent = 'Resume';
        } else if (recorder.state === 'paused') {
            recorder.resume(); segmentStart = performance.now();
            els.pause.textContent = 'Pause'; els.statusLine.classList.remove('paused');
            if (pipPause) pipPause.textContent = 'Pause';
        }
        updateTimer();
    }

    function stopRecording() {
        if (recorder && recorder.state !== 'inactive') {
            if (recorder.state === 'recording') elapsedMs += performance.now() - segmentStart;
            recorder.stop();
        } else if (!recorder) {
            // Stopped during countdown.
            cancelled = true; cleanup(); resetUi();
        }
    }

    function cancelRecording() {
        if (!confirm('Discard this recording?')) return;
        cancelled = true;
        if (recorder && recorder.state !== 'inactive') recorder.stop(); else { cleanup(); resetUi(); }
    }

    function cleanup() {
        clearInterval(tick); tick = null;
        stopStream(displayStream); displayStream = null;
        stopStream(micStream); micStream = null;
        if (audioCtx) { audioCtx.close().catch(() => {}); audioCtx = null; }
        closeBubble();
        els.screenPreview.srcObject = null; els.screenPreview.style.display = 'none';
        setCountdown('');
    }

    function resetUi() {
        recorder = null; busy = false;
        window.removeEventListener('beforeunload', warnLeave);
        els.setup.hidden = false; els.recording.hidden = true; els.uploading.hidden = true;
        els.hint.hidden = false; els.pause.textContent = 'Pause'; els.statusLine.classList.remove('paused');
        els.timer.textContent = '0:00';
        showReady(false);
        if (camStream) els.bubble.style.display = 'block';
    }

    function onRecorderStop() {
        dlog('recorder stopped, cancelled=' + cancelled + ', chunks=' + chunks.length);
        cleanup();
        if (cancelled || !chunks.length) { resetUi(); return; }
        finalBlob = new Blob(chunks, { type: mimeType.split(';')[0] });
        chunks = [];
        upload();
    }

    function grabThumbnail() {
        const v = els.screenPreview;
        if (!v.videoWidth) return;
        const c = document.createElement('canvas');
        const w = 640; c.width = w; c.height = Math.round(w * v.videoHeight / v.videoWidth);
        c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
        c.toBlob(b => { thumbBlob = b; }, 'image/jpeg', 0.8);
    }

    const warnLeave = e => { e.preventDefault(); e.returnValue = ''; };

    // ---------- upload ----------
    async function post(url, body, isJson) {
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', ...(isJson ? { 'Content-Type': 'application/json' } : {}) },
            body: isJson ? JSON.stringify(body) : body,
        });
        if (!r.ok) { const err = new Error('HTTP ' + r.status); err.status = r.status; err.body = await r.json().catch(() => ({})); throw err; }
        return r.status === 204 ? {} : r.json();
    }

    let uploadSlug = null, nextIndex = 0;
    async function upload() {
        els.recording.hidden = true; els.uploading.hidden = false; els.upFail.hidden = true;
        const total = Math.ceil(finalBlob.size / CHUNK_SIZE);
        try {
            if (!uploadSlug) {
                const res = await post(ROUTES.store, { title: els.title.value.trim(), mime: mimeType }, true);
                uploadSlug = res.slug; nextIndex = 0;
            }
            const base = `${ROUTES.base}/${uploadSlug}`;
            while (nextIndex < total) {
                const fd = new FormData();
                fd.append('index', nextIndex);
                fd.append('chunk', finalBlob.slice(nextIndex * CHUNK_SIZE, (nextIndex + 1) * CHUNK_SIZE), 'chunk');
                try { await post(`${base}/chunk`, fd); }
                catch (e) { if (e.status === 409 && e.body.expected !== undefined) { nextIndex = e.body.expected; continue; } throw e; }
                nextIndex++;
                const pct = Math.round(nextIndex / total * 100);
                els.bar.style.width = pct + '%';
                els.upText.textContent = `${pct}% · ${(finalBlob.size / 1048576).toFixed(1)} MB`;
            }
            if (thumbBlob) {
                const fd = new FormData(); fd.append('thumbnail', thumbBlob, 'thumb.jpg');
                await post(`${base}/thumbnail`, fd).catch(() => {});
            }
            const done = await post(`${base}/finish`, { duration: Math.round(elapsedMs / 100) / 10 }, true);
            els.upText.textContent = 'Done! Opening your video…';
            window.removeEventListener('beforeunload', warnLeave);
            location.href = done.url;
        } catch (e) {
            console.error(e); dlog('upload failed: ' + e.message + ' ' + JSON.stringify(e.body || {}));
            els.upFail.hidden = false; els.upText.textContent = 'Stopped at ' + els.bar.style.width;
        }
    }

    function saveLocal() {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(finalBlob);
        a.download = 'recording.' + (mimeType.startsWith('video/mp4') ? 'mp4' : 'webm');
        a.click();
    }

    els.share.addEventListener('click', chooseScreen);
    els.change.addEventListener('click', chooseScreen);
    els.start.addEventListener('click', startRecording);
    els.pause.addEventListener('click', togglePause);
    els.stop.addEventListener('click', stopRecording);
    els.cancel.addEventListener('click', cancelRecording);
    els.retry.addEventListener('click', upload);
    els.saveLocal.addEventListener('click', saveLocal);

    // ---------- init ----------
    (async () => {
        if (!navigator.mediaDevices) { showError('Recording needs a secure page (https or localhost).'); els.share.disabled = true; return; }
        try {
            // Ask once so device names show up in the dropdowns.
            const s = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
            stopStream(s);
        } catch (e) { /* user may allow later or have no camera */ }
        await fillDevices();
        await startCamera();
        dlog('page ready, cam=' + !!camStream + ', docPiP=' + ('documentPictureInPicture' in window) + ', mime=' + pickMime());
    })();
})();
</script>
@endpush
