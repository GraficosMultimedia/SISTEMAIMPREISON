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
    ORDER BY s.sort_order,m.sort_order,f.sort_order,p.color_mode
")->fetchAll();

$msg = $_GET['ok'] ?? '';
$title = 'Configuración de impresión';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/admin-impresion-precios.css">

<style>
/* v9: solo presentación de impresion_precios.php. Endpoints, nombres de campos y CSRF intactos. */
.print-config-page.v9{max-width:1320px;margin:0 auto;padding:0 0 42px;color:#eaf5ff}
.v9 .print-config-toolbar{margin-bottom:14px}
.v9 .v9-catalog-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:14px}
.v9 .v9-catalog-card{position:relative;overflow:hidden;min-height:178px;padding:16px;border:1px solid #244b70;border-radius:15px;background:linear-gradient(145deg,rgba(15,39,66,.98),rgba(7,23,41,.98));box-shadow:0 14px 34px rgba(0,0,0,.16);transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease}
.v9 .v9-catalog-card:hover{transform:translateY(-2px);border-color:#2d77a8;box-shadow:0 18px 38px rgba(0,0,0,.24)}
.v9 .v9-card-glow{position:absolute;inset:auto -30px -50px auto;width:130px;height:130px;border-radius:50%;background:rgba(31,184,255,.08);filter:blur(8px);pointer-events:none}
.v9 .v9-card-top{display:flex;align-items:center;justify-content:space-between;gap:10px}
.v9 .v9-card-title{display:flex;align-items:center;gap:10px;min-width:0}
.v9 .v9-card-number{display:grid;place-items:center;width:34px;height:34px;flex:0 0 34px;border-radius:11px;background:#103d62;border:1px solid #1f6795;color:#39d5ff;font-weight:900}
.v9 .v9-card-title h2{margin:0;color:#f5f9ff;font-size:16px}
.v9 .v9-card-title p{margin:3px 0 0;color:#87a9c8;font-size:11px}
.v9 .v9-count{padding:5px 8px;border-radius:999px;border:1px solid #254f74;background:#091f36;color:#9dc4e3;font-size:10px;font-weight:800;white-space:nowrap}
.v9 .v9-preview{display:grid;gap:5px;margin:15px 0 14px;min-height:49px}
.v9 .v9-preview-row{display:flex;justify-content:space-between;gap:8px;padding:6px 8px;border-radius:7px;background:rgba(4,17,30,.42);border:1px solid rgba(38,76,108,.6);font-size:10px}
.v9 .v9-preview-row span:first-child{color:#e7f3ff;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.v9 .v9-preview-row span:last-child{color:#6fa4ce;white-space:nowrap}
.v9 .v9-card-actions{display:flex;justify-content:space-between;align-items:center;gap:8px}
.v9 .v9-card-hint{font-size:10px;color:#6489a9}
.v9 .v9-manage{min-height:34px!important;padding:8px 12px!important}

.v9 .v9-price-card{padding:16px;border:1px solid #244b70;border-radius:15px;background:linear-gradient(180deg,rgba(13,31,53,.98),rgba(8,23,40,.98));box-shadow:0 16px 38px rgba(0,0,0,.17)}
.v9 .v9-price-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}
.v9 .v9-price-head h2{margin:0;color:#f5f9ff;font-size:17px}
.v9 .v9-price-head p{margin:4px 0 0;color:#8ba9c5;font-size:11px}
.v9 .v9-price-actions{display:flex;gap:8px;flex-wrap:wrap}
.v9 .v9-price-list{display:grid;gap:0;border:1px solid #1d4160;border-radius:11px;overflow:hidden}
.v9 .v9-price-row{display:grid;grid-template-columns:1.05fr 1.05fr 1.05fr .95fr .65fr auto;align-items:center;gap:8px;padding:10px 11px;border-bottom:1px solid #173751;background:rgba(7,25,43,.55);transition:background .16s ease}
.v9 .v9-price-row:last-child{border-bottom:0}
.v9 .v9-price-row:hover{background:rgba(16,49,78,.62)}
.v9 .v9-price-row.v9-price-label{background:#091d31;color:#76a4cc;font-size:9px;text-transform:uppercase;letter-spacing:.06em;font-weight:900}
.v9 .v9-price-cell{min-width:0;color:#e9f4ff;font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.v9 .v9-price-cell.muted{color:#8eabc4}
.v9 .v9-price-value{font-weight:900;color:#f5fbff}
.v9 .v9-price-actions-cell{display:flex;gap:6px;justify-content:flex-end;white-space:nowrap}
.v9 .v9-pill{display:inline-flex;align-items:center;width:max-content;max-width:100%;padding:4px 7px;border-radius:999px;background:#0a2138;border:1px solid #244f73;color:#9fc5e2;font-size:9px;font-weight:800}
.v9 .v9-empty{padding:24px;text-align:center;color:#7394b1;font-size:12px}

.v9 .v9-modal{position:fixed;inset:0;z-index:9999;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(2,9,17,.74);backdrop-filter:blur(7px);opacity:0;transition:opacity .18s ease}
.v9 .v9-modal.is-open{display:flex;opacity:1}
.v9 .v9-modal-panel{width:min(640px,100%);max-height:min(88vh,760px);overflow:auto;border:1px solid #2c6089;border-radius:18px;background:linear-gradient(180deg,#0d2945,#07182b);box-shadow:0 30px 80px rgba(0,0,0,.5);transform:translateY(14px) scale(.985);transition:transform .2s ease}
.v9 .v9-modal.is-open .v9-modal-panel{transform:none}
.v9 .v9-modal-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:17px 18px;border-bottom:1px solid #1c4565}
.v9 .v9-modal-kicker{font-size:9px;letter-spacing:.13em;color:#36d4ff;font-weight:900}
.v9 .v9-modal-head h3{margin:3px 0 0;color:#f5f9ff;font-size:18px}
.v9 .v9-modal-head p{margin:4px 0 0;color:#83a5c2;font-size:11px}
.v9 .v9-close{width:32px;height:32px;border:1px solid #315a7d;border-radius:9px;background:#0a2036;color:#c8e3f7;cursor:pointer;font-size:18px}
.v9 .v9-modal-body{padding:18px}
.v9 .v9-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}
.v9 .v9-field{display:grid;gap:5px}
.v9 .v9-field.full{grid-column:1/-1}
.v9 .v9-field label{font-size:10px;color:#84a8c6;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
.v9 .v9-modal input,.v9 .v9-modal select{width:100%;box-sizing:border-box;min-height:38px;padding:8px 10px;border:1px solid #315a82;border-radius:9px;background:#06182b;color:#eef8ff;font:inherit;font-size:12px;outline:none}
.v9 .v9-modal input:focus,.v9 .v9-modal select:focus{border-color:#2bd1ff;box-shadow:0 0 0 3px rgba(43,209,255,.1)}
.v9 .v9-modal-check{display:flex;align-items:center;gap:9px;padding-top:21px;color:#c4dced;font-size:11px}
.v9 .v9-modal-check input{width:18px;height:18px;min-height:18px;accent-color:#1495f5}
.v9 .v9-modal-foot{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:13px 18px;border-top:1px solid #1c4565;background:rgba(4,16,29,.35)}
.v9 .v9-modal-left,.v9 .v9-modal-right{display:flex;gap:7px;align-items:center;flex-wrap:wrap}
.v9 .v9-danger-zone{display:none}
.v9 .v9-danger-zone.is-visible{display:block}
.v9 .v9-danger-note{margin:0 0 10px;color:#a6bfd2;font-size:10px}
.v9 .v9-confirm{padding:9px 11px;border:1px solid #713447;border-radius:8px;background:#421b2b;color:#ffb8c8;font-weight:800;cursor:pointer}

.v9 .v9-toast{position:fixed;right:18px;bottom:18px;z-index:10000;display:none;padding:11px 14px;border:1px solid #24755f;border-radius:10px;background:#072d27;color:#aaf4df;font-size:11px;box-shadow:0 12px 30px rgba(0,0,0,.3)}
.v9 .v9-toast.show{display:block;animation:v9-toast-in .2s ease}
@keyframes v9-toast-in{from{opacity:0;transform:translateY(7px)}to{opacity:1;transform:none}}

@media(max-width:1000px){
  .v9 .v9-catalog-grid{grid-template-columns:1fr}
  .v9 .v9-catalog-card{min-height:150px}
}
@media(max-width:760px){
  .v9 .v9-price-head{align-items:flex-start;flex-direction:column}
  .v9 .v9-price-actions{width:100%}
  .v9 .v9-price-actions .cp-btn{flex:1}
  .v9 .v9-price-row.v9-price-label{display:none}
  .v9 .v9-price-row{grid-template-columns:1fr 1fr;gap:7px;padding:11px}
  .v9 .v9-price-cell{white-space:normal;overflow:visible}
  .v9 .v9-price-cell:nth-child(1):before{content:'Tamaño: ';color:#5e88aa;font-weight:700}
  .v9 .v9-price-cell:nth-child(2):before{content:'Material: ';color:#5e88aa;font-weight:700}
  .v9 .v9-price-cell:nth-child(3):before{content:'Acabado: ';color:#5e88aa;font-weight:700}
  .v9 .v9-price-cell:nth-child(4):before{content:'Modo: ';color:#5e88aa;font-weight:700}
  .v9 .v9-price-cell:nth-child(5):before{content:'Precio: ';color:#5e88aa;font-weight:700}
  .v9 .v9-price-actions-cell{grid-column:1/-1;justify-content:flex-start;padding-top:3px}
  .v9 .v9-form-grid{grid-template-columns:1fr}
  .v9 .v9-field.full{grid-column:auto}
  .v9 .v9-modal-check{padding-top:0}
  .v9 .v9-modal-foot{align-items:stretch;flex-direction:column}
  .v9 .v9-modal-left,.v9 .v9-modal-right{width:100%}
  .v9 .v9-modal-right .cp-btn,.v9 .v9-modal-left .cp-btn{flex:1}
}
</style>

<div class="print-config-page v9">
  <div class="print-config-toolbar">
    <div class="print-config-context">
      <span class="print-config-kicker">OPERACIÓN DE IMPRESIONES</span>
      <strong>Configuración de impresión</strong>
      <span>Catálogos rápidos y matriz de precios.</span>
    </div>
    <div class="print-config-actions">
      <a class="cp-btn cp-btn-primary" href="/admin/recepcion_impresiones.php">▣ Recepción de impresiones</a>
      <a class="cp-btn cp-btn-soft" href="/admin/recepcion_historial.php">▤ Historial de impresiones</a>
    </div>
  </div>

  <?php if ($msg): ?><div class="cp-success print-config-success">✓ <?= cp_e($msg) ?></div><?php endif; ?>

  <div class="v9-catalog-grid">
    <?php
    $catalogCards = [
      ['size','1','Tamaños','Nombre, código, medidas y disponibilidad.',$sizes,'name','size'],
      ['material','2','Materiales','Materiales disponibles para cotizar.',$materials,'name','material'],
      ['finish','3','Acabados','Nombres, códigos y disponibilidad.',$finishes,'name','finish'],
    ];
    foreach ($catalogCards as [$kind,$num,$heading,$desc,$rows,$labelKey,$type]):
    ?>
      <section class="v9-catalog-card">
        <span class="v9-card-glow" aria-hidden="true"></span>
        <div class="v9-card-top">
          <div class="v9-card-title">
            <div class="v9-card-number"><?= $num ?></div>
            <div><h2><?= $heading ?></h2><p><?= $desc ?></p></div>
          </div>
          <span class="v9-count"><?= count($rows) ?> registros</span>
        </div>
        <div class="v9-preview">
          <?php foreach (array_slice($rows,0,3) as $row): ?>
            <div class="v9-preview-row">
              <span><?= cp_e($row['name']) ?></span>
              <span><?= $type === 'size' ? cp_e($row['width_mm'].' × '.$row['height_mm'].' mm') : ($type === 'material' ? cp_e($row['unit_label']) : cp_e($row['code'])) ?></span>
            </div>
          <?php endforeach; ?>
          <?php if (!$rows): ?><div class="v9-preview-row"><span>Sin registros</span><span>Nuevo</span></div><?php endif; ?>
        </div>
        <div class="v9-card-actions">
          <span class="v9-card-hint">Editar, guardar o eliminar desde el popup.</span>
          <button type="button" class="cp-btn cp-btn-soft v9-manage" data-catalog="<?= $type ?>">Administrar</button>
        </div>
      </section>
    <?php endforeach; ?>
  </div>

  <section class="v9-price-card">
    <div class="v9-price-head">
      <div>
        <h2>Matriz de tarifas</h2>
        <p>La parte que realmente controla el precio del formulario.</p>
      </div>
      <div class="v9-price-actions">
        <button type="button" class="cp-btn cp-btn-primary" id="new-price">＋ Nueva tarifa</button>
      </div>
    </div>

    <div class="v9-price-list">
      <div class="v9-price-row v9-price-label">
        <div>Tamaño</div><div>Material</div><div>Acabado</div><div>Modo</div><div>Precio</div><div>Acciones</div>
      </div>
      <?php if (!$prices): ?>
        <div class="v9-empty">No hay tarifas registradas.</div>
      <?php endif; ?>
      <?php foreach ($prices as $p): ?>
        <div class="v9-price-row">
          <div class="v9-price-cell"><?= cp_e($p['size_name']) ?></div>
          <div class="v9-price-cell"><?= cp_e($p['material_name']) ?></div>
          <div class="v9-price-cell"><?= cp_e($p['finish_name']) ?></div>
          <div class="v9-price-cell"><span class="v9-pill"><?= cp_e($p['color_mode'].' · '.$p['pricing_mode']) ?></span></div>
          <div class="v9-price-cell v9-price-value">$<?= number_format((float)$p['unit_price'], 2, '.', ',') ?></div>
          <div class="v9-price-actions-cell">
            <button type="button" class="cp-btn cp-btn-soft v9-edit-price" data-price-id="<?= (int)$p['id'] ?>">Editar</button>
            <button type="button" class="cp-btn cp-btn-danger v9-delete-price" data-price-id="<?= (int)$p['id'] ?>">Eliminar</button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<!-- Modal de catálogos -->
<div class="v9-modal" id="catalog-modal" aria-hidden="true">
  <div class="v9-modal-panel" role="dialog" aria-modal="true" aria-labelledby="catalog-modal-title">
    <div class="v9-modal-head">
      <div><div class="v9-modal-kicker">CATÁLOGO</div><h3 id="catalog-modal-title">Administrar</h3><p id="catalog-modal-desc">Edita el registro seleccionado.</p></div>
      <button type="button" class="v9-close" data-close="catalog-modal" aria-label="Cerrar">×</button>
    </div>
    <form method="post" action="catalog_save.php" id="catalog-form">
      <div class="v9-modal-body">
        <div class="v9-field full" style="margin-bottom:11px"><label for="catalog-record">Registro</label><select id="catalog-record"><option value="">Nuevo registro</option></select></div>
        <input type="hidden" name="csrf" value="<?= cp_e(cp_csrf()) ?>">
        <input type="hidden" name="type" id="catalog-type" value="">
        <input type="hidden" name="id" id="catalog-id" value="">
        <div class="v9-form-grid" id="catalog-fields"></div>
      </div>
      <div class="v9-modal-foot">
        <div class="v9-modal-left">
          <button type="button" class="cp-btn cp-btn-soft" id="catalog-new">＋ Nuevo</button>
          <button type="button" class="cp-btn cp-btn-soft" id="catalog-clear">Limpiar</button>
          <button type="button" class="cp-btn cp-btn-danger" id="catalog-delete">Eliminar</button>
        </div>
        <div class="v9-modal-right">
          <button type="button" class="cp-btn cp-btn-soft" data-close="catalog-modal">Cancelar</button>
          <button type="submit" class="cp-btn cp-btn-primary">Guardar</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Modal de tarifas -->
<div class="v9-modal" id="price-modal" aria-hidden="true">
  <div class="v9-modal-panel" role="dialog" aria-modal="true" aria-labelledby="price-modal-title">
    <div class="v9-modal-head">
      <div><div class="v9-modal-kicker">MATRIZ DE TARIFAS</div><h3 id="price-modal-title">Nueva tarifa</h3><p>Guarda o edita una combinación sin alterar el listado.</p></div>
      <button type="button" class="v9-close" data-close="price-modal" aria-label="Cerrar">×</button>
    </div>
    <form method="post" action="price_save.php" id="price-form">
      <div class="v9-modal-body">
        <div class="v9-field full" style="margin-bottom:11px"><label for="catalog-record">Registro</label><select id="catalog-record"><option value="">Nuevo registro</option></select></div>
        <input type="hidden" name="csrf" value="<?= cp_e(cp_csrf()) ?>">
        <input type="hidden" name="id" id="price-id" value="">
        <div class="v9-form-grid">
          <div class="v9-field"><label for="price-size">Tamaño</label><select id="price-size" name="size_id" required><option value="">Seleccionar</option><?php foreach ($sizes as $s): ?><option value="<?= (int)$s['id'] ?>"><?= cp_e($s['name']) ?></option><?php endforeach; ?></select></div>
          <div class="v9-field"><label for="price-material">Material</label><select id="price-material" name="material_id" required><option value="">Seleccionar</option><?php foreach ($materials as $m): ?><option value="<?= (int)$m['id'] ?>"><?= cp_e($m['name']) ?></option><?php endforeach; ?></select></div>
          <div class="v9-field"><label for="price-finish">Acabado</label><select id="price-finish" name="finish_id" required><option value="">Seleccionar</option><?php foreach ($finishes as $f): ?><option value="<?= (int)$f['id'] ?>"><?= cp_e($f['name']) ?></option><?php endforeach; ?></select></div>
          <div class="v9-field"><label for="price-color">Modo</label><select id="price-color" name="color_mode"><option value="color">Color</option><option value="bw">B/N</option></select></div>
          <div class="v9-field"><label for="price-value">Precio</label><input id="price-value" type="number" name="unit_price" step=".01" min="0" placeholder="0.00" required></div>
          <div class="v9-field"><label for="price-mode">Cobro</label><select id="price-mode" name="pricing_mode"><option value="per_page">Por página</option><option value="per_sheet">Por hoja</option></select></div>
        </div>
      </div>
      <div class="v9-modal-foot">
        <div class="v9-modal-left"><button type="button" class="cp-btn cp-btn-soft" id="price-clear">Limpiar</button><button type="button" class="cp-btn cp-btn-danger" id="price-delete">Eliminar</button></div>
        <div class="v9-modal-right"><button type="button" class="cp-btn cp-btn-soft" data-close="price-modal">Cancelar</button><button type="submit" class="cp-btn cp-btn-primary">Guardar tarifa</button></div>
      </div>
    </form>
  </div>
</div>

<div class="v9-toast" id="v9-toast">Listo</div>

<script>
(function(){
  const catalogData = <?= json_encode([
      'size' => array_values($sizes),
      'material' => array_values($materials),
      'finish' => array_values($finishes),
  ], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
  const priceData = <?= json_encode(array_values($prices), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;

  const catalogMeta = {
    size:{title:'Tamaños',desc:'Nombre, código, medidas y disponibilidad.',type:'size'},
    material:{title:'Materiales',desc:'Nombre, unidad y disponibilidad.',type:'material'},
    finish:{title:'Acabados',desc:'Nombre, código y disponibilidad.',type:'finish'}
  };

  const catalogModal=document.getElementById('catalog-modal');
  const catalogForm=document.getElementById('catalog-form');
  const catalogFields=document.getElementById('catalog-fields');
  const catalogType=document.getElementById('catalog-type');
  const catalogId=document.getElementById('catalog-id');
  const catalogTitle=document.getElementById('catalog-modal-title');
  const catalogDesc=document.getElementById('catalog-modal-desc');
  const catalogDelete=document.getElementById('catalog-delete');
  const priceModal=document.getElementById('price-modal');
  const priceForm=document.getElementById('price-form');
  const priceId=document.getElementById('price-id');
  const priceTitle=document.getElementById('price-modal-title');
  const toast=document.getElementById('v9-toast');

  function openModal(el){el.classList.add('is-open');el.setAttribute('aria-hidden','false');document.body.style.overflow='hidden'}
  function closeModal(el){el.classList.remove('is-open');el.setAttribute('aria-hidden','true');document.body.style.overflow=''}
  document.querySelectorAll('[data-close]').forEach(b=>b.addEventListener('click',()=>closeModal(document.getElementById(b.dataset.close))));
  [catalogModal,priceModal].forEach(m=>m.addEventListener('click',e=>{if(e.target===m)closeModal(m)}));
  document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeModal(catalogModal);closeModal(priceModal)}});

  function esc(v){return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;')}
  function showToast(text){toast.textContent=text;toast.classList.add('show');setTimeout(()=>toast.classList.remove('show'),1700)}

  function catalogInputs(type,row){
    const active=row ? Number(row.enabled)===1 : true;
    if(type==='size') return `
      <div class="v9-field"><label>Nombre</label><input name="name" required value="${esc(row?.name)}"></div>
      <div class="v9-field"><label>Código</label><input name="code" required value="${esc(row?.code)}"></div>
      <div class="v9-field"><label>Ancho (mm)</label><input name="width_mm" type="number" step=".01" min="0.01" required value="${esc(row?.width_mm)}"></div>
      <div class="v9-field"><label>Alto (mm)</label><input name="height_mm" type="number" step=".01" min="0.01" required value="${esc(row?.height_mm)}"></div>
      <label class="v9-modal-check full"><input name="enabled" type="checkbox" value="1" ${active?'checked':''}> Activo</label>`;
    if(type==='material') return `
      <div class="v9-field"><label>Nombre</label><input name="name" required value="${esc(row?.name)}"></div>
      <div class="v9-field"><label>Código</label><input name="code" required value="${esc(row?.code)}"></div>
      <div class="v9-field"><label>Unidad</label><input name="unit_label" required value="${esc(row?.unit_label)}"></div>
      <label class="v9-modal-check full"><input name="enabled" type="checkbox" value="1" ${active?'checked':''}> Activo</label>`;
    return `
      <div class="v9-field"><label>Nombre</label><input name="name" required value="${esc(row?.name)}"></div>
      <div class="v9-field"><label>Código</label><input name="code" required value="${esc(row?.code)}"></div>
      <label class="v9-modal-check full"><input name="enabled" type="checkbox" value="1" ${active?'checked':''}> Activo</label>`;
  }

  function populateCatalogRecordSelect(type, selectedId){
    const select=document.getElementById('catalog-record');
    select.innerHTML='<option value="">Nuevo registro</option>';
    (catalogData[type]||[]).forEach(row=>{
      const opt=document.createElement('option');
      opt.value=row.id;
      opt.textContent=row.name || ('Registro #'+row.id);
      if(Number(row.id)===Number(selectedId)) opt.selected=true;
      select.appendChild(opt);
    });
  }

  function openCatalog(type,id){
    const meta=catalogMeta[type];
    const row=(catalogData[type]||[]).find(x=>Number(x.id)===Number(id))||null;
    catalogType.value=type;
    catalogId.value=row ? row.id : '';
    populateCatalogRecordSelect(type,row ? row.id : '');
    catalogTitle.textContent=row ? 'Editar '+meta.title.slice(0,-1) : 'Nuevo '+meta.title.slice(0,-1);
    catalogDesc.textContent=meta.desc;
    catalogFields.innerHTML=catalogInputs(type,row);
    catalogDelete.style.display=row?'inline-flex':'none';
    catalogDelete.dataset.type=type;
    catalogDelete.dataset.id=row ? row.id : '';
    openModal(catalogModal);
  }

  document.querySelectorAll('.v9-manage').forEach(btn=>btn.addEventListener('click',()=>openCatalog(btn.dataset.catalog,'')));
  document.getElementById('catalog-record').addEventListener('change',()=>openCatalog(catalogType.value||'size',document.getElementById('catalog-record').value));
  document.getElementById('catalog-new').addEventListener('click',()=>openCatalog(catalogType.value||'size',''));
  document.getElementById('catalog-clear').addEventListener('click',()=>{
    const type=catalogType.value||'size';catalogId.value='';populateCatalogRecordSelect(type,'');catalogFields.innerHTML=catalogInputs(type,null);catalogDelete.style.display='none';catalogTitle.textContent='Nuevo '+catalogMeta[type].title.slice(0,-1);showToast('Formulario limpio');
  });

  catalogDelete.addEventListener('click',()=>{
    const type=catalogDelete.dataset.type,id=catalogDelete.dataset.id;
    if(!id)return;
    if(!confirm('Si este registro ya se usa en tarifas, el sistema puede desactivarlo en lugar de borrarlo. ¿Continuar?'))return;
    const f=document.createElement('form');f.method='post';f.action='catalog_save.php';
    f.innerHTML=`<input type="hidden" name="csrf" value="<?= cp_e(cp_csrf()) ?>"><input type="hidden" name="type" value="${type}_delete"><input type="hidden" name="id" value="${id}">`;
    document.body.appendChild(f);f.submit();
  });

  function resetPrice(){
    priceId.value='';priceTitle.textContent='Nueva tarifa';
    priceForm.reset();priceId.value='';
  }
  function openPrice(id){
    resetPrice();
    const p=priceData.find(x=>Number(x.id)===Number(id));
    if(p){
      priceId.value=p.id;priceTitle.textContent='Editar tarifa';
      document.getElementById('price-size').value=p.size_id;
      document.getElementById('price-material').value=p.material_id;
      document.getElementById('price-finish').value=p.finish_id;
      document.getElementById('price-color').value=p.color_mode;
      document.getElementById('price-value').value=p.unit_price;
      document.getElementById('price-mode').value=p.pricing_mode;
    }
    openModal(priceModal);
  }
  document.getElementById('new-price').addEventListener('click',()=>openPrice(''));
  document.querySelectorAll('.v9-edit-price').forEach(b=>b.addEventListener('click',()=>openPrice(b.dataset.priceId)));
  document.getElementById('price-clear').addEventListener('click',()=>{resetPrice();showToast('Formulario limpio')});
  document.getElementById('price-delete').addEventListener('click',()=>{
    if(!priceId.value){showToast('Selecciona una tarifa');return}
    if(!confirm('¿Eliminar esta tarifa?'))return;
    const f=document.createElement('form');f.method='post';f.action='price_delete.php';
    f.innerHTML=`<input type="hidden" name="csrf" value="<?= cp_e(cp_csrf()) ?>"><input type="hidden" name="id" value="${esc(priceId.value)}">`;
    document.body.appendChild(f);f.submit();
  });
  document.querySelectorAll('.v9-delete-price').forEach(b=>b.addEventListener('click',()=>{
    if(!confirm('¿Eliminar esta tarifa?'))return;
    const f=document.createElement('form');f.method='post';f.action='price_delete.php';
    f.innerHTML=`<input type="hidden" name="csrf" value="<?= cp_e(cp_csrf()) ?>"><input type="hidden" name="id" value="${esc(b.dataset.priceId)}">`;
    document.body.appendChild(f);f.submit();
  }));
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
