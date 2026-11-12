<!DOCTYPE html>
<html>
<head>
    <title>Receipt - {{ $receiptData['sale']->sale_number }}</title>
    <style>
        body {
            font-family: 'Inter', sans-serif; /* A common sans-serif font */
            margin: 0;
            padding: 0;
            font-size: 10px;
            line-height: 1.4;
            color: #333;
            width: 100%;
            box-sizing: border-box;
        }
        .receipt-container {
            width: 100%; /* Adjust for typical receipt width, e.g., 80mm printer, so use px or small % if fixed size */
            /* max-width: 300px;  Typical receipt width */
            margin: 0 auto;
            padding: 5px;
            border: 1px solid #eee; /* For screen view, remove for actual print */
            box-shadow: 0 0 5px rgba(0,0,0,0.05); /* For screen view, remove for actual print */
            background-color: #fff;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 1px dashed #ccc;
            padding-bottom: 5px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            color: #000;
        }
        .header p {
            margin: 2px 0;
            font-size: 9px;
            color: #555;
        }
        .receipt-details {
            margin-bottom: 10px;
            font-size: 9px;
        }
        .receipt-details div {
            margin-bottom: 2px;
        }
        .receipt-details strong {
            display: inline-block;
            width: 70px; /* Align labels */
            color: #444;
        }
        .item-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .item-table th, .item-table td {
            border-bottom: 1px dashed #eee;
            padding: 4px 0;
            text-align: left;
            font-size: 9px;
        }
        .item-table th {
            font-weight: bold;
            color: #000;
            text-align: right; /* Default align right for Qty, Price, Total */
        }
        .item-table th:first-child, .item-table td:first-child {
            text-align: left; /* Name always left aligned */
        }
        .item-table .text-right {
            text-align: right;
        }
        .item-table .total-row {
            font-weight: bold;
            font-size: 10px;
            background-color: #f8f8f8;
            border-top: 2px solid #ccc;
        }
        .item-table .grand-total-row {
            font-weight: bold;
            font-size: 12px;
            background-color: #e9f5ff;
            border-top: 2px solid #007bff;
        }
        .footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #ccc;
            font-size: 8px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="header">
            <h1>{{ $receiptData['company']['name'] }}</h1>
            <p>{{ $receiptData['company']['address'] }}</p>
            <p>Phone: {{ $receiptData['company']['phone'] }} | Email: {{ $receiptData['company']['email'] }}</p>
            <p>--- SALES RECEIPT ---</p>
        </div>

        <div class="receipt-details">
            <div><strong>Receipt No:</strong> {{ $receiptData['sale']->sale_number }}</div>
            <div><strong>Date:</strong> {{ $receiptData['sale']->sale_date->format('Y-m-d H:i:s') }}</div>
            <div><strong>Cashier:</strong> {{ $receiptData['cashier']->name ?? 'N/A' }}</div>
            @if ($receiptData['customer'])
                <div><strong>Customer:</strong> {{ $receiptData['customer']->name }}</div>
                @if ($receiptData['customer']->phone)
                    <div><strong>Phone:</strong> {{ $receiptData['customer']->phone }}</div>
                @endif
            @endif
        </div>

        <table class="item-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($receiptData['sale_items'] as $item)
                    <tr>
                        <td>{{ $item->product->name ?? 'Product Not Found' }}</td>
                        <td class="text-right">{{ $item->quantity }}</td>
                        <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                        <td class="text-right">{{ number_format($item->subtotal, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3" class="text-right">Subtotal:</td>
                    <td class="text-right">{{ number_format($receiptData['sale']->total_amount, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td colspan="3" class="text-right">Paid Amount ({{ $receiptData['currency'] }}):</td>
                    <td class="text-right">{{ number_format($receiptData['sale']->paid_amount, 2) }}</td>
                </tr>
                <tr class="grand-total-row">
                    <td colspan="3" class="text-right">Change ({{ $receiptData['currency'] }}):</td>
                    <td class="text-right">{{ number_format($receiptData['sale']->change_amount, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="footer">
            <p>Thank you for your purchase!</p>
            <p>Visit us again soon.</p>
            <p>&copy; {{ date('Y') }} {{ $receiptData['company']['name'] }}</p>
        </div>
    </div>
</body>
</html>
<?php
