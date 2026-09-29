<?php
declare(strict_types=1);

/*
 * Cambio: mostrar en seguimiento público las condiciones de la cotización
 * y las notas operativas de la orden.
 *
 * Coloca este archivo temporalmente en /admin/ y ábrelo una sola vez:
 *   /admin/aplicar_resumen_comercial_tracking_v1.php
 *
 * Después de comprobar el seguimiento, elimina este archivo y los respaldos
 * .bak_resumen_comercial_v1 si ya no los necesitas.
 *
 * NO requiere cambios SQL.
 */

$root = dirname(__DIR__);
$trackingFile = $root . '/seguimiento.php';
$helperFile   = $root . '/includes/seguimiento.php';

if (!is_file($trackingFile) || !is_file($helperFile)) {
    http_response_code(500);
    exit("No se encontraron seguimiento.php e includes/seguimiento.php.\n");
}

function backup_once(string $file): void {
    $backup = $file . '.bak_resumen_comercial_v1';
    if (!is_file($backup) && !copy($file, $backup)) {
        throw new RuntimeException("No se pudo crear respaldo de {$file}");
    }
}

function replace_once(string $content, string $old, string $new, string $label): string {
    $count = substr_count($content, $old);
    if ($count !== 1) {
        throw new RuntimeException(
            "Bloque esperado no encontrado o ambiguo ({$label}). Coincidencias: {$count}"
        );
    }
    return str_replace($old, $new, $content);
}

try {
    $tracking = (string)file_get_contents($trackingFile);
    $helper   = (string)file_get_contents($helperFile);

    /*
     * 1. Backend:
     *    o.notes -> order_notes
     *    q.notes -> quote_notes
     *
     * Esto evita que dos columnas "notes" colisionen en el array PDO.
     */
    if (strpos($helper, 'o.notes AS order_notes') === false) {
        $old = "SELECT o.id,o.order_number,o.order_date,o.due_date,o.total,o.status,o.notes,\n";
        $new = "SELECT o.id,o.order_number,o.order_date,o.due_date,o.total,o.status,o.notes AS order_notes,\n";
        $helper = replace_once($helper, $old, $new, 'alias de notas de orden');
    }

    if (strpos($helper, 'q.notes AS quote_notes') === false) {
        $old = "q.valid_until,q.payment_terms,q.delivery_time,q.delivery_place,q.terms,\n";
        $new = "q.valid_until,q.payment_terms,q.delivery_time,q.delivery_place,q.notes AS quote_notes,q.terms,\n";
        $helper = replace_once($helper, $old, $new, 'notas para cliente de cotización');
    }

    /*
     * 2. CSS del nuevo bloque comercial.
     */
    if (strpos($tracking, '.commercial-summary{') === false) {
        $cssMarker = ".receipt-disclaimer{font-size:10px;color:#8a98aa;margin:13px 0 0}\n";
        $css = <<<'CSS'
.commercial-summary{margin-top:18px;padding:16px;background:#f7faff;border:1px solid #e2ebf6;border-radius:13px}
.commercial-summary-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:12px}
.commercial-summary-head h3{margin:3px 0 0;color:var(--navy);font-size:14px}
.commercial-summary-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.commercial-summary-item{background:#fff;border:1px solid var(--line);border-radius:10px;padding:11px}
.commercial-summary-item span{display:block;color:var(--muted);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
.commercial-summary-item strong{display:block;margin-top:5px;color:var(--navy);font-size:12px;line-height:1.45;font-weight:700}
.commercial-summary-note{margin-top:10px;background:#fff;border:1px solid var(--line);border-radius:10px;padding:11px}
.commercial-summary-note span{display:block;color:var(--muted);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
.commercial-summary-note p{margin:5px 0 0;color:#4f6078;font-size:12px;line-height:1.6}
.operational-note{margin-top:10px;padding:11px 13px;background:#eef6ff;border:1px solid #cfe3f7;border-radius:10px}
.operational-note strong{display:block;color:var(--navy);font-size:11px}
.operational-note p{margin:5px 0 0;color:#4f6078;font-size:12px;line-height:1.6}
@media(max-width:760px){
  .commercial-summary-grid{grid-template-columns:1fr}
}
CSS;
        $tracking = replace_once($tracking, $cssMarker, $cssMarker . $css . "\n", 'CSS resumen comercial');
    }

    /*
     * 3. HTML del resumen comercial.
     * Se coloca justo después de "SERVICIOS CONTRATADOS" y antes del pago.
     */
    if (strpos($tracking, 'class="commercial-summary"') === false) {
        $marker = '  <?php if ($finance[\'first_payment\']): $fp=$finance[\'first_payment\']; ?>';
        $html = <<<'HTML'

  <?php
    $paymentTerms  = trim((string)($order['payment_terms'] ?? ''));
    $deliveryTime  = trim((string)($order['delivery_time'] ?? ''));
    $deliveryPlace = trim((string)($order['delivery_place'] ?? ''));
    $clientNotes   = trim((string)($order['quote_notes'] ?? ''));
    $commercialTerms = trim((string)($order['terms'] ?? ''));
    $operationalNotes = trim((string)($order['order_notes'] ?? ''));
  ?>
  <?php if ($paymentTerms !== '' || $deliveryTime !== '' || $deliveryPlace !== '' || $clientNotes !== '' || $commercialTerms !== '' || $operationalNotes !== ''): ?>
  <div class="commercial-summary">
    <div class="commercial-summary-head">
      <div>
        <span class="eyebrow">CONDICIONES COMERCIALES</span>
        <h3>Información importante de tu servicio</h3>
      </div>
    </div>

    <?php if ($paymentTerms !== '' || $deliveryTime !== '' || $deliveryPlace !== ''): ?>
    <div class="commercial-summary-grid">
      <?php if ($paymentTerms !== ''): ?>
      <div class="commercial-summary-item"><span>Condiciones de pago</span><strong><?=nl2br(track_e($paymentTerms))?></strong></div>
      <?php endif; ?>
      <?php if ($deliveryTime !== ''): ?>
      <div class="commercial-summary-item"><span>Tiempo de entrega</span><strong><?=nl2br(track_e($deliveryTime))?></strong></div>
      <?php endif; ?>
      <?php if ($deliveryPlace !== ''): ?>
      <div class="commercial-summary-item"><span>Lugar de entrega</span><strong><?=nl2br(track_e($deliveryPlace))?></strong></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($clientNotes !== ''): ?>
    <div class="commercial-summary-note">
      <span>Notas para el cliente</span>
      <p><?=nl2br(track_e($clientNotes))?></p>
    </div>
    <?php endif; ?>

    <?php if ($commercialTerms !== ''): ?>
    <div class="commercial-summary-note">
      <span>Condiciones comerciales</span>
      <p><?=nl2br(track_e($commercialTerms))?></p>
    </div>
    <?php endif; ?>

    <?php if ($operationalNotes !== ''): ?>
    <div class="operational-note">
      <strong>Notas operativas</strong>
      <p><?=nl2br(track_e($operationalNotes))?></p>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

HTML;
        $tracking = replace_once($tracking, $marker, $html . $marker, 'HTML resumen comercial');
    }

    backup_once($helperFile);
    backup_once($trackingFile);

    file_put_contents($helperFile, $helper, LOCK_EX);
    file_put_contents($trackingFile, $tracking, LOCK_EX);

    header('Content-Type: text/plain; charset=UTF-8');
    echo "OK\n";
    echo "Resumen comercial + notas operativas aplicado.\n";
    echo "Incluye: pago, entrega, lugar, notas para cliente, condiciones comerciales y notas operativas.\n";
    echo "No se modificó la base de datos.\n";
    echo "Ahora prueba /seguimiento.php?t=...\n";
    echo "IMPORTANTE: elimina este archivo temporal después de verificar.\n";
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "ERROR: " . $e->getMessage() . "\n";
}
