<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationMember;

class OrganizationContext
{
    protected ?Organization $organization = null;
    protected ?OrganizationMember $member = null;

    public function set(Organization $organization, ?OrganizationMember $member = null): void
    {
        $this->organization = $organization;
        $this->member = $member;
    }

    public function get(): ?Organization
    {
        return $this->organization;
    }

    public function getOrganizationId(): ?int
    {
        return $this->organization?->id;
    }

    public function getMember(): ?OrganizationMember
    {
        return $this->member;
    }

    public function getRole(): ?string
    {
        return $this->member?->role;
    }

    public function clear(): void
    {
        $this->organization = null;
        $this->member = null;
    }
}
