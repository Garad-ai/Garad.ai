<?php

declare(strict_types=1);

namespace App\Extensions\VideoEditor\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoEditorExportJob extends Model
{
    protected $table = 'video_editor_export_jobs';

    protected $fillable = [
        'video_editor_project_id',
        'status',
        'format',
        'resolution',
        'progress',
        'output_path',
        'error_message',
        'credits_charged',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'progress'        => 'integer',
        'credits_charged' => 'integer',
        'started_at'      => 'datetime',
        'completed_at'    => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(VideoEditorProject::class, 'video_editor_project_id');
    }
}
