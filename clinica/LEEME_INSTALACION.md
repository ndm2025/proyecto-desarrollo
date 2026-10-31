# Farmacia y clínica: instalación local

La copia que está funcionando en este equipo se encuentra en C:\xampp_negociofacil\htdocs\clinica y usa la base ClinicaDB.

## Instalación nueva

1. Copiar la carpeta clinica dentro de htdocs de XAMPP.
2. Iniciar Apache y MySQL.
3. Importar db/ClinicaDB.sql en MySQL o phpMyAdmin.
4. Antes de abrir la aplicación, ejecutar desde la carpeta del proyecto:

```powershell
& 'C:\xampp_negociofacil\php\php.exe' db/actualizar_farmacia.php
```

5. Abrir http://localhost/clinica/ e ingresar con la cuenta inicial definida en el SQL. En la instalación de demostración es admin / admin123.

El actualizador aplica las migraciones pendientes y convierte las claves heredadas en hashes, sin cambiar las contraseñas. Puede ejecutarse nuevamente sin duplicar los datos. Los scripts de actualización solo funcionan desde PHP CLI.

## Actualizar una instalación existente

Respaldar primero la carpeta del proyecto y la base con sus procedimientos. Copiar la versión completa del código y ejecutar el actualizador antes de usar la aplicación. No importar nuevamente el SQL inicial sobre una instalación en uso. Los cambios de esquema pueden requerir restaurar el respaldo completo si la actualización falla.

## Integración con la clínica

La autenticación y los usuarios son compartidos. Conservar config/database.php, includes/autenticacion.php, login.php y las migraciones al integrar el trabajo del compañero. Las nuevas cuentas y restablecimientos usan password_hash; el procedimiento anterior sp_ValidarLogin se retira al migrar. No reutilizar el cifrado AES para guardar nuevas claves.

Las sesiones verifican usuario activo, rol activo y SesionVersion. Desactivar una cuenta o restablecer su clave invalida las sesiones anteriores. Los permisos de farmacia están en Permisos, RolPermisos y UsuarioPermisos; no dependen del nombre del rol.

## Operaciones corregidas

- Una compra anulada conserva sus datos y puede originar una recepción corregida con la misma referencia. Solo puede existir una recepción vigente por proveedor y referencia.
- La edición de datos de usuarios, el cambio de estado y el restablecimiento de claves tienen permisos separados. Las cuentas que administran accesos requieren además autorización para administrar accesos.
- Precios de venta tiene una pantalla y un historial propios. La edición general de un producto existente no modifica sus precios.
- Corregir entre lotes registra una salida y una entrada juntas para el mismo producto. Se conserva el total de unidades y no se alteran fechas ni estados de los lotes.
- Inventario separa unidades físicas, disponibles, bloqueadas vigentes y vencidas. Las categorías no duplican unidades.
- Kardex permite consultar por producto, lote y fechas, con saldo inicial del período y saldo final. Los estados del lote no crean entradas ni salidas.
- Las exportaciones CSV incluyen todos los resultados de los filtros. Requieren permisos específicos y no incluyen costos si el usuario no puede consultarlos.
- Las alertas se consultan dentro de la aplicación; no envían mensajes ni notificaciones externas.
- Los ajustes manuales exigen motivo y tienen protección contra reenvíos. El kardex avisa diferencias entre existencias y movimientos sin crear ajustes automáticos.
- Los costos unitarios mostrados son referencias de la última compra recibida vigente; no representan una valoración contable.
- Las ventas, sus comprobantes internos y las devoluciones están conectados con inventario y kardex.

## Bloque 2

Entorno probado: XAMPP 8.2.12, PHP 8.2.12 y MariaDB 10.4.32. El kardex utiliza funciones de ventana de SQL y las migraciones utilizan la sintaxis de MariaDB.

Rutas principales: inventario/index.php, inventario/lotes.php, inventario/alertas.php y kardex/index.php. El menú Lotes conserva su dirección anterior y abre la consulta mejorada por lote.

Permisos: inventario.ver e inventario.exportar controlan la consulta y exportación de existencias. kardex.ver y kardex.exportar controlan el historial y su exportación. medicamentos.costos decide si las consultas y los CSV incluyen costos referenciales.

El saldo inicial del kardex suma los movimientos anteriores a la fecha inicial. Las filas se ordenan por fecha de registro y MovimientoID; la paginación no reinicia el saldo. El CSV incluye filas identificadas de saldo inicial y saldo final. Las cantidades se expresan en unidades base. Si las existencias físicas actuales no coinciden con todo el historial registrado, se muestra una diferencia pendiente de conciliar, sin modificar los datos.

Las alertas de vencimiento solo incluyen lotes con unidades físicas. La disponibilidad excluye productos inactivos, lotes inactivos y vencidos. Los lotes que vencen hoy se consideran vigentes durante ese día.

## Bloque 3

Rutas: ventas/index.php (historial y resumen), ventas/nueva.php (captura y cobro), ventas/ver.php (detalle y lotes), ventas/reintegrar.php (devolución o anulación), ventas/comprobante.php (comprobante interno imprimible) y ventas/exportar.php (CSV).

La migración 006_ventas agrega las tablas del módulo sin cambiar las existencias anteriores. La base inicial sigue siendo db/ClinicaDB.sql; el actualizador aplica todas las migraciones hasta la 006. Los registros de prueba no se incluyen en el código entregado.

Permisos: ventas.ver permite consultar; ventas.crear, vender; ventas.descuento, aplicar descuentos; ventas.devolver, devolver; ventas.anular, anular; ventas.imprimir, abrir e imprimir comprobantes; ventas.exportar, exportar el historial. Las acciones también requieren ventas.ver. Los accesos se pueden heredar del rol, permitir o denegar individualmente desde Roles y permisos. No existe un acceso especial por nombre de rol.

Las presentaciones deben estar activas y tener precio mayor que cero. Se conserva una copia del nombre, código, precio, factor y presentación vendidos. El servidor valida los precios y factores actuales y no acepta precios alterados. El descuento es un monto fijo por renglón completo, introducido en el paso de cobro, nunca un porcentaje ni un descuento automático por unidad. Solo se acepta efectivo y debe cubrir el total final; se calcula el cambio.

La salida se hace en unidades base, primero por vencimiento y luego por ID del lote. Se excluyen productos inactivos y lotes inactivos o vencidos. Los lotes que vencen hoy siguen vigentes durante ese día. Una presentación puede consumir unidades de varios lotes. Se registra la asignación exacta de cada salida; todo se guarda en una transacción. Las claves de operación y bloqueos impiden descontar dos veces o vender más de las existencias ante solicitudes simultáneas.

Las devoluciones parciales se capturan en cantidades enteras de la presentación vendida: una caja se devuelve completa. El descuento se distribuye proporcionalmente usando el importe neto original; las devoluciones acumuladas conservan todos los centavos. Las unidades retornan a sus asignaciones originales de lote, en su orden de salida. Si el lote o producto está inactivo o vencido, se conserva ese estado y no se habilita su venta. La pantalla muestra el efectivo que se debe entregar al cliente; el sistema registra el reintegro, no realiza transferencias de dinero.

Una anulación reintegra solamente lo pendiente, incluso si ya existieron devoluciones parciales. El motivo, usuario, fecha, cantidades, lotes e importes permanecen registrados. No se borran ventas ni se admiten reintegros duplicados o cantidades superiores a lo vendido. Estados: Vigente, Parcial, Devuelta y Anulada.

El comprobante es interno, de formato pequeño y con impresión opcional. Conserva el total original y muestra los reintegros y el estado actuales cuando se reimprime. No se implementó facturación electrónica o fiscal.

El historial filtra por fecha de la venta, vendedor, estado y número/cliente. Muestra importe original, reintegros acumulados y venta neta. Incluye los reintegros posteriores al período si corresponden a esas ventas: no es un cierre diario de caja. El CSV exporta todos los resultados filtrados, aunque se navegue en otra página, y protege los textos frente a fórmulas de hojas de cálculo. El kardex identifica ventas, devoluciones y anulaciones con vínculos sujetos al permiso de consulta de ventas.


## Interfaz de ventas actualizada

Ventas abre el punto de venta: búsqueda por código o nombre, presentación, existencia disponible, cantidad, detalle y cobro en efectivo. La pestaña Listado de ventas muestra el historial paginado, con fechas y filtros; Desde vacío incluye todas las ventas hasta hoy. Los descuentos son montos en quetzales por renglón, sujetos al permiso correspondiente.

El borrador se conserva al navegar o recargar en la misma pestaña durante un máximo de 24 horas. No se comparte entre pestañas ni es un registro guardado en la base de datos. Al registrar la venta se elimina ese borrador. La existencia y los precios se validan nuevamente en el servidor.

En teléfono el menú se abre con Menú del sistema; el detalle y el historial se adaptan a tarjetas. La barra inferior permite ir al cobro. Compras, kardex y correcciones de lotes incluyen buscador de productos. Los ajustes de lote muestran confirmación con el saldo resultante.

Esta actualización conserva PHP, JavaScript, Bootstrap y MySQL/MariaDB. No requiere cambios adicionales del esquema de base de datos.

## Bloque 4: Atención de la Clínica y Recuperación de Usuario

Rutas principales:
- `atencion/index.php`: Sala de atención médica y monitoreo de pacientes del día en espera, atendidos o cancelados.
- `atencion/atender.php`: Estación de atención clínica integral, triaje con signos vitales (P/A, FC, Temp, SpO2, FR, peso, talla, IMC automático), diagnóstico, tratamiento y receta médica conectada al inventario de medicamentos.
- `consultas/imprimir_receta.php`: Impresión oficial de receta médica con membrete y prescripción.
- `recuperar.php`: Módulo de autoservicio para recordar el nombre de usuario mediante el correo institucional y restablecer contraseñas de forma segura con `password_hash` e invalidación de sesiones previas (`SesionVersion`).

Permisos:
- `atencion.gestionar`: Controla el acceso a la sala de atención y la realización de atenciones médicas.
- El rol `Administrador` posee acceso total a todos los módulos clínicos y de farmacia.
