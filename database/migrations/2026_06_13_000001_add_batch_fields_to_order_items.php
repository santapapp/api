<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('order_items', 'batch_uuid')) {
                $table->uuid('batch_uuid')->nullable()->after('metadata')->index();
            }

            if (! Schema::hasColumn('order_items', 'batch_number')) {
                $table->unsignedInteger('batch_number')->nullable()->after('batch_uuid')->index();
            }

            if (! Schema::hasColumn('order_items', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('batch_number')->index();
            }
        });

        DB::table('order_items')
            ->select('order_id')
            ->distinct()
            ->orderBy('order_id')
            ->chunk(100, function ($orders): void {
                foreach ($orders as $order) {
                    $submittedAt = DB::table('order_items')
                        ->where('order_id', $order->order_id)
                        ->whereNull('batch_uuid')
                        ->min('created_at');

                    if ($submittedAt === null) {
                        continue;
                    }

                    DB::table('order_items')
                        ->where('order_id', $order->order_id)
                        ->whereNull('batch_uuid')
                        ->update([
                            'batch_uuid' => (string) Str::uuid(),
                            'batch_number' => 1,
                            'submitted_at' => $submittedAt,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            if (Schema::hasColumn('order_items', 'submitted_at')) {
                $table->dropColumn('submitted_at');
            }

            if (Schema::hasColumn('order_items', 'batch_number')) {
                $table->dropColumn('batch_number');
            }

            if (Schema::hasColumn('order_items', 'batch_uuid')) {
                $table->dropColumn('batch_uuid');
            }
        });
    }
};
