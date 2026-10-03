# Ciclo de vida de la factura

## Estados

| Estado | Significado | ¿Editable? | ¿Terminal? |
|---|---|---|---|
| `draft` | Borrador en construcción | Sí | No |
| `locally_validated` | Pasó el `ValidationEngine` local | Sí | No |
| `sending_simulated` | Emisión en curso (simulación DIAN) | No | No |
| `simulated_rejected` | La simulación DIAN la rechazó | Sí | No |
| `technical_error` | Error técnico simulado durante la emisión | Sí | No |
| `issued` | Emitida, con número y CUFE simulado definitivos | No | No* |
| `partially_credited` | Tiene al menos una nota crédito parcial | No | No* |
| `voided` | Anulada (nota crédito total) | No | Sí |
| `discarded` | Borrador descartado antes de emitir | No | Sí |

\* `issued` y `partially_credited` no son terminales porque todavía pueden recibir una nota crédito que las lleve a `partially_credited` o `voided`.

## Transiciones válidas

```
draft ──────────────► locally_validated ──────────► sending_simulated
  │                         │                              │
  ▼                         ▼                    ┌─────────┼─────────┐
discarded                 draft                   ▼         ▼         ▼
                                                issued  simulated_  technical_
                                                         rejected     error
                                                   │         │         │
                                       ┌───────────┼─────┐   └────┬────┘
                                       ▼           ▼     ▼        ▼
                                   voided  partially_  (nada más)  draft
                                            credited
                                              │
                                              ▼
                                            voided
```

Toda transición pasa por `App\Services\Invoices\InvoiceStateMachine::transition()`, que consulta una tabla de adyacencia fija y lanza `InvalidInvoiceTransitionException` ante cualquier salto no permitido (por ejemplo, `draft` → `issued` directamente, saltándose la validación y la simulación). Ninguna transición inválida deja el modelo mutado: si la transición falla, `$invoice->status` permanece en su valor original.

## Bloqueo de edición

`InvoiceStateMachine::isEditable($status)` determina si el cliente, los productos o el pago pueden seguir modificándose. Son editables únicamente: `draft`, `locally_validated`, `simulated_rejected`, `technical_error`. Todo lo demás (`sending_simulated`, `issued`, `partially_credited`, `voided`, `discarded`) está bloqueado — el controlador del wizard llama a `ensureEditable()` antes de cualquier escritura y responde `403` si el estado no lo permite.

## Notas crédito

Las notas crédito tienen su propia máquina de estados (`CreditNoteStateMachine`), con el mismo patrón: `draft → locally_validated → sending_simulated → issued | simulated_rejected | technical_error`, y `issued` como terminal (una nota crédito emitida no puede volver a cambiar). Al emitir una nota crédito:

- Si cubre toda la cantidad facturada pendiente → la factura pasa a `voided`.
- Si cubre solo una parte → la factura pasa a `partially_credited` y conserva su saldo acreditable (cantidad original menos lo ya acreditado por notas anteriores).
- Un segundo intento de anulación total sobre una factura ya `voided` se bloquea explícitamente.

Cada línea de una nota crédito guarda un snapshot inmutable (`tax_snapshot`, `unit_price`, `original_quantity`) tomado de la línea de factura original en el momento de crearla — la factura original nunca se modifica como efecto de una nota crédito, solo cambia su `status`.

## Trazabilidad

Cada transición y cada acción relevante (selección de cliente, ítem agregado, validación, emisión, PDF generado, entrega simulada, nota crédito) queda registrada en `invoice_events`: tipo de evento, `from_status`/`to_status`, usuario, metadata y timestamp. La tabla es append-only a nivel de modelo (`InvoiceEvent::save()`/`delete()` lanzan excepción si el registro ya existe) — no hay, ni debe haber, una ruta HTTP que edite o elimine un evento.
