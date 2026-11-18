<?php

return [
    // Filesystem disk for finished videos and thumbnails: "local" on your computer, "s3" (Supabase Storage) online.
    'disk' => env('VIDEO_DISK', 'local'),

    // Max size of one video in MB (0 = no limit). Supabase's free plan allows 50 MB per file.
    'max_mb' => (int) env('MAX_VIDEO_MB', 0),

    // Auto-stop recording after this many seconds (0 = no limit).
    'max_seconds' => (int) env('MAX_RECORDING_SECONDS', 0),
];
