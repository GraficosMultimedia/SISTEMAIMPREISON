PDF PROFESIONALES - TAMAÑO CARTA
=================================

Cambios incluidos:
1. Cotización:
   admin/cotizacion_pdf_2paginas.php
   - Tamaño Carta (8.5 x 11 pulgadas)
   - 2 páginas
   - Anticipo y saldo dinámicos

2. Orden:
   admin/orden_pdf_2paginas.php
   - Tamaño Carta
   - 2 páginas
   - Datos dinámicos de la orden
   - Total, pagos y saldo
   - Condiciones comerciales

3. Seguimiento:
   seguimiento_comprobante.php
   - Tamaño Carta
   - 2 páginas
   - Estado del pedido
   - Servicios
   - Total, pagado y saldo
   - Pago registrado
   - Datos para pago

4. Integración de Orden:
   Agregar el botón indicado en CAMBIO_ORDEN_PHP.txt dentro de admin/orden.php.

No se modifica:
- Base de datos
- Flujo de órdenes
- Estados
- Formularios
- Seguimiento
- Lógica administrativa

Validación:
Los tres archivos PHP fueron comprobados con `php -l` y no presentan errores de sintaxis.
