<?php

namespace App\Http\Controllers;

use App\Models\EmailConfig;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmailConfigController extends Controller
{
    public function index(Request $request)
    {
        $allConfigs = EmailConfig::orderBy('category')->orderByDesc('is_default')->get();

        // External (customer-facing) sender configs and the per-category
        // internal senders are managed in their respective tabs.
        $configs         = $allConfigs->where('scope', '!=', 'internal')->values();
        $internalConfigs = $allConfigs->where('scope', 'internal')->values();

        $categories = collect(config('email_categories.external'))
            ->map(fn ($c) => $c['label'])
            ->all();

        $customerSearch     = $request->input('customer_search', '');
        $selectedCustomerId = $request->integer('customer_id');
        $customerResults    = collect();
        $selectedCustomer   = null;

        if ($selectedCustomerId) {
            $selectedCustomer = \App\Models\Customer::with('emailContacts')->find($selectedCustomerId);
        }

        if ($customerSearch) {
            $customerResults = \App\Models\Customer::where('name', 'like', "%{$customerSearch}%")
                ->orWhere('code', 'like', "%{$customerSearch}%")
                ->orderBy('name')->limit(20)->get();
        }

        return view('settings.email-config.index', compact(
            'configs', 'internalConfigs', 'categories',
            'customerSearch', 'customerResults', 'selectedCustomer',
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules($request));

        $data['scope']     = $data['scope'] ?? 'external';
        $data['cc_emails'] = $this->parseCcEmails($request->input('cc_emails'));

        if (!empty($data['is_default'])) {
            EmailConfig::where('category', $data['category'])
                ->where('scope', $data['scope'])
                ->update(['is_default' => false]);
        }

        EmailConfig::create($data);

        return redirect()->route('settings.email-config.index', ['tab' => $this->tabFor($data['scope'])])
            ->with('success', 'Email configuration added.');
    }

    public function update(Request $request, EmailConfig $emailConfig)
    {
        $data = $request->validate($this->rules($request));

        $data['scope']     = $data['scope'] ?? $emailConfig->scope ?? 'external';
        $data['cc_emails'] = $this->parseCcEmails($request->input('cc_emails'));

        if (!empty($data['is_default'])) {
            EmailConfig::where('category', $data['category'])
                ->where('scope', $data['scope'])
                ->where('id', '!=', $emailConfig->id)
                ->update(['is_default' => false]);
        }

        // Don't overwrite secrets with empty strings
        foreach (['smtp_password', 'mailgun_secret', 'sendgrid_api_key', 'oauth2_client_secret'] as $field) {
            if (empty($data[$field])) {
                unset($data[$field]);
            }
        }

        $emailConfig->update($data);

        return redirect()->route('settings.email-config.index', ['tab' => $this->tabFor($data['scope'])])
            ->with('success', 'Email configuration updated.');
    }

    /** Settings tab a given scope belongs to (drives post-save redirects). */
    private function tabFor(?string $scope): string
    {
        return $scope === 'internal' ? 'internal' : 'external';
    }

    /**
     * Shared validation rules for store/update. The valid category list depends
     * on the scope (internal vs external) and is derived from config so it stays
     * in sync with the rest of the email system.
     */
    private function rules(Request $request): array
    {
        $scope    = $request->input('scope') === 'internal' ? 'internal' : 'external';
        $catKeys  = array_keys(config("email_categories.{$scope}"));

        return [
            'name'              => ['required', 'string', 'max:100'],
            'driver'            => ['required', 'in:smtp,mailgun,sendgrid,microsoft365'],
            'category'          => ['required', Rule::in($catKeys)],
            'scope'             => ['nullable', 'in:external,internal'],
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
            'oauth2_tenant_id'  => ['nullable', 'string', 'max:255'],
            'oauth2_client_id'  => ['nullable', 'string', 'max:255'],
            'oauth2_client_secret' => ['nullable', 'string', 'max:512'],
            'from_name'         => ['nullable', 'string', 'max:150'],
            'from_email'        => ['nullable', 'email', 'max:255'],
            'reply_to'          => ['nullable', 'email', 'max:255'],
            'cc_emails'         => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Parse the newline/comma-separated common-CC textarea into a clean array
     * of valid, de-duplicated email addresses.
     *
     * @return string[]
     */
    private function parseCcEmails(?string $raw): array
    {
        if (blank($raw)) {
            return [];
        }

        $emails = preg_split('/[\r\n,;]+/', $raw) ?: [];
        $emails = array_filter(array_map('trim', $emails));
        $emails = array_filter($emails, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));

        return array_values(array_unique($emails));
    }

    public function destroy(EmailConfig $emailConfig)
    {
        $tab = $this->tabFor($emailConfig->scope);
        $emailConfig->delete();
        return redirect()->route('settings.email-config.index', ['tab' => $tab])
            ->with('success', 'Email configuration deleted.');
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

            return redirect()->route('settings.email-config.index', ['tab' => $this->tabFor($emailConfig->scope)])
                ->with('success', "Test email sent to {$request->test_email}.");
        } catch (\Throwable $e) {
            return redirect()->route('settings.email-config.index', ['tab' => $this->tabFor($emailConfig->scope)])
                ->with('error', 'Send failed: ' . $e->getMessage());
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
            'microsoft365' => [
                'transport'     => 'microsoft365',
                'host'          => $config->smtp_host ?? 'smtp.office365.com',
                'port'          => $config->smtp_port ?? 587,
                'username'      => $config->smtp_username,
                'tenant_id'     => $config->oauth2_tenant_id,
                'client_id'     => $config->oauth2_client_id,
                'client_secret' => $config->oauth2_client_secret,
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
