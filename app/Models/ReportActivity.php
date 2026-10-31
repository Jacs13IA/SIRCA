<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'user_id',
        'type',
        'description',
        'metadata',
        'from_status_id',
        'to_status_id',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(ReportStatus::class, 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(ReportStatus::class, 'to_status_id');
    }
}
