<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        // Nomor Penanda Pesanan
        'order_marker_mode',
        'order_marker_max_number',
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
            'order_marker_max_number' => 'integer',
            'opening_hours'          => 'array',
            'settings'               => 'array',
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────────────────────────

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

    public function cashiers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members')
            ->wherePivot('role', 'cashier')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function kitchenStaff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members')
            ->wherePivot('role', 'kitchen')
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

    // ──────────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Jumlah member berdasarkan role tertentu.
     */
    public function getMembersCountByRole(string $role): int
    {
        return $this->memberRecords()->where('role', $role)->count();
    }

    /**
     * Ringkasan komposisi tim dalam format string.
     * Contoh: "Owner: Budi | Cashier: Ani, Raka | Kitchen: Dimas"
     */
    public function getMembersSummary(): string
    {
        $membersByRole = $this->memberRecords()
            ->with('user:id,name')
            ->get()
            ->groupBy('role');

        $parts = [];

        foreach (['owner' => 'Owner', 'cashier' => 'Cashier', 'kitchen' => 'Kitchen'] as $role => $label) {
            if ($membersByRole->has($role)) {
                $names    = $membersByRole[$role]->pluck('user.name')->join(', ');
                $parts[]  = "{$label}: {$names}";
            }
        }

        return empty($parts) ? '—' : implode(' | ', $parts);
    }
}
