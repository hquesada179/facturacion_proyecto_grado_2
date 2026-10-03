# FacturaPro Col

## Descripción

FacturaPro Col es un sistema académico de facturación inteligente orientado a pymes colombianas. Cubre todo el ciclo de un documento de facturación — cliente, producto, cálculo de impuestos, validación, emisión, PDF, trazabilidad, notas crédito/anulación — y añade un asistente de IA contextual que ayuda al usuario a entender el estado de sus documentos sin salirse de las reglas del dominio.

## Alcance

**Prototipo académico — no realiza transmisión tributaria real.**

Todo lo que el sistema llama "DIAN simulada", "CUFE simulado", "entrega simulada" o "documento de prueba" es exactamente eso: una simulación local, pensada para un proyecto de grado. Ningún documento generado por este sistema tiene validez tributaria ante la DIAN ni ante terceros.

## Requisitos

- PHP 8.3+
- Composer 2.x
- Node 20+ y npm
- Base de datos: SQLite (por defecto, cero configuración) o MySQL/PostgreSQL si se prefiere

## Instalación

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

Con SQLite (configuración por defecto de `.env.example`) no se necesita crear base de datos manualmente; `migrate --seed` crea el archivo y los datos iniciales.

## Usuarios demo

El `DatabaseSeeder` crea una empresa ficticia (`FacturaPro Demo SAS`) y un usuario por cada rol, todos con el dominio `facturapro.test` y contraseña `password`. Son cuentas completamente ficticias para desarrollo local, nunca credenciales reales:

| Rol | Correo |
|---|---|
| Administrador | `admin@facturapro.test` |
| Facturador | `facturador@facturapro.test` |
| Contador | `contador@facturapro.test` |
| Auditor | `auditor@facturapro.test` |

## Arquitectura

Resumen rápido — el detalle completo está en [`docs/architecture.md`](docs/architecture.md):

- **Servicios de dominio** (`app/Services/*`) concentran toda la lógica de negocio: cálculo monetario con decimales exactos (`brick/math`), validación, numeración simulada con bloqueo transaccional, generación de PDF/QR, reportes. Los controladores nunca calculan ni validan por su cuenta.
- **Validaciones**: un `ValidationEngine` ejecuta ~28 reglas de dominio (cliente, productos, cantidades, precios, descuentos, impuestos, totales, pago) antes de permitir avanzar un borrador.
- **Estados**: facturas y notas crédito avanzan mediante máquinas de estado explícitas (`InvoiceStateMachine`, `CreditNoteStateMachine`) que son la única vía autorizada para cambiar `status`.
- **Multiempresa**: todo modelo de negocio usa el trait `BelongsToCompany`, que aplica un *global scope* automático por `company_id` y lo asigna solo desde el usuario autenticado — nunca desde la petición del usuario.
- **Asistente IA**: capa opcional y desacoplada (`App\Services\Assistant`) con herramientas de solo lectura sobre los mismos servicios de dominio. Nunca ejecuta una acción crítica sin confirmación humana explícita, y el núcleo de facturación funciona igual con o sin proveedor de IA configurado.

## Funcionalidades

- Autenticación, recuperación de contraseña y roles (Administrador, Facturador, Contador, Auditor)
- Aislamiento multiempresa a nivel de base de datos
- Empresa emisora, clientes, productos/servicios, catálogo de impuestos
- Numeración simulada con reserva atómica de consecutivos (transacción + bloqueo de fila)
- Wizard persistente de facturación (cliente → productos → resumen → validación → emisión)
- Motor de cálculo monetario sin floats (decimales exactos) y motor de validación de reglas de negocio
- Ciclo de vida completo de la factura (borrador → validada → enviando → emitida/rechazada/error → parcialmente abonada/anulada)
- Emisión simulada con CUFE simulado, PDF, código QR y página pública de verificación
- Entrega simulada (mailer de desarrollo)
- Notas crédito (parciales y totales) y anulación de facturas, con saldo acreditable y snapshots históricos inmutables
- Bitácora de trazabilidad append-only (no editable, no eliminable)
- Dashboard y reportes con datos reales de la empresa autenticada
- Asistente IA contextual opcional, con modo local determinista cuando no hay proveedor externo configurado

## Pruebas

```bash
php artisan test
vendor/bin/pint --test
```

La estrategia de pruebas, categorías y comandos están documentados en [`docs/testing.md`](docs/testing.md).

## Limitaciones

- La validación ante la DIAN es simulada; no existe transmisión tributaria real.
- El CUFE es simulado (hash local), no un CUFE oficial.
- El código QR apunta a una página de verificación interna del propio sistema, no al validador oficial de la DIAN.
- El envío de documentos por correo es simulado (mailer de desarrollo / log), no un envío real al cliente.
- El asistente de IA es opcional: sin credenciales de un proveedor externo configuradas, responde en modo local determinista con datos reales del sistema (nunca inventa información).

## Configuración recomendada para producción

Ver [`docs/architecture.md`](docs/architecture.md#configuración-de-entornos) para el detalle completo de variables de entorno. En resumen: `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=error`, `SESSION_SECURE_COOKIE=true` detrás de HTTPS, y credenciales reales nunca en el repositorio (`.env` está excluido de git; usar `.env.example` como plantilla).
