# Pruebas

## Comandos

```bash
php artisan test              # suite completa
php artisan test --filter=X   # un archivo o método concreto
vendor/bin/pint --test        # estilo de código (no modifica nada)
vendor/bin/pint               # aplica el estilo automáticamente
npm run build                 # build de producción de assets
```

Antes de ejecutar la suite por primera vez: `php artisan migrate:fresh --seed` debe correr sin intervención manual sobre SQLite.

## Estrategia

- **Unit** (`tests/Unit`): lógica pura sin HTTP ni base de datos real cuando es posible — motor de cálculo (`InvoiceCalculator`), máquinas de estado, generador de CUFE, simulador DIAN, cálculo de dígito de verificación de NIT.
- **Feature** (`tests/Feature`): flujos reales a través de HTTP (`$this->post(...)`, `$this->get(...)`), con base de datos real (`RefreshDatabase`) y aserciones sobre valores persistidos, no solo códigos de estado.
- Cada módulo de negocio tiene su propio archivo de pruebas (clientes, productos, impuestos, facturas, notas crédito, reportes, asistente IA), más un `EndToEndInvoiceLifecycleTest` que recorre el flujo completo de punta a punta con valores controlados (producto `SERV-TEST`, cantidad 4, precio 100000, IVA 19 % → base 400000 / IVA 76000 / total 476000) y verifica esos valores en `invoice_items`, el resumen, la factura emitida, el PDF y la nota crédito.

## Categorías cubiertas

- **Autenticación y roles**: login, recuperación de contraseña, permisos por rol vía URL directa (no solo visibilidad de botones en la UI).
- **Multiempresa**: una empresa nunca puede listar, ver, descargar o accionar sobre un recurso de otra empresa — probado también contra escritura directa a los modelos (mass assignment de `company_id`).
- **Cálculos y dinero**: decimales exactos, redondeo HALF_EVEN, valores pequeños/grandes, descuentos, múltiples impuestos combinados, ausencia de errores de punto flotante (incluido el clásico `0.1 + 0.2`).
- **Estados**: transiciones válidas e inválidas de factura y nota crédito, bloqueo de edición tras emisión, estados terminales.
- **Notas crédito**: parciales, múltiples parciales, saldo acreditable, anulación total, doble anulación bloqueada, snapshots históricos, inmutabilidad de la factura original.
- **Trazabilidad**: append-only a nivel de modelo (intentos de editar/eliminar un evento lanzan excepción), presencia de actor/estado/metadata.
- **Documentos**: PDF privado, autorización por empresa y por estado (`issued`), integridad por hash, nombre de archivo saneado; página pública de verificación con token de alta entropía, sin exponer datos de contacto del cliente, respuesta segura ante token inválido.
- **Numeración**: reserva atómica aproximada con llamadas sucesivas rápidas sobre la misma resolución (sin duplicados ni huecos) y entre resoluciones distintas en paralelo lógico.
- **Mensajes al usuario**: los mensajes de validación, autenticación y restablecimiento de contraseña se verifican en español, no como claves crudas (`validation.required`) ni en inglés.
- **Asistente IA**: aislamiento multiempresa de cada herramienta, modo local sin credenciales externas, resiliencia si el proveedor externo falla, rate limiting, inyección de prompts en datos de catálogo/cliente sin efecto sobre el comportamiento, acciones críticas que requieren confirmación humana y nunca se ejecutan desde una respuesta textual, métricas y feedback.

## Qué no hacer

No se añaden pruebas solo para subir el contador. Cada prueba nueva de esta fase corresponde a un comportamiento real verificado por lectura de código (y, en varios casos, a un error genuino encontrado y corregido durante la auditoría — ver el informe de la fase correspondiente).
