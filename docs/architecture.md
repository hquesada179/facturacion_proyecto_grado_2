# Arquitectura

## Capas

```
routes/web.php
      │
      ▼
Controladores (app/Http/Controllers)      — validan la petición, autorizan, delegan
      │
      ▼
Servicios de dominio (app/Services/*)     — toda la lógica de negocio vive aquí
      │
      ▼
Modelos Eloquent (app/Models)             — persistencia, scopes, relaciones, casts
      │
      ▼
Base de datos (SQLite por defecto)
```

Los controladores son deliberadamente delgados: autorizan (`$this->authorize(...)` / `Gate::authorize(...)`), validan el request (Form Requests o `$request->validate()`) y llaman a un servicio. Ningún controlador calcula totales, decide transiciones de estado ni construye SQL de aislamiento multiempresa por su cuenta — eso siempre vive en un servicio o en el modelo.

## Servicios y responsabilidades

| Servicio | Responsabilidad |
|---|---|
| `Services/Invoices/Calculation/InvoiceCalculator` | Cálculo monetario puro (subtotal, descuentos, impuestos, totales) usando `brick/math` — nunca float. |
| `Services/Invoices/InvoiceRecalculationService` | Única vía para recalcular y persistir los totales de una factura; relee todas las líneas desde la BD en cada pasada. |
| `Services/Invoices/Validation/ValidationEngine` | Ejecuta el conjunto de reglas de negocio (`Rules/*`) sobre un borrador y devuelve bloqueos/advertencias. |
| `Services/Invoices/InvoiceStateMachine` | Único punto autorizado para decidir si una transición de estado de factura es válida. |
| `Services/Invoices/IssueInvoiceService` | Orquesta la emisión simulada: valida, reserva numeración, simula DIAN, genera CUFE, registra trazabilidad. |
| `Services/Invoices/InvoicePdfService` / `InvoiceQrCodeService` | Generan el PDF histórico (hash SHA-256) y el QR de verificación interna. |
| `Services/Invoices/SimulatedInvoiceDeliveryService` | Simula el envío del documento (mailer de desarrollo). |
| `Services/Numbering/NumberingService` | Reserva de consecutivos atómica (transacción + `lockForUpdate`) para facturas y notas crédito. |
| `Services/CreditNotes/*` | Equivalente de todo lo anterior para notas crédito: cálculo, estado, emisión, PDF, snapshots. |
| `Services/Reports/*` | Agregaciones a nivel SQL (dashboard, reportes documentales, productividad, errores) — nunca cargan colecciones completas en PHP para sumar. |
| `Services/Assistant/*` | Capa de IA desacoplada — ver más abajo. |

## Validaciones

`ValidationEngine` recorre una lista de reglas (`App\Services\Invoices\Validation\Rules\*`), agrupadas por dominio: cliente, productos, cantidades, precios, descuentos, impuestos, totales y condiciones de pago. Cada regla implementa `RuleInterface` y devuelve un resultado con severidad (`bloqueo` o `advertencia`), código, campo afectado y una sugerencia legible. El motor no vive dentro de un controlador ni se duplica en el asistente de IA: ambos consumen la misma instancia.

## Estados

Ver [`invoice-lifecycle.md`](invoice-lifecycle.md) para el grafo completo. La regla general: ninguna parte del sistema asigna `$invoice->status` directamente fuera de `InvoiceStateMachine::transition()` (y su equivalente para notas crédito), y los campos derivados (`number`, `subtotal`, `tax_total`, `total`, `simulated_cufe`, `issued_at`, `pdf_path`, `pdf_hash`, etc.) están deliberadamente excluidos de `$fillable` en los modelos — solo un servicio de dominio puede escribirlos.

## Multiempresa

El trait `App\Models\Concerns\BelongsToCompany`:

- Registra un *global scope* (`CompanyScope`) que filtra automáticamente toda consulta por el `company_id` del usuario autenticado.
- En el evento `creating`, asigna `company_id` desde `Auth::user()->company_id` si todavía no tiene uno — nunca desde el payload de la petición, incluso si el atacante intenta enviar `company_id` en el formulario.

Los servicios que necesitan cruzar el scope intencionalmente (por ejemplo, el asistente de IA resolviendo un recurso por id, o un reporte) lo hacen explícitamente con `withoutGlobalScopes()->where('company_id', $user->company_id)`, nunca confiando en un id arbitrario del cliente.

## Asistente IA

`AiAssistantService` orquesta:

1. `AssistantContextBuilder` — construye el contexto mínimo necesario (pantalla, rol, recurso actual) sin exponer toda la base de datos.
2. Un conjunto de *tools* de solo lectura (`Services/Assistant/Tools/*`) que reutilizan los mismos servicios de dominio (`ValidationEngine`, trazabilidad, etc.) — la IA nunca calcula nada por su cuenta.
3. `AiProviderInterface`, con dos implementaciones: `ExternalAiProvider` (si hay credenciales en `.env`) y `LocalFallbackProvider` (determinista, sin red, usado por defecto).
4. `AssistantActionService` para acciones críticas: el proveedor de IA solo puede *proponer* una acción con un token de confirmación de un solo uso; la ejecución real pasa siempre por el servicio de dominio correspondiente (p. ej. `IssueInvoiceService`), nunca por el texto generado.

Si se elimina por completo el módulo de IA, el sistema de facturación sigue funcionando exactamente igual — no hay ninguna dependencia en sentido inverso.

## Configuración de entornos

Variables relevantes (ver `.env.example` para la lista completa, sin secretos):

| Variable | Desarrollo | Producción recomendada |
|---|---|---|
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | `false` (nunca mostrar excepciones internas a usuarios) |
| `LOG_LEVEL` | `debug` | `error` |
| `APP_LOCALE` | `es` | `es` |
| `SESSION_SECURE_COOKIE` | *(vacío)* | `true`, detrás de HTTPS |
| `DB_CONNECTION` | `sqlite` | `mysql`/`pgsql` según infraestructura |
| `ASSISTANT_AI_PROVIDER` | `local` | `local` o un proveedor externo configurado explícitamente |

Nunca se sube `.env` al repositorio (está en `.gitignore`); `.env.example` nunca debe contener claves, contraseñas ni tokens reales.
