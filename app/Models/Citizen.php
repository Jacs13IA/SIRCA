<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Citizen extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'email', 'phone', 'contact_consent'];

    protected $hidden = ['email_fingerprint', 'phone_fingerprint'];

    protected function casts(): array
    {
        return [
            'email' => 'encrypted',
            'phone' => 'encrypted',
            'contact_consent' => 'boolean',
        ];
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
