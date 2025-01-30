<?php
require 'vendor/autoload.php';
require 'config.php'; // Include the configuration file
use Dompdf\Dompdf;
use Dompdf\Options;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function convertirNumeroTexto($numero) {
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
        return $centenas[floor($numero / 100) - 1] . ' ' . convertirNumeroTexto($numero % 100);
    }
    if ($numero < 1000000) {
        if ($numero % 1000 === 0) return convertirNumeroTexto(floor($numero / 1000)) . ' mil';
        return convertirNumeroTexto(floor($numero / 1000)) . ' mil ' . convertirNumeroTexto($numero % 1000);
    }
    if ($numero < 1000000000) {
        if ($numero % 1000000 === 0) return convertirNumeroTexto(floor($numero / 1000000)) . ' millones';
        return convertirNumeroTexto(floor($numero / 1000000)) . ' millones ' . convertirNumeroTexto($numero % 1000000);
    }
    return 'Número fuera de rango';
}

if (isset($_GET['numero'])) {
    $numero = (float) $_GET['numero'];
    echo convertirNumeroTexto($numero);
    exit; // Asegúrate de que solo se devuelva el texto convertido
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fecha = $_POST['fecha'];
    $nombre = $_POST['nombre'];
    $tipo_documento = $_POST['tipo_documento'];
    $numero_documento = $_POST['numero_documento'];
    $cantidad = (float) str_replace(['.', ','], ['', '.'], $_POST['cantidad']);
    $cantidad_texto = convertirNumeroTexto($cantidad);
    $correo_destino = $_POST['correo_destino'];
    $firma = $_FILES['firma']['tmp_name'];
    $firma_data = base64_encode(file_get_contents($firma));
    $cantidad_formateada = number_format($cantidad, 2, ',', '.');

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
    <p class='declaracion'>
    1. Pertenezco al Régimen Simplificado.<br>
    2. No Soy responsable del Impuesto a las Ventas.<br>
    3. No estoy obligado a expedir factura de venta según el artículo 616-2 del Estatuto Tributario.
    </p>
    <p class='texto'>Para poder aplicar retención en la fuente establecida en el Art. 383 del E.T, informo que no he contratado o vinculado dos o más trabajadores asociados a mi actividad económica.</p>
    <div class='firma'><img src='data:image/png;base64,$firma_data'></div>
    <p class='firma'><strong>$nombre</strong><br>$tipo_documento $numero_documento</p>
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

    // Enviar correo
    if (enviarCorreo($pdf_path, $nombre, $correo_destino, $fecha, $cantidad_texto, $tipo_documento, $numero_documento, $cantidad)) {
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire('Éxito', 'El mensaje ha sido enviado', 'success').then(() => {
                    document.getElementById('formulario').reset();
                });
            });
        </script>";
    } else {
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire('Error', 'El mensaje no pudo ser enviado', 'error').then(() => {
                    document.getElementById('formulario').reset();
                });
            });
        </script>";
    }
}

function enviarCorreo($pdf_path, $nombre, $correo_destino, $fecha, $cantidad_texto, $tipo_documento, $numero_documento, $cantidad) {
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

        // Destinatarios
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress(SMTP_ADMIN_EMAIL, $nombre); // Use the email from config.php

        // Contenido del correo con PDF
        $mail->isHTML(true);
        $mail->Subject = 'Cuenta de Cobro';
        $mail->Body = 'Adjunto encontrarás la cuenta de cobro en formato PDF.<br><br>
        <a href="http://yourdomain.com/autorizar_pago.php?fecha=' . $fecha . '&nombre=' . $nombre . '&tipo_documento=' . $tipo_documento . '&numero_documento=' . $numero_documento . '&cantidad=' . $cantidad . '&correo_destino=' . $correo_destino . '" target="_blank">
        Autorizar Pago</a>';

        $mail->addAttachment($pdf_path);
        $mail->send();

        // Enviar correo adicional sin PDF
        $mail->clearAddresses();
        $mail->addAddress($correo_destino);
        $mail->Subject = 'Cuenta de Cobro';
        $mail->Body = "Envió de cuenta de cobro exitoso<br>Fecha: $fecha<br>Nombre: $nombre<br>La Suma de: $cantidad_texto pesos<br><br>La cuenta de cobro se ha generado exitosamente. Pronto estaremos respondiendo a $correo_destino confirmando que se efectuará el pago. En caso de no ser autorizado el pago, nos estaremos poniendo en contacto indicando el motivo.";
        $mail->send();

        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulario</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        form { max-width: 600px; margin: auto; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; margin-top: 5px; }
        button { margin-top: 15px; padding: 10px; background-color: #4CAF50; color: white; border: none; cursor: pointer; }
        h1, p { text-align: center; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function convertirNumeroTexto() {
            let cantidad = document.getElementById('cantidad').value.replace(/,/g, '').replace(/\./g, '');
            let numero = parseFloat(cantidad);
            if (isNaN(numero) || numero < 5000 || numero > 50000000) {
                document.getElementById('cantidad_texto').innerText = "El valor debe estar entre 5,000 y 50,000,000";
                return;
            }
            fetch('index.php?numero=' + numero)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('cantidad_texto').innerText = data + " pesos";
                });
        }

        function formatDecimalInput(event) {
            let input = event.target;
            let value = parseFloat(input.value.replace(/,/g, '').replace(/\./g, ''));
            if (!isNaN(value)) {
                input.value = value.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            let today = new Date().toISOString().split('T')[0];
            document.getElementById('fecha').value = today;
        });
    </script>
</head>
<body>
    <h1>CUENTA DE COBRO</h1>
    <p>Este formulario es con fines de mejorar el proceso de generar cuentas de cobro, toda información está sujeta a ser segura por la plataforma, no se almacena lo que está diligenciando.</p>
    <form id="formulario" action="" method="POST" enctype="multipart/form-data">
        <label>Fecha:</label> <input type="date" id="fecha" name="fecha" required>
        <label>Nombre:</label> <input type="text" name="nombre" required>
        <label>Tipo Documento:</label>
        <select name="tipo_documento">
            <option value="C.C">Cédula de Ciudadanía</option>
            <option value="NIT">NIT</option>
        </select>
        <label>Número Documento:</label> <input type="text" name="numero_documento" required>
        <label>La Suma de:</label> <input type="text" id="cantidad" name="cantidad" oninput="convertirNumeroTexto()" onblur="formatDecimalInput(event)" required>
        <span id="cantidad_texto"></span>
        <label>Correo Destino:</label> <input type="email" name="correo_destino" required>
        <label>Firma:</label> <input type="file" name="firma" accept="image/*" required>
        <button type="submit">Generar y Enviar</button>
    </form>
</body>
</html>
