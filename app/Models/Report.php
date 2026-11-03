<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'folio',
        'citizen_id',
        'department_id',
        'report_type_id',
        'report_status_id',
        'description',
        'location',
        'latitude',
        'longitude',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'closed_at' => 'datetime',
        ];
    }

    public function citizen(): BelongsTo
    {
        return $this->belongsTo(Citizen::class)->withTrashed();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function reportType(): BelongsTo
    {
        return $this->belongsTo(ReportType::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ReportStatus::class, 'report_status_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ReportAssignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(ReportAssignment::class)
            ->whereNull('unassigned_at')
            ->latestOfMany('assigned_at');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ReportActivity::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
