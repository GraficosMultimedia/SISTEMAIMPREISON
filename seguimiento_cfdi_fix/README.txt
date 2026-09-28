FIX: seguimiento CFDI - error tracking_cfdi_for_order()

OBJETIVO
--------
Corregir el error PHP:
Call to undefined function tracking_cfdi_for_order()

CAMBIOS
-------
1. includes/seguimiento_cfdi.php
   - Conserva tracking_invoice_documents().
   - Añade tracking_cfdi_for_order() como alias compatible.

2. seguimiento.php
   - Debe cargar includes/seguimiento_cfdi.php antes de utilizar
     tracking_cfdi_for_order().

INSTALACIÓN MANUAL
------------------
1. Respaldar los archivos actuales.
2. Reemplazar/subir:
   includes/seguimiento_cfdi.php
3. En seguimiento.php, añadir después de los require iniciales:
   require_once __DIR__ . '/includes/seguimiento_cfdi.php';

4. Probar:
   /seguimiento.php?t=TU_TOKEN

NOTA
----
Este paquete corrige únicamente la función faltante y su carga.
No modifica la lógica de facturación, órdenes, pagos ni base de datos.
