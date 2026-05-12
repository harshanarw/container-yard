<?php

namespace App\Http\Controllers;

use App\Facades\Documents;
use App\Models\CloudStorageSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class CloudStorageSettingController extends Controller
{
    public function index()
    {
        $settings = CloudStorageSetting::current();
        return view('settings.cloud-storage.index', compact('settings'));
    }

    public function save(Request $request)
    {
        $request->validate([
            'provider' => ['required', 'in:local,dropbox,gdrive'],
            // Dropbox
            'dropbox_access_token' => ['nullable', 'required_if:provider,dropbox', 'string'],
            'dropbox_app_key'      => ['nullable', 'string', 'max:100'],
            'dropbox_app_secret'   => ['nullable', 'string', 'max:100'],
            'dropbox_root_folder'  => ['nullable', 'string', 'max:200'],
            // Google Drive
            'gdrive_client_id'     => ['nullable', 'required_if:provider,gdrive', 'string', 'max:200'],
            'gdrive_client_secret' => ['nullable', 'required_if:provider,gdrive', 'string', 'max:200'],
            'gdrive_folder_id'     => ['nullable', 'string', 'max:200'],
        ]);

        $settings = CloudStorageSetting::current();

        $data = ['provider' => $request->provider, 'updated_by' => auth()->id()];

        if ($request->provider === 'dropbox') {
            // Only update token fields if a new value was submitted (blank = keep existing)
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

        // Flush cached driver so next request picks up new settings
        Documents::flushDriver();

        return back()->with('success', 'Cloud storage settings saved.');
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
}
