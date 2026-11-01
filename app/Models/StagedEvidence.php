<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StagedEvidence extends Model
{
    use SoftDeletes;

    protected $table = 'staged_evidences';

    protected $fillable = [
        'reference_hash',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}