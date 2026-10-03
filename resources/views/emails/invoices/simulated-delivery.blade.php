<p style="font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; color: #757682; margin-bottom: 4px;">Fiscora · Plataforma de Facturación</p>

<p>Se registró una entrega simulada de la factura de prueba {{ $invoice->number }}.</p>

<p>
    Este mensaje se genera mediante el mailer de desarrollo y no corresponde a una entrega tributaria real.
    El documento adjunto está marcado como <strong>DOCUMENTO DE PRUEBA – SIN VALIDEZ TRIBUTARIA</strong>.
</p>

<p>Total: {{ $invoice->currency }} {{ \App\Support\ReportFormatter::money($invoice->total) }}</p>

<p style="font-size: 11px; color: #757682;">Generado con Fiscora.</p>
