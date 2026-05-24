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
        // DROP semua tabel lama yang tidak dipakai lagi
        // ============================================================
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('customer_sessions');
        Schema::dropIfExists('open_bills');
        Schema::dropIfExists('table_qr_codes');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('menu_categories');
        Schema::dropIfExists('dining_tables');
        Schema::dropIfExists('organization_invitations');
        Schema::dropIfExists('organization_members');
        Schema::dropIfExists('organizations');

        // Drop Spatie activity log & permission tables (akan di-re-create oleh Spatie jika perlu)
        Schema::dropIfExists('activity_log');
        Schema::dropIfExists('media');

        // Drop Pulse tables
        Schema::dropIfExists('pulse_values');
        Schema::dropIfExists('pulse_entries');
        Schema::dropIfExists('pulse_aggregates');

        // ============================================================
        // 1. organizations
        // ============================================================
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ============================================================
        // 2. organization_members
        // ============================================================
        Schema::create('organization_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20); // owner, cashier, kitchen
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
        });

        // ============================================================
        // 3. dining_tables
        // ============================================================
        Schema::create('dining_tables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('qr_token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ============================================================
        // 4. menus (self-referential)
        // ============================================================
        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menus')->cascadeOnDelete();
            $table->string('type', 30); // product, variant_group, variant, addon_group, addon
            $table->string('name');
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('is_available')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ============================================================
        // 5. orders (pusat transaksi)
        // ============================================================
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->string('public_token', 64)->unique()->nullable();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dining_table_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Status
            $table->string('order_type', 20);       // table_order, cashier_order, open_bill
            $table->string('bill_status', 20);       // none, open, closed
            $table->string('order_status', 20);      // pending, confirmed, preparing, ready, completed, cancelled
            $table->string('payment_status', 20);    // unpaid, pending, paid, failed, cancelled

            // Payment (inline)
            $table->string('payment_method', 20)->nullable(); // qris, cash
            $table->string('payment_reference')->nullable();
            $table->decimal('payment_amount', 12, 2)->default(0);
            $table->decimal('subtotal_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);

            $table->text('note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['organization_id', 'bill_status']);
            $table->index(['organization_id', 'order_status']);
            $table->index(['dining_table_id', 'bill_status']);
        });

        // ============================================================
        // 6. order_items (snapshot)
        // ============================================================
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_item_id')->nullable()->constrained('order_items')->cascadeOnDelete();
            $table->string('name');           // snapshot nama menu
            $table->decimal('price', 12, 2);  // snapshot harga menu
            $table->integer('quantity')->default(1);
            $table->string('item_status', 20)->default('pending');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'item_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('dining_tables');
        Schema::dropIfExists('organization_members');
        Schema::dropIfExists('organizations');
    }
};
