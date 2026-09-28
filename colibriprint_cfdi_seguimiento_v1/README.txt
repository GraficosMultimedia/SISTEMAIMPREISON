COLIBRI PRINT · VINCULO CFDI AL SEGUIMIENTO
============================================

Objetivo
--------
Mostrar en seguimiento.php todas las facturas CFDI vinculadas a la misma orden de servicio y permitir descargar XML/PDF sin exponer otras órdenes.

Archivos incluidos
------------------
includes/seguimiento_cfdi.php
seguimiento_cfdi_documento.php
admin/seguimiento_cfdi_patch.php

Instalación cPanel
------------------
1. Sube y extrae el ZIP en la raíz de Colibrí Print.
2. Inicia sesión como administrador.
3. Abre: /admin/seguimiento_cfdi_patch.php
4. Debe responder "OK: Integración CFDI aplicada al seguimiento."
5. Prueba el seguimiento de la orden que ya tiene las dos facturas.
6. Comprueba que aparezcan las 2 facturas y que XML/PDF descarguen correctamente.
7. Elimina /admin/seguimiento_cfdi_patch.php después de la prueba.

Seguridad
---------
- El enlace público requiere el token de seguimiento de la orden.
- El documento debe pertenecer a la misma order_id del token.
- El archivo físico debe estar dentro de storage/cfdi.
- El instalador crea un respaldo antes de modificar seguimiento.php.
- Si la verificación PHP falla, restaura automáticamente el respaldo.

No se modifica
--------------
- facturacion.php
- includes/cfdi.php
- footer.php
- la base de datos
- la lógica de creación de facturas
- la lógica de pagos
