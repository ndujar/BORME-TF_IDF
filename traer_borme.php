<?php

$boe_url = 'http://boe.es';

//Incluya la carpeta de destino de sus sistema
$destino_local_raiz = '/home/rr/Desktop/ML/BORME/';
$destino_local = $destino_local_raiz.'/borme/dias';

$boe_api_sumario = $boe_url.'/diario_borme/xml.php?id=BORME-S-';

//Establecemos la zona horaria para el cálculo con las fechas
date_default_timezone_set('Europe/Madrid');
$hoy = date('Ymd');

//Leemos los argumentos (fecha_desde y fecha_hasta)
if(isset($argv[1])) {
    $desde = $argv[1];
    if(isset($argv[2])) {
        $hasta = $argv[2];
    } else {
        $hasta = $hoy;
    }
} else {
    $desde = $hoy;
    $hasta = $hoy;
}

$diff1Dia = new DateInterval('P1D');

$fecha = new DateTime();
$fecha->setDate(substr($desde,0,4),substr($desde,4,2),substr($desde,6,2));
$fecha_Ymd = $fecha->format('Ymd');
while($fecha_Ymd <= $hasta) {
    echo 'Fecha: '.$fecha_Ymd, PHP_EOL;
    $fecha_anno = substr($fecha_Ymd,0,4);
    $fecha_mes  = substr($fecha_Ymd,4,2);
    $fecha_dia  = substr($fecha_Ymd,6,2);

    //Creamos las carpetas necesarias en nuestro sistema
    if(!file_exists($destino_local.'/'.$fecha_anno.'/'.$fecha_mes.'/'.$fecha_dia)) { 
        if (!mkdir($destino_local.'/'.$fecha_anno.'/'.$fecha_mes.'/'.$fecha_dia, 0777, true)) {
            die('Error creando carpetas '.$destino_local.'/'.$fecha_anno.'/'.$fecha_mes.'/'.$fecha_dia);
        }
    }
    if(!file_exists($destino_local.'/'.$fecha_anno.'/'.$fecha_mes.'/'.$fecha_dia.'/pdfs')) { 
        if (!mkdir($destino_local.'/'.$fecha_anno.'/'.$fecha_mes.'/'.$fecha_dia.'/pdfs', 0777, true)) {
            die('Error creando carpetas '.$destino_local.'/'.$fecha_anno.'/'.$fecha_mes.'/'.$fecha_dia.'/pdfs');
        }
   }

    $fichero_sumario_xml = $destino_local.'/'.$fecha_anno.'/'.$fecha_mes.'/'.$fecha_dia.'/index.xml';

    //Si existe lo borramos
    if(file_exists($fichero_sumario_xml)) unlink($fichero_sumario_xml);

    echo 'Solicitando '.$boe_api_sumario.$fecha_Ymd.' --> '.$fichero_sumario_xml, PHP_EOL;
    traer_documento($boe_api_sumario.$fecha_Ymd, $fichero_sumario_xml);

    $tamano_sumario_xml = filesize($fichero_sumario_xml);
    echo 'Recibidos: '.$tamano_sumario_xml.' bytes', PHP_EOL;

    if($tamano_sumario_xml < 10) 
        die('ERROR: Sumario XML erroneo o incompleto');

    $xmlSumario = new DOMDocument();
    if(!$xmlSumario->load($fichero_sumario_xml))
        die('ERROR: Sumario XML no pudo ser procesado'."\n");

    if($xmlSumario->documentElement->nodeName == 'error') {
        unlink($fichero_sumario_xml); 
        rmdir($destino_local.'/'.$fecha_anno.'/'.$fecha_mes.'/'.$fecha_dia.'/pdfs');
        rmdir($destino_local.'/'.$fecha_anno.'/'.$fecha_mes.'/'.$fecha_dia);
        echo 'AVISO: No existen boletines para la fecha '.$fecha_Ymd."\n";
    } else {
        $pdfs = $xmlSumario->getElementsByTagName('urlPdf');    
        foreach($pdfs as $pdf) {
            $fichero_pdf = $destino_local_raiz;
            $fichero_pdf_tamano_xml = $pdf->getAttribute('szBytes');
            //Si ya existe el PDF y el tamanno coincide pasamos al siguiente
            if(file_exists($fichero_pdf)) {
               if (filesize($fichero_pdf) == $fichero_pdf_tamano_xml) continue;
               else unlink($fichero_pdf);
            }
            echo 'Solicitando '.$boe_url.$pdf->nodeValue.' --> '.$fichero_pdf, PHP_EOL;
            $intentos = 0;
            $max_intentos = 5;
            do {
                if($intentos != 0) {
                    sleep(5);
                    echo "Intento $intentos\n";
                }
                traer_documento($boe_url.$pdf->nodeValue, $fichero_pdf);
                $intentos++;
            } while ($fichero_pdf_tamano_xml != filesize($fichero_pdf) and $intentos < $max_intentos);
            if($fichero_pdf_tamano_xml != filesize($fichero_pdf)) {
                die('ERROR: El tamano del fichero PDF descargado no coincide con el del XML del Sumario (Descargado: '.filesize($fichero_pdf).' <> XML: '. $fichero_pdf_tamano_xml . ')');
            }
        }
    }

    //Dia siguiente
    $fecha->add($diff1Dia);
    $fecha_Ymd = $fecha->format(Ymd);
}

function traer_documento($origen, $destino) {
    $fp = fopen($destino, 'w');
    $max_intentos = 5;
    $intentos = 0;
    do {
        $ch = curl_init($origen);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        $intentos++;
    } while ($errno > 0 && $intentos < $max_intentos);
    fclose($fp);
}
?>