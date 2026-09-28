<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/seguimiento.php';
require_once __DIR__ . '/includes/company.php';
require_once __DIR__ . '/includes/cotizacion_pdf_renderer.php';

$token = trim((string)($_GET['t'] ?? ''));
if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
    http_response_code(404);
    exit('Enlace no válido.');
}

$order = tracking_order_by_token($token);
if (!$order) {
    http_response_code(404);
    exit('El seguimiento ya no está disponible.');
}

$orderId = (int)$order['id'];
$items = tracking_order_items($orderId);
$finance = tracking_payment_summary($orderId);
$company = company_profile();
$pdf = new SimplePdf();

$navy=[0.035,0.095,0.20]; $blue=[0.035,0.38,0.72]; $red=[0.91,0.08,0.24];
$green=[0.06,0.55,0.30]; $slate=[0.32,0.38,0.46]; $muted=[0.50,0.55,0.62];
$white=[1,1,1]; $paper=[0.985,0.988,0.992]; $light=[0.955,0.97,0.985];
$blueBg=[0.93,0.965,1.00]; $greenBg=[0.92,0.975,0.94]; $redBg=[1.00,0.945,0.955];
$border=[0.82,0.85,0.90];

$brand=trim((string)($company['trade_name']?:$company['legal_name']?:'Colibrí Print'));
$legal=trim((string)($company['legal_name']??''));
$contact=implode(' · ',array_filter([
    trim((string)($company['phone']??'')),
    trim((string)($company['email']??'')),
    trim((string)($company['website']??''))
]));
$orderNumber=(string)$order['order_number'];
$customerName=trim((string)($order['customer_name']??'')) ?: 'Cliente';
$total=(float)$finance['order_total']; $paid=(float)$finance['paid_total']; $balance=(float)$finance['balance'];
$currentClient=tracking_internal_to_client_stage(tracking_current_stage($orderId));
$stages=tracking_client_stages();
$currentLabel=(string)($stages[$currentClient]['label']??'En proceso');
$paymentInfo=trim((string)($company['payment_info']??''));

$labelValue=function(SimplePdf $p,string $label,string $value,float $x,float $y,array $lc,array $vc,float $size=10):void{
    $p->setFill(...$lc); $p->textAt($label,7.0,$x,$y,true);
    $p->setFill(...$vc); $p->textAt($value,$size,$x,$y-15,true);
};

$left=44; $right=551; $width=507;

/* ENCABEZADO */
$pdf->setFill(...$navy); $pdf->fillRect($left,752,$width,52);
$pdf->setFill(...$red); $pdf->fillRect($left,752,7,52);
$pdf->setFill(...$white);
$pdf->textAt($brand,18,60,783,true);
$pdf->textAt(($legal!==''&&$legal!==$brand)?$legal:'Diseño · Impresión · Publicidad',7.7,60,768,false);
if($contact!=='') $pdf->textAt($contact,6.8,60,755,false);
$pdf->textAt('RECIBO',13,405,783,true);
$pdf->textAt('COMERCIAL',8.0,405,768,true);
$pdf->textAt($orderNumber,8.0,405,755,false);

/* IDENTIFICACIÓN */
$y=724;
$labelValue($pdf,'ORDEN',$orderNumber,58,$y,$slate,$navy,11.5);
$labelValue($pdf,'CLIENTE',$customerName,215,$y,$slate,$navy,10.0);
$labelValue($pdf,'FECHA',date('d/m/Y'),430,$y,$slate,$navy,10.0);
$pdf->setStroke(...$border); $pdf->line($left,695,$right,695);

/* SERVICIOS */
$y=676; $pdf->setFill(...$navy); $pdf->textAt('SERVICIOS CONTRATADOS',9.0,$left,$y,true);
$pdf->setFill(...$muted); $pdf->textAt('CANT.',7.0,412,$y,true); $pdf->textAt('IMPORTE',7.0,492,$y,true);
$y-=13; $pdf->setStroke(...$border); $pdf->line($left,$y,$right,$y); $y-=14;

foreach($items as $idx=>$item){
    $description=trim((string)($item['description']??'')) ?: 'Servicio';
    $lines=pdf_wrap($description,63);
    if(count($lines)>3){ $lines=array_slice($lines,0,3); $lines[2]=rtrim($lines[2],'.').'...'; }
    $rowH=max(22,count($lines)*10+8);
    if($idx%2===0){$pdf->setFill(...$paper);$pdf->fillRect($left,$y-$rowH+5,$width,$rowH);}
    $pdf->setFill(...$blue); $pdf->textAt(str_pad((string)($idx+1),2,'0',STR_PAD_LEFT),7.0,48,$y,true);
    $pdf->setFill(...$navy); $lineY=$y;
    foreach($lines as $line){$pdf->textAt($line,7.8,70,$lineY,false);$lineY-=10;}
    $pdf->setFill(...$slate); $pdf->textAt(number_format((float)$item['quantity'],3,'.',''),7.8,412,$y,false);
    $pdf->setFill(...$navy); $pdf->textAt(pdf_money((float)$item['subtotal']),8.0,492,$y,true);
    $pdf->setStroke(...$border); $pdf->line($left,$y-$rowH+5,$right,$y-$rowH+5); $y-=$rowH;
}
if(!$items){$pdf->setFill(...$muted);$pdf->textAt('No hay conceptos registrados.',8.0,70,$y,false);$y-=24;}

/* RESUMEN */
$y-=14; $pdf->setFill(...$navy); $pdf->textAt('RESUMEN DE PAGO',9.0,$left,$y,true);
$y-=13; $pdf->setStroke(...$border); $pdf->line($left,$y,$right,$y); $y-=16;
$cols=[['TOTAL DEL SERVICIO',pdf_money($total),58],['PAGADO',pdf_money($paid),235],['SALDO',pdf_money($balance),405]];
foreach($cols as [$lab,$val,$x]){
    $pdf->setFill(...$muted);$pdf->textAt($lab,6.9,$x,$y,true);
    if($lab==='SALDO') $pdf->setFill($balance>0?$red[0]:$green[0],$balance>0?$red[1]:$green[1],$balance>0?$red[2]:$green[2]); else $pdf->setFill(...$navy);
    $pdf->textAt($val,12,$x,$y-18,true);
}
$y-=39;
if($balance>0){$pdf->setFill(...$redBg);$pdf->fillRect($left,$y-22,$width,22);$pdf->setFill(...$red);$pdf->textAt('SALDO PENDIENTE DE LIQUIDAR',8.0,58,$y-14,true);}
else{$pdf->setFill(...$greenBg);$pdf->fillRect($left,$y-22,$width,22);$pdf->setFill(...$green);$pdf->textAt('[OK] ORDEN LIQUIDADA',8.0,58,$y-14,true);}
$y-=37;

/* PAGO */
$pdf->setFill(...$navy);$pdf->textAt('PAGO REGISTRADO',9.0,$left,$y,true);$y-=13;$pdf->setStroke(...$border);$pdf->line($left,$y,$right,$y);$y-=14;
if($finance['first_payment']){
    $fp=$finance['first_payment']; $paymentDate='';
    if(!empty($fp['payment_date'])){$ts=strtotime((string)$fp['payment_date']);if($ts!==false)$paymentDate=date('d/m/Y',$ts);}
    $method=trim((string)($fp['method_label']??'')); $amount=pdf_money((float)$fp['amount']);
    $pdf->setFill(...$greenBg);$pdf->fillRect($left,$y-32,$width,32);
    $pdf->setFill(...$green);$pdf->textAt('CONFIRMADO',7.0,58,$y-13,true);
    $pdf->setFill(...$navy);$pdf->textAt($amount,10,150,$y-13,true);
    $pdf->setFill(...$slate);$pdf->textAt($method!==''?$method:'Pago registrado',7.7,250,$y-13,false);
    if($paymentDate!=='')$pdf->textAt($paymentDate,7.7,430,$y-13,false);
    $y-=47;
}else{
    $pdf->setFill(...$redBg);$pdf->fillRect($left,$y-30,$width,30);$pdf->setFill(...$red);
    $pdf->textAt('SIN PAGO CONFIRMADO',7.7,58,$y-13,true);$pdf->setFill(...$slate);
    $pdf->textAt('El pago aparecerá aquí cuando Administración lo confirme.',7.5,205,$y-13,false);$y-=45;
}

/* ESTADO */
$pdf->setFill(...$navy);$pdf->textAt('ESTADO DEL PEDIDO',9.0,$left,$y,true);$y-=13;$pdf->setStroke(...$border);$pdf->line($left,$y,$right,$y);$y-=14;
$pdf->setFill(...$blueBg);$pdf->fillRect($left,$y-31,$width,31);$pdf->setFill(...$blue);$pdf->textAt('●',10,58,$y-14,true);
$pdf->setFill(...$navy);$pdf->textAt($currentLabel,10.5,75,$y-14,true);
if(!empty($order['due_date'])){$pdf->setFill(...$slate);$pdf->textAt('Fecha compromiso: '.date('d/m/Y',strtotime((string)$order['due_date'])),7.7,345,$y-14,false);}
$y-=47;

/* DATOS BANCARIOS */
if($paymentInfo!==''){
    $pdf->setFill(...$navy);$pdf->textAt('DATOS PARA PAGO',9.0,$left,$y,true);$y-=13;$pdf->setStroke(...$border);$pdf->line($left,$y,$right,$y);$y-=13;
    $bankLines=array_values(array_filter(array_map('trim',preg_split('/\R/u',$paymentInfo)),static fn($v)=>$v!==''));
    $bankTop=$y; $bankHeight=min(82,max(50,count($bankLines)*10+18));
    $pdf->setFill(...$light);$pdf->fillRect($left,$bankTop-$bankHeight,330,$bankHeight);
    $lineY=$bankTop-15;
    foreach($bankLines as $line){foreach(pdf_wrap($line,55) as $wrapped){$pdf->setFill(...$navy);$pdf->textAt($wrapped,7.5,58,$lineY,false);$lineY-=10;}if($lineY<$bankTop-$bankHeight+12)break;}
    $pdf->setFill(...$navy);$pdf->fillRect(389,$bankTop-$bankHeight,162,$bankHeight);
    $pdf->setFill(...$white);$pdf->textAt('REFERENCIA',7.0,403,$bankTop-17,true);$pdf->textAt('DE PAGO',7.0,403,$bankTop-28,true);$pdf->textAt($orderNumber,10.5,403,$bankTop-50,true);
    $y=$bankTop-$bankHeight-15;
}

/* CONDICIONES */
$conditions=array_values(array_filter([
    !empty($order['payment_terms'])?'Pago: '.(string)$order['payment_terms']:'',
    !empty($order['delivery_time'])?'Entrega: '.(string)$order['delivery_time']:'',
    !empty($order['delivery_place'])?'Lugar: '.(string)$order['delivery_place']:''
],static fn($v)=>trim((string)$v)!==''));
if($conditions){$pdf->setFill(...$navy);$pdf->textAt('ENTREGA Y CONDICIONES',8.5,$left,$y,true);$y-=13;foreach($conditions as $condition){$pdf->setFill(...$slate);$pdf->textAt('·',8,58,$y,true);$pdf->textAt((string)$condition,7.3,68,$y,false);$y-=10;}}

/* FOOTER */
$footerY=55;$pdf->setStroke(...$border);$pdf->line($left,$footerY,$right,$footerY);$pdf->setFill(...$muted);
$pdf->textAt('Constancia comercial previa · No sustituye un comprobante fiscal digital (CFDI).',6.5,$left,42,false);
$pdf->textAt($brand.' · '.$orderNumber,6.5,$left,30,false);

$data=$pdf->finish();
header('Content-Type: application/pdf');
header('Content-Length: '.strlen($data));
header('Content-Disposition: attachment; filename="Recibo-comercial-'.$orderNumber.'.pdf"');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
echo $data;
