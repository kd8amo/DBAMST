<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * UC-32: List all users. Admin, Scheduler/Manager, Auditor only.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = User::with(['role'])
            ->when($request->filled('role_id'), fn ($q) => $q->where('role_id', $request->role_id))
            ->when($request->boolean('active_only', true), fn ($q) => $q->where('is_active', true))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'ilike', "%{$request->search}%")
                ->orWhere('email', 'ilike', "%{$request->search}%")
            ))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 50));

        return response()->json($users);
    }

    /**
     * UC-32: Show a single user. Admin can view any; others can view own profile.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return response()->json($user->load(['role']));
    }

    /**
     * UC-32: Create a user. Admin only.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(10)->mixedCase()->numbers()],
            'role_id'  => ['required', 'integer', 'exists:roles,id'],
            'locale'   => ['nullable', 'string', 'max:10'],
        ]);

        $user = User::create([
            'name'       => $validated['name'],
            'email'      => $validated['email'],
            'password'   => Hash::make($validated['password']),
            'role_id'    => $validated['role_id'],
            'locale'     => $validated['locale'] ?? 'en',
            'is_active'  => true,
            'created_by' => $request->user()->id,
        ]);

        AuditLog::recordForUser(
            $request->user(),
            'user.created',
            'user',
            $user->id,
            "User '{$user->email}' created with role ID {$user->role_id}"
        );

        return response()->json($user->load(['role']), 201);
    }

    /**
     * UC-32: Update a user's details or role. Admin only.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name'    => ['sometimes', 'string', 'max:120'],
            'email'   => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'role_id' => ['sometimes', 'integer', 'exists:roles,id'],
            'locale'  => ['nullable', 'string', 'max:10'],
        ]);

        $user->update($validated);

        AuditLog::recordForUser(
            $request->user(),
            'user.updated',
            'user',
            $user->id,
            "User '{$user->email}' updated"
        );

        return response()->json($user->fresh(['role']));
    }

    /**
     * UC-32: Deactivate a user. Admin only. Users are never deleted.
     */
    public function deactivate(Request $request, User $user): JsonResponse
    {
        $this->authorize('deactivate', $user);

        // Prevent deactivating yourself.
        if ($user->id === $request->user()->id) {
            return response()->json(['error' => 'You cannot deactivate your own account.'], 422);
        }

        $user->update(['is_active' => false]);

        AuditLog::recordForUser(
            $request->user(),
            'user.deactivated',
            'user',
            $user->id,
            "User '{$user->email}' deactivated"
        );

        return response()->json(['message' => "User '{$user->email}' deactivated."]);
    }

    /**
     * Allow a user to update their own password and locale preference.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'locale'           => ['nullable', 'string', 'max:10'],
            'current_password' => ['required_with:password', 'string'],
            'password'         => ['nullable', Password::min(10)->mixedCase()->numbers(), 'confirmed'],
        ]);

        if (! empty($validated['password'])) {
            if (! Hash::check($validated['current_password'], $user->password)) {
                return response()->json(['error' => 'Current password is incorrect.'], 422);
            }
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        if (! empty($validated['locale'])) {
            $user->update(['locale' => $validated['locale']]);
        }

        return response()->json($user->fresh(['role']));
    }

    /**
     * UC-35: Login — issue a Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['error' => 'Invalid credentials.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['error' => 'Account is deactivated.'], 403);
        }

        // Revoke all previous tokens and issue a fresh one.
        $user->tokens()->delete();
        $token = $user->createToken('gui-session')->plainTextToken;

        AuditLog::recordForUser(
            $user,
            'user.login',
            'user',
            $user->id,
            "User '{$user->email}' logged in"
        );

        return response()->json([
            'token' => $token,
            'user'  => $user->load(['role']),
        ]);
    }

    /**
     * Logout — revoke current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
