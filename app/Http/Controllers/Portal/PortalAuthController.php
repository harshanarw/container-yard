<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PortalAuthController extends Controller
{
    public function showLogin()
    {
        $company = CompanySetting::current();
        return view('portal.login', compact('company'));
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = \App\Models\User::where('email', $request->email)
            ->where('role', 'portal')
            ->where('status', 'active')
            ->first();

        if (!$user || !Hash::check($request->password, $user->portal_password ?? $user->password)) {
            return back()->withErrors(['email' => 'Invalid credentials.'])->withInput();
        }

        session(['portal_user_id' => $user->id, 'portal_customer_id' => $user->customer_id]);

        return redirect()->route('portal.dashboard')->with('success', 'Welcome back!');
    }

    public function logout(Request $request)
    {
        $request->session()->forget(['portal_user_id', 'portal_customer_id']);
        return redirect()->route('portal.login');
    }
}
