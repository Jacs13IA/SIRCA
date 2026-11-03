<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CitizenMatchReview extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'citizen_id',
        'candidate_citizen_ids',
        'identity_key',
        'reason',
        'status',
        'resolution',
        'resolved_by',
        'resolved_at',
    ];

    protected $hidden = ['identity_key'];

    protected function casts(): array
    {
        return [
            'candidate_citizen_ids' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function citizen(): BelongsTo
    {
        return $this->belongsTo(Citizen::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
