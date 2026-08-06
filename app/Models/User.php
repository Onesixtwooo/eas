<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'email_verified_at',
        'registration_verified_at',
        'registration_declined_at',
        'registration_decline_reason',
        'email_verification_otp',
        'email_verification_otp_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_otp',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'registration_verified_at' => 'datetime',
            'registration_declined_at' => 'datetime',
            'email_verification_otp_expires_at' => 'datetime',
        ];
    }

    public function student() { return $this->hasOne(Student::class); }
    public function faculty() { return $this->hasOne(Faculty::class); }
    public function hasRole(string ...$roles): bool { return in_array($this->role, $roles, true); }
    public function maskedAdministrativeName(): string
    {
        $parts = collect(preg_split('/\s+/', trim($this->name)))->filter()->values();
        $initials = $parts->count() > 1 ? [$parts->first(), $parts->last()] : [$parts->first()];

        return collect($initials)->filter()->map(fn ($part, $index) =>
            mb_strtoupper(mb_substr($part, 0, 1)).str_repeat('*', $index === 0 ? 4 : 5)
        )->implode(' ');
    }
}
