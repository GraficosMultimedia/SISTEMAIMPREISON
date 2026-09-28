<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';

cp_check_csrf($_POST['csrf'] ?? null);
$db = cp_db();
$type = trim((string)($_POST['type'] ?? ''));
$id = (int)($_POST['id'] ?? 0);
$action = trim((string)($_POST['catalog_action'] ?? 'save'));

try {
    if (!in_array($type, ['size', 'material', 'finish'], true)) {
        throw new RuntimeException('Tipo de catálogo no válido.');
    }

    if ($action === 'delete') {
        if ($id < 1) throw new RuntimeException('Registro no válido.');

        $table = ['size' => 'cp_print_sizes', 'material' => 'cp_print_materials', 'finish' => 'cp_print_finishes'][$type];
        $column = ['size' => 'size_id', 'material' => 'material_id', 'finish' => 'finish_id'][$type];
        $refs = 0;
        foreach (['cp_print_prices', 'cp_print_request_items', 'cp_print_price_rules'] as $refTable) {
            try {
                $st = $db->prepare("SELECT COUNT(*) FROM {$refTable} WHERE {$column} = ?");
                $st->execute([$id]);
                $refs += (int)$st->fetchColumn();
            } catch (Throwable $ignored) {
                // La tabla puede no existir en instalaciones antiguas.
            }
        }

        if ($refs > 0) {
            $st = $db->prepare("UPDATE {$table} SET enabled=0, updated_at=NOW() WHERE id=?");
            $st->execute([$id]);
            cp_redirect('impresion_precios.php?ok=El registro tiene historial y fue desactivado para conservar la integridad del sistema');
        }

        $st = $db->prepare("DELETE FROM {$table} WHERE id=?");
        $st->execute([$id]);
        cp_redirect('impresion_precios.php?ok=Registro eliminado');
    }

    if ($type === 'size') {
        $name = trim((string)($_POST['name'] ?? ''));
        $code = strtolower(trim((string)($_POST['code'] ?? '')));
        $width = (float)($_POST['width_mm'] ?? 0);
        $height = (float)($_POST['height_mm'] ?? 0);
        $orientation = trim((string)($_POST['orientation'] ?? 'portrait'));
        $custom = isset($_POST['is_custom']) ? 1 : 0;
        $sort = (int)($_POST['sort_order'] ?? 0);
        if ($name === '' || $code === '' || $width < 0 || $height < 0) throw new RuntimeException('Completa nombre, código y medidas.');
        if (!in_array($orientation, ['portrait', 'landscape'], true)) $orientation = 'portrait';
        if ($id > 0) {
            $st = $db->prepare('UPDATE cp_print_sizes SET name=?,code=?,width_mm=?,height_mm=?,orientation=?,is_custom=?,sort_order=?,enabled=?,updated_at=NOW() WHERE id=?');
            $st->execute([$name,$code,$width,$height,$orientation,$custom,$sort,isset($_POST['enabled'])?1:0,$id]);
        } else {
            $st = $db->prepare('INSERT INTO cp_print_sizes (name,code,width_mm,height_mm,orientation,is_custom,enabled,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,?,1,?,NOW(),NOW())');
            $st->execute([$name,$code,$width,$height,$orientation,$custom,$sort]);
        }
    } elseif ($type === 'material') {
        $name = trim((string)($_POST['name'] ?? ''));
        $code = strtolower(trim((string)($_POST['code'] ?? '')));
        $unit = trim((string)($_POST['unit_label'] ?? 'hoja')) ?: 'hoja';
        $sort = (int)($_POST['sort_order'] ?? 0);
        if ($name === '' || $code === '') throw new RuntimeException('Completa nombre y código.');
        if ($id > 0) {
            $st = $db->prepare('UPDATE cp_print_materials SET name=?,code=?,unit_label=?,sort_order=?,enabled=?,updated_at=NOW() WHERE id=?');
            $st->execute([$name,$code,$unit,$sort,isset($_POST['enabled'])?1:0,$id]);
        } else {
            $st = $db->prepare('INSERT INTO cp_print_materials (name,code,enabled,sort_order,created_at,updated_at,unit_label) VALUES (?,?,1,?,NOW(),NOW(),?)');
            $st->execute([$name,$code,$sort,$unit]);
        }
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $code = strtolower(trim((string)($_POST['code'] ?? '')));
        $sort = (int)($_POST['sort_order'] ?? 0);
        if ($name === '' || $code === '') throw new RuntimeException('Completa nombre y código.');
        if ($id > 0) {
            $st = $db->prepare('UPDATE cp_print_finishes SET name=?,code=?,sort_order=?,enabled=?,updated_at=NOW() WHERE id=?');
            $st->execute([$name,$code,$sort,isset($_POST['enabled'])?1:0,$id]);
        } else {
            $st = $db->prepare('INSERT INTO cp_print_finishes (name,code,enabled,sort_order,created_at,updated_at) VALUES (?,?,1,?,NOW(),NOW())');
            $st->execute([$name,$code,$sort]);
        }
    }
    cp_redirect('impresion_precios.php?ok=Catálogo guardado correctamente');
} catch (Throwable $e) {
    cp_redirect('impresion_precios.php?ok=' . rawurlencode('No se pudo guardar: ' . $e->getMessage()));
}
