<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
$db = cp_db();
$rows = $db->query("SELECT r.*, COUNT(i.id) AS files, COALESCE(SUM(i.page_count),0) AS pages FROM cp_print_requests r LEFT JOIN cp_print_request_items i ON i.request_id=r.id WHERE r.archive_status='archived' GROUP BY r.id ORDER BY r.archived_at DESC, r.id DESC")->fetchAll();
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Historial de impresiones · Colibrí Print</title><link rel="stylesheet" href="../assets/css/colibri-print.css"></head><body>
<div class="cp-wrap"><div class="cp-brand">COLIBRÍ PRINT · PRODUCCIÓN</div><div class="cp-hero"><div><h1>Historial de impresiones</h1><p>Trabajos finalizados cuyos archivos y registros fueron conservados.</p></div><a class="cp-btn cp-btn-soft" href="recepcion_impresiones.php">← Recepción</a></div>
<section class="cp-card"><table class="cp-admin-table"><tr><th>Folio</th><th>Cliente</th><th>Archivos</th><th>Páginas</th><th>Total</th><th>Archivado</th><th></th></tr>
<?php foreach($rows as $r): ?><tr><td><strong>#<?= (int)$r['id'] ?></strong></td><td><?=cp_e($r['customer_name'])?><br><span class="cp-muted"><?=cp_e($r['customer_phone'])?></span></td><td><?= (int)$r['files'] ?></td><td><?= (int)$r['pages'] ?></td><td><strong><?=cp_money($r['total_estimate'])?></strong></td><td><?=cp_e($r['archived_at'] ?? '')?></td><td><a class="cp-btn cp-btn-soft" href="recepcion_detalle.php?id=<?= (int)$r['id'] ?>">Ver detalle</a></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="7" class="cp-muted">No hay trabajos archivados.</td></tr><?php endif; ?></table></section></div></body></html>
