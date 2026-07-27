<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Milon\Barcode\DNS2D;
use Milon\Barcode\DNS1D;

class LabelController extends Controller
{
    /**
     * UC-11: Generate a printable PDF label sheet for one or more devices.
     *
     * Query params:
     *   ids        = comma-separated device IDs (required)
     *   template   = 'thermal' | 'avery' (default: avery)
     */
    public function generate(Request $request): Response
    {
        $this->authorize('printLabel', Device::class);

        $request->validate([
            'ids'      => ['required', 'string'],
            'template' => ['nullable', 'string', 'in:thermal,avery'],
        ]);

        $ids      = explode(',', $request->ids);
        $template = $request->input('template', 'avery');

        $devices = Device::with(['category', 'site'])
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->get();

        if ($devices->isEmpty()) {
            return response('No active devices found for the given IDs.', 404);
        }

        // Generate QR codes for each device (asset tag encoded)
        $dns2d  = new DNS2D();
        $dns1d  = new DNS1D();

        $labelsHtml = '';

        foreach ($devices as $device) {
            $qrCode  = $dns2d->getBarcodePNG($device->asset_tag, 'QRCODE', 4, 4);
            $barcode = $dns1d->getBarcodePNG($device->asset_tag, 'C128', 2, 40);

            if ($template === 'thermal') {
                $labelsHtml .= $this->thermalLabel($device, $qrCode, $barcode);
            } else {
                $labelsHtml .= $this->averyLabel($device, $qrCode, $barcode);
            }
        }

        $css = $template === 'thermal'
            ? $this->thermalCss()
            : $this->averyCss();

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Device Labels</title>
    <style>{$css}</style>
</head>
<body>
    <div class="label-sheet">
        {$labelsHtml}
    </div>
</body>
</html>
HTML;

        $pdf = app('dompdf.wrapper');
        $pdf->loadHTML($html);

        if ($template === 'thermal') {
            $pdf->setPaper([0, 0, 204, 144], 'landscape'); // ~72mm x 51mm thermal
        } else {
            $pdf->setPaper('A4', 'portrait');
        }

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="device_labels.pdf"',
        ]);
    }

    /**
     * Thermal printer label HTML (one label per page section).
     */
    private function thermalLabel(Device $device, string $qrCode, string $barcode): string
    {
        $category = strtoupper($device->category->name ?? '');
        $site     = $device->site->name ?? '';

        return <<<HTML
<div class="label thermal-label">
    <div class="label-left">
        <img src="data:image/png;base64,{$qrCode}" class="qr-code" />
    </div>
    <div class="label-right">
        <div class="asset-tag">{$device->asset_tag}</div>
        <div class="device-name">{$device->manufacturer}</div>
        <div class="device-model">{$device->model}</div>
        <div class="device-meta">{$category} | {$site}</div>
        <img src="data:image/png;base64,{$barcode}" class="barcode" />
    </div>
</div>
HTML;
    }

    /**
     * Avery sheet label HTML (grid layout, 3 columns x 8 rows = 24 per A4 sheet).
     */
    private function averyLabel(Device $device, string $qrCode, string $barcode): string
    {
        $category = strtoupper($device->category->name ?? '');

        return <<<HTML
<div class="label avery-label">
    <img src="data:image/png;base64,{$qrCode}" class="qr-code" />
    <div class="label-text">
        <div class="asset-tag">{$device->asset_tag}</div>
        <div class="device-name">{$device->manufacturer} {$device->model}</div>
        <div class="device-meta">{$category}</div>
    </div>
</div>
HTML;
    }

    private function thermalCss(): string
    {
        return '
            body { margin: 0; padding: 0; font-family: Arial, sans-serif; }
            .label-sheet { width: 100%; }
            .thermal-label {
                display: flex;
                width: 100%;
                height: 144pt;
                padding: 6pt;
                box-sizing: border-box;
                page-break-after: always;
                border: 1pt solid #ccc;
            }
            .label-left { width: 35%; display: flex; align-items: center; justify-content: center; }
            .label-right { width: 65%; padding-left: 6pt; display: flex; flex-direction: column; justify-content: center; }
            .qr-code { width: 60pt; height: 60pt; }
            .asset-tag { font-size: 14pt; font-weight: bold; font-family: monospace; }
            .device-name { font-size: 9pt; font-weight: bold; margin-top: 2pt; }
            .device-model { font-size: 9pt; color: #444; }
            .device-meta { font-size: 7pt; color: #666; margin-top: 2pt; }
            .barcode { max-width: 100%; height: 28pt; margin-top: 4pt; }
        ';
    }

    private function averyCss(): string
    {
        return '
            body { margin: 0; padding: 10pt; font-family: Arial, sans-serif; }
            .label-sheet {
                display: flex;
                flex-wrap: wrap;
                width: 100%;
            }
            .avery-label {
                width: 33.33%;
                height: 90pt;
                box-sizing: border-box;
                padding: 4pt;
                border: 0.5pt solid #ddd;
                display: flex;
                align-items: center;
                gap: 4pt;
                overflow: hidden;
            }
            .qr-code { width: 48pt; height: 48pt; flex-shrink: 0; }
            .label-text { flex: 1; overflow: hidden; }
            .asset-tag { font-size: 9pt; font-weight: bold; font-family: monospace; }
            .device-name { font-size: 7pt; color: #333; margin-top: 1pt; }
            .device-meta { font-size: 6pt; color: #666; margin-top: 1pt; }
        ';
    }
}
