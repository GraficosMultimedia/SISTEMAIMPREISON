CAMBIO v4 - impresion_precios.php

REEMPLAZAR UNICAMENTE:
admin/impresion_precios.php
admin/catalog_save.php
admin/price_save.php
admin/price_delete.php

Correcciones:
1. Se corrige el guardado de tamaños/materiales/acabados: ahora el codigo forma parte del formulario.
2. Si una actualización antigua no envía codigo, el backend recupera el codigo existente.
3. Guardar, editar y eliminar quedan centralizados en impresion_precios.php.
4. La matriz de tarifas permite guardar, editar y eliminar.
5. Se elimina el encabezado duplicado que estaba rompiendo la apariencia del dashboard.
6. CSS de esta pantalla queda aislado y forzado al tema oscuro del dashboard.
7. No se modifica la base de datos ni config/bootstrap.php.

IMPORTANTE:
Subir primero a CPanel y probar.
NO actualizar GitHub hasta validar.
