<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';

$db = cp_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['section_action'] ?? ''));
    try {
        cp_check_csrf($_POST['csrf'] ?? null);

        if ($action === 'catalog_save') {
            $type = trim((string)($_POST['type'] ?? ''));
            $id = (int)($_POST['id'] ?? 0);
            $allowed = ['size', 'material', 'finish'];
            if (!in_array($type, $allowed, true)) {
                throw new RuntimeException('Tipo de catálogo no válido.');
            }

            if ($type === 'size') {
                $name = trim((string)($_POST['name'] ?? ''));
                $code = strtolower(trim((string)($_POST['code'] ?? '')));
                $width = (float)($_POST['width_mm'] ?? 0);
                $height = (float)($_POST['height_mm'] ?? 0);
                $orientation = trim((string)($_POST['orientation'] ?? 'portrait'));
                $custom = isset($_POST['is_custom']) ? 1 : 0;
                $sort = (int)($_POST['sort_order'] ?? 0);
                if ($name === '' || $code === '' || $width <= 0 || $height <= 0) {
                    throw new RuntimeException('Completa nombre, código y medidas válidas.');
                }
                if (!in_array($orientation, ['portrait', 'landscape'], true)) $orientation = 'portrait';

                if ($id > 0) {
                    $st = $db->prepare('UPDATE cp_print_sizes SET name=?,code=?,width_mm=?,height_mm=?,orientation=?,is_custom=?,sort_order=?,enabled=?,updated_at=NOW() WHERE id=?');
                    $st->execute([$name,$code,$width,$height,$orientation,$custom,$sort,isset($_POST['enabled']) ? 1 : 0,$id]);
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
                    $st->execute([$name,$code,$unit,$sort,isset($_POST['enabled']) ? 1 : 0,$id]);
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
                    $st->execute([$name,$code,$sort,isset($_POST['enabled']) ? 1 : 0,$id]);
                } else {
                    $st = $db->prepare('INSERT INTO cp_print_finishes (name,code,enabled,sort_order,created_at,updated_at) VALUES (?,?,1,?,NOW(),NOW())');
                    $st->execute([$name,$code,$sort]);
                }
            }
            cp_redirect('impresion_precios.php?ok=' . rawurlencode('Catálogo guardado correctamente'));
        }

        if ($action === 'catalog_delete') {
            $type = trim((string)($_POST['type'] ?? ''));
            $id = (int)($_POST['id'] ?? 0);
            $table = ['size'=>'cp_print_sizes','material'=>'cp_print_materials','finish'=>'cp_print_finishes'][$type] ?? null;
            $column = ['size'=>'size_id','material'=>'material_id','finish'=>'finish_id'][$type] ?? null;
            if (!$table || !$column || $id < 1) throw new RuntimeException('Registro no válido.');

            $refs = 0;
            foreach (['cp_print_prices','cp_print_request_items','cp_print_price_rules'] as $refTable) {
                try {
                    $st = $db->prepare("SELECT COUNT(*) FROM {$refTable} WHERE {$column}=?");
                    $st->execute([$id]);
                    $refs += (int)$st->fetchColumn();
                } catch (Throwable $ignored) {}
            }

            if ($refs > 0) {
                $st = $db->prepare("UPDATE {$table} SET enabled=0, updated_at=NOW() WHERE id=?");
                $st->execute([$id]);
                cp_redirect('impresion_precios.php?ok=' . rawurlencode('Tiene historial relacionado y fue desactivado para conservar la integridad.'));
            }

            $st = $db->prepare("DELETE FROM {$table} WHERE id=?");
            $st->execute([$id]);
            cp_redirect('impresion_precios.php?ok=' . rawurlencode('Registro eliminado correctamente'));
        }

        if ($action === 'price_save') {
            $id = (int)($_POST['id'] ?? 0);
            $sizeId = (int)($_POST['size_id'] ?? 0);
            $materialId = (int)($_POST['material_id'] ?? 0);
            $finishId = (int)($_POST['finish_id'] ?? 0);
            $colorMode = trim((string)($_POST['color_mode'] ?? 'color'));
            $pricingMode = trim((string)($_POST['pricing_mode'] ?? 'per_page'));
            $unitPrice = (float)($_POST['unit_price'] ?? 0);
            $minQty = max(1, (int)($_POST['min_qty'] ?? 1));

            if ($sizeId < 1 || $materialId < 1 || $finishId < 1 || $unitPrice < 0) {
                throw new RuntimeException('Datos de tarifa inválidos.');
            }
            if (!in_array($colorMode, ['color','bw'], true) || !in_array($pricingMode, ['per_page','per_sheet'], true)) {
                throw new RuntimeException('Modo de tarifa inválido.');
            }

            $find = $db->prepare('SELECT id FROM cp_print_prices WHERE size_id=? AND material_id=? AND finish_id=? AND color_mode=? AND pricing_mode=? AND id<>? LIMIT 1');
            $find->execute([$sizeId,$materialId,$finishId,$colorMode,$pricingMode,$id]);
            if ($find->fetchColumn()) throw new RuntimeException('Ya existe una tarifa con esa combinación.');

            if ($id > 0) {
                $st = $db->prepare('UPDATE cp_print_prices SET size_id=?,material_id=?,finish_id=?,color_mode=?,pricing_mode=?,unit_price=?,min_qty=?,updated_at=NOW() WHERE id=?');
                $st->execute([$sizeId,$materialId,$finishId,$colorMode,$pricingMode,$unitPrice,$minQty,$id]);
            } else {
                $st = $db->prepare('INSERT INTO cp_print_prices (size_id,material_id,finish_id,color_mode,pricing_mode,unit_price,min_qty,enabled,created_at,updated_at) VALUES (?,?,?,?,?,?,?,1,NOW(),NOW())');
                $st->execute([$sizeId,$materialId,$finishId,$colorMode,$pricingMode,$unitPrice,$minQty]);
            }
            cp_redirect('impresion_precios.php?ok=' . rawurlencode('Tarifa guardada correctamente'));
        }

        if ($action === 'price_delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id < 1) throw new RuntimeException('Tarifa no válida.');
            $st = $db->prepare('DELETE FROM cp_print_prices WHERE id=?');
            $st->execute([$id]);
            if ($st->rowCount() < 1) throw new RuntimeException('La tarifa no existe o ya fue eliminada.');
            cp_redirect('impresion_precios.php?ok=' . rawurlencode('Tarifa eliminada correctamente'));
        }

        throw new RuntimeException('Acción no válida.');
    } catch (Throwable $e) {
        cp_redirect('impresion_precios.php?ok=' . rawurlencode('No se pudo completar la operación: ' . $e->getMessage()));
    }
}

$sizes = $db->query("SELECT * FROM cp_print_sizes ORDER BY sort_order,id")->fetchAll();
$materials = $db->query("SELECT * FROM cp_print_materials ORDER BY sort_order,id")->fetchAll();
$finishes = $db->query("SELECT * FROM cp_print_finishes ORDER BY sort_order,id")->fetchAll();
$prices = $db->query("
    SELECT p.*, s.name AS size_name, m.name AS material_name, f.name AS finish_name
    FROM cp_print_prices p
    JOIN cp_print_sizes s ON s.id=p.size_id
    JOIN cp_print_materials m ON m.id=p.material_id
    JOIN cp_print_finishes f ON f.id=p.finish_id
    ORDER BY s.sort_order,m.sort_order,f.sort_order,p.color_mode,p.pricing_mode
")->fetchAll();

$msg = $_GET['ok'] ?? '';
$editPriceId = (int)($_GET['edit_price'] ?? 0);

$title = 'Configuración de impresión';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/colibri-print.css">
<style>
.print-config-page{max-width:1400px;margin:0 auto;padding:24px 0 40px}
.print-config-page .cp-page-head{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:22px}
.print-config-page .cp-page-head h1{margin:0 0 6px;font-size:clamp(24px,3vw,34px);line-height:1.1}
.print-config-page .cp-page-head p{margin:0;color:#64748b;max-width:760px}
.print-config-page .cp-page-actions{display:flex;gap:10px;flex-wrap:wrap}
.crud-actions{display:flex;gap:6px;flex-wrap:wrap}
.crud-actions form{margin:0}
.price-edit{margin:0}
.price-edit-grid{display:grid;grid-template-columns:repeat(6,minmax(110px,1fr));gap:8px}
@media(max-width:900px){.price-edit-grid{grid-template-columns:repeat(2,minmax(120px,1fr))}}
@media(max-width:720px){.print-config-page{padding:16px 0 28px}.print-config-page .cp-page-head{align-items:stretch;flex-direction:column}.print-config-page .cp-page-actions{width:100%}.print-config-page .cp-page-actions .cp-btn{flex:1}}
</style>
<div class="print-config-page">
  <div class="cp-page-head">
    <div>
      <div class="eyebrow">SISTEMA · CONFIGURACIÓN DE IMPRESIÓN</div>
      <h1>Configuración de impresión</h1>
      <p>Administra tamaños, materiales, acabados y las combinaciones de precios que utilizará el cotizador y verá el cliente.</p>
    </div>
    <div class="cp-page-actions">
      <a class="cp-btn cp-btn-soft" href="/admin/recepcion_impresiones.php">Recepción</a>
      <a class="cp-btn cp-btn-primary" href="/solicitar_impresion.php">Ver formulario</a>
    </div>
  </div>

<?php if ($msg): ?><div class="cp-success">✓ <?= cp_e($msg) ?></div><?php endif; ?>

<div class="cp-admin-grid">

<section class="cp-card cp-section">
<div class="cp-card-head"><div class="cp-section-title"><div class="cp-section-num">1</div><div><h2>Tamaños</h2><p>Editar, guardar o eliminar tamaños.</p></div></div></div>
<table class="cp-admin-table">
<tr><th>Nombre</th><th>Medidas</th><th>Activo</th><th>Acciones</th></tr>
<?php foreach ($sizes as $s): $fid='size-'.$s['id']; ?>
<tr>
<td><input form="<?= $fid ?>" name="name" value="<?= cp_e($s['name']) ?>"></td>
<td><input form="<?= $fid ?>" name="width_mm" type="number" step=".01" value="<?= $s['width_mm'] ?>" style="width:90px"> × <input form="<?= $fid ?>" name="height_mm" type="number" step=".01" value="<?= $s['height_mm'] ?>" style="width:90px"></td>
<td><input form="<?= $fid ?>" name="enabled" type="checkbox" value="1" <?= $s['enabled'] ? 'checked' : '' ?>></td>
<td class="crud-actions">
<form id="<?= $fid ?>" method="post" action="impresion_precios.php">
<input type="hidden" name="csrf" value="<?= cp_e(cp_csrf()) ?>"><input type="hidden" name="section_action" value="catalog_save"><input type="hidden" name="type" value="size"><input type="hidden" name="id" value="<?= $s['id'] ?>">
<button class="cp-btn cp-btn-soft" type="submit">Guardar</button>
</form>
<form method="post" action="impresion_precios.php" onsubmit="return confirm('¿Eliminar este tamaño? Si tiene historial, se desactivará para proteger los datos.')">
<input type="hidden" name="csrf" value="<?= cp_e(cp_csrf()) ?>"><input type="hidden" name="section_action" value="catalog_delete"><input type="hidden" name="type" value="size"><input type="hidden" name="id" value="<?= $s['id'] ?>">
<button class="cp-btn cp-btn-danger" type="submit">Eliminar</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
</section>

<section class="cp-card cp-section">
<div class="cp-card-head"><div class="cp-section-title"><div class="cp-section-num">2</div><div><h2>Materiales</h2><p>Editar, guardar o eliminar materiales.</p></div></div></div>
<table class="cp-admin-table">
<tr><th>Nombre</th><th>Unidad</th><th>Activo</th><th>Acciones</th></tr>
<?php foreach ($materials as $m): $fid='material-'.$m['id']; ?>
<tr>
<td><input form="<?= $fid ?>" name="name" value="<?= cp_e($m['name']) ?>"></td>
<td><input form="<?= $fid ?>" name="unit_label" value="<?= cp_e($m['unit_label']) ?>" style="width:100px"></td>
<td><input form="<?= $fid ?>" name="enabled" type="checkbox" value="1" <?= $m['enabled'] ? 'checked' : '' ?>></td>
<td class="crud-actions">
<form id="<?= $fid ?>" method="post" action="impresion_precios.php">
<input type="hidden" name="csrf" value="<?= cp_e(cp_csrf()) ?>"><input type="hidden" name="section_action" value="catalog_save"><input type="hidden" name="type" value="material"><input type="hidden" name="id" value="<?= $m['id'] ?>">
<button class="cp-btn cp-btn-soft" type="submit">Guardar</button>
</form>
<form method="post" action="impresion_precios.php" onsubmit="return confirm('¿Eliminar este material? Si tiene historial, se desactivará para proteger los datos.')">
<input type="hidden" name="csrf" value="<?= cp_e(cp_csrf()) ?>"><input type="hidden" name="section_action" value="catalog_delete"><input type="hidden" name="type" value="material"><input type="hidden" name="id" value="<?= $m['id'] ?>">
<button class="cp-btn cp-btn-danger" type="submit">Eliminar</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
</section>

</div>

<section class="cp-card cp-section">
<div class="cp-card-head"><div class="cp-section-title"><div class="cp-section-num">3</div><div><h2>Acabados</h2><p>Editar, guardar o eliminar acabados.</p></div></div></div>
<table class="cp-admin-table">
<tr><th>Nombre</th><th>Código</th><th>Activo</th><th>Acciones</th></tr>
<?php foreach ($finishes as $f): $fid='finish-'.$f['id']; ?>
<tr>
<td><input form="<?= $fid ?>" name="name" value="<?= cp_e($f['name']) ?>"></td>
<td><?= cp_e($f['code']) ?></td>
<td><input form="<?= $fid ?>" name="enabled" type="checkbox" value="1" <?= $f['enabled'] ? 'checked' : '' ?>></td>
<td class="crud-actions">
<form id="<?= $fid ?>" method="post" action="impresion_precios.php">
<input type="hidden" name="csrf" value="<?= cp_e(cp_csrf()) ?>"><input type="hidden" name="section_action" value="catalog_save"><input type="hidden" name="type" value="finish"><input type="hidden" name="id" value="<?= $f['id'] ?>">
<button class="cp-btn cp-btn-soft" type="submit">Guardar</button>
</form>
<form method="post" action="impresion_precios.php" onsubmit="return confirm('¿Eliminar este acabado? Si tiene historial, se desactivará para proteger los datos.')">
<input type="hidden" name="csrf" value="<?= cp_e(cp_csrf()) ?>"><input type="hidden" name="section_action" value="catalog_delete"><input type="hidden" name="type" value="finish"><input type="hidden" name="id" value="<?= $f['id'] ?>">
<button class="cp-btn cp-btn-danger" type="submit">Eliminar</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
</section>

<section class="cp-card">
<div class="cp-card-head"><div class="cp-section-title"><div class="cp-section-num">4</div><div><h2>Matriz de tarifas</h2><p>Editar, guardar o eliminar las tarifas que controlan el precio del formulario.</p></div></div></div>

<form class="cp-admin-price" method="post" action="impresion_precios.php" style="margin-bottom:18px">
<input type="hidden" name="csrf" value="<?= cp_e(cp_csrf()) ?>"><input type="hidden" name="section_action" value="price_save"><input type="hidden" name="id" value="0">
<select name="size_id" required><option value="">Tamaño</option><?php foreach ($sizes as $s): ?><option value="<?= $s['id'] ?>"><?= cp_e($s['name']) ?></option><?php endforeach; ?></select>
<select name="material_id" required><option value="">Material</option><?php foreach ($materials as $m): ?><option value="<?= $m['id'] ?>"><?= cp_e($m['name']) ?></option><?php endforeach; ?></select>
<select name="finish_id" required><option value="">Acabado</option><?php foreach ($finishes as $f): ?><option value="<?= $f['id'] ?>"><?= cp_e($f['name']) ?></option><?php endforeach; ?></select>
<select name="color_mode"><option value="color">Color</option><option value="bw">B/N</option></select>
<input type="number" name="unit_price" step=".01" min="0" placeholder="Precio" required>
<select name="pricing_mode"><option value="per_page">Por página</option><option value="per_sheet">Por hoja</option></select>
<input type="number" name="min_qty" min="1" value="1" title="Cantidad mínima">
<button class="cp-btn cp-btn-primary" type="submit">Guardar tarifa</button>
</form>

<table class="cp-admin-table">
<tr><th>Tamaño</th><th>Material</th><th>Acabado</th><th>Modo</th><th>Precio</th><th>Acciones</th></tr>
<?php foreach ($prices as $p): ?>
<?php if ($editPriceId === (int)$p['id']): ?>
<tr>
<td colspan="6">
<form class="price-edit" method="post" action="impresion_precios.php">
<input type="hidden" name="csrf" value="<?= cp_e(cp_csrf()) ?>"><input type="hidden" name="section_action" value="price_save"><input type="hidden" name="id" value="<?= $p['id'] ?>">
<div class="price-edit-grid">
<select name="size_id" required><?php foreach ($sizes as $s): ?><option value="<?= $s['id'] ?>" <?= (int)$s['id']===(int)$p['size_id']?'selected':'' ?>><?= cp_e($s['name']) ?></option><?php endforeach; ?></select>
<select name="material_id" required><?php foreach ($materials as $m): ?><option value="<?= $m['id'] ?>" <?= (int)$m['id']===(int)$p['material_id']?'selected':'' ?>><?= cp_e($m['name']) ?></option><?php endforeach; ?></select>
<select name="finish_id" required><?php foreach ($finishes as $f): ?><option value="<?= $f['id'] ?>" <?= (int)$f['id']===(int)$p['finish_id']?'selected':'' ?>><?= cp_e($f['name']) ?></option><?php endforeach; ?></select>
<select name="color_mode"><option value="color" <?= $p['color_mode']==='color'?'selected':'' ?>>Color</option><option value="bw" <?= $p['color_mode']==='bw'?'selected':'' ?>>B/N</option></select>
<input type="number" name="unit_price" step=".01" min="0" value="<?= cp_e($p['unit_price']) ?>" required>
<select name="pricing_mode"><option value="per_page" <?= $p['pricing_mode']==='per_page'?'selected':'' ?>>Por página</option><option value="per_sheet" <?= $p['pricing_mode']==='per_sheet'?'selected':'' ?>>Por hoja</option></select>
<input type="number" name="min_qty" min="1" value="<?= (int)$p['min_qty'] ?>">
<button class="cp-btn cp-btn-primary" type="submit">Guardar cambios</button>
<a class="cp-btn cp-btn-soft" href="impresion_precios.php">Cancelar</a>
</div>
</form>
</td>
</tr>
<?php else: ?>
<tr>
<td><?= cp_e($p['size_name']) ?></td><td><?= cp_e($p['material_name']) ?></td><td><?= cp_e($p['finish_name']) ?></td>
<td><?= cp_e($p['color_mode']) ?> · <?= cp_e($p['pricing_mode']) ?></td>
<td>$<?= number_format((float)$p['unit_price'],2,'.',',') ?></td>
<td class="crud-actions">
<a class="cp-btn cp-btn-soft" href="impresion_precios.php?edit_price=<?= (int)$p['id'] ?>">Editar</a>
<form method="post" action="impresion_precios.php" onsubmit="return confirm('¿Eliminar definitivamente esta tarifa?')">
<input type="hidden" name="csrf" value="<?= cp_e(cp_csrf()) ?>"><input type="hidden" name="section_action" value="price_delete"><input type="hidden" name="id" value="<?= $p['id'] ?>">
<button class="cp-btn cp-btn-danger" type="submit">Eliminar</button>
</form>
</td>
</tr>
<?php endif; ?>
<?php endforeach; ?>
</table>
</section>
</div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
