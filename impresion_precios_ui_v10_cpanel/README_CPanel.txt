IMPRESION_PRECIOS V10 - CAMBIO VISUAL AISLADO

Archivo:
  admin/impresion_precios.php

IMPORTANTE:
- Este pack reemplaza UNICAMENTE admin/impresion_precios.php.
- Se conserva dentro del archivo la capa POST/CSRF/SQL funcional de la version estable v7.
- No reemplaza header.php, footer.php, catalog_save.php, price_save.php ni price_delete.php.

CAMBIO VISUAL:
- Tamaños, Materiales y Acabados pasan a tarjetas compactas.
- Cada tarjeta abre un popup animado para nuevo/editar/guardar/limpiar/eliminar.
- El checkbox de activo se presenta como switch accesible, conservando el input checkbox real y name=enabled.
- Matriz de tarifas queda como listado.
- Nueva tarifa y Editar usan popup.
- Eliminar usa los endpoints existentes.
- Responsive para PC, tablet y movil.

PRUEBA RECOMENDADA EN CPANEL:
1. Respaldar admin/impresion_precios.php actual.
2. Subir este archivo a /admin/.
3. Probar abrir popup de Tamaños, seleccionar Carta, cambiar un dato y Guardar.
4. Probar Materiales y Acabados.
5. Probar Nueva tarifa, Editar tarifa y Eliminar.
6. Verificar mensaje de resultado y que el listado se actualice.
