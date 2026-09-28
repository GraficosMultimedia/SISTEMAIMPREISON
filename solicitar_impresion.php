<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';

$db = cp_db();
$sizes = $db->query("SELECT id,name,code,width_mm,height_mm,orientation FROM cp_print_sizes WHERE enabled=1 ORDER BY sort_order,id")->fetchAll();
$materials = $db->query("SELECT id,name,code,unit_label FROM cp_print_materials WHERE enabled=1 ORDER BY sort_order,id")->fetchAll();
$finishes = $db->query("SELECT id,name,code FROM cp_print_finishes WHERE enabled=1 ORDER BY sort_order,id")->fetchAll();
$prices = $db->query("SELECT size_id,material_id,finish_id,color_mode,pricing_mode,unit_price FROM cp_print_prices WHERE enabled=1")->fetchAll();

$priceMap = [];
foreach ($prices as $p) {
    $sizeId = (int)$p['size_id'];
    $materialId = (int)$p['material_id'];
    $finishId = (int)$p['finish_id'];
    $colorMode = strtolower(trim((string)$p['color_mode']));

    $entry = [
        'unit_price' => (float)$p['unit_price'],
        'pricing_mode' => (string)$p['pricing_mode']
    ];

    // La clave oficial es la misma que usa el backend. Se agregan alias
    // normalizados para evitar que una diferencia de formato en el JS
    // deje una combinación válida como “Sin tarifa”.
    $priceMap[cp_price_key($sizeId, $materialId, $finishId, $colorMode)] = $entry;
    $priceMap[$sizeId . '|' . $materialId . '|' . $finishId . '|' . $colorMode] = $entry;
    $priceMap[$sizeId . '-' . $materialId . '-' . $finishId . '-' . $colorMode] = $entry;
    $priceMap[$sizeId . '_' . $materialId . '_' . $finishId . '_' . $colorMode] = $entry;
}
$defaultSize = $sizes[0]['id'] ?? 0;
$defaultMaterial = $materials[0]['id'] ?? 0;
$defaultFinish = $finishes[0]['id'] ?? 0;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Solicita tus impresiones · Colibrí Print</title>
<link rel="stylesheet" href="assets/css/colibri-print.css">
</head>
<body>
<div class="cp-wrap">
  <div class="cp-brand">COLIBRÍ PRINT</div>
  <div class="cp-hero">
    <div>
      <h1>Solicita tus impresiones</h1>
      <p>Sube tus archivos, configura cada uno y conoce el precio antes de enviarnos tu solicitud.</p>
    </div>
    <div class="cp-pill">✓ Cálculo por páginas</div>
  </div>

  <div class="cp-stepper">
    <div class="cp-step is-active"><div class="cp-step-num">1</div><div><strong>Tus datos</strong><small>Completa tu información</small></div></div>
    <div class="cp-step is-active"><div class="cp-step-num">2</div><div><strong>Archivos</strong><small>Sube tus archivos</small></div></div>
    <div class="cp-step is-active"><div class="cp-step-num">3</div><div><strong>Configuración</strong><small>Personaliza cada archivo</small></div></div>
    <div class="cp-step"><div class="cp-step-num">4</div><div><strong>Enviar</strong><small>Revisa y confirma</small></div></div>
  </div>

  <div id="cp-price-warning" class="cp-alert" hidden></div>

  <form id="cp-request-form" method="post" action="api/submit_request.php">
    <input type="hidden" name="csrf" id="cp-csrf" value="<?=cp_e(cp_csrf())?>">
    <input type="hidden" name="upload_token" id="cp-upload-token">
    <input type="hidden" name="items_json" id="cp-items-json">

    <div class="cp-layout">
      <main class="cp-main">
        <section class="cp-card">
          <div class="cp-card-head">
            <div class="cp-section-title"><div class="cp-section-num">1</div><div><h2>Tus datos</h2><p>¿A quién debemos entregar la solicitud?</p></div></div>
          </div>
          <div class="cp-grid-3">
            <div class="cp-field"><label>Nombre completo</label><input required name="customer_name" placeholder="Nombre completo"></div>
            <div class="cp-field"><label>Correo electrónico</label><input required type="email" name="customer_email" placeholder="correo@ejemplo.com"></div>
            <div class="cp-field"><label>Teléfono</label><input required name="customer_phone" placeholder="10 dígitos"></div>
          </div>
        </section>

        <section class="cp-card">
          <div class="cp-card-head">
            <div class="cp-section-title"><div class="cp-section-num">2</div><div><h2>Archivos</h2><p>Puedes enviar varios archivos en una sola solicitud.</p></div></div>
            <span class="cp-badge"><span id="cp-files-count">0</span> archivos</span>
          </div>
          <div id="cp-dropzone" class="cp-upload">
            <div class="cp-upload-inner">
              <div class="cp-file-icons">
                <div class="cp-file-icon pdf">PDF</div><div class="cp-file-icon png">PNG</div><div class="cp-file-icon jpg">JPG</div>
              </div>
              <div>
                <h3>Arrastra tus archivos aquí</h3>
                <p>o selecciona desde tu equipo</p>
                <label class="cp-btn cp-btn-primary" for="cp-files">▣ &nbsp; Seleccionar archivos</label>
                <input class="cp-file-input" id="cp-files" type="file" multiple accept=".pdf,.jpg,.jpeg,.png">
                <p>PDF, JPG y PNG · hasta 25 MB por archivo</p>
                <small id="cp-upload-status" class="cp-muted">El número de páginas se captura manualmente para calcular tu cotización.</small>
              </div>
            </div>
          </div>
          <div id="cp-upload-list" class="cp-file-list"></div>
        </section>

        <section class="cp-card">
          <div class="cp-card-head">
            <div class="cp-section-title"><div class="cp-section-num">3</div><div><h2>Configura cada archivo</h2><p>El precio se actualiza al cambiar cualquier opción.</p></div></div>
          </div>
          <div id="cp-config-list"></div>
        </section>

        <section class="cp-card">
          <div class="cp-card-head">
            <div class="cp-section-title"><div class="cp-section-num">4</div><div><h2>Enviar solicitud</h2><p>Revisaremos los archivos y la configuración recibida.</p></div></div>
            <button class="cp-btn cp-btn-primary" id="cp-submit" type="submit">Enviar solicitud →</button>
          </div>
          <div class="cp-muted" style="font-size:13px">🔒 Tu solicitud quedará lista para recepción. El precio mostrado es estimado y se calculará con la cantidad de páginas que indiques.</div>
        </section>
      </main>

      <aside class="cp-summary">
        <div class="cp-summary-card">
          <div class="cp-summary-hero">
            <span>RESUMEN</span>
            <small>Total estimado</small>
            <div class="cp-summary-total" id="cp-total">$0.00</div>
          </div>
          <div class="cp-summary-body">
            <div class="cp-summary-metrics">
              <div class="cp-metric"><strong data-files-count>0</strong><span>archivos</span></div>
              <div class="cp-metric"><strong id="cp-pages-count">0</strong><span>páginas</span></div>
              <div class="cp-metric"><strong id="cp-sheets-count">0</strong><span>hojas físicas</span></div>
            </div>
            <h3 class="cp-detail-title">Detalle de archivos</h3>
            <div id="cp-summary-detail"><div class="cp-empty">Tu resumen aparecerá aquí al cargar archivos.</div></div>
            <div class="cp-total-line"><span>Total estimado</span><strong id="cp-total-bottom">$0.00</strong></div>
            <div class="cp-notice">⚠ <span><strong>Este es un precio estimado.</strong><br>La confirmación final se realiza al revisar tus archivos.</span></div>
            <button class="cp-btn cp-btn-primary" type="submit" id="cp-submit-2">Continuar con mi solicitud →</button>
            <div class="cp-trust"><div><strong>✓</strong>Archivos protegidos</div><div><strong>☁</strong>PDF, JPG y PNG</div><div><strong>▣</strong>Todo tipo de proyectos</div></div>
          </div>
        </div>
        <div class="cp-footer-brand" style="margin-top:14px"><strong>COLIBRÍ PRINT</strong><span>Imprimimos tus ideas<br>Calidad · Color · Gran Formato</span></div>
      </aside>
    </div>
  </form>
</div>
<script>
window.CP_CATALOGS = <?=cp_json([
  'sizes'=>$sizes,'materials'=>$materials,'finishes'=>$finishes,'prices'=>$priceMap
])?>;

// Compatibilidad con versiones del JS que construyan la clave de tarifa
// con otro separador. No cambia la fuente de precios de la base de datos.
(() => {
  const originalPrices = window.CP_CATALOGS.prices || {};
  const normalizeKey = (key) => {
    const raw = String(key ?? '').trim();
    const parts = raw.split(/[:|_\-\/,\s]+/).filter(Boolean);
    if (parts.length !== 4) return raw;
    return `${parseInt(parts[0], 10)}:${parseInt(parts[1], 10)}:${parseInt(parts[2], 10)}:${String(parts[3]).toLowerCase()}`;
  };

  window.CP_CATALOGS.prices = new Proxy(originalPrices, {
    get(target, prop, receiver) {
      if (typeof prop !== 'string') return Reflect.get(target, prop, receiver);
      if (Object.prototype.hasOwnProperty.call(target, prop)) return target[prop];
      const normalized = normalizeKey(prop);
      if (Object.prototype.hasOwnProperty.call(target, normalized)) return target[normalized];
      return undefined;
    }
  });
})();
</script>
<script src="assets/js/colibri-print.js"></script>
</body>
</html>
