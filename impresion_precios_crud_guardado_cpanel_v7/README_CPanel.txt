V7 - RESTAURACION DEL GUARDADO DE IMPRESION_PRECIOS

CAUSA:
La version v6 reemplazo impresion_precios.php por una version de solo visualizacion.
Esa version ya no contenia el manejador POST section_action.
Por eso catalog_save.php, price_save.php y price_delete.php ya no tenian donde ejecutar las operaciones.

ESTA VERSION RESTAURA:
- manejador POST catalog_save
- manejador POST catalog_delete
- manejador POST price_save
- manejador POST price_delete
- wrappers catalog_save.php / price_save.php / price_delete.php

REEMPLAZAR UNICAMENTE:
admin/impresion_precios.php
admin/catalog_save.php
admin/price_save.php
admin/price_delete.php

NO REEMPLAZAR:
includes/header.php
includes/footer.php
config/bootstrap.php
config/runtime.php
otros archivos.

FLUJO DE PRUEBA:
1. Subir estos 4 archivos a /public_html/admin/
2. Probar guardar un TAMAÑO.
3. Probar guardar un MATERIAL.
4. Probar guardar un ACABADO.
5. Probar editar un registro.
6. Probar una tarifa.
7. Si todo funciona, actualizar GitHub manualmente.

NOTA:
Este paquete no modifica la base de datos.
