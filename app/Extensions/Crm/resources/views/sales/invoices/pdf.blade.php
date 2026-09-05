<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1a1a2e;
            line-height: 1.5;
        }

        .invoice-wrapper {
            padding: 40px;
        }

        /* Header */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }

        .header-left {
            display: table-cell;
            width: 60%;
            vertical-align: top;
        }

        .header-right {
            display: table-cell;
            width: 40%;
            vertical-align: top;
            text-align: right;
        }

        .from-name {
            font-size: 18px;
            font-weight: bold;
            color: #1a1a2e;
            margin-bottom: 4px;
        }

        .from-detail {
            font-size: 11px;
            color: #6b7280;
            line-height: 1.6;
        }

        .invoice-number {
            font-size: 24px;
            font-weight: bold;
            color: #6366f1;
            margin-bottom: 6px;
        }

        .invoice-meta {
            font-size: 11px;
            color: #6b7280;
        }

        /* Status badge */
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 8px;
        }

        .status-draft { background: #fef3c7; color: #d97706; }
        .status-sent { background: #dbeafe; color: #2563eb; }
        .status-paid { background: #d1fae5; color: #059669; }
        .status-partial { background: #ffedd5; color: #ea580c; }
        .status-overdue { background: #fee2e2; color: #dc2626; }
        .status-cancelled { background: #f3f4f6; color: #6b7280; }

        /* Bill To */
        .bill-to {
            background: #f9fafb;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 30px;
        }

        .bill-to-label {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #9ca3af;
            margin-bottom: 6px;
        }

        .bill-to-name {
            font-size: 14px;
            font-weight: bold;
            color: #1a1a2e;
        }

        .bill-to-detail {
            font-size: 11px;
            color: #6b7280;
        }

        /* Line Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        .items-table thead th {
            background: #f9fafb;
            padding: 10px 12px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #9ca3af;
            border-bottom: 2px solid #e5e7eb;
        }

        .items-table thead th:first-child {
            text-align: left;
            border-radius: 6px 0 0 0;
        }

        .items-table thead th:last-child {
            border-radius: 0 6px 0 0;
        }

        .items-table tbody td {
            padding: 12px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 12px;
        }

        .items-table tbody tr:last-child td {
            border-bottom: 2px solid #e5e7eb;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        /* Totals */
        .totals-wrapper {
            display: table;
            width: 100%;
        }

        .totals-spacer {
            display: table-cell;
            width: 55%;
        }

        .totals-box {
            display: table-cell;
            width: 45%;
        }

        .totals-row {
            display: table;
            width: 100%;
            margin-bottom: 6px;
        }

        .totals-row-label {
            display: table-cell;
            text-align: left;
            color: #6b7280;
            font-size: 12px;
            padding: 4px 0;
        }

        .totals-row-value {
            display: table-cell;
            text-align: right;
            font-size: 12px;
            padding: 4px 0;
        }

        .totals-divider {
            border-top: 1px solid #e5e7eb;
            margin: 8px 0;
        }

        .totals-total {
            font-size: 16px;
            font-weight: bold;
            color: #1a1a2e;
        }

        .text-red {
            color: #dc2626;
        }

        .text-green {
            color: #059669;
        }

        /* Notes */
        .notes-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .notes-label {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #9ca3af;
            margin-bottom: 6px;
        }

        .notes-content {
            font-size: 11px;
            color: #6b7280;
            white-space: pre-line;
        }

        /* Payments */
        .payments-section {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .payments-table {
            width: 100%;
            border-collapse: collapse;
        }

        .payments-table thead th {
            padding: 8px 12px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #9ca3af;
            border-bottom: 1px solid #e5e7eb;
        }

        .payments-table thead th:first-child {
            text-align: left;
        }

        .payments-table tbody td {
            padding: 8px 12px;
            font-size: 11px;
            border-bottom: 1px solid #f3f4f6;
        }

        /* Footer */
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #9ca3af;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="invoice-wrapper">
        {{-- Header --}}
        <div class="header">
            <div class="header-left">
                @if ($invoice->from_name)
                    <div class="from-name">{{ $invoice->from_name }}</div>
                @endif
                @if ($invoice->from_email)
                    <div class="from-detail">{{ $invoice->from_email }}</div>
                @endif
                @if ($invoice->from_phone)
                    <div class="from-detail">{{ $invoice->from_phone }}</div>
                @endif
                @if ($invoice->from_address)
                    <div class="from-detail">{{ $invoice->from_address }}</div>
                @endif
            </div>
            <div class="header-right">
                <div class="invoice-number">{{ $invoice->invoice_number }}</div>
                <div class="invoice-meta">
                    {{ __('Issue Date') }}: {{ $invoice->issue_date->format('M d, Y') }}
                </div>
                @if ($invoice->due_date)
                    <div class="invoice-meta">
                        {{ __('Due Date') }}: {{ $invoice->due_date->format('M d, Y') }}
                    </div>
                @endif
                <span class="status-badge status-{{ $invoice->status }}">
                    {{ ucfirst($invoice->status) }}
                </span>
            </div>
        </div>

        {{-- Bill To --}}
        @if ($invoice->company || $invoice->contact)
            <div class="bill-to">
                <div class="bill-to-label">{{ __('Bill To') }}</div>
                @if ($invoice->company)
                    <div class="bill-to-name">{{ $invoice->company->name }}</div>
                    @if ($invoice->company->email)
                        <div class="bill-to-detail">{{ $invoice->company->email }}</div>
                    @endif
                    @if ($invoice->company->address)
                        <div class="bill-to-detail">{{ $invoice->company->address }}</div>
                    @endif
                @endif
                @if ($invoice->contact)
                    <div class="bill-to-name" style="{{ $invoice->company ? 'margin-top: 8px;' : '' }}">{{ $invoice->contact->full_name }}</div>
                    @if ($invoice->contact->email)
                        <div class="bill-to-detail">{{ $invoice->contact->email }}</div>
                    @endif
                @endif
            </div>
        @endif

        {{-- Line Items --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th style="text-align: left;">{{ __('Description') }}</th>
                    <th class="text-center">{{ __('Qty') }}</th>
                    <th class="text-center">{{ __('Unit') }}</th>
                    <th class="text-right">{{ __('Rate') }}</th>
                    <th class="text-right">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoice->items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td class="text-center">{{ $item->quantity }}</td>
                        <td class="text-center">{{ $item->unit ?: '-' }}</td>
                        <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                        <td class="text-right">{{ number_format($item->total, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center" style="color: #9ca3af; padding: 20px;">
                            {{ __('No items') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Totals --}}
        <div class="totals-wrapper">
            <div class="totals-spacer"></div>
            <div class="totals-box">
                <div class="totals-row">
                    <span class="totals-row-label">{{ __('Subtotal') }}</span>
                    <span class="totals-row-value">{{ $invoice->currency }} {{ number_format($invoice->subtotal, 2) }}</span>
                </div>

                @if ((float) $invoice->discount_value > 0)
                    @php
                        $discountAmount = $invoice->discount_type === 'percentage'
                            ? (float) $invoice->subtotal * ((float) $invoice->discount_value / 100)
                            : (float) $invoice->discount_value;
                    @endphp
                    <div class="totals-row">
                        <span class="totals-row-label">
                            {{ __('Discount') }}
                            @if ($invoice->discount_type === 'percentage')
                                ({{ $invoice->discount_value }}%)
                            @endif
                        </span>
                        <span class="totals-row-value text-red">-{{ $invoice->currency }} {{ number_format($discountAmount, 2) }}</span>
                    </div>
                @endif

                @if ((float) $invoice->tax_rate > 0)
                    <div class="totals-row">
                        <span class="totals-row-label">{{ __('Tax') }} ({{ $invoice->tax_rate }}%)</span>
                        <span class="totals-row-value">{{ $invoice->currency }} {{ number_format($invoice->tax_amount, 2) }}</span>
                    </div>
                @endif

                <div class="totals-divider"></div>

                <div class="totals-row">
                    <span class="totals-row-label totals-total">{{ __('Total') }}</span>
                    <span class="totals-row-value totals-total">{{ $invoice->currency }} {{ number_format($invoice->total, 2) }}</span>
                </div>

                @if ((float) $invoice->amount_paid > 0)
                    <div class="totals-row">
                        <span class="totals-row-label text-green">{{ __('Paid') }}</span>
                        <span class="totals-row-value text-green">-{{ $invoice->currency }} {{ number_format($invoice->amount_paid, 2) }}</span>
                    </div>

                    <div class="totals-divider"></div>

                    <div class="totals-row">
                        <span class="totals-row-label" style="font-weight: bold; {{ $invoice->balance_due > 0 ? 'color: #dc2626;' : 'color: #059669;' }}">
                            {{ __('Balance Due') }}
                        </span>
                        <span class="totals-row-value" style="font-weight: bold; {{ $invoice->balance_due > 0 ? 'color: #dc2626;' : 'color: #059669;' }}">
                            {{ $invoice->currency }} {{ number_format($invoice->balance_due, 2) }}
                        </span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Payments --}}
        @if ($invoice->payments->count() > 0)
            <div class="payments-section">
                <div class="notes-label">{{ __('Payments') }}</div>
                <table class="payments-table">
                    <thead>
                        <tr>
                            <th style="text-align: left;">{{ __('Date') }}</th>
                            <th class="text-center">{{ __('Method') }}</th>
                            <th class="text-center">{{ __('Reference') }}</th>
                            <th class="text-right">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->payments as $payment)
                            <tr>
                                <td>{{ $payment->payment_date->format('M d, Y') }}</td>
                                <td class="text-center">{{ $payment->payment_method ? ucfirst(str_replace('_', ' ', $payment->payment_method)) : '-' }}</td>
                                <td class="text-center">{{ $payment->reference ?: '-' }}</td>
                                <td class="text-right text-green" style="font-weight: bold;">{{ $invoice->currency }} {{ number_format($payment->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Notes --}}
        @if ($invoice->notes)
            <div class="notes-section">
                <div class="notes-label">{{ __('Notes') }}</div>
                <div class="notes-content">{{ $invoice->notes }}</div>
            </div>
        @endif

        {{-- Footer --}}
        <div class="footer">
            {{ __('Generated on') }} {{ now()->format('M d, Y \a\t H:i') }}
        </div>
    </div>
</body>
</html>
