<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_variants', function (Blueprint $table): void {
            $table->id();

            // FK ke order_items — hapus row jika order item dihapus
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();

            // FK ke menus (nullable — jika menu dihapus, snapshot tetap ada)
            $table->foreignId('variant_group_id')->nullable()->constrained('menus')->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('menus')->nullOnDelete();

            // Snapshot nama — tidak tergantung menu yang masih ada
            $table->string('variant_group_name');
            $table->string('variant_name');

            // Harga tambahan dari variant (bisa 0 jika variant tidak menambah harga)
            $table->decimal('price', 12, 2)->default(0);

            $table->timestamps();

            // Index untuk load variants per order item
            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_variants');
    }
};
