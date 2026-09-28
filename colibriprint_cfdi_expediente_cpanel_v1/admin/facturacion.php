<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/runtime.php';
require_once __DIR__ . '/../includes/actions.php';
require_once __DIR__ . '/../includes/finanzas.php';
require_once __DIR__ . '/../includes/cfdi.php';
require_auth();

$title = 'Facturación';
$error = null;
$success = null;
$editId = (int)($_GET['edit'] ?? 0);
$selectedOrderId = (int)($_GET['order_id'] ?? 0);
$editing = $editId > 0 ? finance_invoice_get($editId) : null;
if ($editing) $selectedOrderId = (int)$editing['order_id'];

$invoice = [
    'id' => 0,
    'order_id' => $selectedOrderId,
    'invoice_number' => '',
    'invoice_date' => date('Y-m-d'),
    'subtotal' => '',
    'tax' => '0.00',
    'total' => '',
    'status' => 'draft',
    'cfdi_uuid' => '',
    'notes' => '',
];
if (!$editing) $invoice['invoice_number'] = finance_tables_ready() ? finance_invoice_number_next() : 'F-' . date('Y') . '-00001';
if ($editing) {
    foreach ($invoice as $key => $value) if (array_key_exists($key, $editing)) $invoice[$key] = $editing[$key];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if (!csrf_check($_POST['_csrf'] ?? null)) {
        $error = 'La sesión del formulario expiró. Recarga la página.';
    } elseif ($action === 'invoice_save') {
        try {
            $id = (int)($_POST['id'] ?? 0);
            $orderId = (int)($_POST['order_id'] ?? 0);
            $invoiceNumber = trim((string)($_POST['invoice_number'] ?? ''));
            $invoiceDate = trim((string)($_POST['invoice_date'] ?? ''));
            $subtotal = round((float)str_replace(',', '', (string)($_POST['subtotal'] ?? 0)), 2);
            $tax = round((float)str_replace(',', '', (string)($_POST['tax'] ?? 0)), 2);
            $total = round((float)str_replace(',', '', (string)($_POST['total'] ?? 0)), 2);
            $status = (string)($_POST['status'] ?? 'draft');
            $cfdiUuid = trim((string)($_POST['cfdi_uuid'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? ''));
            $order = order_get($orderId);
            if (!$order) throw new RuntimeException('Selecciona una orden válida.');
            if ($invoiceNumber === '' || !preg_match('/^[A-Za-z0-9._\/-]{2,60}$/', $invoiceNumber)) throw new RuntimeException('El folio de factura no es válido.');
            $dt = DateTime::createFromFormat('Y-m-d', $invoiceDate);
            if (!$dt || $dt->format('Y-m-d') !== $invoiceDate) throw new RuntimeException('La fecha de factura no es válida.');
            if ($subtotal < 0 || $tax < 0 || $total < 0) throw new RuntimeException('Los importes no pueden ser negativos.');
            if (!array_key_exists($status, finance_invoice_statuses())) throw new RuntimeException('Estado de factura no válido.');
            if ($cfdiUuid !== '' && strlen($cfdiUuid) > 80) throw new RuntimeException('El UUID fiscal es demasiado largo.');
            $uid = (int)(current_user()['id'] ?? 0);
            $customerId = (int)($order['customer_id'] ?? 0);
            if ($id > 0) {
                $current = finance_invoice_get($id);
                if (!$current) throw new RuntimeException('La factura indicada no existe.');
                db()->prepare('UPDATE cp_invoices SET order_id=?,customer_id=?,invoice_number=?,invoice_date=?,subtotal=?,tax=?,total=?,status=?,cfdi_uuid=?,notes=?,updated_by=?,updated_at=NOW() WHERE id=?')->execute([$orderId,$customerId?:null,$invoiceNumber,$invoiceDate,$subtotal,$tax,$total,$status,$cfdiUuid?:null,$notes?:null,$uid?:null,$id]);
                log_activity('update','invoices','Factura actualizada #'.$id);
            } else {
                db()->prepare('INSERT INTO cp_invoices(order_id,customer_id,invoice_number,invoice_date,subtotal,tax,total,status,cfdi_uuid,notes,created_by,updated_by,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')->execute([$orderId,$customerId?:null,$invoiceNumber,$invoiceDate,$subtotal,$tax,$total,$status,$cfdiUuid?:null,$notes?:null,$uid?:null,$uid?:null]);
                $id=(int)db()->lastInsertId();
                log_activity('create','invoices','Factura registrada #'.$id);
            }
            redirect('/admin/facturacion.php?order_id='.$orderId.'&saved=1');
        } catch (Throwable $e) {
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'No se pudo guardar la factura.';
            foreach ($invoice as $key => $value) if (array_key_exists($key,$_POST)) $invoice[$key]=(string)$_POST[$key];
            $selectedOrderId=(int)($invoice['order_id']??0);
        }
    } elseif ($action === 'cfdi_import') {
        try {
            $invoiceId=(int)($_POST['invoice_id']??0);
            if ($invoiceId<=0) throw new RuntimeException('Selecciona primero la factura administrativa que recibirá el CFDI.');
            if (!isset($_FILES['xml_file']) || (int)$_FILES['xml_file']['error']!==UPLOAD_ERR_OK) throw new RuntimeException('Selecciona un XML CFDI válido.');
            $pdfFile = isset($_FILES['pdf_file']) && (int)$_FILES['pdf_file']['error']===UPLOAD_ERR_OK ? $_FILES['pdf_file'] : null;
            $doc=cfdi_import_document($invoiceId,$_FILES['xml_file'],$pdfFile);
            $success='CFDI importado correctamente. UUID: '.($doc['uuid'] ?: 'no encontrado');
            $selectedOrderId=(int)$doc['order_id'];
            $editing=finance_invoice_get($invoiceId);
            if ($editing) foreach ($invoice as $key=>$value) if(array_key_exists($key,$editing)) $invoice[$key]=$editing[$key];
        } catch(Throwable $e) {
            $error=$e instanceof RuntimeException ? $e->getMessage() : 'No se pudo importar el CFDI.';
        }
    }
}

if (isset($_GET['saved'])) $success='Registro de facturación guardado correctamente.';
$tablesReady=finance_tables_ready();
$orders=$tablesReady?finance_order_options():[];
$invoices=$tablesReady?finance_invoice_list():[];
$orderSelected=$selectedOrderId>0?order_get($selectedOrderId):null;
$summary=$selectedOrderId>0&&$tablesReady?finance_order_summary($selectedOrderId):null;
$invoiceDocuments=$tablesReady?cfdi_invoice_document_list():[];

require __DIR__.'/../includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/finanzas.css?v=20260928-1">
<link rel="stylesheet" href="/assets/css/cfdi-admin.css?v=20260928-1">
<div class="finance-toolbar">
  <div><span class="eyebrow">FASE 10 · FACTURACIÓN</span><h2>Facturación y documentos fiscales</h2><p class="muted">Control interno de facturas y expediente CFDI. El XML es la fuente fiscal; el PDF es su representación visual.</p></div>
  <div class="finance-actions"><a class="btn btn-secondary" href="/admin/pagos.php">💰 Pagos</a></div>
</div>
<?php if (!$tablesReady): ?>
<div class="notice danger">La estructura financiera todavía no está instalada. Ejecuta la migración financiera de Fase 10 antes de usar este módulo.</div>
<?php else: ?>
<div class="notice finance-disclaimer">🧾 El sistema conserva el registro administrativo y puede importar CFDI ya timbrados. El timbrado/PAC queda fuera de esta fase.</div>
<?php if ($error): ?><div class="notice danger"><?=e($error)?></div><?php endif; ?>
<?php if ($success): ?><div class="notice"><span class="ok">✓</span> <?=e($success)?></div><?php endif; ?>

<div class="cfdi-overview">
  <div class="cfdi-stat"><span>Borradores</span><strong><?=count(array_filter($invoices,fn($i)=>$i['status']==='draft'))?></strong></div>
  <div class="cfdi-stat"><span>Emitidas</span><strong><?=count(array_filter($invoices,fn($i)=>$i['status']==='issued'))?></strong></div>
  <div class="cfdi-stat"><span>Con UUID</span><strong><?=count(array_filter($invoiceDocuments,fn($d)=>!empty($d['uuid'])))?></strong></div>
  <div class="cfdi-stat"><span>Documentos</span><strong><?=count($invoiceDocuments)?></strong></div>
</div>

<div class="finance-grid">
<section class="card">
  <div class="section-heading"><div><span class="eyebrow">REGISTRAR</span><h3><?=$editing?'Editar registro':'Nuevo registro'?></h3></div></div>
  <form method="post" class="finance-form">
    <input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="invoice_save"><input type="hidden" name="id" value="<?=((int)$invoice['id'])?>">
    <div class="form-grid">
      <div class="field full"><label>Orden de servicio <span class="required">*</span></label><select name="order_id" id="invoice_order_id" required><option value="">Selecciona una orden…</option><?php foreach($orders as $o): ?><option value="<?=((int)$o['id'])?>" data-total="<?=e((string)$o['total'])?>" <?=((int)$invoice['order_id']===(int)$o['id']?'selected':'')?>><?=e($o['order_number'])?> · <?=e($o['customer_name']?:'Sin cliente')?> · Orden $<?=number_format((float)$o['total'],2,'.',',')?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Folio <span class="required">*</span></label><input name="invoice_number" maxlength="60" value="<?=e((string)$invoice['invoice_number'])?>" required></div>
      <div class="field"><label>Fecha <span class="required">*</span></label><input type="date" name="invoice_date" value="<?=e((string)$invoice['invoice_date'])?>" required></div>
      <div class="field"><label>Subtotal</label><input type="number" name="subtotal" id="invoice_subtotal" min="0" step="0.01" value="<?=e((string)$invoice['subtotal'])?>"></div>
      <div class="field"><label>Impuesto</label><input type="number" name="tax" id="invoice_tax" min="0" step="0.01" value="<?=e((string)$invoice['tax'])?>"></div>
      <div class="field"><label>Total <span class="required">*</span></label><input type="number" name="total" id="invoice_total" min="0" step="0.01" value="<?=e((string)$invoice['total'])?>" required></div>
      <div class="field"><label>Estado</label><select name="status"><?php foreach(finance_invoice_statuses() as $k=>$label): ?><option value="<?=e($k)?>" <?=$invoice['status']===$k?'selected':''?>><?=e($label)?></option><?php endforeach; ?></select></div>
      <div class="field full"><label>UUID / folio fiscal</label><input name="cfdi_uuid" maxlength="80" value="<?=e((string)$invoice['cfdi_uuid'])?>" placeholder="Se actualiza automáticamente al importar el XML."></div>
      <div class="field full"><label>Notas</label><textarea name="notes" rows="4" maxlength="1500" placeholder="Notas administrativas…"><?=e((string)$invoice['notes'])?></textarea></div>
    </div>
    <div class="form-actions"><a class="btn btn-cancel" href="/admin/facturacion.php<?= $selectedOrderId>0?'?order_id='.((int)$selectedOrderId):'' ?>">Limpiar</a><button class="btn btn-save" type="submit">Guardar facturación</button></div>
  </form>
</section>

<section class="card">
  <div class="section-heading"><div><span class="eyebrow">EXPEDIENTE FISCAL</span><h3>Importar CFDI timbrado</h3></div></div>
  <form method="post" enctype="multipart/form-data" class="cfdi-import-form">
    <input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="cfdi_import">
    <div class="field"><label>Factura administrativa <span class="required">*</span></label><select name="invoice_id" required><option value="">Selecciona…</option><?php foreach($invoices as $i): ?><option value="<?=((int)$i['id'])?>" <?=((int)$invoice['id']===(int)$i['id']?'selected':'')?>><?=e($i['invoice_number'])?> · <?=e($i['order_number'])?> · $<?=number_format((float)$i['total'],2,'.',',')?></option><?php endforeach; ?></select></div>
    <div class="field"><label>XML CFDI 4.0 <span class="required">*</span></label><input type="file" name="xml_file" accept=".xml,text/xml,application/xml" required></div>
    <div class="field"><label>PDF del CFDI <span class="optional">opcional</span></label><input type="file" name="pdf_file" accept=".pdf,application/pdf"></div>
    <div class="cfdi-drop-help">El importador valida el comprobante, extrae UUID, emisor, receptor, conceptos, impuestos y totales, y actualiza la factura sin alterar la orden.</div>
    <button class="btn btn-save" type="submit">📥 Importar CFDI</button>
  </form>
  <?php if($invoiceDocuments): ?><div class="cfdi-recent"><strong>Últimos documentos</strong><?php foreach(array_slice($invoiceDocuments,0,5) as $d): ?><div class="cfdi-row"><span><?=e($d['invoice_number'])?> · <?=e($d['uuid']?:'Sin UUID')?></span><span><a class="btn btn-sm btn-secondary" href="/admin/cfdi_documento.php?id=<?=((int)$d['id'])?>">Ver</a></span></div><?php endforeach; ?></div><?php endif; ?>
</section>
</div>

<section class="card">
  <div class="section-heading"><div><span class="eyebrow">ORDEN SELECCIONADA</span><h3><?= $orderSelected ? e($orderSelected['order_number']) : 'Elige una orden' ?></h3></div></div>
  <?php if($orderSelected&&$summary): ?><div class="balance-box"><div><span>Total orden</span><strong>$<?=number_format($summary['order_total'],2,'.',',')?></strong></div><div><span>Pagado</span><strong class="positive">$<?=number_format($summary['paid_total'],2,'.',',')?></strong></div><div><span>Saldo</span><strong class="pending">$<?=number_format($summary['balance'],2,'.',',')?></strong></div></div><p class="help-text">Cliente: <?=e($orderSelected['customer_name']??'Sin cliente')?> · Facturas registradas: <?=((int)$summary['invoice_count'])?></p><?php else: ?><p class="empty">Selecciona una orden para revisar su información financiera.</p><?php endif; ?>
</section>

<section class="card">
  <div class="section-heading"><div><span class="eyebrow">HISTORIAL</span><h3>Facturas y estado fiscal</h3></div></div>
  <div class="table-wrap"><table class="table finance-table"><thead><tr><th>Fecha</th><th>Folio</th><th>Orden</th><th>Cliente</th><th>Total</th><th>Estado</th><th>CFDI</th><th>Acciones</th></tr></thead><tbody>
  <?php if(!$invoices): ?><tr><td colspan="8" class="empty">No hay registros de facturación.</td></tr><?php else: foreach($invoices as $i): $doc=cfdi_invoice_document_latest((int)$i['id']); ?><tr><td><?=e(date('d/m/Y',strtotime((string)$i['invoice_date'])))?></td><td><strong><?=e($i['invoice_number'])?></strong></td><td><a href="/admin/orden.php?id=<?=((int)$i['order_id'])?>"><?=e($i['order_number'])?></a></td><td><?=e($i['customer_name']?:'Sin cliente')?></td><td class="amount-cell">$<?=number_format((float)$i['total'],2,'.',',')?></td><td><span class="finance-badge <?=($i['status']==='issued'?'is-ok':($i['status']==='cancelled'?'is-danger':'is-neutral'))?>"><?=e(finance_invoice_statuses()[$i['status']]??$i['status'])?></span></td><td><?php if($doc): ?><span class="cfdi-pill"><?=e($doc['uuid']?:'CFDI')?></span><?php else: ?><span class="muted">Pendiente</span><?php endif; ?></td><td class="cfdi-actions"><a class="btn btn-sm btn-secondary" href="/admin/facturacion.php?edit=<?=((int)$i['id'])?>">Editar</a><?php if($doc): ?><a class="btn btn-sm btn-secondary" href="/admin/cfdi_documento.php?id=<?=((int)$doc['id'])?>">CFDI</a><?php endif; ?></td></tr><?php endforeach; endif; ?>
  </tbody></table></div>
</section>

<script>
(function(){const order=document.getElementById('invoice_order_id'),subtotal=document.getElementById('invoice_subtotal'),tax=document.getElementById('invoice_tax'),total=document.getElementById('invoice_total');if(!order||!subtotal||!tax||!total)return;order.addEventListener('change',function(){const o=order.options[order.selectedIndex],v=parseFloat(o&&o.dataset.total?o.dataset.total:'0');if(!<?= $editing?'true':'false' ?>&&v>0){subtotal.value=v.toFixed(2);tax.value='0.00';total.value=v.toFixed(2);}});function calc(){const s=parseFloat(subtotal.value||'0')||0,t=parseFloat(tax.value||'0')||0;total.value=(s+t).toFixed(2);}subtotal.addEventListener('input',calc);tax.addEventListener('input',calc);})();
</script>
<?php endif; ?>
<?php require __DIR__.'/../includes/footer.php'; ?>
