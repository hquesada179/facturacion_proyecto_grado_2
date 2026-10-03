# Base de datos

34 migraciones, aplicables desde cero con `php artisan migrate:fresh --seed` sin intervención manual.

## Entidades principales

- **companies** — empresa emisora (razón social, NIT, régimen fiscal). Raíz del aislamiento multiempresa.
- **users** — usuarios con rol (`Administrador`, `Facturador`, `Contador`, `Auditor`) y `company_id`.
- **customers** — clientes de una empresa; incluye al "consumidor final" genérico.
- **product_services** — catálogo de productos/servicios, con impuestos asociados vía tabla pivote `product_service_tax`.
- **taxes** — catálogo de impuestos (IVA, INC, exentos, excluidos); puede ser global (`company_id` nulo) o específico de una empresa.
- **numbering_resolutions** — rangos de numeración simulados por empresa y tipo de documento (factura / nota crédito), con consecutivo actual bajo bloqueo transaccional.
- **invoices** — documento principal: estado, totales, snapshots de emisor/cliente al momento de emitir, CUFE simulado, token de verificación pública, campos de PDF (ruta, hash) y de entrega simulada.
- **invoice_items** / **invoice_item_taxes** — líneas de factura y su desglose de impuestos por línea.
- **invoice_events** — bitácora append-only (ver más abajo).
- **credit_notes** / **credit_note_items** — notas crédito, con snapshot inmutable de cada línea de la factura original en el momento de acreditar.
- **assistant_conversations** / **assistant_messages** / **assistant_metrics** — historial y métricas del asistente de IA (sin secretos ni razonamiento interno del modelo).

## Relaciones

```
companies 1──N users
companies 1──N customers, product_services, invoices, credit_notes, numbering_resolutions
customers 1──N invoices
invoices  1──N invoice_items ──N invoice_item_taxes
invoices  1──N invoice_events
invoices  1──N credit_notes ──N credit_note_items
product_services N──N taxes (pivote product_service_tax)
users     1──N invoice_events, assistant_conversations
```

## Restricciones e integridad

- **Claves foráneas**: toda tabla hija referencia a su padre (`company_id`, `invoice_id`, `customer_id`, etc.) con `foreignId()->constrained()`.
- **Borrado restringido en documentos históricos**: las claves foráneas `invoices.company_id`, `credit_notes.company_id` e `invoice_events.invoice_id` usan `restrictOnDelete()` (en vez del `cascadeOnDelete()` original) para que borrar una empresa o factura nunca arrastre silenciosamente su historial (ver migración `restrict_delete_on_historical_documents`).
- **Únicos**: `verification_token` en `invoices` es único; el consecutivo por `(company_id, document_type, prefix)` se protege a nivel de aplicación (`NumberingService`) con transacción + `lockForUpdate`, no solo con un índice.
- **Nullable deliberado**: campos como `number`, `issued_at`, `simulated_cufe`, `pdf_path` son nulos mientras el documento es un borrador — se vuelven obligatorios solo tras la emisión real, nunca asignables desde el usuario.
- **Sin soft deletes**: los documentos de negocio (facturas, notas crédito) no usan soft deletes; su ciclo de vida se modela con estados (`voided`, `discarded`) en vez de borrado, para preservar la trazabilidad completa.
- **Índices**: añadidos donde hay consultas reales que los necesitan (por ejemplo `assistant_metrics` por `company_id`+`status` y por `user_id`+`created_at`), no de forma especulativa.
