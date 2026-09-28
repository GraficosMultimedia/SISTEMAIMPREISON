<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$db=cp_db();
$requests=$db->query("SELECT r.*,COUNT(i.id) files,COALESCE(SUM(i.page_count),0) pages FROM cp_print_requests r LEFT JOIN cp_print_request_items i ON i.request_id=r.id WHERE COALESCE(r.archive_status,'active')='active' GROUP BY r.id ORDER BY r.id DESC")->fetchAll();
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Recepción de impresiones · Colibrí Print</title><link rel="stylesheet" href="../assets/css/colibri-print.css"></head><body>
<div class="cp-wrap"><div class="cp-brand">COLIBRÍ PRINT · PRODUCCIÓN</div><div class="cp-hero"><div><h1>Recepción de impresiones</h1><p>Cada archivo conserva sus páginas, configuración y costo.</p></div><div class="cp-admin-nav"><a class="cp-btn cp-btn-soft" href="impresion_precios.php">💰 Precios</a><a class="cp-btn cp-btn-soft" href="recepcion_historial.php">📁 Historial</a><a class="cp-btn cp-btn-primary" href="../solicitar_impresion.php">Ver formulario</a></div></div>
<section class="cp-card"><table class="cp-admin-table"><tr><th>Folio</th><th>Cliente</th><th>Archivos</th><th>Páginas</th><th>Estado</th><th>Total</th><th>Fecha</th><th></th></tr>
<?php foreach($requests as $r):?><tr><td><strong>#<?=$r['id']?></strong></td><td><?=cp_e($r['customer_name'])?><br><span class="cp-muted"><?=cp_e($r['customer_email'])?> · <?=cp_e($r['customer_phone'])?></span></td><td><?=$r['files']?></td><td><?=$r['pages']?></td><td><?=cp_e($r['status'])?></td><td><strong><?=cp_money($r['total_estimate'])?></strong></td><td><?=cp_e($r['created_at'])?></td><td><a class="cp-btn cp-btn-soft" href="recepcion_detalle.php?id=<?=$r['id']?>">Ver detalle</a></td></tr><?php endforeach;?>
</table></section></div></body></html>
