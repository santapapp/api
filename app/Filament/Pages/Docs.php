<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Str;

class Docs extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.pages.docs';

    protected static ?string $title = 'API Documentation';

    protected static \UnitEnum|string|null $navigationGroup = 'System Audit';

    protected static ?string $navigationLabel = 'API Docs';

    protected static ?string $slug = 'docs';

    public string $currentDoc = 'index';

    public function getDocsList(): array
    {
        return [
            'index' => '00. Indeks Utama',
            '00-overview' => '01. Overview Produk dan Konteks',
            '01-multi-organization-and-permissions' => '02. Multi-Organisasi, Role, & Permission',
            '02-authentication-and-sessions' => '03. Autentikasi dan Session',
            '03-core-workflows' => '04. Alur Sistem Utama',
            '04-database-schema' => '05. Skema Database Awal',
            '05-api-design' => '06. API Design',
            '06-middleware-and-data-scoping' => '07. Middleware & Data Scoping',
            '07-realtime-queue-and-admin' => '08. Realtime, Queue, & Admin Panel',
            '08-laravel-implementation-rules' => '09. Struktur Laravel, Enum, & Security',
            '09-neon-and-installation' => '10. Neon & Instalasi Awal',
            '10-roadmap-and-mvp' => '11. Rencana Implementasi & MVP',
            '11-decisions-and-notes' => '12. Keputusan Final & Catatan',
            '12-client-api-flows' => '13. Alur API (Flutter & Web Customer)',
        ];
    }

    public function getHtmlContent(): string
    {
        $file = $this->currentDoc;
        $path = $file === 'index' 
            ? base_path('docs/santap-api.md') 
            : base_path("docs/santap-api/{$file}.md");

        if (!file_exists($path)) {
            return '<p class="text-danger">Dokumentasi tidak ditemukan.</p>';
        }

        $markdown = file_get_contents($path);

        // Render markdown to HTML
        return Str::markdown($markdown);
    }

    public function setDoc(string $doc): void
    {
        if (array_key_exists($doc, $this->getDocsList())) {
            $this->currentDoc = $doc;
        }
    }
}
