<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';

cp_check_csrf($_POST['csrf'] ?? null);

try {
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) throw new RuntimeException('Tarifa no válida.');

    $db = cp_db();
    $st = $db->prepare('UPDATE cp_print_prices SET enabled=0, updated_at=NOW() WHERE id=?');
    $st->execute([$id]);

    cp_redirect('impresion_precios.php?ok=Tarifa desactivada correctamente');
} catch (Throwable $e) {
    cp_redirect('impresion_precios.php?ok=' . rawurlencode('No se pudo desactivar la tarifa: ' . $e->getMessage()));
}
