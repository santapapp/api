<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('order_item_variants');
    }

    public function down(): void
    {
        // Tidak di-restore — table ini sudah digantikan oleh order_items.metadata
        // Jika perlu rollback, jalankan migration 2026_05_27_000001 secara manual.
    }
};
