<?php
session_start();
header('Content-Type: application/json');
require 'vendor/autoload.php';
require 'config.php';
use Dompdf\Dompdf;
use Dompdf\Options;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$fecha = $_POST['fecha'];
$nombre = $_POST['nombre'];
$tipo_documento = $_POST['tipo_documento'];
$numero_documento = $_POST['numero_documento'];
$correo_destino = $_POST['correo_destino_codigo_firma'];
$idUser = $_POST['idUser'];
$cantidad = (float) str_replace(['.', ','], ['', '.'], $_POST['cantidad']);
$cantidad_texto = convertirNumeroTextoPDF($cantidad);
$cantidad_formateada = number_format($cantidad, 2, ',', '.');
$concepto = $_POST['concepto'];
$codigo_unico = $_POST['codigo_firma'];

$codigo_firma = $_POST['codigo_firma'];
$fecha_manana = date("Y-m-d 00:00:00", strtotime("+1 day"));

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Consulta para verificar el estado del código
$sql = "SELECT usado, fecha_creacion FROM codigos_firma_digital WHERE codigo = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $codigo_firma);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($usado, $fecha_creacion);

if ($stmt->num_rows > 0) {
    $stmt->fetch();
    
    // Si el código ya fue usado, impedir el proceso
    if ($usado == 1) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "El código de firma ya ha sido utilizado. No puedes continuar."]);
        exit;
    }

    // Validar si la fecha de creación ha pasado la fecha de mañana
    if ($fecha_creacion > $fecha_manana) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "El código de firma ha expirado ".$fecha_manana.". No puedes continuar."]);
        exit;
    }

    // Si todo está correcto, actualizar a usado = 1
    $update_sql = "UPDATE codigos_firma_digital SET usado = 1 WHERE codigo = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("s", $codigo_firma);
    $update_stmt->execute();
    $update_stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "El código de firma no existe."]);
    exit;
}

$stmt->close();
$conn->close();

// Generar contenido PDF
$html = "<html>
<head><style>
body { font-family: Arial, sans-serif; margin: 40px; }
.titulo { text-align: center; font-weight: bold; font-size: 18px; }
.subtitulo { text-align: center; font-size: 16px; }
.datos { margin-top: 20px; }
.firma img { max-width: 200px; }
.fecha { text-align: right; font-size: 14px; }
.empresa { text-align: center; font-size: 16px; font-weight: bold; }
.debe-a { text-align: center; font-size: 16px; margin-top: 20px; }
.codigo-firma { text-align: left; margin-top: 40px; padding: 10px; background-color: #f0f0f0; border: 1px solid #ccc; display: inline-block; }
.codigo-firma span { font-weight: bold; color: black; }
.codigo-firma p { font-size: 12px; margin-top: 5px; color: gray; }
</style></head>
<body>
<div class='fecha'>$fecha</div><br>
<div class='titulo'>Cuenta de Cobro</div><br><br>
<div class='empresa'>DESARROLLO AGIL DIGITAL SAS<br>C.C. 901.724.982-3</div>
<div class='debe-a'>
    <p>DEBE A</p>
    <p>$nombre</p>
    <p>$tipo_documento $numero_documento</p>
</div>
<div class='datos'>
<p><strong>La Suma de:</strong> $cantidad_texto pesos ($cantidad_formateada)</p>
<p><strong>Concepto:</strong> $concepto</p>
<p class='declaracion'>
1. Pertenezco al Régimen Simplificado.<br>
2. No Soy responsable del Impuesto a las Ventas.<br>
3. No estoy obligado a expedir factura de venta según el artículo 616-2 del Estatuto Tributario.
</p>
<p class='texto'>Para poder aplicar retención en la fuente establecida en el Art. 383 del E.T, informo que no he contratado o vinculado dos o más trabajadores asociados a mi actividad económica.</p>
</div>
<br>
<br>
<p>$nombre</p>
<p>Firma Digital:</p>
<div class='codigo-firma'>
    <span>$codigo_unico</span>
    <p>Este código garantiza y asegura que fue firmado y aprobado por medio del correo $correo_destino</p>
</div>
</body></html>";

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$pdf_output = $dompdf->output();
$pdf_path = "cuenta_cobro.pdf";
file_put_contents($pdf_path, $pdf_output);



//GENERACION DE GASTOS DOLI
// Configuración de credenciales de la API
$api_url = API_URL;
$api_url_login = $api_url."/login";
// Obtener el token de autenticación
$api_token = API_TOKEN;



$curl = curl_init();
// Variables necesarias
$notePrivate = $concepto . ' ,con el código de firma digital ' . $codigo_unico;

// Fechas dinámicas
$date_today = date("Y-m-d");  // Fecha en formato Y-m-d
$date_today_timestamp = strtotime($date_today);  // Convertir a timestamp
$date_create = time();  // Timestamp actual
$date_fin = strtotime("+1 day", $date_today_timestamp);  // Un día después

// Cálculo de impuestos y totales
$total_ttc = $cantidad;  // Total con impuestos (el monto final)
$total_tva = $total_ttc * 0.19;  // IVA del 19%
$total_ht = $total_ttc - $total_tva;  // Total sin impuestos

// Construcción del JSON
$post_data = [
    "entity" => "1",
    "total_ht" => number_format($total_ht, 8, '.', ''),
    "total_tva" => number_format($total_tva, 8, '.', ''),
    "total_localtax1" => "0.00000000",
    "total_localtax2" => "0.00000000",
    "total_ttc" => number_format($total_ttc, 8, '.', ''),
    "date_debut" => $date_today_timestamp,
    "date_fin" => $date_fin,
    "date_create" => $date_create,
    "date_valid" => null,
    "date_approve" => null,
    "date_refuse" => null,
    "date_cancel" => null,
    "fk_user_author" => $idUser,
    "fk_user_creat" => "1",
    "fk_user_modif" => null,
    "fk_user_valid" => null,
    "fk_user_validator" => "1",
    "fk_user_approve" => null,
    "fk_user_refuse" => null,
    "fk_user_cancel" => null,
    "fk_statut" => "0",
    "fk_c_paiement" => null,
    "paid" => "0",
    "note_public" => $notePrivate,
    "note_private" => $notePrivate,
    "model_pdf" => "expensereport/(PROV36)/(PROV36).pdf",
    "lines" => [
        [
            "rowid" => "5",
            "qty" => number_format($total_ttc, 2, '.', ''),
            "value_unit" => "1.00000000",
            "subprice" => null,
            "multicurrency_subprice" => null,
            "date" => $date_today,
            "dates" => $date_today_timestamp,
            "fk_c_type_fees" => "2",
            "fk_c_exp_tax_cat" => "0",
            "type_fees_code" => "TF_TRIP",
            "type_fees_libelle" => "Transportation",
            "type_fees_accountancy_code" => null,
            "projet_ref" => null,
            "projet_title" => null,
            "rang" => "0",
            "vatrate" => "19.0000",
            "vat_src_code" => "co",
            "tva_tx" => "19.0000",
            "localtax1_tx" => "0.0000",
            "localtax2_tx" => "0.0000",
            "localtax1_type" => "0",
            "localtax2_type" => "0",
            "total_ht" => number_format($total_ht, 8, '.', ''),
            "total_tva" => number_format($total_tva, 8, '.', ''),
            "total_ttc" => number_format($total_ttc, 8, '.', ''),
            "total_localtax1" => "0.00000000",
            "total_localtax2" => "0.00000000",
            "multicurrency_tx" => null,
            "multicurrency_total_ht" => null,
            "multicurrency_total_tva" => null,
            "multicurrency_total_ttc" => null,
            "fk_ecm_files" => null,
            "rule_warning_message" => null,
            "id" => "4",
            "fk_unit" => null,
            "description" => null,
            "product" => null,
            "product_ref" => null,
            "product_label" => null,
            "product_barcode" => null,
            "product_desc" => null,
            "fk_product_type" => null,
            "remise_percent" => null,
            "info_bits" => null,
            "special_code" => null,
            "module" => null,
            "entity" => null,
            "import_key" => null
        ]
    ]
];

// Convertir array a JSON
$json_data = json_encode($post_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// Configurar cURL
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $api_url.'/expensereports',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $json_data,
    CURLOPT_HTTPHEADER => [
        'DOLAPIKEY: ' . $api_token,
        'Content-Type: application/json'
    ],
]);

$response = curl_exec($curl);
curl_close($curl);

// Capturar la respuesta de la petición expensereports
$idGasto = json_decode($response, true);


//END GENERACION DE GASTOS DOLI

// Enviar correo
if (!enviarCorreo($pdf_path, $nombre, $correo_destino, $fecha, $cantidad_texto, $tipo_documento, $numero_documento, $cantidad, $concepto, $codigo_unico, $idGasto)) {
    http_response_code(403); // Internal Server Error
    echo json_encode(["status" => "error", "message" => "El mensaje no pudo ser enviado"]);
} 


// Enviar correo con PDF adjunto
function enviarCorreo($pdf_path, $nombre, $correo_destino, $fecha, $cantidad_texto, $tipo_documento, $numero_documento, $cantidad, $concepto, $codigo_unico,$idGasto) {
    $mail = new PHPMailer(true);
    try {
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Cambiado a SSL
        $mail->Port = SMTP_PORT; // Puerto para SSL

        // Configuración de charset
        $mail->CharSet = 'UTF-8';

        // Destinatarios
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress(SMTP_ADMIN_EMAIL, $nombre); // Use the email from config.php

        // Contenido del correo con PDF
        $mail->isHTML(true);
        $mail->Subject = 'Cuenta de Cobro de ' . $nombre;
        $mail->Body = 'Adjunto encontrarás la cuenta de cobro en formato PDF.<br><br>
        <a href="https://suite.devquick.co/expensereport/card.php?id=' . $idGasto . '&save_lastsearch_values=1" target="_blank" style="padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px;">Autorizar Pago</a><br><br>
        <strong>Concepto:</strong> ' . $concepto;

        $mail->addAttachment($pdf_path);
        $mail->send();

        // Enviar correo adicional sin PDF
        $mail->clearAddresses();
        $mail->addAddress($correo_destino);
        $mail->Subject = 'Cuenta de Cobro';
        $mail->Body = "Envió de cuenta de cobro exitoso<br>Fecha: $fecha<br>Nombre: $nombre<br>La Suma de: $cantidad_texto pesos<br><strong>Concepto:</strong> $concepto<br><br>La cuenta de cobro se ha generado exitosamente. Pronto estaremos respondiendo a $correo_destino confirmando que se efectuará el pago. En caso de no ser autorizado el pago, nos estaremos poniendo en contacto indicando el motivo.";
        $mail->send();

        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Convertir número a texto para PDF
function convertirNumeroTextoPDF($numero) {
    $unidades = ['cero', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
    $decenas = ['diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve'];
    $decenas2 = ['veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
    $centenas = ['cien', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

    if ($numero < 10) return $unidades[$numero];
    if ($numero < 20) return $decenas[$numero - 10];
    if ($numero < 100) {
        if ($numero % 10 === 0) return $decenas2[floor($numero / 10) - 2];
        return $decenas2[floor($numero / 10) - 2] . ' y ' . $unidades[$numero % 10];
    }
    if ($numero < 1000) {
        if ($numero % 100 === 0) return $centenas[floor($numero / 100) - 1];
        return $centenas[floor($numero / 100) - 1] . ' ' . convertirNumeroTextoPDF($numero % 100);
    }
    if ($numero < 1000000) {
        if ($numero % 1000 === 0) return convertirNumeroTextoPDF(floor($numero / 1000)) . ' mil';
        return convertirNumeroTextoPDF(floor($numero / 1000)) . ' mil ' . convertirNumeroTextoPDF($numero % 1000);
    }
    if ($numero < 1000000000) {
        if ($numero % 1000000 === 0) return convertirNumeroTextoPDF(floor($numero / 1000000)) . ' millones';
        return convertirNumeroTextoPDF(floor($numero / 1000000)) . ' millones ' . convertirNumeroTextoPDF($numero % 1000000);
    }
    return 'Número fuera de rango';
}
http_response_code(200); // OK
echo json_encode(['success' => true, 'id' => $idGasto]);
?>
