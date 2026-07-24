<?php
declare(strict_types=1);

namespace app\model\ai;

use core\base\Model;

class AiArtifact extends Model
{
    protected $name = 'ai_artifacts';

    protected $fillable = [
        'spec_id', 'stage_id', 'module', 'title', 'state',
        'check_summary', 'checked_at', 'applied_at', 'applied_files', 'error',
    ];

    protected $type = [
        'check_summary' => 'json',
        'applied_files' => 'json',
    ];
}
