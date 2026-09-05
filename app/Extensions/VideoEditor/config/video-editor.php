<?php

return [
    'version'                    => 1.0,
    'max_upload_size_mb'         => 500,
    'allowed_video_types'        => ['mp4', 'webm', 'mov', 'avi'],
    'allowed_audio_types'        => ['mp3', 'wav', 'ogg', 'aac'],
    'allowed_image_types'        => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    'export_credit_cost'         => [
        '720p'  => 1,
        '1080p' => 2,
        '4k'    => 5,
    ],
    'ffmpeg_path'                  => env('FFMPEG_PATH', '/usr/bin/ffmpeg'),
    'ffprobe_path'                 => env('FFPROBE_PATH', '/usr/bin/ffprobe'),
    'export_timeout_seconds'       => 3600,
    'max_project_duration_seconds' => 600,

    // Per-clip intermediate renders and downloaded source files live here.
    // Files are reused across exports when the clip hash matches.
    'export_cache_dir'             => env('VIDEO_EDITOR_EXPORT_CACHE_DIR', sys_get_temp_dir() . '/ve-clip-cache'),
    'export_source_cache_dir'      => env('VIDEO_EDITOR_SOURCE_CACHE_DIR', sys_get_temp_dir() . '/ve-src'),
    'export_work_dir'              => env('VIDEO_EDITOR_WORK_DIR', sys_get_temp_dir() . '/ve-export'),
    'export_cache_max_bytes'       => 5 * 1024 * 1024 * 1024,
    'export_cache_max_age_seconds' => 7 * 86400,

    // Bumping this invalidates every cached clip intermediate. Increment when
    // the per-clip filter formula changes in TimelineComposerService.
    'export_clip_cache_version'    => 2,
];
