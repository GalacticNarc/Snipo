<?php

namespace App\Models;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Video extends Model
{
    protected $fillable = ['user_id', 'slug', 'title', 'mime', 'extension', 'size', 'chunks', 'duration', 'views', 'has_thumbnail', 'status'];

    protected $casts = [
        'has_thumbnail' => 'boolean',
        'duration' => 'float',
    ];

    public static function newSlug(): string
    {
        do {
            $slug = Str::lower(Str::random(10));
        } while (static::where('slug', $slug)->exists());

        return $slug;
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(config('snipo.disk'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->user_id === $user->id;
    }

    /** Path of the finished video on the storage disk. */
    public function fileKey(): string
    {
        return "videos/{$this->slug}.{$this->extension}";
    }

    public function thumbKey(): string
    {
        return "thumbs/{$this->slug}.jpg";
    }

    /** Local temp file the chunks are appended to while uploading. */
    public function tempPath(): string
    {
        return storage_path("app/private/uploads/{$this->slug}.part");
    }

    public function durationLabel(): string
    {
        if (! $this->duration) {
            return '–:––';
        }
        $s = (int) round($this->duration);

        return sprintf('%d:%02d', intdiv($s, 60), $s % 60);
    }

    public function sizeLabel(): string
    {
        $mb = $this->size / 1048576;

        return $mb >= 1 ? number_format($mb, 1).' MB' : number_format($this->size / 1024, 0).' KB';
    }
}
