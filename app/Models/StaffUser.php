<?php

namespace App\Models;

use App\Concerns\HasAvatar;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class StaffUser extends Authenticatable
{
    use HasAvatar, Notifiable;

    protected $fillable = [
        'personnel_id',
        'name',
        'last_name',
        'first_name',
        'middle_name',
        'suffix',
        'email',
        'email_verified_at',
        'email_verification_code',
        'email_verification_expires_at',
        'username',
        'phone',
        'password',
        'role',
        'employee_number',
        'department',
        'avatar_path',
        'status',
        'reset_token',
        'reset_token_expires_at',
    ];

    protected $hidden = ['password', 'reset_token', 'email_verification_code'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'email_verification_expires_at' => 'datetime',
            'reset_token_expires_at' => 'datetime',
        ];
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Registrations HR should review (email already confirmed).
     */
    public function scopeAwaitingHrApproval($query)
    {
        return $query->where('status', 'pending')->whereNotNull('email_verified_at');
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function locatorRequests(): HasMany
    {
        return $this->hasMany(LocatorRequest::class);
    }

    public function travelOrders(): HasMany
    {
        return $this->hasMany(TravelOrder::class);
    }

    public function faceEnrollments(): HasMany
    {
        return $this->hasMany(FaceEnrollment::class);
    }

    public function emailChangeRequests(): HasMany
    {
        return $this->hasMany(EmailChangeRequest::class);
    }

    /**
     * Build the display name ("Juan P. Dela Cruz Jr.") from the separate name fields.
     */
    public static function composeName(string $last, string $first, ?string $middle = null, ?string $suffix = null): string
    {
        return trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([$first, $middle, $last, $suffix]))) ?? '');
    }

    /**
     * Surname-first form used on DepEd forms ("DELA CRUZ, JUAN P. JR.").
     */
    public function surnameFirstName(): string
    {
        $rest = trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->suffix])));

        return $rest === '' ? (string) $this->last_name : $this->last_name.', '.$rest;
    }

    /**
     * Last / First / Middle for official letters. Keeps double given names together
     * ("Mark Ian") instead of pushing the second given name into Middle.
     *
     * @return array{last:string,first:string,middle:string}
     */
    public function formNameParts(): array
    {
        if (filled($this->last_name) || filled($this->first_name)) {
            $last = strtoupper(trim((string) $this->last_name));
            if (filled($this->suffix)) {
                $last = trim($last.' '.strtoupper((string) $this->suffix));
            }

            return [
                'last' => $last,
                'first' => strtoupper(trim((string) $this->first_name)),
                'middle' => strtoupper(trim((string) $this->middle_name)),
            ];
        }

        return static::splitDisplayName((string) $this->name);
    }

    /**
     * Printed name on letters: LAST FIRST MIDDLE — e.g. "CRISTOBAL MARK IAN CABALDO".
     */
    public function officialLetterName(): string
    {
        $parts = $this->formNameParts();

        return trim(implode(' ', array_filter([
            $parts['last'],
            $parts['first'],
            $parts['middle'],
        ], fn ($part) => $part !== '')));
    }

    /**
     * @return array{last:string,first:string,middle:string}
     */
    public static function splitDisplayName(string $fullName): array
    {
        $fullName = trim(preg_replace('/\s+/', ' ', $fullName) ?? '');
        if ($fullName === '') {
            return ['last' => '', 'first' => '', 'middle' => ''];
        }

        if (str_contains($fullName, ',')) {
            [$last, $rest] = array_map('trim', explode(',', $fullName, 2));
            $restParts = $rest === '' ? [] : explode(' ', $rest);
            if (count($restParts) >= 3) {
                $middle = array_pop($restParts);
                $first = implode(' ', $restParts);
            } else {
                $first = array_shift($restParts) ?? '';
                $middle = implode(' ', $restParts);
            }

            return [
                'last' => strtoupper($last),
                'first' => strtoupper($first),
                'middle' => strtoupper($middle),
            ];
        }

        $parts = explode(' ', $fullName);
        $suffix = '';
        if (count($parts) > 2 && in_array(strtoupper((string) end($parts)), ['JR', 'JR.', 'SR', 'SR.', 'II', 'III', 'IV', 'V'], true)) {
            $suffix = ' '.array_pop($parts);
        }

        if (count($parts) === 1) {
            return ['last' => strtoupper($parts[0].$suffix), 'first' => '', 'middle' => ''];
        }

        // 4+ tokens: "Mark Ian Cabaldo Cristobal" → First MARK IAN, Middle CABALDO, Last CRISTOBAL
        if (count($parts) >= 4) {
            $last = array_pop($parts);
            $middle = array_pop($parts);

            return [
                'last' => strtoupper($last.$suffix),
                'first' => strtoupper(implode(' ', $parts)),
                'middle' => strtoupper((string) $middle),
            ];
        }

        $last = array_pop($parts);
        $first = array_shift($parts);
        $middle = implode(' ', $parts);

        return [
            'last' => strtoupper($last.$suffix),
            'first' => strtoupper((string) $first),
            'middle' => strtoupper($middle),
        ];
    }

    /**
     * Next unused 00-001 style number across staff and personnel records.
     */
    public static function nextEmployeeNumber(): string
    {
        $numbers = static::query()->whereNotNull('employee_number')->pluck('employee_number')
            ->merge(Personnel::query()->whereNotNull('employee_number')->pluck('employee_number'));

        $max = 0;
        foreach ($numbers as $value) {
            if (preg_match('/(\d+)\s*$/', (string) $value, $match)) {
                $max = max($max, (int) $match[1]);
            }
        }

        return '00-'.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'employee' => 'Employee',
            'teacher' => 'Teacher',
            'non_teaching' => 'Non-Teaching Staff',
            'cleaning_staff' => 'Cleaning Staff',
            default => ucfirst($this->role),
        };
    }

    /**
     * Cleaning staff may view attendance only — no leave, locator, travel, 201, or schedule.
     */
    public function isAttendanceOnly(): bool
    {
        return $this->role === 'cleaning_staff';
    }

    public function isNonTeaching(): bool
    {
        if ($this->role === 'non_teaching') {
            return true;
        }

        $position = strtoupper(trim((string) $this->personnel?->role));

        return $position !== '' && (bool) preg_match('/^(ADAS|AO|ASSP|REGISTRAR)\b/', $position);
    }
}
