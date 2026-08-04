# Guía visual global de HIDROIL

Versión: UI 1.0  
Ámbito: toda la aplicación administrativa  
Base funcional compatible: fase 17.0.2.2.1

## 1. Principios obligatorios

1. La identidad de HIDROIL se conserva: navegación azul oscuro, azul/celeste como color principal, fondo gris claro y superficies blancas.
2. La lógica de negocio no se cambia para resolver un problema visual.
3. Un patrón repetido se corrige en el componente, token o estilo global responsable; no mediante parches por pantalla.
4. Antes de crear un componente se verifica si ya existe uno equivalente que pueda ampliarse.
5. Las rutas, permisos, roles, validaciones, nombres de campos, eventos y atributos de integración se conservan.
6. Los módulos futuros permanecen visibles y abren una pantalla informativa; nunca simulan datos u operaciones inexistentes.
7. Toda pantalla debe funcionar con teclado, tener foco visible y comunicar estados mediante texto o icono además del color.

## 2. Fuente oficial de estilos

- `public/css/hidroil-admin.css` contiene los estilos heredados que se migrarán progresivamente.
- `public/css/hidroil-design-system.css` contiene los tokens y componentes globales aprobados.
- Toda regla global nueva debe incorporarse al sistema de diseño.
- No se permiten estilos `style="..."`, bloques `<style>` en Blade ni valores arbitrarios repetidos.
- El orden de carga es: estilos heredados y después sistema de diseño. Esto permite una migración gradual sin regresiones.

## 3. Tokens

Los valores oficiales se declaran en `:root` dentro de `hidroil-design-system.css`:

- Colores corporativos, texto, superficies y bordes.
- Colores semánticos: información, éxito, advertencia, peligro y neutral.
- Escala de espaciado de 4 a 48 px.
- Radios de borde de 6 a 20 px.
- Sombras baja, media y alta.
- Alturas de controles de 40, 44 y 48 px.
- Anchos máximos de contenido.
- Duraciones y curvas de transición.

No debe declararse un color hexadecimal nuevo en una vista. Si un valor se repite o comunica un significado, debe convertirse en token.

## 4. Estructura de página

Las páginas internas usarán este orden:

1. Topbar global.
2. Contenedor de página.
3. Encabezado con área, título, descripción, estado y acciones.
4. Indicadores, cuando aporten una decisión real.
5. Filtros o navegación secundaria.
6. Contenido principal en paneles.
7. Acciones finales.
8. Estados de carga, vacío, éxito o error.

Reglas:

- Ancho máximo: `--container-max`.
- Padding adaptable mediante `--page-gutter`.
- Una acción primaria predominante por sección.
- Los encabezados equivalentes usan `<x-ui.page-header>`.
- El estado del documento se coloca junto al título, no aislado al final de la página.

## 5. Componentes globales

Componentes iniciales aprobados:

- `<x-ui.page-header>`: encabezado interno de una página.
- `<x-ui.status-badge>`: estado semántico consistente.
- `<x-ui.module-card>`: acceso completo y clicable a un módulo.
- `<x-ui.confirmation-modal>`: confirmaciones internas accesibles.
- `<x-ui.planned-module>`: pantalla de módulo futuro.
- Componentes existentes de iconos, paginación, estados vacíos, tablas responsive, combobox y stepper.

Todo componente nuevo debe:

- Resolver un patrón presente o previsto en más de una pantalla.
- Aceptar contenido mediante propiedades o slots.
- Evitar conocimiento de controladores o modelos concretos.
- Tener estado responsive, focus, disabled y loading cuando aplique.

## 6. Botones y acciones

- Primario: acción principal de la sección.
- Secundario: acción segura alternativa.
- Terciario: navegación o acción de baja prioridad.
- Destructivo: anular, desactivar o eliminar.
- Solo icono: únicamente cuando el significado sea evidente; requiere `aria-label` y tooltip.

No se permiten `alert()`, `confirm()` ni mensajes del navegador. Las acciones destructivas deben explicar la consecuencia y solicitar el motivo cuando el backend ya lo exija.

## 7. Formularios

- Altura mínima de control: 44 px.
- Label siempre visible y asociado al campo.
- Ayuda debajo del control; error debajo de la ayuda o en su lugar.
- Los campos obligatorios muestran marca textual accesible.
- `name`, `id`, `value`, `old()`, CSRF, validaciones y atributos `data-*` se conservan.
- Los formularios extensos se dividen visualmente en secciones; no necesariamente en rutas distintas.
- En móvil los campos ocupan el ancho disponible y las acciones se apilan.

## 8. Tablas y filtros

- El buscador recibe más espacio que los filtros.
- Los filtros se reorganizan, no se comprimen por debajo de un ancho legible.
- La columna principal es un enlace descriptivo.
- Las acciones se agrupan y cada icono incluye nombre accesible.
- El listado muestra estado vacío y paginación.
- Solo las tablas que lo necesitan usan desplazamiento horizontal controlado.

## 9. Módulos en planificación

Un módulo futuro:

- Sigue visible en dashboard y sidebar cuando el permiso lo autoriza.
- Usa una ruta válida y la plantilla global.
- Indica área, estado, descripción, perfil autorizado y ausencia de operaciones.
- No contiene estadísticas, formularios ni datos simulados.
- Puede mostrar `Próximamente`, `En planificación` o `En desarrollo`.

## 10. Responsive y accesibilidad

Verificación mínima: 1440, 1366, 1280, 1024, 768, 480 y 375 px.

Criterios obligatorios:

- Sin scroll horizontal global.
- Navegación móvil en drawer y cierre al elegir una opción.
- Controles táctiles de al menos 40 px; preferencia 44 px.
- Focus visible en enlaces, botones y campos.
- `aria-current` en navegación activa y `aria-expanded` en grupos.
- Modales con foco inicial, trampa de foco, cierre con Escape y retorno del foco.
- Respeto de `prefers-reduced-motion`.

## 11. Orden de propagación

1. Base global: tokens, layout, topbar, sidebar, modal, estados y módulos futuros.
2. Piloto: Clientes, Tipos de cliente, Cotizaciones, Proformas y Órdenes.
3. Dashboard administrativo.
4. Compras y Proveedores.
5. Almacén.
6. Control de Planta.
7. Contabilidad y Administración.

Cada bloque debe incluir pruebas de regresión y una lista exacta de archivos. Ningún bloque visual crea migraciones.

## 12. Criterios de aceptación

- Estructura consistente entre pantallas equivalentes.
- Sin controles fuera de paneles ni campos desbordados.
- Sin confirmaciones nativas.
- Sidebar y topbar compartidos.
- Estados y botones coherentes.
- Módulos futuros accesibles y claramente identificados.
- Lógica, rutas, permisos y validaciones intactos.
- Sin errores de consola ni regresiones funcionales.

