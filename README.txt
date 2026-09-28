COLIBRI PRINT - FLUJO MANUAL DE PAGINAS

Cambios:
- api/analyze_files.php SOLO recibe y guarda archivos temporalmente.
- NO abre PDFs ni cuenta paginas.
- solicitar_impresion.php pide al cliente el numero de paginas.
- submit_request.php valida el numero manual y registra la solicitud.
- submit_request.php incluye fallback copy+unlink si rename() falla por permisos.
- Los errores 500 ahora generan un codigo corto para localizar el error en el log.

Subir estos archivos respetando sus rutas:
api/analyze_files.php
submit_request.php
solicitar_impresion.php
assets/js/colibri-print.js
