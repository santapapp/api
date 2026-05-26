<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // 1. users — tambah is_superadmin & metadata
        // ============================================================
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_superadmin')->default(false)->after('last_login_at');
            $table->json('metadata')->nullable()->after('is_superadmin');

            $table->index('is_superadmin');
        });

        // ============================================================
        // 2. organizations — tambah info restoran, pajak, jam operasional
        // ============================================================
        Schema::table('organizations', function (Blueprint $table): void {
            // Identitas visual
            $table->string('logo')->nullable()->after('slug');
            $table->string('banner')->nullable()->after('logo');

            // Kontak
            $table->string('phone', 20)->nullable()->after('banner');
            $table->string('email')->nullable()->after('phone');

            // Alamat
            $table->text('address')->nullable()->after('email');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('province', 100)->nullable()->after('city');
            $table->string('postal_code', 10)->nullable()->after('province');

            // Koordinat GPS
            $table->decimal('latitude', 10, 8)->nullable()->after('postal_code');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');

            // Lokalisasi
            $table->string('timezone', 50)->default('Asia/Jakarta')->after('longitude');
            $table->string('currency', 3)->default('IDR')->after('timezone');

            // Pajak
            $table->boolean('tax_enabled')->default(false)->after('currency');
            $table->decimal('tax_rate', 5, 2)->default(11.00)->after('tax_enabled');

            // Service charge
            $table->boolean('service_charge_enabled')->default(false)->after('tax_rate');
            $table->decimal('service_charge_rate', 5, 2)->default(5.00)->after('service_charge_enabled');

            // JSON fleksibel
            $table->json('opening_hours')->nullable()->after('service_charge_rate');
            $table->json('settings')->nullable()->after('opening_hours');

            $table->index('city');
        });

        // ============================================================
        // 3. dining_tables — tambah code, capacity, location, metadata
        // ============================================================
        Schema::table('dining_tables', function (Blueprint $table): void {
            $table->string('code', 20)->nullable()->after('name');
            $table->unsignedTinyInteger('capacity')->nullable()->after('code');
            $table->string('location', 100)->nullable()->after('capacity');
            $table->json('metadata')->nullable()->after('location');

            // Kode meja unique per organisasi
            $table->unique(['organization_id', 'code'], 'dining_tables_org_code_unique');
        });

        // ============================================================
        // 4. menus — tambah image, sku, description, selection rules, metadata
        //    Tidak ada category. Tidak ada unique constraint untuk sku.
        // ============================================================
        Schema::table('menus', function (Blueprint $table): void {
            // Konten produk (hanya relevan untuk type=product)
            $table->string('image')->nullable()->after('name');
            $table->string('sku', 50)->nullable()->after('image');
            $table->text('description')->nullable()->after('sku');

            // Selection rules (hanya relevan untuk type=variant_group / addon_group)
            $table->boolean('is_required')->default(false)->after('is_available');
            $table->unsignedTinyInteger('min_select')->default(0)->after('is_required');
            $table->unsignedTinyInteger('max_select')->default(1)->after('min_select');

            // Data fleksibel
            $table->json('metadata')->nullable()->after('max_select');

            // Index (bukan unique — sku boleh null dan duplikat antar org)
            $table->index(['organization_id', 'type'], 'menus_org_type_index');
            $table->index(['organization_id', 'sku'], 'menus_org_sku_index');
        });

        // ============================================================
        // 5. orders — tambah customer info, snapshot, financial, cancel, metadata
        // ============================================================
        Schema::table('orders', function (Blueprint $table): void {
            // Info pelanggan (opsional)
            $table->string('customer_name', 100)->nullable()->after('created_by');
            $table->string('customer_phone', 20)->nullable()->after('customer_name');

            // Snapshot rate saat order dibuat — PENTING untuk histori akurat
            $table->decimal('tax_rate_snapshot', 5, 2)->default(0)->after('customer_phone');
            $table->decimal('service_charge_rate_snapshot', 5, 2)->default(0)->after('tax_rate_snapshot');

            // Financial breakdown
            $table->decimal('discount_amount', 12, 2)->default(0)->after('subtotal_amount');
            $table->decimal('tax_amount', 12, 2)->default(0)->after('discount_amount');
            $table->decimal('service_charge_amount', 12, 2)->default(0)->after('tax_amount');
            $table->decimal('change_amount', 12, 2)->default(0)->after('payment_amount');

            // Pembatalan
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete()->after('created_by');
            $table->string('cancel_reason')->nullable()->after('cancelled_by');
            $table->timestamp('cancelled_at')->nullable()->after('cancel_reason');

            // Metadata fleksibel
            $table->json('metadata')->nullable()->after('closed_at');

            // Index tambahan
            $table->index(['organization_id', 'payment_status'], 'orders_org_payment_status_index');
            $table->index(['organization_id', 'order_type'], 'orders_org_order_type_index');
        });

        // ============================================================
        // 6. order_items — tambah item_type, subtotal, metadata
        // ============================================================
        Schema::table('order_items', function (Blueprint $table): void {
            // Snapshot tipe item (product/variant/addon) — tidak perlu join ke menus saat query
            $table->string('item_type', 20)->default('product')->after('parent_item_id');

            // Denormalized: price * quantity — hemat re-kalkulasi
            $table->decimal('subtotal', 12, 2)->default(0)->after('quantity');

            // Snapshot metadata tambahan dari menus
            $table->json('metadata')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        // ── order_items ──
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['item_type', 'subtotal', 'metadata']);
        });

        // ── orders ──
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_org_payment_status_index');
            $table->dropIndex('orders_org_order_type_index');
            $table->dropForeign(['cancelled_by']);
            $table->dropColumn([
                'customer_name',
                'customer_phone',
                'tax_rate_snapshot',
                'service_charge_rate_snapshot',
                'discount_amount',
                'tax_amount',
                'service_charge_amount',
                'change_amount',
                'cancelled_by',
                'cancel_reason',
                'cancelled_at',
                'metadata',
            ]);
        });

        // ── menus ──
        Schema::table('menus', function (Blueprint $table): void {
            $table->dropIndex('menus_org_type_index');
            $table->dropIndex('menus_org_sku_index');
            $table->dropColumn(['image', 'sku', 'description', 'is_required', 'min_select', 'max_select', 'metadata']);
        });

        // ── dining_tables ──
        Schema::table('dining_tables', function (Blueprint $table): void {
            $table->dropUnique('dining_tables_org_code_unique');
            $table->dropColumn(['code', 'capacity', 'location', 'metadata']);
        });

        // ── organizations ──
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropIndex(['city']);
            $table->dropColumn([
                'logo', 'banner', 'phone', 'email',
                'address', 'city', 'province', 'postal_code',
                'latitude', 'longitude',
                'timezone', 'currency',
                'tax_enabled', 'tax_rate',
                'service_charge_enabled', 'service_charge_rate',
                'opening_hours', 'settings',
            ]);
        });

        // ── users ──
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_superadmin']);
            $table->dropColumn(['is_superadmin', 'metadata']);
        });
    }
};
