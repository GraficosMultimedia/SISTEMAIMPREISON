<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/runtime.php';
require_once __DIR__ . '/../includes/cotizaciones.php';
require_once __DIR__ . '/../includes/company.php';

require_auth();

$id = (int)($_GET['id'] ?? 0);
$quote = quote_get($id);
if (!$quote) {
    http_response_code(404);
    exit('Cotización no encontrada.');
}

$company = company_profile();
$items = quote_items($id);
$totals = quote_totals($id);

function cp_pdf_escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function cp_pdf_advance_percent(array $quote): ?float {
    $terms = trim((string)($quote['payment_terms'] ?? ''));
    if ($terms !== '' && preg_match('/(\d+(?:[\.,]\d+)?)\s*%/', $terms, $m)) {
        $pct = (float)str_replace(',', '.', $m[1]);
        if ($pct >= 0 && $pct <= 100) return $pct;
    }
    return null;
}

$total = (float)$totals['total'];
$advancePct = cp_pdf_advance_percent($quote);
$advance = $advancePct !== null ? round($total * ($advancePct / 100), 2) : null;
$balance = $advance !== null ? round($total - $advance, 2) : null;
$money = static fn(float $n): string => '$' . number_format($n, 2, '.', ',');
$paymentInfo = trim((string)($company['payment_info'] ?? ''));
$logo = trim((string)($company['logo_path'] ?? ''));
$brand = (string)($company['trade_name'] ?: $company['legal_name']);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cotización <?=cp_pdf_escape((string)$quote['quote_number'])?></title>
<style>
@page{size:A4;margin:12mm 14mm}
*{box-sizing:border-box}html,body{margin:0;padding:0;background:#e9eef3;color:#17212b;font-family:Arial,Helvetica,sans-serif;font-size:10.5pt;line-height:1.4}
.sheet{width:210mm;min-height:297mm;margin:0 auto 12mm;padding:12mm 14mm;background:#fff;position:relative;page-break-after:always;box-shadow:0 10px 30px rgba(10,30,50,.12)}
.sheet:last-child{page-break-after:auto}.accent{height:3px;background:#ec0b63;margin:-12mm -14mm 7mm}.accent.teal{background:#16b7a4}
.header{display:flex;justify-content:space-between;gap:20px;align-items:flex-start}.brand{display:flex;align-items:center;gap:12px}.brand img{width:43mm;max-height:22mm;object-fit:contain}.brand-name strong{display:block;font-size:16pt;color:#0b2239}.brand-name small{color:#667085}.quote-title{text-align:right}.eyebrow{font-size:8pt;letter-spacing:.14em;color:#667085;font-weight:800}.quote-title h1{margin:3px 0;font-size:25pt;color:#0b2239}.quote-title p{margin:0;color:#667085;font-size:8.5pt}
.company-grid{display:grid;grid-template-columns:40mm 78mm 62mm;margin-top:6mm;background:#f4f7fa;border:1px solid #d9e1e8}.company-grid>div{padding:4mm;border-right:1px solid #d9e1e8}.company-grid>div:last-child{border-right:0}.label{font-size:7.5pt;color:#667085;text-transform:uppercase;letter-spacing:.08em;font-weight:800}.value{margin-top:2px;font-size:9pt}.box{border:1px solid #d9e1e8;margin-top:5mm;padding:4mm}.box-title{font-size:7.5pt;letter-spacing:.08em;color:#16b7a4;font-weight:800;text-transform:uppercase}.client-name{font-size:14pt;font-weight:800;margin-top:2px;color:#0b2239}.client-grid{display:grid;grid-template-columns:1fr 1fr;gap:4px 18px;margin-top:7px;color:#44515e;font-size:8.5pt}.reference-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;border-top:1px solid #d9e1e8;border-bottom:1px solid #d9e1e8;padding:4mm 0;margin-top:4mm}.reference-grid strong{display:block;font-size:8.5pt;margin-top:2px;color:#17212b}
table{width:100%;border-collapse:collapse;margin-top:6mm}th{background:#0b2239;color:#fff;text-align:left;padding:8px 7px;font-size:8pt}td{border-bottom:1px solid #d9e1e8;padding:8px 7px;vertical-align:top;font-size:8.8pt}th:not(:first-child),td:not(:first-child){text-align:right}.totals{width:48%;margin-left:auto;margin-top:4mm}.totals div{display:flex;justify-content:space-between;padding:5px 0}.totals .grand{border-top:2px solid #16b7a4;margin-top:4px;padding-top:9px;font-size:15pt;font-weight:800;color:#0b2239}.section{border-top:1px solid #d9e1e8;margin-top:6mm;padding-top:4mm}.section h3{margin:0 0 5px;font-size:10pt;color:#0b2239}.section p{margin:0;color:#596675;font-size:8.7pt}.footer{position:absolute;left:14mm;right:14mm;bottom:7mm;border-top:1px solid #16b7a4;padding-top:4px;color:#667085;font-size:7pt;display:flex;justify-content:space-between}
.page2-title{margin-top:7mm}.page2-title h1{font-size:24pt;color:#0b2239;margin:0}.page2-title p{margin:4px 0;color:#667085}.payment-summary{margin-top:9mm;border:1px solid #d9e1e8}.payment-summary .row{display:flex;justify-content:space-between;padding:12px 14px;border-bottom:1px solid #d9e1e8}.payment-summary .head{background:#0b2239;color:#fff;font-weight:800;font-size:8pt;letter-spacing:.08em}.payment-summary .advance{background:#e8fbf8}.payment-summary .advance strong:last-child{font-size:20pt;color:#0b2239}.payment-box{margin-top:9mm;border:2px solid #16b7a4;background:#f4f7fa;padding:7mm}.payment-box h2{margin:0 0 6px;color:#0b2239;font-size:14pt}.payment-box p{margin:0;color:#44515e;font-size:9pt}.bank{margin-top:9mm}.bank-grid{display:grid;grid-template-columns:1fr 1fr;border:1px solid #d9e1e8;background:#f4f7fa}.bank-grid div{padding:5mm;border-right:1px solid #d9e1e8;border-bottom:1px solid #d9e1e8}.bank-grid div:nth-child(2n){border-right:0}.bank-grid div:nth-last-child(-n+2){border-bottom:0}.bank-grid strong{display:block;margin-top:3px;color:#17212b}.notice{margin-top:9mm;padding:5mm;border:1px solid #d9e1e8;background:#fff;color:#596675;font-size:8.7pt}.closing{margin-top:9mm;background:#0b2239;color:#fff;padding:5mm;display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}.closing span{display:block;font-size:7pt;opacity:.75;text-transform:uppercase;letter-spacing:.08em}.closing strong{display:block;margin-top:2px;font-size:11pt}
@media screen and (max-width:900px){body{background:#fff}.sheet{width:100%;min-height:auto;margin:0 0 12px;box-shadow:none}.company-grid,.reference-grid,.client-grid,.bank-grid,.closing{grid-template-columns:1fr}.company-grid>div{border-right:0;border-bottom:1px solid #d9e1e8}.bank-grid div:nth-child(2n){border-right:0}.totals{width:100%}}
@media print{html,body{background:#fff}.sheet{width:auto;min-height:0;margin:0;padding:0;box-shadow:none}.accent{margin:0 0 7mm}.footer{position:fixed;bottom:0}.no-print{display:none!important}}
</style>
</head>
<body>
<section class="sheet">
<div class="accent"></div>
<div class="header"><div class="brand"><?php if($logo): ?><img src="<?=cp_pdf_escape($logo)?>" alt="<?=cp_pdf_escape($brand)?>"><?php endif; ?><div class="brand-name"><strong><?=cp_pdf_escape($brand)?></strong><small><?=cp_pdf_escape((string)$company['legal_name'])?></small></div></div><div class="quote-title"><div class="eyebrow">COTIZACIÓN</div><h1><?=cp_pdf_escape((string)$quote['quote_number'])?></h1><p>Fecha: <?=cp_pdf_escape(date('d/m/Y',strtotime((string)$quote['issue_date'])))?><?php if($quote['valid_until']): ?> · Vigencia: <?=cp_pdf_escape(date('d/m/Y',strtotime((string)$quote['valid_until'])))?><?php endif; ?></p></div></div>
<div class="company-grid"><div><div class="label">RFC</div><div class="value"><?=cp_pdf_escape((string)$company['rfc'])?></div></div><div><div class="label">Domicilio</div><div class="value"><?=cp_pdf_escape(trim((string)$company['address'].' '.(string)$company['neighborhood']))?><br><?=cp_pdf_escape(trim((string)$company['city'].' '.(string)$company['state'].' C.P. '.(string)$company['postal_code']))?></div></div><div><div class="label">Contacto</div><div class="value"><?=cp_pdf_escape(trim((string)$company['phone'].' · '.(string)$company['email']))?><br><?=cp_pdf_escape((string)$company['website'])?></div></div></div>
<div class="box"><div class="box-title">Datos del cliente</div><div class="client-name"><?=cp_pdf_escape((string)($quote['customer_name']??'Sin cliente'))?></div><div class="client-grid"><?php if($quote['customer_tax_number']): ?><div><b>RFC:</b> <?=cp_pdf_escape((string)$quote['customer_tax_number'])?></div><?php endif; ?><?php if($quote['customer_email']): ?><div><b>Correo:</b> <?=cp_pdf_escape((string)$quote['customer_email'])?></div><?php endif; ?><?php if($quote['customer_phone']): ?><div><b>Teléfono:</b> <?=cp_pdf_escape((string)$quote['customer_phone'])?></div><?php endif; ?><?php if($quote['customer_address']): ?><div><b>Domicilio:</b> <?=cp_pdf_escape((string)$quote['customer_address'])?></div><?php endif; ?></div></div>
<?php if($quote['client_reference']||$quote['payment_terms']||$quote['delivery_time']||$quote['delivery_place']): ?><div class="reference-grid"><?php foreach([['Referencia / proyecto',$quote['client_reference']],['Condiciones de pago',$quote['payment_terms']],['Tiempo de entrega',$quote['delivery_time']],['Lugar de entrega',$quote['delivery_place']]] as $r): if(trim((string)$r[1])!==''): ?><div><div class="label"><?=cp_pdf_escape((string)$r[0])?></div><strong><?=cp_pdf_escape((string)$r[1])?></strong></div><?php endif; endforeach; ?></div><?php endif; ?>
<table><thead><tr><th>Descripción</th><th>Cant.</th><th>Precio unitario</th><th>Importe</th></tr></thead><tbody><?php foreach($items as $item): ?><tr><td><?=cp_pdf_escape((string)$item['description'])?></td><td><?=cp_pdf_escape((string)$item['quantity'])?></td><td><?=$money((float)$item['unit_price'])?></td><td><?=$money((float)$item['subtotal'])?></td></tr><?php endforeach; ?></tbody></table>
<div class="totals"><div><span>Subtotal</span><strong><?=$money((float)$totals['subtotal'])?></strong></div><div><span>Descuento</span><strong><?=$money((float)$totals['discount'])?></strong></div><div><span>Impuestos</span><strong><?=$money((float)$totals['tax'])?></strong></div><div class="grand"><span>Total</span><strong><?=$money($total)?></strong></div></div>
<?php if($quote['notes']): ?><div class="section"><h3>Notas</h3><p><?=nl2br(cp_pdf_escape((string)$quote['notes']))?></p></div><?php endif; ?>
<div class="footer"><span><?=cp_pdf_escape($brand)?> · <?=cp_pdf_escape((string)$quote['quote_number'])?></span><span>Página 1 de 2</span></div>
</section>
<section class="sheet">
<div class="accent teal"></div>
<div class="page2-title"><div class="eyebrow">RESUMEN FINANCIERO</div><h1>Resumen de pago</h1><p>Información para confirmar el anticipo y el saldo de la cotización <b><?=cp_pdf_escape((string)$quote['quote_number'])?></b>.</p></div>
<div class="payment-summary"><div class="row head"><span>CONCEPTO</span><span>IMPORTE</span></div><div class="row"><span>Total de la cotización</span><strong><?=$money($total)?></strong></div><div class="row advance"><span><b>Pago anticipado<?= $advancePct !== null ? ' · '.rtrim(rtrim(number_format($advancePct,2,'.',''), '0'), '.').'%' : '' ?></b></span><strong><?= $advance !== null ? $money($advance) : 'Por definir' ?></strong></div><div class="row"><span>Saldo pendiente</span><strong><?= $balance !== null ? $money($balance) : 'Por definir' ?></strong></div></div>
<div class="payment-box"><h2>Pago anticipado</h2><?php if($advance !== null): ?><p>Para iniciar la producción se requiere un anticipo de <b><?=$money($advance)?></b><?= $advancePct !== null ? ' ('.rtrim(rtrim(number_format($advancePct,2,'.',''), '0'), '.').'%)' : '' ?>. El saldo restante es de <b><?=$money($balance)?></b>.</p><?php else: ?><p>Las condiciones de pago de esta cotización no especifican un porcentaje de anticipo. El monto deberá confirmarse antes de iniciar la producción.</p><?php endif; ?></div>
<div class="bank"><div class="eyebrow">DATOS PARA REALIZAR EL ANTICIPO</div><?php if($paymentInfo): ?><div class="box" style="margin-top:4mm"><p style="margin:0;white-space:pre-line;color:#44515e;font-size:9pt"><?=cp_pdf_escape($paymentInfo)?></p></div><?php else: ?><div class="box"><p style="margin:0;color:#667085">No se han configurado datos bancarios en el perfil de la empresa.</p></div><?php endif; ?></div>
<?php if($quote['terms']): ?><div class="section"><h3>Condiciones comerciales</h3><p><?=nl2br(cp_pdf_escape((string)$quote['terms']))?></p></div><?php endif; ?>
<div class="closing"><div><span>Cotización</span><strong><?=cp_pdf_escape((string)$quote['quote_number'])?></strong></div><div><span>Total</span><strong><?=$money($total)?></strong></div><div><span>Anticipo</span><strong><?= $advance !== null ? $money($advance) : 'Por definir' ?></strong></div></div>
<div class="footer"><span><?=cp_pdf_escape($brand)?> · <?=cp_pdf_escape((string)$quote['quote_number'])?></span><span>Página 2 de 2</span></div>
</section>
</body></html>
