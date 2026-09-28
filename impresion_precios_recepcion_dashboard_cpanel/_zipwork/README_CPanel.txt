CAMBIO - SUBMENU DE OPERACION EN impresion_precios.php

REEMPLAZAR UNICAMENTE:
admin/impresion_precios.php

Se agregan dos accesos contextuales en la parte superior de Configuracion de impresion:
- Recepcion de impresiones -> recepcion_impresiones.php
- Historial de impresiones -> recepcion_historial.php

El bloque respeta el CSS oscuro del dashboard y es responsive.
No se modifica la base de datos ni otros archivos.

FLUJO:
1. Subir a CPanel.
2. Probar ambos botones.
3. Verificar que impresion_precios.php conserve su apariencia.
4. No actualizar GitHub hasta validar en CPanel.
