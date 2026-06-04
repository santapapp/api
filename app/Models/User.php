<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserStatus;
use App\Services\MediaService;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'status',
        'last_login_at',
        'is_superadmin',
        'metadata',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'status'            => UserStatus::class,
            'last_login_at'     => 'datetime',
            'is_superadmin'     => 'boolean',
            'metadata'          => 'array',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_superadmin === true;
    }

    /**
     * URL avatar untuk ditampilkan di account menu Filament.
     * Path relatif dikonversi ke URL penuh (konsisten dengan API).
     */
    public function getFilamentAvatarUrl(): ?string
    {
        return MediaService::toUrl($this->avatar);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }
}
