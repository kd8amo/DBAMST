<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\DeviceStatus;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DeviceImportController extends Controller
{
    /**
     * UC-2: Download a blank CSV template for bulk device import.
     */
    public function template(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="device_import_template.csv"',
        ];

        $columns = [
            'manufacturer',
            'model',
            'serial_number',
            'category',        // e.g. measurement, load_emulation, emulation, automotive_comms, signal_generation
            'site_code',       // e.g. ML001
            'notes',
        ];

        return response()->stream(function () use ($columns) {
            $handle = fopen('php://output', 'w');
            // Header row
            fputcsv($handle, $columns);
            // Example row
            fputcsv($handle, ['Keysight', '34461A', 'MY12345678', 'measurement', 'ML001', 'Example device']);
            fclose($handle);
        }, 200, $headers);
    }

    /**
     * UC-2: Import devices from an uploaded CSV file.
     * Technician/Scheduler/Admin only.
     * Returns a results report: successes, failures with reasons.
     */
    public function import(Request $request): JsonResponse
    {
        $this->authorize('bulkImport', Device::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $file     = $request->file('file');
        $handle   = fopen($file->getRealPath(), 'r');
        $headers  = fgetcsv($handle); // Skip header row

        // Load lookup tables into memory for fast validation
        $categories = DeviceCategory::pluck('id', 'name')->toArray();
        $sites      = Site::where('is_active', true)->pluck('id', 'code')->toArray();
        $unassignedStatusId = DeviceStatus::where('name', DeviceStatus::UNASSIGNED)->value('id');

        $results = [
            'total'     => 0,
            'succeeded' => 0,
            'failed'    => 0,
            'rows'      => [],
        ];

        $rowNumber = 1; // Start at 1 (after header)

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $results['total']++;

            // Map CSV columns to named fields
            $data = [
                'manufacturer'  => trim($row[0] ?? ''),
                'model'         => trim($row[1] ?? ''),
                'serial_number' => trim($row[2] ?? '') ?: null,
                'category'      => trim($row[3] ?? ''),
                'site_code'     => trim($row[4] ?? ''),
                'notes'         => trim($row[5] ?? '') ?: null,
            ];

            // Validate the row
            $validator = Validator::make($data, [
                'manufacturer' => ['required', 'string', 'max:120'],
                'model'        => ['required', 'string', 'max:120'],
                'category'     => ['required', 'string'],
                'site_code'    => ['required', 'string'],
            ]);

            if ($validator->fails()) {
                $results['failed']++;
                $results['rows'][] = [
                    'row'     => $rowNumber,
                    'status'  => 'failed',
                    'data'    => $data,
                    'errors'  => $validator->errors()->all(),
                ];
                continue;
            }

            // Resolve category
            $categoryId = $categories[$data['category']] ?? null;
            if (! $categoryId) {
                $results['failed']++;
                $results['rows'][] = [
                    'row'    => $rowNumber,
                    'status' => 'failed',
                    'data'   => $data,
                    'errors' => ["Unknown category '{$data['category']}'. Valid values: " . implode(', ', array_keys($categories))],
                ];
                continue;
            }

            // Resolve site
            $siteId = $sites[$data['site_code']] ?? null;
            if (! $siteId) {
                $results['failed']++;
                $results['rows'][] = [
                    'row'    => $rowNumber,
                    'status' => 'failed',
                    'data'   => $data,
                    'errors' => ["Unknown site code '{$data['site_code']}'. Valid codes: " . implode(', ', array_keys($sites))],
                ];
                continue;
            }

            // Create the device
            try {
                $device = DB::transaction(function () use ($data, $categoryId, $siteId, $unassignedStatusId, $categories, $request) {
                    $prefix   = DeviceCategory::find($categoryId)->prefix;
                    $count    = Device::where('category_id', $categoryId)->count() + 1;
                    $assetTag = $prefix . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);

                    return Device::create([
                        'asset_tag'     => $assetTag,
                        'manufacturer'  => $data['manufacturer'],
                        'model'         => $data['model'],
                        'serial_number' => $data['serial_number'],
                        'category_id'   => $categoryId,
                        'site_id'       => $siteId,
                        'status_id'     => $unassignedStatusId,
                        'notes'         => $data['notes'],
                        'created_by'    => $request->user()->id,
                        'updated_by'    => $request->user()->id,
                    ]);
                });

                $results['succeeded']++;
                $results['rows'][] = [
                    'row'       => $rowNumber,
                    'status'    => 'succeeded',
                    'asset_tag' => $device->asset_tag,
                    'data'      => $data,
                ];

            } catch (\Exception $e) {
                $results['failed']++;
                $results['rows'][] = [
                    'row'    => $rowNumber,
                    'status' => 'failed',
                    'data'   => $data,
                    'errors' => [$e->getMessage()],
                ];
            }
        }

        fclose($handle);

        AuditLog::recordForUser(
            $request->user(),
            'device.bulk_import',
            'device',
            0,
            "Bulk import: {$results['succeeded']} succeeded, {$results['failed']} failed out of {$results['total']} rows"
        );

        return response()->json($results, $results['failed'] > 0 ? 207 : 200);
    }
}
