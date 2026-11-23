<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailChangeRequest extends Model
{
    protected $fillable = [
        'staff_user_id',
        'current_email',
        'new_email',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
