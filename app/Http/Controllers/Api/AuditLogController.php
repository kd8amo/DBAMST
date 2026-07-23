<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * UC-34: List audit log entries. Admin, Auditor, Scheduler/Manager only.
     * Supports filtering by entity type, entity ID, user, action, and date range.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $entries = AuditLog::with(['user'])
            ->when($request->filled('entity_type'),
                fn ($q) => $q->where('entity_type', $request->entity_type)
            )
            ->when($request->filled('entity_id'),
                fn ($q) => $q->where('entity_id', $request->entity_id)
            )
            ->when($request->filled('user_id'),
                fn ($q) => $q->where('user_id', $request->user_id)
            )
            ->when($request->filled('action'),
                fn ($q) => $q->where('action', 'ilike', "%{$request->action}%")
            )
            ->when($request->filled('from'),
                fn ($q) => $q->where('created_at', '>=', $request->from)
            )
            ->when($request->filled('to'),
                fn ($q) => $q->where('created_at', '<=', $request->to)
            )
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 100));

        return response()->json($entries);
    }

    /**
     * UC-34: Show the full audit trail for a specific entity.
     * e.g. GET /api/audit-log/device/42 returns all events for device 42.
     */
    public function forEntity(Request $request, string $entityType, int $entityId): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $entries = AuditLog::with(['user'])
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($entries);
    }
}
