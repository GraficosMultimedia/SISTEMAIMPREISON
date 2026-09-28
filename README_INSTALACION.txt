COLIBRI PRINT · PARCHE CFDI IMPUESTO TRASLADADO

1. Sube el contenido de este ZIP respetando las carpetas:
   admin/cfdi_tax_patch.php

2. Abre una sola vez:
   /admin/cfdi_tax_patch.php

3. El parche:
   - requiere sesión de administrador;
   - crea respaldo de admin/facturacion.php e includes/cfdi.php;
   - modifica únicamente la lectura del impuesto trasladado;
   - valida sintaxis PHP;
   - prueba internamente TotalImpuestosTrasladados="430.32";
   - no cambia la estructura de la base de datos;
   - no reemplaza funcionalidades de facturación.

4. Después de confirmar "Cambio aplicado correctamente", elimina:
   /admin/cfdi_tax_patch.php

5. Prueba en facturacion.php cargando el XML. El campo "Impuesto trasladado"
   debe mostrar el valor de cfdi:Impuestos/@TotalImpuestosTrasladados.

Ejemplo esperado:
TotalImpuestosTrasladados="430.32" → Impuesto trasladado = 430.32
