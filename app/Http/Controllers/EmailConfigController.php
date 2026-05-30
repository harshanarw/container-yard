<?php

namespace App\Http\Controllers;

use App\Models\EmailConfig;
use Illuminate\Http\Request;

class EmailConfigController extends Controller
{
    public function index()
    {
        $configs = EmailConfig::orderBy('category')->orderByDesc('is_default')->get();

        $categories = [
            'general'         => 'General',
            'estimate'        => 'Repair Estimates',
            'invoice'         => 'Invoices',
            'stock_report'    => 'Stock Reports',
            'movement_report' => 'Movement Reports',
        ];

        return view('settings.email-config.index', compact('configs', 'categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:100'],
            'driver'            => ['required', 'in:smtp,mailgun,sendgrid'],
            'category'          => ['required', 'in:estimate,invoice,stock_report,movement_report,general'],
            'is_default'        => ['boolean'],
            'is_active'         => ['boolean'],
            'smtp_host'         => ['nullable', 'string', 'max:255'],
            'smtp_port'         => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption'   => ['nullable', 'in:tls,ssl,none'],
            'smtp_username'     => ['nullable', 'string', 'max:255'],
            'smtp_password'     => ['nullable', 'string', 'max:255'],
            'mailgun_domain'    => ['nullable', 'string', 'max:255'],
            'mailgun_secret'    => ['nullable', 'string', 'max:255'],
            'mailgun_endpoint'  => ['nullable', 'string', 'max:100'],
            'sendgrid_api_key'  => ['nullable', 'string', 'max:255'],
            'from_name'         => ['nullable', 'string', 'max:150'],
            'from_email'        => ['nullable', 'email', 'max:255'],
            'reply_to'          => ['nullable', 'email', 'max:255'],
        ]);

        if (!empty($data['is_default'])) {
            EmailConfig::where('category', $data['category'])->update(['is_default' => false]);
        }

        EmailConfig::create($data);

        return back()->with('success', 'Email configuration added.');
    }

    public function update(Request $request, EmailConfig $emailConfig)
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:100'],
            'driver'            => ['required', 'in:smtp,mailgun,sendgrid'],
            'category'          => ['required', 'in:estimate,invoice,stock_report,movement_report,general'],
            'is_default'        => ['boolean'],
            'is_active'         => ['boolean'],
            'smtp_host'         => ['nullable', 'string', 'max:255'],
            'smtp_port'         => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption'   => ['nullable', 'in:tls,ssl,none'],
            'smtp_username'     => ['nullable', 'string', 'max:255'],
            'smtp_password'     => ['nullable', 'string', 'max:255'],
            'mailgun_domain'    => ['nullable', 'string', 'max:255'],
            'mailgun_secret'    => ['nullable', 'string', 'max:255'],
            'mailgun_endpoint'  => ['nullable', 'string', 'max:100'],
            'sendgrid_api_key'  => ['nullable', 'string', 'max:255'],
            'from_name'         => ['nullable', 'string', 'max:150'],
            'from_email'        => ['nullable', 'email', 'max:255'],
            'reply_to'          => ['nullable', 'email', 'max:255'],
        ]);

        if (!empty($data['is_default'])) {
            EmailConfig::where('category', $data['category'])
                ->where('id', '!=', $emailConfig->id)
                ->update(['is_default' => false]);
        }

        // Don't overwrite secrets with empty strings
        foreach (['smtp_password', 'mailgun_secret', 'sendgrid_api_key'] as $field) {
            if (empty($data[$field])) {
                unset($data[$field]);
            }
        }

        $emailConfig->update($data);

        return back()->with('success', 'Email configuration updated.');
    }

    public function destroy(EmailConfig $emailConfig)
    {
        $emailConfig->delete();
        return back()->with('success', 'Email configuration deleted.');
    }

    public function test(Request $request, EmailConfig $emailConfig)
    {
        $request->validate(['test_email' => ['required', 'email']]);

        try {
            $this->applyMailerConfig($emailConfig);

            \Illuminate\Support\Facades\Mail::mailer('dynamic')->raw(
                "This is a test email from {$emailConfig->name}.",
                function ($message) use ($request, $emailConfig) {
                    $message->to($request->test_email)
                            ->subject('Test Email — ' . $emailConfig->name);
                    if ($emailConfig->from_email) {
                        $message->from($emailConfig->from_email, $emailConfig->from_name);
                    }
                }
            );

            return back()->with('success', "Test email sent to {$request->test_email}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Send failed: ' . $e->getMessage());
        }
    }

    private function applyMailerConfig(EmailConfig $config): void
    {
        $settings = match ($config->driver) {
            'smtp' => [
                'transport'  => 'smtp-no-verify',
                'host'       => $config->smtp_host,
                'port'       => $config->smtp_port ?? 587,
                'encryption' => $config->smtp_encryption === 'none' ? null : $config->smtp_encryption,
                'username'   => $config->smtp_username,
                'password'   => $config->smtp_password,
            ],
            'mailgun' => [
                'transport' => 'mailgun',
                'domain'    => $config->mailgun_domain,
                'secret'    => $config->mailgun_secret,
                'endpoint'  => $config->mailgun_endpoint,
            ],
            'sendgrid' => [
                'transport' => 'smtp',
                'host'      => 'smtp.sendgrid.net',
                'port'      => 587,
                'encryption'=> 'tls',
                'username'  => 'apikey',
                'password'  => $config->sendgrid_api_key,
            ],
            default => [],
        };

        config(['mail.mailers.dynamic' => $settings]);

        if ($config->from_email) {
            config(['mail.from.address' => $config->from_email]);
            config(['mail.from.name'    => $config->from_name ?? config('mail.from.name')]);
        }
    }
}
