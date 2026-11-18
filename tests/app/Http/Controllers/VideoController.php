<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class VideoController extends Controller
{
    /** Library: all ready videos. */
    public function index()
    {
        $videos = Video::where('status', 'ready')->latest()->get();

        return view('videos.index', compact('videos'));
    }

    /** Recorder page. */
    public function record()
    {
        return view('videos.record');
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

        File::ensureDirectoryExists(storage_path('app/private/videos'));

        $video = Video::create([
            'slug' => Video::newSlug(),
            'title' => ($data['title'] ?? null) ?: 'Recording '.now()->format('M j, Y g:i A'),
            'mime' => $isMp4 ? 'video/mp4' : 'video/webm',
            'extension' => $isMp4 ? 'mp4' : 'webm',
        ]);

        return response()->json(['slug' => $video->slug]);
    }

    /** Append one chunk (chunks must arrive in order). */
    public function chunk(Request $request, Video $video)
    {
        abort_if($video->status !== 'uploading', 409, 'Upload already finished.');

        $request->validate([
            'index' => 'required|integer|min:0',
            'chunk' => 'required|file|max:8192', // KB
        ]);

        if ((int) $request->input('index') !== $video->chunks) {
            return response()->json(['expected' => $video->chunks], 409);
        }

        $in = fopen($request->file('chunk')->getRealPath(), 'rb');
        $out = fopen($video->filePath(), 'ab');
        $bytes = stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        $video->increment('chunks');
        $video->increment('size', $bytes);

        return response()->json(['received' => $video->chunks]);
    }

    /** Mark the upload as done. */
    public function finish(Request $request, Video $video)
    {
        $data = $request->validate(['duration' => 'nullable|numeric|min:0']);

        abort_unless(is_file($video->filePath()) && filesize($video->filePath()) > 0, 422, 'No video data received.');

        $video->update([
            'status' => 'ready',
            'duration' => $data['duration'] ?? null,
        ]);

        return response()->json(['url' => route('videos.show', $video)]);
    }

    public function thumbnail(Request $request, Video $video)
    {
        $request->validate(['thumbnail' => 'required|image|max:2048']);
        File::ensureDirectoryExists(dirname($video->thumbPath()));
        $request->file('thumbnail')->move(dirname($video->thumbPath()), basename($video->thumbPath()));
        $video->update(['has_thumbnail' => true]);

        return response()->noContent();
    }

    /** Public share page. */
    public function show(Video $video)
    {
        abort_if($video->status !== 'ready', 404);

        return view('videos.show', compact('video'));
    }

    /** Streams the video file (supports Range requests so seeking works). */
    public function file(Video $video)
    {
        abort_unless(is_file($video->filePath()), 404);

        return response()->file($video->filePath(), [
            'Content-Type' => $video->mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function download(Video $video)
    {
        abort_unless(is_file($video->filePath()), 404);

        return response()->download($video->filePath(), str($video->title)->slug().'.'.$video->extension);
    }

    public function thumb(Video $video)
    {
        abort_unless($video->has_thumbnail && is_file($video->thumbPath()), 404);

        return response()->file($video->thumbPath(), ['Content-Type' => 'image/jpeg']);
    }

    public function view(Video $video)
    {
        $video->increment('views');

        return response()->json(['views' => $video->views]);
    }

    public function update(Request $request, Video $video)
    {
        $data = $request->validate(['title' => 'required|string|max:150']);
        $video->update($data);

        return response()->json(['title' => $video->title]);
    }

    public function destroy(Video $video)
    {
        File::delete([$video->filePath(), $video->thumbPath()]);
        $video->delete();

        return redirect()->route('videos.index')->with('status', 'Video deleted.');
    }
}
