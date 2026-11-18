<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Video extends Model
{
    protected $fillable = ['slug', 'title', 'mime', 'extension', 'size', 'chunks', 'duration', 'views', 'has_thumbnail', 'status'];

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

    public function filePath(): string
    {
        return storage_path("app/private/videos/{$this->slug}.{$this->extension}");
    }

    public function thumbPath(): string
    {
        return storage_path("app/private/videos/{$this->slug}.jpg");
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
