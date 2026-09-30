COLIBRI PRINT - COTIZACION PDF CORPORATIVO 2 PAGINAS
====================================================

Este paquete contiene la nueva salida de cotización en 2 páginas:

1) Primera página: cotización comercial completa.
2) Segunda página: resumen de pago, pago anticipado, saldo y datos para realizar el anticipo.

ARCHIVO
-------
admin/cotizacion_pdf_2paginas.php

INTEGRACION
-----------
El archivo reutiliza la lógica existente del sistema:
- quote_get()
- quote_items()
- quote_totals()
- company_profile()

No modifica la base de datos.

Para abrir la nueva cotización desde el panel, utiliza:
/admin/cotizacion_pdf_2paginas.php?id=ID_DE_COTIZACION

CAMBIO RECOMENDADO EN admin/cotizacion.php
-------------------------------------------
Sustituir el botón actual:

<button class="btn btn-secondary" type="button" onclick="window.print()">Imprimir / PDF</button>

por:

<a class="btn btn-secondary" target="_blank" href="/admin/cotizacion_pdf_2paginas.php?id=<?=((int)$id)?>">Imprimir / PDF</a>

ANTICIPO
--------
El porcentaje se obtiene de payment_terms cuando contiene un porcentaje, por ejemplo:
"50% de anticipo y 50% contra entrega".

El sistema calcula automáticamente:
- total de la cotización
- importe del anticipo
- saldo pendiente

Si payment_terms no contiene un porcentaje, el PDF muestra el anticipo como "Por definir" y no inventa un importe.

DATOS BANCARIOS
---------------
La segunda página utiliza company_profile()['payment_info'] exactamente como está configurado en el sistema.

SEGURIDAD
---------
La vista exige autenticación mediante require_auth(). No acepta un ID de cotización inexistente.

PRUEBA
------
Validación PHP realizada con php -l: sin errores de sintaxis.
