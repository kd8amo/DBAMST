<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SiteController extends Controller
{
    /**
     * UC-36: List all sites, with optional active-only filter.
     * Accessible by all authenticated users — site is a filter dimension,
     * not an access boundary.
     */
    public function index(Request $request): JsonResponse
    {
        $sites = Site::query()
            ->when($request->boolean('active_only', false), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        return response()->json($sites);
    }

    /**
     * UC-36: Show a single site.
     */
    public function show(Site $site): JsonResponse
    {
        return response()->json($site);
    }

    /**
     * UC-36: Create a new site. Admin only.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Site::class);

        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:120', 'unique:sites,name'],
            'code'    => ['required', 'string', 'max:10', 'unique:sites,code'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $site = Site::create($validated);

        AuditLog::recordForUser(
            $request->user(),
            'site.created',
            'site',
            $site->id,
            "Site '{$site->name}' created"
        );

        return response()->json($site, 201);
    }

    /**
     * UC-36: Update a site. Admin only.
     */
    public function update(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $validated = $request->validate([
            'name'      => ['sometimes', 'string', 'max:120', Rule::unique('sites')->ignore($site->id)],
            'code'      => ['sometimes', 'string', 'max:10', Rule::unique('sites')->ignore($site->id)],
            'address'   => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $site->update($validated);

        AuditLog::recordForUser(
            $request->user(),
            'site.updated',
            'site',
            $site->id,
            "Site '{$site->name}' updated"
        );

        return response()->json($site);
    }
}
