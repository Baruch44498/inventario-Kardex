# Inventario de prueba Volvo SCH-40

Fuente: `COSTO DE FABRICACION VOLVO SCH-40(6).xlsx`, pestaña `COSTO DE FABRICACIÓN SCH-40 `.
El CSV de `database/fixtures/volvo_sch40_inventario.csv` conserva fila, descripción,
proveedor indicado, cantidad presupuestada y costo unitario **PEN con IGV de compra**.

Hay 187 líneas de materiales y 177 códigos únicos. Se excluyeron 12 servicios y
5 pagos (no son artículos almacenables). Los códigos `IGV` repetidos y los vacíos
se sustituyeron por `DV-R{fila}` para que cada artículo tenga un código único.
Los proveedores sin identificar (`#N/A`) permanecen vacíos en el CSV; los demás
se crean por razón social sin inventarles RUC. El esquema actual no relaciona
directamente un producto con un precio de proveedor: consulta la fila del CSV
para ese dato. El inventario usa como referencia el costo con IGV, redondeado
a cuatro decimales, igual que las notas de ingreso por compra.

**El Excel no contiene repisas ni existencias físicas.** Las tres ubicaciones
`DEMO-VOLVO-EST`, `DEMO-VOLVO-EQP` y `DEMO-VOLVO-ACB` son ficticias. Sirven para
ensayar el flujo entre Planta y Almacén y se asignan por tramo de filas del Excel
(7–80, 81–155 y 156–223). La carga inicial deja todas las existencias en cero.

Desde la raíz del proyecto y con `APP_ENV=local`:

```bash
php artisan migrate
php artisan hidroil:inventario-volvo
php artisan hidroil:inventario-volvo --aplicar
```

Para probar disponibilidad y faltantes con **cinco saldos ficticios**, usa un ID
real de usuario de tu instalación. El movimiento de entrada queda en Kardex con
`origen_tipo=DEMO_VOLVO` y el usuario responsable:

```bash
php artisan hidroil:inventario-volvo --aplicar --stock-demo --usuario=1
php artisan test tests/Feature/InventarioDemoVolvoExcelTest.php
```

El stock ficticio es: 10154=2, 10276=10, 10459=12, 10004=8, 10199=2.
Las cantidades planeadas en el Excel no son saldos de almacén. Ejecutar de nuevo
no duplica productos, ubicaciones ni movimientos; si ya hay movimientos en una
ubicación, el comando nunca cambia su saldo. Los códigos existentes con descripción
o unidad diferente se omiten y se informan en pantalla. No usar esta carga en la
base de producción; para probarla conviene una copia local de la base.

No hay migraciones nuevas. La carga usa tablas existentes de productos, repisas,
inventarios, proveedores y movimientos de inventario.

## Escenario de ingresos para Planta y Almacén

El comando `hidroil:escenario-volvo` crea **tres proveedores ficticios**, sus
cotizaciones, solicitudes y órdenes de compra, y confirma tres notas de ingreso
mediante el servicio normal de Almacén. Los precios unitarios corresponden al
Excel en PEN con IGV; proveedor y cantidades recibidas son simulados y
reproducibles a partir del código de producto. Aproximadamente uno de cada cinco
productos queda sin ingreso, para ensayar faltantes y solicitudes de compra.
No agrega stock a productos que ya tienen existencias en alguna repisa.

```bash
php artisan hidroil:inventario-volvo --aplicar
php artisan hidroil:escenario-volvo
php artisan hidroil:escenario-volvo --aplicar --usuario=1
php artisan test tests/Feature/EscenarioComprasVolvoTest.php
```

Usa el ID de un usuario activo en lugar de `1`. El primer comando también
actualiza la referencia de costo DEMO antigua sin IGV **solo** si la ubicación
está en cero, coincide con el valor antiguo y no tiene movimientos. No altera
compras ni Kardex existentes. Los documentos generados llevan `DEMO-VOLVO` en
su código o descripción y no corresponden a compras reales. El escenario se
crea una sola vez; una segunda ejecución no duplica notas ni saldos. Pruébalo
en una base local de ensayo, pues sus existencias sí quedan disponibles para
las salidas de Planta. Si ya cargaste `--stock-demo`, sus cinco saldos anteriores
se conservan y no se duplican.
