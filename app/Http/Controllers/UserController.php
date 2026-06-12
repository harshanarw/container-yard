<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:settings.users.view')->only(['index', 'show']);
        $this->middleware('can:settings.users.create')->only(['create', 'store']);
        $this->middleware('can:settings.users.edit')->only(['edit', 'update', 'resetPassword']);
        $this->middleware('can:settings.users.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->search, fn ($q, $search) =>
                $q->where(function ($q2) use ($search) {
                    $q2->where('first_name', 'like', "%{$search}%")
                       ->orWhere('last_name', 'like', "%{$search}%")
                       ->orWhere('name', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%")
                       ->orWhere('employee_reg_no', 'like', "%{$search}%");
                })
            )
            ->when($request->role,       fn ($q, $role)   => $q->where('role', $role))
            ->when($request->status,     fn ($q, $status) => $q->where('status', $status))
            ->when($request->department, fn ($q, $dept)   => $q->where('department', $dept))
            ->latest();

        if (!auth()->user()->isSystemAdmin()) {
            $users->where('role', '!=', 'system_administrator');
        }

        $users = $users->paginate(15)->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        return view('users.create');
    }

    public function store(StoreUserRequest $request)
    {
        if ($request->role === 'system_administrator' && !auth()->user()->isSystemAdmin()) {
            return back()->with('error', 'You do not have permission to create System Administrator accounts.');
        }

        $data = collect($request->validated())->except(['profile_photo', 'password'])->toArray();
        $data['name']     = trim(($request->first_name ?? '') . ' ' . ($request->last_name ?? ''));
        $data['password'] = Hash::make($request->password);

        if ($request->hasFile('profile_photo')) {
            $data['profile_photo'] = $request->file('profile_photo')->store('users/photos', 'public');
        }

        User::create($data);

        return redirect()->route('users.index')
            ->with('success', 'User created successfully.');
    }

    public function show(User $user)
    {
        $user->loadCount(['inspectedInquiries', 'createdEstimates', 'gateMovements']);

        return view('users.show', compact('user'));
    }

    public function edit(User $user)
    {
        return view('users.edit', compact('user'));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        if ($user->role === 'system_administrator' && !auth()->user()->isSystemAdmin()) {
            abort(403);
        }
        if ($request->role === 'system_administrator' && !auth()->user()->isSystemAdmin()) {
            abort(403);
        }

        $data = collect($request->validated())->except(['profile_photo', 'password', 'remove_photo'])->toArray();
        $data['name'] = trim(($request->first_name ?? '') . ' ' . ($request->last_name ?? ''));

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->boolean('remove_photo') && $user->profile_photo) {
            Storage::disk('public')->delete($user->profile_photo);
            $data['profile_photo'] = null;
        }

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo) {
                Storage::disk('public')->delete($user->profile_photo);
            }
            $data['profile_photo'] = $request->file('profile_photo')->store('users/photos', 'public');
        }

        $user->update($data);

        return redirect()->route('users.show', $user)
            ->with('success', 'User profile updated successfully.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $request->validate([
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return back()->with('success', "Password for {$user->full_name} has been reset successfully.");
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->role === 'system_administrator' && !auth()->user()->isSystemAdmin()) {
            abort(403);
        }

        if ($user->profile_photo) {
            Storage::disk('public')->delete($user->profile_photo);
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'User deleted successfully.');
    }
}
