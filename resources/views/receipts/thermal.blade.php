<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt #{{ $sale->receipt_number }}</title>
    <style>
        @page {
            margin: 4mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            line-height: 1.25;
            color: #000;
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .font-bold { font-weight: bold; }
        .title { font-size: 13px; font-weight: bold; margin-bottom: 2px; }
        .subtitle { font-size: 9px; color: #333; margin-bottom: 4px; }
        .divider { border-top: 1px dashed #444; margin: 4px 0; }
        .double-divider { border-top: 1px double #000; margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; margin: 4px 0; font-size: 9.5px; }
        th, td { padding: 2px 0; }
        .copy-banner {
            background-color: #eee;
            border: 1px solid #999;
            text-align: center;
            font-weight: bold;
            font-size: 9px;
            padding: 2px;
            margin-bottom: 4px;
        }
        .totals-table td { padding: 1.5px 0; }
        .footer { font-size: 8px; color: #555; text-align: center; margin-top: 6px; }
    </style>
</head>
<body>
    @if(!empty($isCopy))
        <div class="copy-banner">*** DUPLICATE COPY / REPRINT ***</div>
    @endif

    <div class="text-center">
        <div class="title">{{ $sale->pharmacy->name }}</div>
        <div class="subtitle">{{ $sale->branch->name ?? 'Main Branch' }}</div>
        @if(!empty($sale->pharmacy->address))
            <div class="subtitle">{{ $sale->pharmacy->address }}</div>
        @endif
        @if(!empty($sale->pharmacy->phone))
            <div class="subtitle">Tel: {{ $sale->pharmacy->phone }}</div>
        @endif
        @if(!empty($sale->pharmacy->kra_pin))
            <div class="subtitle">PIN: {{ $sale->pharmacy->kra_pin }}</div>
        @endif
    </div>

    <div class="divider"></div>

    <div>
        <div><span class="font-bold">Receipt #:</span> {{ $sale->receipt_number }}</div>
        <div><span class="font-bold">Date:</span> {{ $sale->sale_date->format('d/m/Y H:i:s') }}</div>
        <div><span class="font-bold">Cashier:</span> {{ $sale->cashier->name }}</div>
        @if($sale->customer)
            <div><span class="font-bold">Customer:</span> {{ $sale->customer->name }} ({{ $sale->customer->phone ?? 'N/A' }})</div>
        @elseif($sale->prescription)
            <div><span class="font-bold">Patient:</span> {{ $sale->prescription->patient_name }}</div>
        @else
            <div><span class="font-bold">Customer:</span> Walk-in Customer</div>
        @endif
    </div>

    <div class="divider"></div>

    <table>
        <thead>
            <tr style="border-bottom: 1px solid #000;">
                <th style="text-align: left; width: 50%;">Item</th>
                <th style="text-align: center; width: 15%;">Qty</th>
                <th style="text-align: right; width: 15%;">Price</th>
                <th style="text-align: right; width: 20%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $item)
                <tr>
                    <td>
                        <div class="font-bold">{{ $item->product->name }}</div>
                        <div style="font-size: 8px; color: #555;">B:{{ $item->batch_number }} | Exp:{{ \Carbon\Carbon::parse($item->expiry_date)->format('m/y') }}</div>
                    </td>
                    <td class="text-center">{{ (float) $item->quantity }}</td>
                    <td class="text-end">{{ number_format($item->unit_selling_price, 2) }}</td>
                    <td class="text-end font-bold">{{ number_format($item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <table class="totals-table">
        <tr>
            <td>Subtotal:</td>
            <td class="text-end font-bold">KES {{ number_format($sale->subtotal, 2) }}</td>
        </tr>
        @if($sale->discount_amount > 0)
            <tr>
                <td>Discount:</td>
                <td class="text-end text-danger">- KES {{ number_format($sale->discount_amount, 2) }}</td>
            </tr>
        @endif
        @if($sale->tax_amount > 0)
            <tr>
                <td>Tax:</td>
                <td class="text-end">KES {{ number_format($sale->tax_amount, 2) }}</td>
            </tr>
        @endif
        <tr style="border-top: 1px solid #000; font-size: 11px;">
            <td class="font-bold" style="padding-top: 3px;">TOTAL AMOUNT:</td>
            <td class="text-end font-bold" style="padding-top: 3px;">KES {{ number_format($sale->total_amount, 2) }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    <div>
        <div class="font-bold" style="margin-bottom: 2px;">Payment Method(s):</div>
        @foreach($sale->payments as $p)
            <div style="display: flex; justify-content: space-between;">
                <span>• {{ strtoupper($p->payment_method) }} @if($p->reference_number) (Ref: {{ $p->reference_number }}) @endif:</span>
                <span class="font-bold">KES {{ number_format($p->amount, 2) }}</span>
            </div>
            @if($p->payment_method === 'cash' && $p->change_returned > 0)
                <div style="font-size: 8.5px; color: #444; padding-left: 8px;">
                    Tendered: KES {{ number_format($p->amount_tendered, 2) }} | Change: KES {{ number_format($p->change_returned, 2) }}
                </div>
            @endif
        @endforeach
    </div>

    @if($sale->balance_due > 0)
        <div class="divider"></div>
        <div style="color: #c00; font-weight: bold;">
            Outstanding Balance: KES {{ number_format($sale->balance_due, 2) }}
        </div>
    @endif

    <div class="double-divider"></div>

    <div class="footer">
        <div>Thank you for choosing {{ $sale->pharmacy->name }}!</div>
        <div style="margin-top: 2px;">Wishing you quick recovery & good health.</div>
        <div style="margin-top: 2px; font-size: 7.5px;">Medicines once sold can only be returned in accordance with our pharmacy return policy.</div>
    </div>
</body>
</html>
