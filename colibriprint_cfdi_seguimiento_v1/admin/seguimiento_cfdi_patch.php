<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/runtime.php';
require_once __DIR__ . '/../includes/actions.php';
require_auth();

header('Content-Type: text/plain; charset=utf-8');

$target = dirname(__DIR__) . '/seguimiento.php';
$includeTarget = dirname(__DIR__) . '/includes/seguimiento.php';
$publicEndpoint = dirname(__DIR__) . '/seguimiento_cfdi_documento.php';
$marker = '<!-- COLIBRIPRINT_CFDI_TRACKING_V1 -->';

if (!is_file($target)) {
    http_response_code(500);
    exit("ERROR: No existe seguimiento.php\n");
}
if (!is_file($includeTarget)) {
    http_response_code(500);
    exit("ERROR: No existe includes/seguimiento.php\n");
}
if (!is_file($publicEndpoint)) {
    http_response_code(500);
    exit("ERROR: Falta seguimiento_cfdi_documento.php\n");
}

$source = file_get_contents($target);
$trackingInclude = file_get_contents($includeTarget);
if ($source === false || $trackingInclude === false) {
    http_response_code(500);
    exit("ERROR: No se pudieron leer los archivos.\n");
}

if (strpos($source, $marker) !== false) {
    echo "OK: El módulo CFDI ya está integrado en seguimiento.php.\n";
    exit;
}

$stamp = date('Ymd_His');
$backupDir = dirname(__DIR__) . '/storage/backups';
if (!is_dir($backupDir) && !@mkdir($backupDir, 0750, true)) {
    http_response_code(500);
    exit("ERROR: No se pudo crear storage/backups.\n");
}

$backupPath = $backupDir . '/seguimiento.php.' . $stamp . '.bak';
if (file_put_contents($backupPath, $source, LOCK_EX) === false) {
    http_response_code(500);
    exit("ERROR: No se pudo crear respaldo de seguimiento.php.\n");
}

// 1) Extiende includes/seguimiento.php con la consulta aislada, sin tocar su lógica existente.
$requireLine = "require_once __DIR__ . '/includes/seguimiento_cfdi.php';";
if (strpos($source, $requireLine) === false) {
    $needle = "require_once __DIR__ . '/includes/company.php';";
    if (strpos($source, $needle) === false) {
        @unlink($backupPath);
        http_response_code(500);
        exit("ERROR: No encontré el punto seguro para cargar el módulo CFDI.\n");
    }
    $source = str_replace($needle, $needle . "\n" . $requireLine, $source, $count);
    if ($count !== 1) {
        @unlink($backupPath);
        http_response_code(500);
        exit("ERROR: El punto de inclusión no es único.\n");
    }
}

// 2) Obtiene las facturas de la misma orden.
$paymentsLine = '$paymentReceipts = $order ? tracking_payment_receipts((int)$order[\'id\']) : [];';
$invoiceLine = '$invoiceDocuments = $order ? tracking_invoice_documents((int)$order[\'id\']) : [];';
if (strpos($source, $invoiceLine) === false) {
    if (strpos($source, $paymentsLine) === false) {
        @unlink($backupPath);
        http_response_code(500);
        exit("ERROR: No encontré el bloque de comprobantes de pago.\n");
    }
    $source = str_replace($paymentsLine, $paymentsLine . "\n" . $invoiceLine, $source, $count);
    if ($count !== 1) {
        @unlink($backupPath);
        http_response_code(500);
        exit("ERROR: El bloque de comprobantes de pago no es único.\n");
    }
}

// 3) Inserta el expediente fiscal después del bloque actual de comprobantes de pago.
$section = <<<'HTML'

$marker
<section class="cfdi-public-tracking-card" id="facturas-cfdi">
  <div class="cfdi-public-head">
    <div>
      <span class="eyebrow">EXPEDIENTE FISCAL</span>
      <h2>Facturas de esta orden</h2>
      <p>Documentos CFDI vinculados directamente a esta orden de servicio.</p>
    </div>
    <span class="cfdi-public-count"><?=count($invoiceDocuments)?> <?=count($invoiceDocuments) === 1 ? 'factura' : 'facturas'?></span>
  </div>
  <?php if ($invoiceDocuments): ?>
    <div class="cfdi-public-list">
    <?php foreach ($invoiceDocuments as $doc): ?>
      <article class="cfdi-public-item">
        <div class="cfdi-public-icon">🧾</div>
        <div class="cfdi-public-main">
          <strong><?=track_e((string)($doc['uuid'] ?: $doc['invoice_number'] ?: 'CFDI'))?></strong>
          <span><?=track_e((string)($doc['invoice_number'] ?: 'Factura'))?> · <?=track_date((string)($doc['issued_at'] ?: $doc['invoice_date']))?></span>
          <small>Importe: <?=track_money((float)$doc['total'])?> · <?=track_e((string)($doc['invoice_status'] ?: 'Emitida'))?></small>
        </div>
        <div class="cfdi-public-actions">
          <?php if (!empty($doc['xml_path'])): ?><a href="/seguimiento_cfdi_documento.php?t=<?=rawurlencode($token)?>&id=<?=((int)$doc['document_id'])?>&download=xml">XML</a><?php endif; ?>
          <?php if (!empty($doc['pdf_path'])): ?><a class="primary" href="/seguimiento_cfdi_documento.php?t=<?=rawurlencode($token)?>&id=<?=((int)$doc['document_id'])?>&download=pdf">PDF</a><?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="cfdi-public-empty">Todavía no hay facturas CFDI adjuntas a esta orden.</div>
  <?php endif; ?>
</section>

<style>
.cfdi-public-tracking-card{margin-top:16px;padding:21px;background:#fff;border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow)}
.cfdi-public-head{display:flex;justify-content:space-between;align-items:flex-start;gap:15px}
.cfdi-public-head h2{margin:3px 0 4px;color:var(--navy);font-size:20px}
.cfdi-public-head p{margin:0;color:var(--muted);font-size:11px}
.cfdi-public-count{font-size:10px;font-weight:800;color:#087443;background:#eaf9f1;border:1px solid #c9ecd9;padding:8px 11px;border-radius:999px;white-space:nowrap}
.cfdi-public-list{display:grid;gap:9px;margin-top:15px}
.cfdi-public-item{display:flex;align-items:center;gap:12px;border:1px solid var(--line);border-radius:13px;padding:12px 13px;background:#fbfdff}
.cfdi-public-icon{width:38px;height:38px;border-radius:11px;background:#eef6ff;display:flex;align-items:center;justify-content:center;font-size:20px;flex:0 0 38px}
.cfdi-public-main{flex:1;min-width:0}
.cfdi-public-main strong,.cfdi-public-main span,.cfdi-public-main small{display:block}
.cfdi-public-main strong{color:var(--navy);font-size:12px;overflow-wrap:anywhere}
.cfdi-public-main span{color:#52657f;font-size:11px;margin-top:3px}
.cfdi-public-main small{color:#8a98aa;font-size:10px;margin-top:3px}
.cfdi-public-actions{display:flex;gap:7px;flex-wrap:wrap}
.cfdi-public-actions a{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border:1px solid #cfe0f0;background:#fff;color:var(--blue);border-radius:9px;padding:8px 11px;font-size:10px;font-weight:800}
.cfdi-public-actions a.primary{background:var(--blue);border-color:var(--blue);color:#fff}
.cfdi-public-empty{margin-top:13px;padding:12px;border:1px dashed #cbd9e8;border-radius:11px;color:var(--muted);font-size:11px}
@media(max-width:680px){
  .cfdi-public-head{flex-direction:column}
  .cfdi-public-item{align-items:flex-start;flex-wrap:wrap}
  .cfdi-public-main{width:calc(100% - 50px)}
  .cfdi-public-actions{width:100%;margin-left:50px}
  .cfdi-public-actions a{flex:1;min-width:90px}
}
</style>
HTML;
$section = str_replace('$marker', $marker, $section);

// Insertar antes del cierre del body. No depende de la posición exacta del bloque de pagos.
$bodyMarker = '</body>';
$pos = strripos($source, $bodyMarker);
if ($pos === false) {
    @unlink($backupPath);
    http_response_code(500);
    exit("ERROR: No encontré </body> en seguimiento.php.\n");
}
$source = substr($source, 0, $pos) . $section . "\n" . substr($source, $pos);

if (file_put_contents($target, $source, LOCK_EX) === false) {
    // Restaurar automáticamente si la escritura falla.
    @copy($backupPath, $target);
    http_response_code(500);
    exit("ERROR: No se pudo escribir seguimiento.php. Se restauró el respaldo.\n");
}

// Verificación básica de sintaxis.
$php = PHP_BINARY;
$cmd = escapeshellarg($php) . ' -l ' . escapeshellarg($target) . ' 2>&1';
$output = shell_exec($cmd) ?? '';
if (stripos($output, 'No syntax errors detected') === false) {
    @copy($backupPath, $target);
    http_response_code(500);
    exit("ERROR: La verificación PHP falló. Se restauró el respaldo.\n" . $output . "\n");
}

echo "OK: Integración CFDI aplicada al seguimiento.\n";
echo "Respaldo: " . $backupPath . "\n";
echo "Nuevo endpoint público: /seguimiento_cfdi_documento.php\n";
echo "IMPORTANTE: elimina admin/seguimiento_cfdi_patch.php después de probar.\n";
