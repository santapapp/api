<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Organization;
use App\Services\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::creating(function ($model) {
            if (empty($model->organization_id) && app()->bound(OrganizationContext::class)) {
                $context = app(OrganizationContext::class);
                if ($context->getOrganizationId()) {
                    $model->organization_id = $context->getOrganizationId();
                }
            }
        });

        static::addGlobalScope('organization', function (Builder $builder) {
            if (app()->bound(OrganizationContext::class)) {
                $context = app(OrganizationContext::class);
                if ($context->getOrganizationId()) {
                    // Scope query by organization_id
                    $builder->where($builder->getModel()->getTable() . '.organization_id', $context->getOrganizationId());
                }
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
