CAMBIO: RESUMEN COMERCIAL + NOTAS OPERATIVAS EN SEGUIMIENTO
==============================================================

Base revisada:
- GitHub: GraficosMultimedia/SISTEMAIMPREISON
- seguimiento.php actual
- includes/seguimiento.php actual
- admin/cotizacion_nueva.php actual

Qué hace este cambio
--------------------
En "RESUMEN COMERCIAL / Servicio y pagos" del seguimiento público agrega:

1. Condiciones de pago
2. Tiempo de entrega
3. Lugar de entrega
4. Notas para el cliente
5. Condiciones comerciales
6. Notas operativas de la orden

Origen de datos
---------------
- Condiciones de pago: cp_quotes.payment_terms
- Tiempo de entrega: cp_quotes.delivery_time
- Lugar de entrega: cp_quotes.delivery_place
- Notas para el cliente: cp_quotes.notes
- Condiciones comerciales: cp_quotes.terms
- Notas operativas: cp_orders.notes

Las notas internas (cp_orders.internal_notes y las notas internas de la
cotización) NO se muestran al cliente.

No requiere SQL
---------------
El sistema ya tiene los campos necesarios. El cambio solo corrige los
alias del SELECT de seguimiento y presenta la información.

Instalación
-----------
1. Descomprime este ZIP.
2. Sube "aplicar_resumen_comercial_tracking_v1.php" dentro de /admin/.
3. Abre una sola vez:
   /admin/aplicar_resumen_comercial_tracking_v1.php
4. Debe responder "OK".
5. Prueba el enlace público de seguimiento.
6. Elimina inmediatamente el archivo temporal de /admin/.
7. Opcional: elimina los respaldos:
   seguimiento.php.bak_resumen_comercial_v1
   includes/seguimiento.php.bak_resumen_comercial_v1

El script valida los bloques esperados y no intenta modificar el archivo
si el código ya está aplicado o si la estructura cambió.

Importante
----------
Este ZIP NO incluye credenciales ni datos de producción.
