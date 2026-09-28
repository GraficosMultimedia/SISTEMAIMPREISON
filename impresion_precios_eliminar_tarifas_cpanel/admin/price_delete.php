<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';

cp_check_csrf($_POST['csrf'] ?? null);

try {
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) {
        throw new RuntimeException('Tarifa no válida.');
    }

    $db = cp_db();

    $st = $db->prepare('DELETE FROM cp_print_prices WHERE id=?');
    $st->execute([$id]);

    if ($st->rowCount() < 1) {
        throw new RuntimeException('La tarifa no existe o ya fue eliminada.');
    }

    cp_redirect('impresion_precios.php?ok=Tarifa eliminada correctamente');
} catch (Throwable $e) {
    cp_redirect(
        'impresion_precios.php?ok=' .
        rawurlencode('No se pudo eliminar la tarifa: ' . $e->getMessage())
    );
}
