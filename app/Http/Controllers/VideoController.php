<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class VideoController extends Controller
{
    /** Library: the signed-in user's videos. */
    public function index(Request $request)
    {
        $videos = $request->user()->videos()->where('status', 'ready')->latest()->get();

        return view('videos.index', compact('videos'));
    }

    /** Recorder page. */
    public function record()
    {
        return view('videos.record', [
            'maxSeconds' => config('snipo.max_seconds'),
            'maxMb' => config('snipo.max_mb'),
        ]);
    }

    /** Start an upload: creates the DB row and returns the slug. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:150',
            'mime' => 'required|string|max:100',
        ]);

        $isMp4 = str_starts_with($data['mime'], 'video/mp4');
        if (! $isMp4 && ! str_starts_with($data['mime'], 'video/webm')) {
            abort(422, 'Unsupported video format.');
        }

        $video = $request->user()->videos()->create([
            'slug' => Video::newSlug(),
            'title' => ($data['title'] ?? null) ?: 'Recording '.now()->format('M j, Y g:i A'),
            'mime' => $isMp4 ? 'video/mp4' : 'video/webm',
            'extension' => $isMp4 ? 'mp4' : 'webm',
        ]);

        File::ensureDirectoryExists(dirname($video->tempPath()));

        return response()->json(['slug' => $video->slug]);
    }

    /** Append one chunk (chunks must arrive in order). */
    public function chunk(Request $request, Video $video)
    {
        $this->authorizeOwner($request, $video);
        abort_if($video->status !== 'uploading', 409, 'Upload already finished.');

        $request->validate([
            'index' => 'required|integer|min:0',
            'chunk' => 'required|file|max:8192', // KB
        ]);

        if ((int) $request->input('index') !== $video->chunks) {
            return response()->json(['expected' => $video->chunks], 409);
        }

        $maxBytes = config('snipo.max_mb') * 1048576;
        if ($maxBytes && $video->size + $request->file('chunk')->getSize() > $maxBytes) {
            return response()->json(['message' => 'Video is larger than '.config('snipo.max_mb').' MB.'], 413);
        }

        File::ensureDirectoryExists(dirname($video->tempPath()));
        $in = fopen($request->file('chunk')->getRealPath(), 'rb');
        $out = fopen($video->tempPath(), 'ab');
        $bytes = stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        $video->increment('chunks');
        $video->increment('size', $bytes);

        return response()->json(['received' => $video->chunks]);
    }

    /** Move the assembled upload to the storage disk and mark it ready. */
    public function finish(Request $request, Video $video)
    {
        $this->authorizeOwner($request, $video);
        $data = $request->validate(['duration' => 'nullable|numeric|min:0']);

        $temp = $video->tempPath();
        abort_unless(is_file($temp) && filesize($temp) > 0, 422, 'No video data received.');

        $stream = fopen($temp, 'rb');
        $saved = Video::disk()->writeStream($video->fileKey(), $stream, ['ContentType' => $video->mime]);
        if (is_resource($stream)) {
            fclose($stream);
        }
        abort_unless($saved, 500, 'Could not save the video. Please retry.');
        @unlink($temp);

        $video->update([
            'status' => 'ready',
            'duration' => $data['duration'] ?? null,
        ]);

        return response()->json(['url' => route('videos.show', $video)]);
    }

    public function thumbnail(Request $request, Video $video)
    {
        $this->authorizeOwner($request, $video);
        $request->validate(['thumbnail' => 'required|image|max:2048']);

        $saved = Video::disk()->put($video->thumbKey(), $request->file('thumbnail')->get(), ['ContentType' => 'image/jpeg']);
        $video->update(['has_thumbnail' => (bool) $saved]);

        return response()->noContent();
    }

    /** Public share page. */
    public function show(Request $request, Video $video)
    {
        abort_if($video->status !== 'ready', 404);

        return view('videos.show', [
            'video' => $video,
            'isOwner' => $video->isOwnedBy($request->user()),
        ]);
    }

    /** The video file. Local disk: streamed with Range support. Supabase: redirect to a short-lived signed URL. */
    public function file(Video $video)
    {
        abort_if($video->status !== 'ready', 404);

        return $this->serve($video->fileKey(), $video->mime);
    }

    public function download(Video $video)
    {
        abort_if($video->status !== 'ready', 404);
        $name = str($video->title)->slug()->whenEmpty(fn () => str('video')).'.'.$video->extension;

        if ($this->isLocalDisk()) {
            $path = Video::disk()->path($video->fileKey());
            abort_unless(is_file($path), 404);

            return response()->download($path, $name);
        }

        return redirect(Video::disk()->temporaryUrl($video->fileKey(), now()->addMinutes(30), [
            'ResponseContentDisposition' => 'attachment; filename="'.$name.'"',
        ]));
    }

    public function thumb(Video $video)
    {
        abort_unless($video->has_thumbnail, 404);

        return $this->serve($video->thumbKey(), 'image/jpeg');
    }

    public function view(Video $video)
    {
        $video->increment('views');

        return response()->json(['views' => $video->views]);
    }

    public function update(Request $request, Video $video)
    {
        $this->authorizeOwner($request, $video);
        $data = $request->validate(['title' => 'required|string|max:150']);
        $video->update($data);

        return response()->json(['title' => $video->title]);
    }

    public function destroy(Request $request, Video $video)
    {
        $this->authorizeOwner($request, $video);
        Video::disk()->delete([$video->fileKey(), $video->thumbKey()]);
        @unlink($video->tempPath());
        $video->delete();

        return redirect()->route('videos.index')->with('status', 'Video deleted.');
    }

    private function authorizeOwner(Request $request, Video $video): void
    {
        abort_unless($video->isOwnedBy($request->user()), 403);
    }

    private function isLocalDisk(): bool
    {
        return config('filesystems.disks.'.config('snipo.disk').'.driver') === 'local';
    }

    private function serve(string $key, string $mime)
    {
        if ($this->isLocalDisk()) {
            $path = Video::disk()->path($key);
            abort_unless(is_file($path), 404);

            return response()->file($path, ['Content-Type' => $mime, 'Cache-Control' => 'private, max-age=3600']);
        }

        return redirect(Video::disk()->temporaryUrl($key, now()->addHours(2)));
    }
}
