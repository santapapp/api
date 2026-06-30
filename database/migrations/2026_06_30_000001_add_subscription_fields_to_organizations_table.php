<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('plan', 20)->default('free')->after('is_active');               // free, basic, pro, enterprise
            $table->string('subscription_status', 20)->default('trial')->after('plan');    // trial, active, expired, suspended
            $table->timestamp('subscription_expires_at')->nullable()->after('subscription_status');

            $table->index('subscription_status');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropIndex(['subscription_status']);
            $table->dropColumn(['plan', 'subscription_status', 'subscription_expires_at']);
        });
    }
};
