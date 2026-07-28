<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #334155;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        .header {
            margin-bottom: 25px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 15px;
        }
        .header-title {
            font-size: 20px;
            font-weight: 800;
            color: #1e3a8a;
            margin: 0 0 5px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header-meta {
            font-size: 10px;
            color: #64748b;
        }
        .filter-summary {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 20px;
        }
        .filter-grid {
            width: 100%;
        }
        .filter-grid td {
            padding: 2px 0;
        }
        .filter-label {
            font-weight: 600;
            color: #475569;
            width: 120px;
        }
        .filter-value {
            color: #0f172a;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9px;
            border-bottom: 2px solid #cbd5e1;
            padding: 8px 10px;
            text-align: left;
        }
        td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
            word-wrap: break-word;
        }
        tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 8px;
            font-weight: 600;
            border-radius: 9999px;
            text-transform: uppercase;
        }
        .badge-slate { background-color: #f1f5f9; color: #475569; }
        .badge-indigo { background-color: #e0e7ff; color: #4338ca; }
        .badge-emerald { background-color: #d1fae5; color: #065f46; }
        .badge-rose { background-color: #ffe4e6; color: #9f1239; }
        .badge-amber { background-color: #fef3c7; color: #92400e; }
        .badge-cyan { background-color: #ecfeff; color: #155e75; }

        .text-right { text-align: right; }
        .font-semibold { font-weight: 600; }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1 class="header-title">{{ $title }}</h1>
        <div class="header-meta">Generated on {{ now()->format('d M Y \a\t H:i:s') }} by {{ auth()->user()->name }}</div>
    </div>

    <div class="filter-summary">
        <table class="filter-grid">
            <tr>
                <td class="filter-label">Filtered Member:</td>
                <td class="filter-value">{{ $formattedMember }}</td>
                <td class="filter-label">Date Range:</td>
                <td class="filter-value">{{ $dateRangeStr }}</td>
            </tr>
            <tr>
                <td class="filter-label">Total Records:</td>
                <td class="filter-value font-semibold">{{ $data->count() }}</td>
                <td></td>
                <td></td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th class="{{ $header === 'Value (USD)' || $header === 'Budget (USD)' || $header === 'Offer Amount (USD)' || $header === 'Final Amount (USD)' ? 'text-right' : '' }}">
                        {{ $header }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($data as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    
                    @if($type === 'customers')
                        <td>{{ $row->id }}</td>
                        <td>
                            <span class="font-semibold">{{ $row->name }}</span>
                        </td>
                        <td>{{ $row->email ?? '—' }}</td>
                        <td>{{ $row->phone ?? '—' }}</td>
                        <td>{{ $row->address ?? '—' }}</td>
                        <td>{{ $row->company ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $row->status ? $row->status->badgeClass() : 'badge-slate' }}">
                                {{ $row->status ? $row->status->label() : '—' }}
                            </span>
                        </td>
                        <td>{{ $row->source ? $row->source : '—' }}</td>
                        <td>
                            @if($row->pipeline_stage)
                                <span class="badge" style="background-color: {{ $row->pipeline_stage->color() }}22; color: {{ $row->pipeline_stage->color() }}">
                                    {{ $row->pipeline_stage->label() }}
                                </span>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $row->current_queue ? $row->current_queue->label() : '—' }}</td>
                        <td>{{ $row->first_purchase_date ? $row->first_purchase_date->format('d M Y') : '—' }}</td>
                        <td>{{ $row->product_interests ? implode('; ', $row->product_interests) : '—' }}</td>
                        <td class="text-right font-semibold">${{ number_format($row->lifetime_value, 2) }}</td>
                        <td>{{ $row->assignee ? $row->assignee->name : 'Unassigned' }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($row->interactions->first()?->content ?? '—', 60) }}</td>
                        <td>{{ $row->created_at->format('d M Y') }}</td>
                        <td>{{ $row->updated_at->format('d M Y') }}</td>

                    @elseif($type === 'logistics')
                        <td class="font-semibold">{{ $row->order_id ?? '—' }}</td>
                        <td>{{ $row->customer ? $row->customer->name : '—' }}</td>
                        <td>{{ $row->product ? $row->product->name : '—' }}</td>
                        <td>{{ $row->recipient_name }}</td>
                        <td style="max-width: 150px;">{{ $row->shipping_address }}</td>
                        <td class="text-right font-semibold">${{ number_format($row->shipping_budget, 2) }}</td>
                        <td>
                            <span class="badge {{ $row->status ? $row->status->badgeClass() : 'badge-slate' }}">
                                {{ $row->status ? $row->status->label() : '—' }}
                            </span>
                        </td>
                        <td>{{ $row->pickup_datetime ? $row->pickup_datetime->format('d M Y H:i') : '—' }}</td>

                    @elseif($type === 'website')
                        <td class="font-semibold">{{ $row->client_name }}</td>
                        <td>{{ $row->client_email ?? '—' }}</td>
                        <td>{{ $row->client_phone ?? '—' }}</td>
                        <td>{{ $row->source ? $row->source->label() : '—' }}</td>
                        <td>{{ $row->product ? $row->product->name : ($row->product_interested ?? '—') }}</td>
                        <td>
                            <span class="badge {{ $row->status ? $row->status->badgeClass() : 'badge-slate' }}">
                                {{ $row->status ? $row->status->label() : '—' }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ $row->temperature ? $row->temperature->badgeClass() : 'badge-slate' }}">
                                {{ $row->temperature ? $row->temperature->label() : '—' }}
                            </span>
                        </td>
                        <td>{{ $row->received_at ? $row->received_at->format('d M Y H:i') : '—' }}</td>

                    @elseif($type === 'ebay')
                        <td>{{ $row->store ? $row->store->store_name : '—' }}</td>
                        <td class="font-semibold">{{ $row->customer ? $row->customer->name : ($row->client_name ?? '—') }}</td>
                        <td>{{ $row->ebay_username ?? '—' }}</td>
                        <td>{{ $row->product ? $row->product->name : '—' }}</td>
                        <td class="text-right">${{ number_format($row->offer_amount, 2) }}</td>
                        <td class="text-right font-semibold">${{ number_format($row->final_amount, 2) }}</td>
                        <td>
                            <span class="badge {{ $row->status ? $row->status->badgeClass() : 'badge-slate' }}">
                                {{ $row->status ? $row->status->label() : '—' }}
                            </span>
                        </td>
                        <td>{{ $row->received_at ? $row->received_at->format('d M Y H:i') : '—' }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="15" style="text-align: center; color: #94a3b8; padding: 30px;">
                        No records match the selected filters.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Digital & CRM Management System · Confidential Reports
    </div>

</body>
</html>
