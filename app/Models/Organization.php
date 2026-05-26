<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'is_active',
        // Identitas visual
        'logo',
        'banner',
        // Kontak
        'phone',
        'email',
        // Alamat
        'address',
        'city',
        'province',
        'postal_code',
        // Koordinat
        'latitude',
        'longitude',
        // Lokalisasi
        'timezone',
        'currency',
        // Pajak
        'tax_enabled',
        'tax_rate',
        // Service charge
        'service_charge_enabled',
        'service_charge_rate',
        // JSON
        'opening_hours',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active'              => 'boolean',
            'latitude'               => 'decimal:8',
            'longitude'              => 'decimal:8',
            'tax_enabled'            => 'boolean',
            'tax_rate'               => 'decimal:2',
            'service_charge_enabled' => 'boolean',
            'service_charge_rate'    => 'decimal:2',
            'opening_hours'          => 'array',
            'settings'               => 'array',
        ];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members')
            ->wherePivot('role', 'owner')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function memberRecords(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function diningTables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Menu::class)->where('type', 'product')->whereNull('parent_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
