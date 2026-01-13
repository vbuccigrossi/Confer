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
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('do_not_disturb_until')->nullable()->after('email_verified_at');
            $table->enum('default_notify_level', ['all', 'mentions', 'nothing'])->default('all')->after('do_not_disturb_until');
            $table->json('notification_keywords')->nullable()->after('default_notify_level');
            $table->time('quiet_hours_start')->nullable()->after('notification_keywords');
            $table->time('quiet_hours_end')->nullable()->after('quiet_hours_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'do_not_disturb_until',
                'default_notify_level',
                'notification_keywords',
                'quiet_hours_start',
                'quiet_hours_end',
            ]);
        });
    }
};
