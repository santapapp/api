<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom Nomor Penanda Pesanan ke organizations dan orders.
 *
 * organizations:
 *   - order_marker_mode       : 'disabled' | 'optional' | 'required'  (default: 'disabled')
 *   - order_marker_max_number : integer nullable — batas atas nomor yang diizinkan
 *
 * orders:
 *   - order_marker_number : integer nullable — nomor fisik yang diberikan kasir
 *
 * Catatan: tidak memakai ->after() agar migrasi aman di semua environment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('order_marker_mode')->default('disabled');
            $table->integer('order_marker_max_number')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->integer('order_marker_number')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['order_marker_mode', 'order_marker_max_number']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('order_marker_number');
        });
    }
};
