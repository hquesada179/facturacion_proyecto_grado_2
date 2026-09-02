---
name: Facturación Pro
colors:
  surface: '#f8f9ff'
  surface-dim: '#cbdbf5'
  surface-bright: '#f8f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff4ff'
  surface-container: '#e5eeff'
  surface-container-high: '#dce9ff'
  surface-container-highest: '#d3e4fe'
  on-surface: '#0b1c30'
  on-surface-variant: '#444651'
  inverse-surface: '#213145'
  inverse-on-surface: '#eaf1ff'
  outline: '#757682'
  outline-variant: '#c5c5d3'
  surface-tint: '#4059aa'
  primary: '#00236f'
  on-primary: '#ffffff'
  primary-container: '#1e3a8a'
  on-primary-container: '#90a8ff'
  inverse-primary: '#b6c4ff'
  secondary: '#4648d4'
  on-secondary: '#ffffff'
  secondary-container: '#6063ee'
  on-secondary-container: '#fffbff'
  tertiary: '#4b1c00'
  on-tertiary: '#ffffff'
  tertiary-container: '#6e2c00'
  on-tertiary-container: '#f39461'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dce1ff'
  primary-fixed-dim: '#b6c4ff'
  on-primary-fixed: '#00164e'
  on-primary-fixed-variant: '#264191'
  secondary-fixed: '#e1e0ff'
  secondary-fixed-dim: '#c0c1ff'
  on-secondary-fixed: '#07006c'
  on-secondary-fixed-variant: '#2f2ebe'
  tertiary-fixed: '#ffdbcb'
  tertiary-fixed-dim: '#ffb691'
  on-tertiary-fixed: '#341100'
  on-tertiary-fixed-variant: '#773205'
  background: '#f8f9ff'
  on-background: '#0b1c30'
  surface-variant: '#d3e4fe'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 48px
    fontWeight: '700'
    lineHeight: 56px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '600'
    lineHeight: 40px
    letterSpacing: -0.01em
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  title-lg:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '500'
    lineHeight: 20px
    letterSpacing: 0.05em
  label-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 4px
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 32px
  gutter: 24px
  margin-mobile: 16px
  margin-desktop: 40px
---

## Brand & Style

El sistema de diseño está orientado a transmitir **confianza, eficiencia y modernidad** para el sector FinTech en Colombia. Se basa en una estética de **Minimalismo Profesional** que prioriza la legibilidad de datos financieros y la reducción de la carga cognitiva para los propietarios de PYMES y personal administrativo.

La dirección visual combina superficies limpias con una jerarquía clara, utilizando espacios en blanco generosos para separar flujos de trabajo complejos. El estilo evita decoraciones innecesarias, enfocándose en la precisión técnica y la claridad operativa necesaria para la gestión tributaria y contable.

## Colores

La paleta se centra en el **Azul Corporativo (Primary)** para establecer una base de estabilidad y cumplimiento legal. El **Púrpura Eléctrico (Secondary)** se reserva exclusivamente para interacciones potenciadas por IA y funciones inteligentes.

- **Fondo Principal:** Se utiliza un gris pizarra muy claro (`#F8FAFC`) para reducir el brillo de la pantalla durante jornadas laborales extensas.
- **Superficies:** El blanco puro se reserva para tarjetas y contenedores de datos, creando un contraste nítido con el fondo.
- **Semántica:** Los colores de estado (Éxito, Advertencia, Error) siguen estándares internacionales para una interpretación rápida de la validez de las facturas.

## Tipografía

Se utiliza **Inter** como fuente única debido a su excepcional legibilidad en pantallas de alta densidad y su carácter neutral y sistemático.

- **Jerarquía:** Los pesos de 600 (Semibold) se reservan para títulos y estados financieros clave. El peso 400 (Regular) se utiliza para toda la entrada de datos y lectura de tablas.
- **Datos Numéricos:** Se recomienda activar las funciones OpenType de "Tabular Figures" para las tablas de facturación, asegurando que las columnas de precios y valores DIAN se alineen verticalmente.
- **Adaptabilidad:** En dispositivos móviles, los títulos de sección se reducen automáticamente para maximizar el área de trabajo útil.

## Layout & Espaciado

El sistema utiliza una **cuadrícula fluida de 12 columnas** para escritorio y una cuadrícula de 4 columnas para móvil. El ritmo vertical se basa en una unidad base de 4px.

- **Estrategia de Contenedores:** Los módulos de facturación utilizan un ancho máximo de 1280px para mantener la legibilidad de las líneas de artículos.
- **Márgenes:** Se aplican márgenes internos (padding) de 24px (`lg`) en tarjetas para evitar el amontonamiento de datos técnicos.
- **Densidad:** El espaciado entre filas de tablas debe ser de 12px (densidad media) para permitir una visualización clara de múltiples registros sin sacrificar la capacidad de escaneo.

## Elevación y Profundidad

La jerarquía visual se construye mediante **Capas Tonales** y sombras ambientales sutiles. No se utilizan bordes pesados; en su lugar, la profundidad define los límites.

- **Nivel 0 (Fondo):** `#F8FAFC`.
- **Nivel 1 (Tarjetas):** Fondo blanco con una sombra de caída suave (0px 4px 12px, 5% opacidad de Primary Color).
- **Nivel 2 (Modales/Pop-overs):** Elevación superior con sombra más difusa (0px 10px 25px, 10% opacidad de Primary Color) para separar claramente el proceso de edición del panel principal.
- **Interacción:** Los elementos interactivos (botones) ganan una sombra ligeramente más oscura al pasar el cursor (hover) para simular cercanía física.

## Formas

El sistema adopta un lenguaje de formas **Rounded (Redondeado)** para suavizar la naturaleza rígida de los datos financieros, haciendo la herramienta más amigable y menos intimidante.

- **Componentes Base:** Los botones y campos de entrada utilizan un radio de 8px (`0.5rem`).
- **Contenedores de Contenido:** Las tarjetas y paneles principales utilizan un radio de 16px (`1rem`) para una apariencia moderna y sofisticada.
- **Indicadores:** Los badges de estado (e.g., "Pagado", "Pendiente") utilizan un radio estilo "píldora" para diferenciarse claramente de los campos de datos cuadrados.

## Componentes

### Botones y Acciones
- **Primario:** Fondo azul corporativo, texto blanco, bordes redondeados de 8px.
- **IA/Sugerencias:** Gradiente sutil entre el color primario y secundario con íconos lineales.
- **Estados:** Deshabilitado con opacidad al 40% para indicar procesos bloqueados por validación.

### Campos de Entrada (Inputs)
- **Estilo:** Fondo blanco, borde de 1px en gris claro (`#E2E8F0`).
- **Foco:** El borde cambia al azul primario con un anillo de resplandor (ring) de 3px con 10% de opacidad.
- **Validación:** Los errores muestran un borde rojo suave y un mensaje de ayuda inferior en 12px.

### Tarjetas de Resumen (KPI Cards)
- Ubicadas en la parte superior del dashboard.
- Utilizan íconos lineales con un fondo circular de color semántico tenue (15% de opacidad) para representar categorías de facturación.

### Tablas de Facturación
- Encabezados en `label-sm` con color de texto neutral.
- Filas con efecto hover que cambia el fondo a un gris muy sutil.
- Las celdas de monto deben usar tipografía monoespaciada o ajustes numéricos para alineación perfecta de decimales.

### Badges de Estado
- **Validada:** Texto verde oscuro sobre fondo verde claro.
- **En Proceso:** Texto ámbar sobre fondo crema.
- **Rechazada:** Texto rojo sobre fondo rosa pálido.