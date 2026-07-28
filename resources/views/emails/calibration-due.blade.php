<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        h1 { color: #1a56db; font-size: 20px; }
        h2 { font-size: 16px; margin-top: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #f3f4f6; text-align: left; padding: 8px; font-size: 13px; }
        td { padding: 8px; border-bottom: 1px solid #e5e7eb; font-size: 13px; }
        .overdue { color: #dc2626; font-weight: bold; }
        .due-soon { color: #d97706; }
        .footer { margin-top: 32px; font-size: 12px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 16px; }
    </style>
</head>
<body>
    <h1>{{ $appName }} — Maintenance Alert</h1>
    <p>This is an automated notification from the Test System Maintenance platform.</p>

    @if(count($overdueItems) > 0)
    <h2>🔴 Overdue ({{ count($overdueItems) }} items)</h2>
    <table>
        <thead>
            <tr>
                <th>Asset Tag</th>
                <th>Device</th>
                <th>Site</th>
                <th>Type</th>
                <th>Was Due</th>
            </tr>
        </thead>
        <tbody>
            @foreach($overdueItems as $item)
            <tr>
                <td class="overdue">{{ $item['asset_tag'] }}</td>
                <td>{{ $item['manufacturer'] }} {{ $item['model'] }}</td>
                <td>{{ $item['site'] }}</td>
                <td>{{ $item['event_type'] }}</td>
                <td class="overdue">{{ $item['next_due_date'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if(count($dueSoonItems) > 0)
    <h2>🟡 Due Soon ({{ count($dueSoonItems) }} items)</h2>
    <table>
        <thead>
            <tr>
                <th>Asset Tag</th>
                <th>Device</th>
                <th>Site</th>
                <th>Type</th>
                <th>Due Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($dueSoonItems as $item)
            <tr>
                <td>{{ $item['asset_tag'] }}</td>
                <td>{{ $item['manufacturer'] }} {{ $item['model'] }}</td>
                <td>{{ $item['site'] }}</td>
                <td>{{ $item['event_type'] }}</td>
                <td class="due-soon">{{ $item['next_due_date'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">
        <p>This notification was sent automatically by {{ $appName }}.</p>
        <p>Log in to the system to view full details and take action.</p>
    </div>
</body>
</html>
