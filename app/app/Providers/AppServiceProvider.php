<?php

namespace App\Providers;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Apply dynamic mail configuration from database
        $this->configureMail();
    }

    /**
     * Configure mail settings from database.
     */
    private function configureMail(): void
    {
        // Only configure if the system_settings table exists
        // This prevents errors during migrations
        try {
            if (!Schema::hasTable('system_settings')) {
                return;
            }

            $mailSettings = SystemSetting::getGroup('mail');

            if (empty($mailSettings)) {
                return;
            }

            $driver = $mailSettings['driver'] ?? null;

            if ($driver) {
                config(['mail.default' => $driver]);
            }

            if (!empty($mailSettings['smtp_host'])) {
                config(['mail.mailers.smtp.host' => $mailSettings['smtp_host']]);
            }

            if (!empty($mailSettings['smtp_port'])) {
                config(['mail.mailers.smtp.port' => $mailSettings['smtp_port']]);
            }

            if (isset($mailSettings['smtp_username'])) {
                config(['mail.mailers.smtp.username' => $mailSettings['smtp_username']]);
            }

            if (!empty($mailSettings['smtp_password'])) {
                config(['mail.mailers.smtp.password' => $mailSettings['smtp_password']]);
            }

            if (isset($mailSettings['smtp_encryption'])) {
                $encryption = $mailSettings['smtp_encryption'];
                // Handle 'null' string value
                if ($encryption === 'null' || $encryption === '') {
                    $encryption = null;
                }
                config(['mail.mailers.smtp.encryption' => $encryption]);
            }

            if (!empty($mailSettings['from_address'])) {
                config(['mail.from.address' => $mailSettings['from_address']]);
            }

            if (!empty($mailSettings['from_name'])) {
                config(['mail.from.name' => $mailSettings['from_name']]);
            }
        } catch (\Exception $e) {
            // Silently fail during migrations or if database is not available
            // Log the error in production
            if (app()->bound('log')) {
                \Log::warning('Failed to load mail settings from database: ' . $e->getMessage());
            }
        }
    }
}
