<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class EmailSettingsController extends Controller
{
    /**
     * Show the email settings page.
     */
    public function index(Request $request, Workspace $workspace): Response
    {
        $this->authorize('viewAdmin', $workspace);

        $mailSettings = SystemSetting::getGroup('mail');

        // Don't expose actual password, just indicate if it's set
        $hasPassword = !empty($mailSettings['smtp_password'] ?? null);

        return Inertia::render('Admin/Email', [
            'workspace' => $workspace,
            'settings' => [
                'mail_driver' => $mailSettings['driver'] ?? 'smtp',
                'mail_host' => $mailSettings['smtp_host'] ?? '',
                'mail_port' => $mailSettings['smtp_port'] ?? '587',
                'mail_username' => $mailSettings['smtp_username'] ?? '',
                'mail_password_set' => $hasPassword,
                'mail_encryption' => $mailSettings['smtp_encryption'] ?? 'tls',
                'mail_from_address' => $mailSettings['from_address'] ?? '',
                'mail_from_name' => $mailSettings['from_name'] ?? config('app.name'),
            ],
            'presets' => $this->getEmailPresets(),
        ]);
    }

    /**
     * Update email settings.
     */
    public function update(Request $request, Workspace $workspace)
    {
        $this->authorize('viewAdmin', $workspace);

        $validated = $request->validate([
            'mail_driver' => 'required|in:smtp,sendmail,mailgun,ses,postmark,log',
            'mail_host' => 'required_if:mail_driver,smtp|nullable|string|max:255',
            'mail_port' => 'required_if:mail_driver,smtp|nullable|integer|min:1|max:65535',
            'mail_username' => 'nullable|string|max:255',
            'mail_password' => 'nullable|string|max:255',
            'mail_encryption' => 'nullable|in:tls,ssl,null',
            'mail_from_address' => 'required|email|max:255',
            'mail_from_name' => 'required|string|max:255',
        ]);

        // Save non-sensitive settings
        SystemSetting::set('mail', 'driver', $validated['mail_driver']);
        SystemSetting::set('mail', 'smtp_host', $validated['mail_host'] ?? '');
        SystemSetting::set('mail', 'smtp_port', $validated['mail_port'] ?? '587');
        SystemSetting::set('mail', 'smtp_username', $validated['mail_username'] ?? '');
        SystemSetting::set('mail', 'smtp_encryption', $validated['mail_encryption'] ?? 'tls');
        SystemSetting::set('mail', 'from_address', $validated['mail_from_address']);
        SystemSetting::set('mail', 'from_name', $validated['mail_from_name']);

        // Only update password if a new one is provided
        if (!empty($validated['mail_password'])) {
            SystemSetting::set('mail', 'smtp_password', $validated['mail_password'], true);
        }

        // Clear mail config cache
        SystemSetting::clearGroupCache('mail');

        return back()->with('success', 'Email settings updated successfully.');
    }

    /**
     * Send a test email.
     */
    public function test(Request $request, Workspace $workspace)
    {
        $this->authorize('viewAdmin', $workspace);

        $validated = $request->validate([
            'test_email' => 'required|email|max:255',
        ]);

        try {
            // Apply dynamic mail config
            $this->applyDynamicMailConfig();

            Mail::raw(
                "This is a test email from your Latch instance.\n\n" .
                "If you received this email, your email configuration is working correctly.\n\n" .
                "Sent at: " . now()->toDateTimeString(),
                function ($message) use ($validated) {
                    $fromAddress = SystemSetting::get('mail', 'from_address', config('mail.from.address'));
                    $fromName = SystemSetting::get('mail', 'from_name', config('mail.from.name'));

                    $message->to($validated['test_email'])
                        ->from($fromAddress, $fromName)
                        ->subject('Test Email from Latch');
                }
            );

            return back()->with('success', 'Test email sent successfully to ' . $validated['test_email']);
        } catch (\Exception $e) {
            return back()->withErrors([
                'test_email' => 'Failed to send test email: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Apply dynamic mail configuration from database.
     */
    private function applyDynamicMailConfig(): void
    {
        $mailSettings = SystemSetting::getGroup('mail');

        if (empty($mailSettings)) {
            return;
        }

        $driver = $mailSettings['driver'] ?? 'smtp';

        config([
            'mail.default' => $driver,
            'mail.mailers.smtp.host' => $mailSettings['smtp_host'] ?? config('mail.mailers.smtp.host'),
            'mail.mailers.smtp.port' => $mailSettings['smtp_port'] ?? config('mail.mailers.smtp.port'),
            'mail.mailers.smtp.username' => $mailSettings['smtp_username'] ?? config('mail.mailers.smtp.username'),
            'mail.mailers.smtp.password' => $mailSettings['smtp_password'] ?? config('mail.mailers.smtp.password'),
            'mail.mailers.smtp.encryption' => $mailSettings['smtp_encryption'] ?? config('mail.mailers.smtp.encryption'),
            'mail.from.address' => $mailSettings['from_address'] ?? config('mail.from.address'),
            'mail.from.name' => $mailSettings['from_name'] ?? config('mail.from.name'),
        ]);
    }

    /**
     * Get email provider presets.
     */
    private function getEmailPresets(): array
    {
        return [
            'gmail' => [
                'name' => 'Gmail / Google Workspace',
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'encryption' => 'tls',
                'note' => 'Use an App Password if 2FA is enabled',
            ],
            'outlook' => [
                'name' => 'Outlook / Office 365',
                'host' => 'smtp.office365.com',
                'port' => 587,
                'encryption' => 'tls',
                'note' => 'Use your Microsoft 365 email and password',
            ],
            'sendgrid' => [
                'name' => 'SendGrid',
                'host' => 'smtp.sendgrid.net',
                'port' => 587,
                'encryption' => 'tls',
                'note' => 'Username is "apikey", password is your API key',
            ],
            'mailgun' => [
                'name' => 'Mailgun',
                'host' => 'smtp.mailgun.org',
                'port' => 587,
                'encryption' => 'tls',
                'note' => 'Use your Mailgun SMTP credentials',
            ],
            'amazon_ses' => [
                'name' => 'Amazon SES',
                'host' => 'email-smtp.us-east-1.amazonaws.com',
                'port' => 587,
                'encryption' => 'tls',
                'note' => 'Update region in host as needed',
            ],
            'custom' => [
                'name' => 'Custom SMTP',
                'host' => '',
                'port' => 587,
                'encryption' => 'tls',
                'note' => 'Enter your SMTP server details',
            ],
        ];
    }
}
