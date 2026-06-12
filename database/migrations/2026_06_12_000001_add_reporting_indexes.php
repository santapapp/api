<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * PostgreSQL does not allow CREATE INDEX CONCURRENTLY inside a transaction.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS orders_org_payment_paid_at_idx ON orders (organization_id, payment_status, paid_at)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS orders_org_cancelled_at_idx ON orders (organization_id, cancelled_at)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS orders_org_created_payment_paid_idx ON orders (organization_id, created_by, payment_status, paid_at)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS order_items_order_parent_type_status_idx ON order_items (order_id, parent_item_id, item_type, item_status)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS order_items_order_parent_type_status_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS orders_org_created_payment_paid_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS orders_org_cancelled_at_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS orders_org_payment_paid_at_idx');
    }
};
