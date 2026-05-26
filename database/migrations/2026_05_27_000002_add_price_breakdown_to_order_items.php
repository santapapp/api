<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            // base_price  = harga produk saja (snapshot dari menus.price)
            // variant_total = total tambahan dari semua selected variants
            // unit_price  = base_price + variant_total (harga per unit)
            // subtotal    = unit_price × quantity  (sudah ada, tapi semantik diperbarui)

            $table->decimal('base_price', 12, 2)->default(0)->after('price');
            $table->decimal('variant_total', 12, 2)->default(0)->after('base_price');
            $table->decimal('unit_price', 12, 2)->default(0)->after('variant_total');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['base_price', 'variant_total', 'unit_price']);
        });
    }
};
