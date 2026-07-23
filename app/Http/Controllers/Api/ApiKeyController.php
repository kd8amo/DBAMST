<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    /**
     * UC-33: List all API keys. Admin only.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ApiKey::class);

        $keys = ApiKey::with(['createdBy'])
            ->when($request->boolean('active_only', true), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get()
            ->map(fn ($key) => array_merge($key->toArray(), [
                // Never return the raw token after creation.
                'token' => '••••••••',
            ]));

        return response()->json($keys);
    }

    /**
     * UC-33: Create a scoped API key. Admin only.
     * The plain-text token is returned ONCE at creation — it cannot be retrieved again.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ApiKey::class);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:120', 'unique:api_keys,name'],
            'scopes'      => ['required', 'array', 'min:1'],
            'scopes.*'    => ['string', 'in:read,write:usage,write:bookings,write:faults,write:maintenance,admin'],
            'expires_at'  => ['nullable', 'date', 'after:today'],
            'description' => ['nullable', 'string'],
        ]);

        // Generate a secure random token — stored as a hash, returned plain once.
        $plainToken  = 'tsm_' . Str::random(48);
        $tokenHash   = hash('sha256', $plainToken);

        $apiKey = ApiKey::create([
            'name'        => $validated['name'],
            'token_hash'  => $tokenHash,
            'scopes'      => $validated['scopes'],
            'expires_at'  => $validated['expires_at'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active'   => true,
            'created_by'  => $request->user()->id,
        ]);

        AuditLog::recordForUser(
            $request->user(),
            'api_key.created',
            'api_key',
            $apiKey->id,
            "API key '{$apiKey->name}' created with scopes: " . implode(', ', $validated['scopes'])
        );

        return response()->json([
            'api_key'     => $apiKey,
            'token'       => $plainToken,
            'notice'      => 'Store this token securely — it will not be shown again.',
        ], 201);
    }

    /**
     * UC-33: Revoke an API key. Admin only.
     */
    public function revoke(Request $request, ApiKey $apiKey): JsonResponse
    {
        $this->authorize('revoke', $apiKey);

        $apiKey->update(['is_active' => false]);

        AuditLog::recordForUser(
            $request->user(),
            'api_key.revoked',
            'api_key',
            $apiKey->id,
            "API key '{$apiKey->name}' revoked"
        );

        return response()->json(['message' => "API key '{$apiKey->name}' revoked."]);
    }
}
