<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ApiDocs extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected static \UnitEnum|string|null $navigationGroup = 'System';
    protected static ?int $navigationSort = 100;
    protected static ?string $title = 'API Documentation';
    protected static ?string $slug = 'docs';

    protected string $view = 'filament.pages.api-docs';

    public function getDocsContentProperty(): string
    {
        $path = base_path('docs/santap-api/12-client-api-flows.md');
        if (file_exists($path)) {
            return file_get_contents($path);
        }
        
        return '# Documentation Not Found';
    }
}
