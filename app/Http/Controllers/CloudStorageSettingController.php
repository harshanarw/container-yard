<?php

namespace App\Http\Controllers;

use App\Facades\Documents;
use App\Models\CloudStorageSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CloudStorageSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:settings.cloud-storage.view')
             ->only(['index']);
        $this->middleware('can:settings.cloud-storage.edit')
             ->only(['save', 'test', 'gdriveAuth', 'gdriveCallback', 'dropboxAuth', 'dropboxCallback']);
    }

    public function index()
    {
        $settings = CloudStorageSetting::current();
        return view('settings.cloud-storage.index', compact('settings'));
    }

    public function save(Request $request)
    {
        $request->validate([
            'provider' => ['required', 'in:local,dropbox,gdrive'],
            'activate' => ['nullable', 'boolean'],
            // Dropbox
            'dropbox_app_key'      => ['nullable', 'string', 'max:100'],
            'dropbox_app_secret'   => ['nullable', 'string', 'max:100'],
            'dropbox_access_token' => ['nullable', 'string'],
            'dropbox_root_folder'  => ['nullable', 'string', 'max:200'],
            // Google Drive
            'gdrive_client_id'     => ['nullable', 'string', 'max:200'],
            'gdrive_client_secret' => ['nullable', 'string', 'max:200'],
            'gdrive_folder_id'     => ['nullable', 'string', 'max:200'],
        ]);

        $settings = CloudStorageSetting::current();

        // activate=1 means switch the active provider; activate=0 means save credentials only
        $activate = $request->boolean('activate', true);

        $data = ['updated_by' => auth()->id()];

        if ($activate) {
            $data['provider'] = $request->provider;
        }

        if ($request->provider === 'dropbox') {
            if ($request->filled('dropbox_access_token')) {
                $data['dropbox_access_token'] = $request->dropbox_access_token;
            }
            if ($request->filled('dropbox_app_key')) {
                $data['dropbox_app_key'] = $request->dropbox_app_key;
            }
            if ($request->filled('dropbox_app_secret')) {
                $data['dropbox_app_secret'] = $request->dropbox_app_secret;
            }
            $data['dropbox_root_folder'] = $request->dropbox_root_folder ?: '/container-yard';
        }

        if ($request->provider === 'gdrive') {
            if ($request->filled('gdrive_client_id')) {
                $data['gdrive_client_id'] = $request->gdrive_client_id;
            }
            if ($request->filled('gdrive_client_secret')) {
                $data['gdrive_client_secret'] = $request->gdrive_client_secret;
            }
            $data['gdrive_folder_id'] = $request->gdrive_folder_id;
            // Refresh token is set via OAuth callback; don't overwrite from form
        }

        $settings->update($data);

        Documents::flushDriver();

        $labels = ['local' => 'Internal', 'dropbox' => 'Dropbox', 'gdrive' => 'Google Drive'];

        if ($activate) {
            $label = $labels[$request->provider] ?? $request->provider;
            return back()->with('success', "{$label} storage is now active.");
        }

        return back()->with('success', 'External storage configuration saved.');
    }

    public function test(Request $request)
    {
        Documents::flushDriver();
        $result = Documents::testConnection();

        $settings = CloudStorageSetting::current();
        $settings->update([
            'tested_at'    => now(),
            'last_test_ok' => $result['ok'],
        ]);

        return response()->json($result);
    }

    // ── Google Drive OAuth2 ───────────────────────────────────────────────────

    public function gdriveAuth()
    {
        if (!class_exists(\Google\Client::class)) {
            return back()->with('error', 'Google client library not installed. Run: composer require google/apiclient');
        }

        $settings = CloudStorageSetting::current();

        $client = new \Google\Client();
        $client->setClientId($settings->gdrive_client_id);
        $client->setClientSecret($settings->gdrive_client_secret);
        $client->setRedirectUri(route('settings.cloud-storage.gdrive.callback'));
        $client->addScope(\Google\Service\Drive::DRIVE);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return redirect($client->createAuthUrl());
    }

    public function gdriveCallback(Request $request)
    {
        if (!class_exists(\Google\Client::class)) {
            return redirect()->route('settings.cloud-storage.index')
                ->with('error', 'Google client library not installed.');
        }

        if ($request->has('error')) {
            return redirect()->route('settings.cloud-storage.index')
                ->with('error', 'Google Drive authorization denied: ' . $request->error);
        }

        $settings = CloudStorageSetting::current();

        $client = new \Google\Client();
        $client->setClientId($settings->gdrive_client_id);
        $client->setClientSecret($settings->gdrive_client_secret);
        $client->setRedirectUri(route('settings.cloud-storage.gdrive.callback'));

        $token = $client->fetchAccessTokenWithAuthCode($request->code);

        if (isset($token['error'])) {
            return redirect()->route('settings.cloud-storage.index')
                ->with('error', 'Failed to get Google token: ' . $token['error_description']);
        }

        $settings->update([
            'gdrive_refresh_token' => $token['refresh_token'] ?? $settings->gdrive_refresh_token,
            'updated_by'           => auth()->id(),
        ]);

        Documents::flushDriver();

        return redirect()->route('settings.cloud-storage.index')
            ->with('success', 'Google Drive connected successfully.');
    }

    // ── Dropbox OAuth2 ────────────────────────────────────────────────────────

    public function dropboxAuth()
    {
        $settings = CloudStorageSetting::current();

        if (empty($settings->dropbox_app_key)) {
            return back()->with('error', 'Please save your Dropbox App Key first before connecting.');
        }

        $callbackUrl = route('settings.cloud-storage.dropbox.callback');

        $authUrl = 'https://www.dropbox.com/oauth2/authorize?' . http_build_query([
            'client_id'          => $settings->dropbox_app_key,
            'response_type'      => 'code',
            'token_access_type'  => 'offline',   // requests a refresh token
            'redirect_uri'       => $callbackUrl,
        ]);

        return redirect($authUrl);
    }

    public function dropboxCallback(Request $request)
    {
        if ($request->has('error')) {
            return redirect()->route('settings.cloud-storage.index')
                ->with('error', 'Dropbox authorization denied: ' . $request->get('error_description', $request->error));
        }

        $code = $request->get('code');
        if (!$code) {
            return redirect()->route('settings.cloud-storage.index')
                ->with('error', 'No authorization code received from Dropbox.');
        }

        try {
            $settings    = CloudStorageSetting::current();
            $callbackUrl = route('settings.cloud-storage.dropbox.callback');

            if (empty($settings->dropbox_app_key) || empty($settings->dropbox_app_secret)) {
                return redirect()->route('settings.cloud-storage.index')
                    ->with('error', 'Dropbox App Key and App Secret must be saved before connecting.');
            }

            // Exchange the authorization code for tokens
            $response = Http::asForm()->withBasicAuth(
                $settings->dropbox_app_key,
                $settings->dropbox_app_secret
            )->post('https://api.dropboxapi.com/oauth2/token', [
                'code'         => $code,
                'grant_type'   => 'authorization_code',
                'redirect_uri' => $callbackUrl,
            ]);

            if (! $response->successful()) {
                return redirect()->route('settings.cloud-storage.index')
                    ->with('error', 'Dropbox token exchange failed (HTTP ' . $response->status() . '): '
                        . ($response->json('error_description') ?? $response->body()));
            }

            $refreshToken = $response->json('refresh_token');
            $accessToken  = $response->json('access_token');

            if (empty($refreshToken)) {
                return redirect()->route('settings.cloud-storage.index')
                    ->with('error', 'Dropbox did not return a refresh token. Ensure your app has '
                        . '"token_access_type=offline" and the Dropbox app permissions are submitted.');
            }

            // Check the required columns exist (migration 035 guard)
            if (! \Schema::hasColumn('cloud_storage_settings', 'dropbox_refresh_token')) {
                return redirect()->route('settings.cloud-storage.index')
                    ->with('error', 'Database migration is pending. Please run: php artisan migrate — '
                        . 'then try connecting again.');
            }

            $settings->update([
                'dropbox_refresh_token'      => $refreshToken,
                'dropbox_access_token_cache' => $accessToken,
                'dropbox_token_expires_at'   => now()->addSeconds((int) ($response->json('expires_in') ?? 14400) - 60),
                'updated_by'                 => auth()->id(),
            ]);

            Documents::flushDriver();

            return redirect()->route('settings.cloud-storage.index')
                ->with('success', 'Dropbox connected successfully. Access token will auto-refresh.');

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return redirect()->route('settings.cloud-storage.index')
                ->with('error', 'Could not reach Dropbox API: ' . $e->getMessage()
                    . ' — check your server\'s outbound internet access.');
        } catch (\Throwable $e) {
            \Log::error('[DropboxCallback] ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->route('settings.cloud-storage.index')
                ->with('error', 'Dropbox callback error: ' . $e->getMessage());
        }
    }
}
