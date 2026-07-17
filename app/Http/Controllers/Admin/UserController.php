<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Regions an admin can assign to a user.
     *
     * @var list<string>
     */
    public const REGIONS = ['carib', 'networks'];

    /**
     * Assignable user roles.
     *
     * @var list<string>
     */
    public const ROLES = ['admin', 'viewer'];

    /**
     * Show the user management page.
     */
    public function index(): Response
    {
        return Inertia::render('users', [
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'region', 'role']),
            'regions' => self::REGIONS,
            'roles' => self::ROLES,
        ]);
    }

    /**
     * Create a new user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', Password::defaults()],
            'region' => ['required', Rule::in(self::REGIONS)],
            'role' => ['required', Rule::in(self::ROLES)],
        ]);

        User::create([
            ...$validated,
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('users.index');
    }

    /**
     * Update a user's region or role.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'region' => ['sometimes', 'required', Rule::in(self::REGIONS)],
            'role' => ['sometimes', 'required', Rule::in(self::ROLES)],
        ]);

        // Admins cannot remove their own admin role, so the app always keeps at least one admin
        if ($user->is($request->user()) && ($validated['role'] ?? 'admin') !== 'admin') {
            return back()->withErrors(['role' => __('You cannot remove your own admin role.')]);
        }

        $user->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('users.index');
    }
}
