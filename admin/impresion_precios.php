<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';

$db = cp_db();

$sizes = $db->query("SELECT * FROM cp_print_sizes ORDER BY sort_order,id")->fetchAll();
$materials = $db->query("SELECT * FROM cp_print_materials ORDER BY sort_order,id")->fetchAll();
$finishes = $db->query("SELECT * FROM cp_print_finishes ORDER BY sort_order,id")->fetchAll();

$prices = $db->query("
    SELECT
        p.*,
        s.name AS size_name,
        m.name AS material_name,
        f.name AS finish_name
    FROM cp_print_prices p
    JOIN cp_print_sizes s ON s.id = p.size_id
    JOIN cp_print_materials m ON m.id = p.material_id
    JOIN cp_print_finishes f ON f.id = p.finish_id
    ORDER BY s.sort_order,m.sort_order,f.sort_order,p.color_mode,p.pricing_mode,p.id
")->fetchAll();

$msg = trim((string)($_GET['ok'] ?? ''));
?>
<?php
$title = 'Configuración de impresión';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/colibri-print.css">
<style>
.print-config-page{max-width:1500px;margin:0 auto;padding:24px 0 40px}
.print-config-page .cp-page-head{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:22px}
.print-config-page .cp-page-head h1{margin:0 0 6px;font-size:clamp(24px,3vw,34px);line-height:1.1}
.print-config-page .cp-page-head p{margin:0;color:#64748b;max-width:820px}
.print-config-page .cp-page-actions{display:flex;gap:10px;flex-wrap:wrap}
.print-config-page .section-note{color:#64748b;font-size:13px;margin:0}
.print-config-page .table-wrap{overflow-x:auto}
.print-config-page .cp-admin-table input,
.print-config-page .cp-admin-table select{min-width:90px}
.print-config-page .cp-admin-table .input-code{width:130px}
.print-config-page .cp-admin-table .input-name{min-width:180px}
.print-config-page .cp-admin-table .input-number{width:105px}
.print-config-page .price-form{display:grid;grid-template-columns:repeat(7,minmax(100px,1fr)) auto;gap:8px;align-items:end}
.print-config-page .price-form .field{display:flex;flex-direction:column;gap:4px}
.print-config-page .price-form label{font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase}
.print-config-page .price-form input,
.print-config-page .price-form select{width:100%;box-sizing:border-box}
.print-config-page .status-active{font-weight:700}
.print-config-page .status-off{font-weight:700;opacity:.7}
.print-config-page .disabled-row{opacity:.62}
.print-config-page .inline-actions{display:flex;gap:6px;flex-wrap:wrap}
.print-config-page .new-catalog-form{display:grid;grid-template-columns:1.3fr 1fr 1fr 1fr auto;gap:8px;align-items:end;margin-top:16px}
.print-config-page .new-catalog-form .field{display:flex;flex-direction:column;gap:4px}
.print-config-page .new-catalog-form label{font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase}
@media(max-width:1000px){
  .print-config-page .price-form{grid-template-columns:repeat(3,minmax(120px,1fr))}
  .print-config-page .new-catalog-form{grid-template-columns:repeat(2,minmax(140px,1fr))}
}
@media(max-width:720px){
  .print-config-page{padding:16px 0 28px}
  .print-config-page .cp-page-head{align-items:stretch;flex-direction:column}
  .print-config-page .cp-page-actions{width:100%}
  .print-config-page .cp-page-actions .cp-btn{flex:1}
  .print-config-page .price-form,.print-config-page .new-catalog-form{grid-template-columns:1fr}
}
</style>

<div class="print-config-page">
  <div class="cp-page-head">
    <div>
      <div class="eyebrow">SISTEMA · CONFIGURACIÓN DE IMPRESIÓN</div>
      <h1>Configuración de impresión</h1>
      <p>Administra catálogos y tarifas de <strong>cp_print_prices</strong>. Los cambios activos son los que consume el formulario público de solicitudes.</p>
    </div>
    <div class="cp-page-actions">
      <a class="cp-btn cp-btn-soft" href="/admin/recepcion_impresiones.php">Recepción</a>
      <a class="cp-btn cp-btn-primary" href="/solicitar_impresion.php">Ver formulario</a>
    </div>
  </div>

<?php if ($msg): ?>
<div class="cp-success">✓ <?= cp_e($msg) ?></div>
<?php endif; ?>

<section class="cp-card cp-section">
  <div class="cp-card-head">
    <div class="cp-section-title">
      <div class="cp-section-num">1</div>
      <div><h2>Tamaños</h2><p class="section-note">Nombre, código, medidas, orientación y estado.</p></div>
    </div>
  </div>

  <div class="table-wrap">
  <table class="cp-admin-table">
    <tr>
      <th>Nombre</th><th>Código</th><th>Ancho mm</th><th>Alto mm</th><th>Orientación</th><th>Orden</th><th>Activo</th><th></th>
    </tr>
    <?php foreach ($sizes as $s): $fid = 'size-' . $s['id']; ?>
    <tr>
      <td><input class="input-name" form="<?= $fid ?>" name="name" value="<?= cp_e($s['name']) ?>" required></td>
      <td><input class="input-code" form="<?= $fid ?>" name="code" value="<?= cp_e($s['code']) ?>" required></td>
      <td><input class="input-number" form="<?= $fid ?>" name="width_mm" type="number" step=".01" min="0" value="<?= cp_e((string)$s['width_mm']) ?>" required></td>
      <td><input class="input-number" form="<?= $fid ?>" name="height_mm" type="number" step=".01" min="0" value="<?= cp_e((string)$s['height_mm']) ?>" required></td>
      <td>
        <select form="<?= $fid ?>" name="orientation">
          <option value="portrait" <?= $s['orientation'] === 'portrait' ? 'selected' : '' ?>>Vertical</option>
          <option value="landscape" <?= $s['orientation'] === 'landscape' ? 'selected' : '' ?>>Horizontal</option>
        </select>
      </td>
      <td><input class="input-number" form="<?= $fid ?>" name="sort_order" type="number" value="<?= (int)$s['sort_order'] ?>"></td>
      <td>
        <input form="<?= $fid ?>" type="hidden" name="enabled" value="0">
        <input form="<?= $fid ?>" name="enabled" type="checkbox" value="1" <?= $s['enabled'] ? 'checked' : '' ?>>
      </td>
      <td>
        <form id="<?= $fid ?>" method="post" action="catalog_save.php">
          <input type="hidden" name="csrf" value="<?= cp_e(cp_csrf()) ?>">
          <input type="hidden" name="type" value="size">
          <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <input type="hidden" name="is_custom" value="<?= !empty($s['is_custom']) ? '1' : '0' ?>">
          <button class="cp-btn cp-btn-soft" type="submit">Guardar</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>

  <form class="new-catalog-form" method="post" action="catalog_save.php">
    <input type="hidden" name="csrf" value="<?= cp_e(cp_csrf()) ?>">
    <input type="hidden" name="type" value="size">
    <div class="field"><label>Nuevo tamaño</label><input name="name" required placeholder="Ej. 60 × 90 cm"></div>
    <div class="field"><label>Código</label><input name="code" required placeholder="60x90"></div>
    <div class="field"><label>Ancho mm</label><input name="width_mm" type="number" step=".01" min="0" required></div>
    <div class="field"><label>Alto mm</label><input name="height_mm" type="number" step=".01" min="0" required></div>
    <div class="field"><label>Orientación</label><select name="orientation"><option value="portrait">Vertical</option><option value="landscape">Horizontal</option></select></div>
    <div class="field"><label>Orden</label><input name="sort_order" type="number" value="0"></div>
    <input type="hidden" name="is_custom" value="1">
    <button class="cp-btn cp-btn-primary" type="submit">+ Agregar</button>
  </form>
</section>

<section class="cp-card cp-section">
  <div class="cp-card-head">
    <div class="cp-section-title">
      <div class="cp-section-num">2</div>
      <div><h2>Materiales</h2><p class="section-note">Código y unidad son parte de la estructura real de producción.</p></div>
    </div>
  </div>

  <div class="table-wrap">
  <table class="cp-admin-table">
    <tr><th>Nombre</th><th>Código</th><th>Unidad</th><th>Orden</th><th>Activo</th><th></th></tr>
    <?php foreach ($materials as $m): $fid = 'material-' . $m['id']; ?>
    <tr>
      <td><input class="input-name" form="<?= $fid ?>" name="name" value="<?= cp_e($m['name']) ?>" required></td>
      <td><input class="input-code" form="<?= $fid ?>" name="code" value="<?= cp_e($m['code']) ?>" required></td>
      <td><input form="<?= $fid ?>" name="unit_label" value="<?= cp_e($m['unit_label']) ?>" style="width:100px"></td>
      <td><input class="input-number" form="<?= $fid ?>" name="sort_order" type="number" value="<?= (int)$m['sort_order'] ?>"></td>
      <td>
        <input form="<?= $fid ?>" type="hidden" name="enabled" value="0">
        <input form="<?= $fid ?>" name="enabled" type="checkbox" value="1" <?= $m['enabled'] ? 'checked' : '' ?>>
      </td>
      <td style="white-space:nowrap">
        <form id="<?= $fid ?>" method="post" action="catalog_save.php" style="display:inline-block">
          <input type="hidden" name="csrf" value="<?= cp_e(cp_csrf()) ?>">
          <input type="hidden" name="type" value="material">
          <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
          <button class="cp-btn cp-btn-soft" type="submit">Guardar</button>
        </form>
        <form method="post" action="catalog_save.php" style="display:inline-block;margin-left:6px" onsubmit="return confirm('El sistema conservará el historial y desactivará el material si tiene referencias. ¿Continuar?')">
          <input type="hidden" name="csrf" value="<?= cp_e(cp_csrf()) ?>">
          <input type="hidden" name="type" value="material">
          <input type="hidden" name="catalog_action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
          <button class="cp-btn cp-btn-danger" type="submit">Eliminar</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>

  <form class="new-catalog-form" method="post" action="catalog_save.php">
    <input type="hidden" name="csrf" value="<?= cp_e(cp_csrf()) ?>">
    <input type="hidden" name="type" value="material">
    <div class="field"><label>Nuevo material</label><input name="name" required placeholder="Ej. Vinil adhesivo"></div>
    <div class="field"><label>Código</label><input name="code" required placeholder="vinil"></div>
    <div class="field"><label>Unidad</label><input name="unit_label" value="hoja"></div>
    <div class="field"><label>Orden</label><input name="sort_order" type="number" value="0"></div>
    <button class="cp-btn cp-btn-primary" type="submit">+ Agregar</button>
  </form>
</section>

<section class="cp-card cp-section">
  <div class="cp-card-head">
    <div class="cp-section-title">
      <div class="cp-section-num">3</div>
      <div><h2>Acabados</h2><p class="section-note">Código editable para mantener consistente el catálogo.</p></div>
    </div>
  </div>

  <div class="table-wrap">
  <table class="cp-admin-table">
    <tr><th>Nombre</th><th>Código</th><th>Orden</th><th>Activo</th><th></th></tr>
    <?php foreach ($finishes as $f): $fid = 'finish-' . $f['id']; ?>
    <tr>
      <td><input class="input-name" form="<?= $fid ?>" name="name" value="<?= cp_e($f['name']) ?>" required></td>
      <td><input class="input-code" form="<?= $fid ?>" name="code" value="<?= cp_e($f['code']) ?>" required></td>
      <td><input class="input-number" form="<?= $fid ?>" name="sort_order" type="number" value="<?= (int)$f['sort_order'] ?>"></td>
      <td>
        <input form="<?= $fid ?>" type="hidden" name="enabled" value="0">
        <input form="<?= $fid ?>" name="enabled" type="checkbox" value="1" <?= $f['enabled'] ? 'checked' : '' ?>>
      </td>
      <td>
        <form id="<?= $fid ?>" method="post" action="catalog_save.php">
          <input type="hidden" name="csrf" value="<?= cp_e(cp_csrf()) ?>">
          <input type="hidden" name="type" value="finish">
          <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
          <button class="cp-btn cp-btn-soft" type="submit">Guardar</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>

  <form class="new-catalog-form" method="post" action="catalog_save.php">
    <input type="hidden" name="csrf" value="<?= cp_e(cp_csrf()) ?>">
    <input type="hidden" name="type" value="finish">
    <div class="field"><label>Nuevo acabado</label><input name="name" required placeholder="Ej. Laminado mate"></div>
    <div class="field"><label>Código</label><input name="code" required placeholder="mate"></div>
    <div class="field"><label>Orden</label><input name="sort_order" type="number" value="0"></div>
    <button class="cp-btn cp-btn-primary" type="submit">+ Agregar</button>
  </form>
</section>

<section class="cp-card">
  <div class="cp-card-head">
    <div class="cp-section-title">
      <div class="cp-section-num">4</div>
      <div><h2>Matriz de tarifas</h2><p class="section-note">Estas son las tarifas de <strong>cp_print_prices</strong> que consume el formulario público.</p></div>
    </div>
  </div>

  <form class="price-form" method="post" action="price_save.php" style="margin-bottom:22px">
    <input type="hidden" name="csrf" value="<?= cp_e(cp_csrf()) ?>">
    <input type="hidden" name="id" value="0">
    <div class="field"><label>Tamaño</label><select name="size_id" required><option value="">Selecciona</option><?php foreach ($sizes as $s): ?><option value="<?= (int)$s['id'] ?>"><?= cp_e($s['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Material</label><select name="material_id" required><option value="">Selecciona</option><?php foreach ($materials as $m): ?><option value="<?= (int)$m['id'] ?>"><?= cp_e($m['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Acabado</label><select name="finish_id" required><option value="">Selecciona</option><?php foreach ($finishes as $f): ?><option value="<?= (int)$f['id'] ?>"><?= cp_e($f['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Color</label><select name="color_mode"><option value="color">Color</option><option value="bw">B/N</option></select></div>
    <div class="field"><label>Precio unitario</label><input type="number" name="unit_price" step=".01" min="0" placeholder="0.00" required></div>
    <div class="field"><label>Cobro</label><select name="pricing_mode"><option value="per_page">Por página</option><option value="per_sheet">Por hoja</option></select></div>
    <div class="field"><label>Mínimo</label><input type="number" name="min_qty" min="1" value="1" required></div>
    <button class="cp-btn cp-btn-primary" type="submit">+ Guardar tarifa</button>
  </form>

  <div class="table-wrap">
  <table class="cp-admin-table">
    <tr><th>Tamaño</th><th>Material</th><th>Acabado</th><th>Color</th><th>Modo</th><th>Precio</th><th>Mínimo</th><th>Estado</th><th>Acciones</th></tr>
    <?php foreach ($prices as $p): $fid = 'price-' . $p['id']; ?>
    <tr class="<?= $p['enabled'] ? '' : 'disabled-row' ?>">
      <td>
        <select form="<?= $fid ?>" name="size_id" required>
          <?php foreach ($sizes as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)$s['id'] === (int)$p['size_id'] ? 'selected' : '' ?>><?= cp_e($s['name']) ?></option><?php endforeach; ?>
        </select>
      </td>
      <td>
        <select form="<?= $fid ?>" name="material_id" required>
          <?php foreach ($materials as $m): ?><option value="<?= (int)$m['id'] ?>" <?= (int)$m['id'] === (int)$p['material_id'] ? 'selected' : '' ?>><?= cp_e($m['name']) ?></option><?php endforeach; ?>
        </select>
      </td>
      <td>
        <select form="<?= $fid ?>" name="finish_id" required>
          <?php foreach ($finishes as $f): ?><option value="<?= (int)$f['id'] ?>" <?= (int)$f['id'] === (int)$p['finish_id'] ? 'selected' : '' ?>><?= cp_e($f['name']) ?></option><?php endforeach; ?>
        </select>
      </td>
      <td><select form="<?= $fid ?>" name="color_mode"><option value="color" <?= $p['color_mode'] === 'color' ? 'selected' : '' ?>>Color</option><option value="bw" <?= $p['color_mode'] === 'bw' ? 'selected' : '' ?>>B/N</option></select></td>
      <td><select form="<?= $fid ?>" name="pricing_mode"><option value="per_page" <?= $p['pricing_mode'] === 'per_page' ? 'selected' : '' ?>>Página</option><option value="per_sheet" <?= $p['pricing_mode'] === 'per_sheet' ? 'selected' : '' ?>>Hoja</option></select></td>
      <td><input form="<?= $fid ?>" class="input-number" name="unit_price" type="number" step=".01" min="0" value="<?= cp_e((string)$p['unit_price']) ?>" required></td>
      <td><input form="<?= $fid ?>" class="input-number" name="min_qty" type="number" min="1" value="<?= max(1, (int)$p['min_qty']) ?>" required></td>
      <td class="<?= $p['enabled'] ? 'status-active' : 'status-off' ?>">
        <input form="<?= $fid ?>" type="hidden" name="enabled" value="0">
        <input form="<?= $fid ?>" name="enabled" type="checkbox" value="1" <?= $p['enabled'] ? 'checked' : '' ?>>
        <?= $p['enabled'] ? 'Activa' : 'Inactiva' ?>
      </td>
      <td>
        <form id="<?= $fid ?>" method="post" action="price_save.php">
          <input type="hidden" name="csrf" value="<?= cp_e(cp_csrf()) ?>">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="cp-btn cp-btn-soft" type="submit">Guardar</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
</section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
