<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('open_bill_id')->constrained('open_bills')->cascadeOnDelete();
            $table->foreignUuid('customer_session_id')->nullable()->constrained('customer_sessions')->nullOnDelete();
            $table->foreignId('dining_table_id')->constrained('dining_tables');
            $table->string('order_number');
            $table->string('source')->default('customer'); // customer, cashier, owner
            $table->string('status')->default('pending');
            $table->text('note')->nullable();
            $table->decimal('subtotal_amount', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
