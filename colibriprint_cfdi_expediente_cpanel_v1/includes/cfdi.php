<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/runtime.php';
require_once __DIR__ . '/finanzas.php';

function cfdi_storage_dir(): string {
    $dir = dirname(__DIR__) . '/storage/cfdi';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    return $dir;
}
function cfdi_tables_ready(): bool {
    try { return (bool)db()->query("SHOW TABLES LIKE 'cp_invoice_documents'")->fetchColumn(); } catch(Throwable $e){ return false; }
}
function cfdi_invoice_document_list(int $limit=150): array {
    if(!cfdi_tables_ready()) return [];
    $limit=max(1,min(500,$limit));
    $st=db()->query("SELECT d.*,i.invoice_number,o.order_number FROM cp_invoice_documents d INNER JOIN cp_invoices i ON i.id=d.invoice_id INNER JOIN cp_orders o ON o.id=d.order_id ORDER BY d.id DESC LIMIT {$limit}");
    return $st->fetchAll() ?: [];
}
function cfdi_invoice_document_latest(int $invoiceId): ?array {
    if(!cfdi_tables_ready()||$invoiceId<=0) return null;
    $st=db()->prepare('SELECT d.*,i.invoice_number,o.order_number FROM cp_invoice_documents d INNER JOIN cp_invoices i ON i.id=d.invoice_id INNER JOIN cp_orders o ON o.id=d.order_id WHERE d.invoice_id=? ORDER BY d.id DESC LIMIT 1');
    $st->execute([$invoiceId]); $row=$st->fetch(); return $row?:null;
}
function cfdi_document_get(int $id): ?array {
    if(!cfdi_tables_ready()||$id<=0) return null;
    $st=db()->prepare('SELECT d.*,i.invoice_number,i.invoice_date,i.status AS invoice_status,o.order_number,c.name AS customer_name FROM cp_invoice_documents d INNER JOIN cp_invoices i ON i.id=d.invoice_id INNER JOIN cp_orders o ON o.id=d.order_id LEFT JOIN cp_customers c ON c.id=i.customer_id WHERE d.id=? LIMIT 1');
    $st->execute([$id]); $row=$st->fetch(); return $row?:null;
}
function cfdi_xpath(SimpleXMLElement $xml,string $expr): ?SimpleXMLElement { $r=$xml->xpath($expr); return ($r && isset($r[0]))?$r[0]:null; }
function cfdi_attr(?SimpleXMLElement $node,string $name,string $default=''): string { if(!$node)return $default; $a=$node->attributes(); return isset($a[$name])?(string)$a[$name]:$default; }
function cfdi_import_document(int $invoiceId,array $xmlUpload,?array $pdfUpload=null): array {
    if(!cfdi_tables_ready()) throw new RuntimeException('La tabla de documentos CFDI no está instalada. Ejecuta la migración 013.');
    $invoice=finance_invoice_get($invoiceId); if(!$invoice) throw new RuntimeException('La factura administrativa no existe.');
    $err=(int)($xmlUpload['error']??UPLOAD_ERR_NO_FILE); if($err!==UPLOAD_ERR_OK) throw new RuntimeException('El XML no pudo recibirse.');
    $size=(int)($xmlUpload['size']??0); if($size<=0||$size>5*1024*1024) throw new RuntimeException('El XML debe pesar entre 1 byte y 5 MB.');
    $raw=file_get_contents((string)$xmlUpload['tmp_name']); if($raw===false||trim($raw)==='') throw new RuntimeException('El XML está vacío.');
    if(substr(ltrim($raw),0,5)==='<?xml' || str_contains(ltrim($raw),'<cfdi:Comprobante') || str_contains(ltrim($raw),'<Comprobante')){}
    libxml_use_internal_errors(true); $xml=simplexml_load_string($raw,'SimpleXMLElement',LIBXML_NONET|LIBXML_NOBLANKS); if($xml===false){libxml_clear_errors();throw new RuntimeException('El archivo no contiene XML válido.');}
    $rootName=$xml->getName(); if($rootName!=='Comprobante') throw new RuntimeException('El archivo no parece ser un CFDI de comprobante.');
    $version=cfdi_attr($xml,'Version'); if($version!==''&&$version!=='4.0') throw new RuntimeException('Solo se admite CFDI 4.0 en esta primera integración.');
    $xml->registerXPathNamespace('cfdi','http://www.sat.gob.mx/cfd/4'); $xml->registerXPathNamespace('tfd','http://www.sat.gob.mx/TimbreFiscalDigital');
    $emisor=cfdi_xpath($xml,'/cfdi:Comprobante/cfdi:Emisor'); $receptor=cfdi_xpath($xml,'/cfdi:Comprobante/cfdi:Receptor'); $tfd=cfdi_xpath($xml,'/cfdi:Comprobante/cfdi:Complemento/tfd:TimbreFiscalDigital');
    $uuid=strtoupper(trim(cfdi_attr($tfd,'UUID'))); if($uuid!==''&&!preg_match('/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/',$uuid)) throw new RuntimeException('El UUID del Timbre Fiscal Digital no es válido.');
    $subtotal=(float)cfdi_attr($xml,'SubTotal','0'); $total=(float)cfdi_attr($xml,'Total','0'); $moneda=cfdi_attr($xml,'Moneda','MXN'); $serie=cfdi_attr($xml,'Serie'); $folio=cfdi_attr($xml,'Folio'); $fecha=cfdi_attr($xml,'Fecha');
    $impuestos=cfdi_xpath($xml,'/cfdi:Comprobante/cfdi:Impuestos'); $tax=(float)cfdi_attr($impuestos,'TotalImpuestosTrasladados','0');
    if($total<0||$subtotal<0||$tax<0) throw new RuntimeException('Los importes fiscales no son válidos.');
    $sha=hash('sha256',$raw); $uid=(int)(current_user()['id']??0);
    $year=date('Y'); $dir=cfdi_storage_dir().'/'.$year; if(!is_dir($dir)&&!@mkdir($dir,0750,true)) throw new RuntimeException('No se pudo crear el almacenamiento CFDI.');
    $base=($uuid?:'cfdi_'.date('Ymd_His').'_'.$invoiceId); $base=preg_replace('/[^A-Za-z0-9._-]+/','_', $base);
    $xmlPath=$dir.'/'.$base.'.xml'; if(file_put_contents($xmlPath,$raw,LOCK_EX)===false) throw new RuntimeException('No se pudo guardar el XML.');
    $pdfPath=null; if($pdfUpload){$ps=(int)($pdfUpload['size']??0); if($ps<=0||$ps>15*1024*1024) throw new RuntimeException('El PDF debe pesar como máximo 15 MB.'); $fh=fopen((string)$pdfUpload['tmp_name'],'rb'); $head=$fh?fread($fh,5):''; if($fh)fclose($fh); if($head!=='%PDF-') throw new RuntimeException('El archivo adjunto no parece ser un PDF.');}
    $issuedAt=null; if($fecha!==''){try{$issuedAt=(new DateTime($fecha))->format('Y-m-d H:i:s');}catch(Throwable $e){throw new RuntimeException('La fecha fiscal del CFDI no es válida.');}}
    $relativeXml=str_replace(dirname(__DIR__).'/','',$xmlPath); $relativePdf=$pdfPath?str_replace(dirname(__DIR__).'/','',$pdfPath):null;
    $db=db(); $db->beginTransaction();
    try {
        if($uuid){$st=$db->prepare('SELECT id FROM cp_invoice_documents WHERE uuid=? LIMIT 1');$st->execute([$uuid]);if($existing=$st->fetchColumn()){throw new RuntimeException('Ese UUID ya está registrado en el expediente CFDI #'.(int)$existing.'.');}}
        $pdfPath=null; if($pdfUpload){$pdfPath=$dir.'/'.$base.'.pdf'; if(!move_uploaded_file((string)$pdfUpload['tmp_name'],$pdfPath)) throw new RuntimeException('No se pudo guardar el PDF.'); $relativePdf=str_replace(dirname(__DIR__).'/','',$pdfPath);}
        $st=$db->prepare('INSERT INTO cp_invoice_documents(invoice_id,order_id,uuid,version,serie,folio,issued_at,moneda,emisor_rfc,emisor_nombre,receptor_rfc,receptor_nombre,subtotal,tax,total,forma_pago,metodo_pago,certificado_sat,no_certificado_sat,sello_cfdi,sello_sat,xml_path,pdf_path,xml_sha256,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
        $forma=cfdi_attr($xml,'FormaPago'); $metodo=cfdi_attr($xml,'MetodoPago'); $cert=cfdi_attr($tfd,'NoCertificadoSAT'); $noCert=cfdi_attr($xml,'NoCertificado'); $sello=cfdi_attr($xml,'Sello'); $selloSat=cfdi_attr($tfd,'SelloSAT');
        $st->execute([$invoiceId,(int)$invoice['order_id'],$uuid,$version?:'4.0',$serie?:null,$folio?:null,$issuedAt,$moneda?:null,cfdi_attr($emisor,'Rfc')?:null,cfdi_attr($emisor,'Nombre')?:null,cfdi_attr($receptor,'Rfc')?:null,cfdi_attr($receptor,'Nombre')?:null,$subtotal,$tax,$total,$forma?:null,$metodo?:null,$cert?:null,$noCert?:null,$sello?:null,$selloSat?:null,$relativeXml,$relativePdf,$sha,$uid?:null]);
        $docId=(int)$db->lastInsertId();
        $items=$xml->xpath('/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto')?:[]; $itemSt=$db->prepare('INSERT INTO cp_invoice_items(document_id,clave_prod_serv,no_identificacion,cantidad,clave_unidad,unidad,descripcion,valor_unitario,importe,objeto_imp,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW())');
        foreach($items as $item){$itemSt->execute([$docId,cfdi_attr($item,'ClaveProdServ')?:null,cfdi_attr($item,'NoIdentificacion')?:null,(float)cfdi_attr($item,'Cantidad','0'),cfdi_attr($item,'ClaveUnidad')?:null,cfdi_attr($item,'Unidad')?:null,trim((string)($item['Descripcion']??''))?:null,(float)cfdi_attr($item,'ValorUnitario','0'),(float)cfdi_attr($item,'Importe','0'),cfdi_attr($item,'ObjetoImp')?:null]);}
        $newNumber=trim((string)$invoice['invoice_number']); $newDate=substr($fecha,0,10); if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$newDate))$newDate=(string)$invoice['invoice_date'];
        $up=$db->prepare('UPDATE cp_invoices SET invoice_date=?,subtotal=?,tax=?,total=?,status=?,cfdi_uuid=?,updated_by=?,updated_at=NOW() WHERE id=?'); $up->execute([$newDate,$subtotal,$tax,$total,'issued',$uuid?:null,$uid?:null,$invoiceId]);
        log_activity('update','invoices','CFDI importado para factura #'.$invoiceId.($uuid?' · UUID '.$uuid:''));
        $db->commit();
        return ['id'=>$docId,'invoice_id'=>$invoiceId,'order_id'=>(int)$invoice['order_id'],'uuid'=>$uuid,'xml_path'=>$relativeXml,'pdf_path'=>$relativePdf];
    } catch(Throwable $e){$db->rollBack();@unlink($xmlPath);if($pdfPath)@unlink($pdfPath);throw $e;}
}
function cfdi_stream_file(array $doc,string $kind): never {
    $key=$kind==='pdf'?'pdf_path':'xml_path'; $path=(string)($doc[$key]??''); if($path==='') {http_response_code(404);exit('Documento no disponible.');}
    $full=dirname(__DIR__).'/'.ltrim($path,'/'); if(!is_file($full)){http_response_code(404);exit('Archivo no encontrado.');}
    $mime=$kind==='pdf'?'application/pdf':'application/xml'; $name=basename($full); header('Content-Type: '.$mime); header('Content-Length: '.(string)filesize($full)); header('Content-Disposition: inline; filename="'.preg_replace('/[^A-Za-z0-9._-]+/','_', $name).'"'); readfile($full); exit;
}
