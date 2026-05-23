<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrganizationMemberResource\Pages;

use App\Filament\Resources\OrganizationMemberResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageOrganizationMembers extends ManageRecords
{
    protected static string $resource = OrganizationMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
