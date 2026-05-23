<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

use App\Models\Organization;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Spatie\Permission\PermissionRegistrar;

#[Fillable(['name', 'email', 'password', 'phone', 'avatar', 'status', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Determine if the user can access the Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            $registrar = app(PermissionRegistrar::class);
            $originalTeamId = $registrar->getPermissionsTeamId();
            
            // Set team ID to null to check global roles
            $registrar->setPermissionsTeamId(null);
            $canAccess = $this->hasRole('administrator');
            
            // Restore original team ID
            $registrar->setPermissionsTeamId($originalTeamId);
            
            return $canAccess;
        }

        return false;
    }

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
            'last_login_at' => 'datetime',
        ];
    }

    public function organizations(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members')
            ->withPivot(['role_name', 'status', 'joined_at'])
            ->withTimestamps();
    }
}
