<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Nota crédito de prueba {{ $creditNote->number }}</title>
    <style>
        @page { margin: 28px 32px; }
        * { box-sizing: border-box; }
        body { color: #0b1c30; font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; line-height: 1.38; margin: 0; }
        .watermark { color: rgba(155, 28, 28, 0.08); font-size: 54px; font-weight: 700; left: 22px; position: fixed; text-align: center; text-transform: uppercase; top: 330px; transform: rotate(-28deg); width: 720px; z-index: -1; }
        .banner { background: #fde8e8; border: 1px solid #9b1c1c; color: #9b1c1c; font-size: 13px; font-weight: 700; margin-bottom: 14px; padding: 8px 10px; text-align: center; text-transform: uppercase; }
        .header { display: table; margin-bottom: 14px; width: 100%; }
        .header > div { display: table-cell; vertical-align: top; }
        .brand { width: 58%; }
        .document { text-align: right; width: 42%; }
        h1 { color: #00236f; font-size: 22px; margin: 0 0 4px; }
        h2 { color: #00236f; font-size: 14px; margin: 0 0 6px; }
        .box { border: 1px solid #c5c5d3; border-radius: 4px; margin-bottom: 12px; padding: 10px; }
        .grid { display: table; width: 100%; }
        .grid > div { display: table-cell; padding-right: 14px; vertical-align: top; width: 50%; }
        .grid > div:last-child { padding-right: 0; }
        table { border-collapse: collapse; margin-top: 8px; width: 100%; }
        th { background: #eff4ff; color: #0b1c30; font-size: 9px; padding: 7px 5px; text-align: left; text-transform: uppercase; }
        td { border-bottom: 1px solid #e5eeff; padding: 7px 5px; vertical-align: top; }
        .right { text-align: right; }
        .mono { font-family: DejaVu Sans Mono, Consolas, monospace; }
        .totals { margin-left: auto; width: 310px; }
        .totals td { border-bottom: 0; padding: 5px; }
        .totals tr:last-child td { border-top: 1px solid #00236f; color: #00236f; font-size: 14px; font-weight: 700; padding-top: 8px; }
        .legend { background: #fdf6b2; border: 1px solid rgba(114, 59, 19, 0.25); color: #723b13; margin-top: 12px; padding: 8px; }
    </style>
</head>
<body>
    <div class="watermark">Documento de prueba sin validez tributaria</div>
    <div class="banner">DOCUMENTO DE PRUEBA – SIN VALIDEZ TRIBUTARIA</div>

    <div class="header">
        <div class="brand">
            <h1>{{ $issuer['legal_name'] ?? $issuer['name'] ?? 'Empresa emisora' }}</h1>
            <div>NIT {{ $issuer['nit'] ?? '-' }}@if(! empty($issuer['nit_dv']))-{{ $issuer['nit_dv'] }}@endif</div>
            <div>{{ $issuer['address'] ?? '-' }}</div>
            <div>{{ $issuer['city'] ?? '' }}@if(! empty($issuer['department'])) · {{ $issuer['department'] }}@endif</div>
            <div>{{ $issuer['email'] ?? '-' }}@if(! empty($issuer['phone'])) · {{ $issuer['phone'] }}@endif</div>
        </div>
        <div class="document">
            <h2>Nota crédito de prueba {{ $creditNote->number }}</h2>
            <div>Factura relacionada: {{ $invoice->number }}</div>
            <div>Estado: {{ $creditNote->status->label() }}</div>
            <div>Fecha emisión: {{ optional($creditNote->issued_at)->format('Y-m-d H:i') }}</div>
        </div>
    </div>

    <div class="grid">
        <div>
            <div class="box">
                <h2>Cliente</h2>
                <strong>{{ $customer['name'] ?? '-' }}</strong><br>
                {{ $customer['identification_type'] ?? '-' }} {{ $customer['identification_number'] ?? '-' }}@if(! empty($customer['dv']))-{{ $customer['dv'] }}@endif<br>
                {{ $customer['email'] ?? '-' }}
            </div>
        </div>
        <div>
            <div class="box">
                <h2>Motivo</h2>
                <strong>{{ $creditNote->reason }}</strong><br>
                {{ $creditNote->reason_text }}
                <div class="mono">CUFE/ID SIMULADO: {{ $creditNote->simulated_cufe }}</div>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Descripción</th>
                <th class="right">Cantidad</th>
                <th class="right">Precio</th>
                <th class="right">Base</th>
                <th>Impuestos</th>
                <th class="right">Valor acreditado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($creditNote->items as $item)
                <tr>
                    <td>{{ $item->product_code ?? '-' }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ $item->credited_quantity }} {{ $item->unit }}</td>
                    <td class="right">{{ \App\Support\ReportFormatter::money($item->unit_price) }}</td>
                    <td class="right">{{ \App\Support\ReportFormatter::money($item->taxable_base) }}</td>
                    <td>
                        @forelse ($item->tax_snapshot ?? [] as $tax)
                            {{ $tax['code'] }} {{ $tax['rate'] }}%: {{ \App\Support\ReportFormatter::money($tax['value']) }}<br>
                        @empty
                            Sin impuesto
                        @endforelse
                    </td>
                    <td class="right">{{ \App\Support\ReportFormatter::money($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="grid" style="margin-top: 14px;">
        <div>
            <div class="box">
                <h2>Impuestos discriminados</h2>
                @forelse ($taxesByCode as $tax)
                    <div>{{ $tax['code'] }} - {{ $tax['name'] }} ({{ $tax['rate'] }}%): base {{ \App\Support\ReportFormatter::money($tax['base']) }}, impuesto {{ \App\Support\ReportFormatter::money($tax['value']) }}</div>
                @empty
                    <div>Sin impuestos aplicados.</div>
                @endforelse
            </div>
        </div>
        <div>
            <table class="totals">
                <tr><td>Subtotal acreditado</td><td class="right">{{ \App\Support\ReportFormatter::money($creditNote->subtotal) }}</td></tr>
                <tr><td>Total impuestos</td><td class="right">{{ \App\Support\ReportFormatter::money($creditNote->tax_total) }}</td></tr>
                <tr><td>Total nota crédito</td><td class="right">{{ $invoice->currency }} {{ \App\Support\ReportFormatter::money($creditNote->total) }}</td></tr>
            </table>
        </div>
    </div>

    <div class="legend">
        Validación DIAN simulada. Esta nota crédito pertenece a un prototipo académico y no representa un documento tributario real.
    </div>
</body>
</html>
