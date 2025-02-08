<?php
session_start();
require 'vendor/autoload.php';
require 'config.php';
require 'index.php'; // Include the index.php file to access the functions
use Dompdf\Dompdf;
use Dompdf\Options;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$fecha = $_POST['fecha'];
$nombre = $_POST['nombre'];
$tipo_documento = $_POST['tipo_documento'];
$numero_documento = $_POST['numero_documento'];
$correo_destino = $_POST['correo_destino_codigo_firma'];
$cantidad = (float) str_replace(['.', ','], ['', '.'], $_POST['cantidad']);
$cantidad_texto = convertirNumeroTextoPDF($cantidad);
$cantidad_formateada = number_format($cantidad, 2, ',', '.');
$concepto = $_POST['concepto'];
$codigo_unico = $_POST['codigo_firma'];
validarCodigo($codigo_unico);
guardarCodigoEnBaseDeDatos($codigo_unico, $numero_documento,$cantidad,$concepto);

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

// Enviar correo
if (enviarCorreo($pdf_path, $nombre, $correo_destino, $fecha, $cantidad_texto, $tipo_documento, $numero_documento, $cantidad, $concepto, $codigo_unico)) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire('Éxito', 'Cuenta de cobro generado exitosamente.', 'success').then(() => {
                //document.getElementById('formulario').reset();
            });
        });
    </script>";
} else {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire('Error', 'El mensaje no pudo ser enviado', 'error').then(() => {
                //document.getElementById('formulario').reset();
            });
        });
    </script>";
}
function generarCodigoUnico($longitud = 8) {
    $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $codigo = '';
    for ($i = 0; $i < $longitud; $i++) {
        $codigo .= $caracteres[rand(0, strlen($caracteres) - 1)];
    }
    return $codigo;
}

function guardarCodigoEnBaseDeDatos($codigo, $numero_documento,$cantidad) {
    // Conexión a la base de datos
    $conexion = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conexion->connect_error) {
        die("Conexión fallida: " . $conexion->connect_error);
    }

    // Verificar si el código ya existe
    $stmt = $conexion->prepare("SELECT * FROM codigos_firma_digital WHERE codigo = ?");
    $stmt->bind_param("s", $codigo);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        // Si el código ya existe, generar uno nuevo
        $codigo = generarCodigoUnico();
        return guardarCodigoEnBaseDeDatos($codigo, $numero_documento);
    } else {
        // Guardar el nuevo código en la base de datos
        $stmt = $conexion->prepare("INSERT INTO codigos_firma_digital (codigo, numero_documento, usado) VALUES (?, ?, 0)");
        $stmt->bind_param("ss", $codigo, $numero_documento);
        $stmt->execute();
        $stmt->close();
        $conexion->close();
        return $codigo;
    }

    registrarGasto($numero_documento, $cantidad, $concepto);
}

function validarCodigo($codigo) {
    // Conexión a la base de datos
    $conexion = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conexion->connect_error) {
        die("Conexión fallida: " . $conexion->connect_error);
    }

    // Verificar el código
    $stmt = $conexion->prepare("SELECT * FROM codigos_firma_digital WHERE codigo = ?");
    $stmt->bind_param("s", $codigo);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $fila = $resultado->fetch_assoc();
        $fecha_creacion = new DateTime($fila['fecha_creacion']);
        $fecha_actual = new DateTime();

        // Validar solo la fecha, no la hora
        $fecha_creacion->setTime(0, 0, 0);
        $fecha_actual->setTime(0, 0, 0);

        if ($fila['usado'] == 1) {
            echo "<script>alert('Código ya usado. La página se cerrará por políticas de seguridad.'); window.location.href = 'index.php';</script>";
            exit; // Cancel the process
        } elseif ($fecha_creacion < $fecha_actual) {
            echo "<script>alert('Código vencido. La página se cerrará por políticas de seguridad.'); window.location.href = 'index.php';</script>";
            exit; // Cancel the process
        } else {
            $stmt = $conexion->prepare("UPDATE codigos_firma_digital SET usado = 1 WHERE codigo = ?");
            $stmt->bind_param("s", $codigo);
            $stmt->execute();
            // Removed alert for valid code
        }
    } else {
        echo "<script>alert('Código no encontrado');</script>";
    }

    $stmt->close();
    $conexion->close();
}


function enviarCorreo($pdf_path, $nombre, $correo_destino, $fecha, $cantidad_texto, $tipo_documento, $numero_documento, $cantidad, $concepto, $codigo_unico) {
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
        <a href="https://suite.devquick.co/" target="_blank" style="padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px;">Autorizar Pago</a><br><br>
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

function registrarGasto($numero_documento, $cantidad, $concepto) {
    $iva = 19; // IVA del 19%
    $total_ttc = $cantidad;
    $total_ht = $total_ttc / (1 + ($iva / 100));
    $total_tva = $total_ht * ($iva / 100);

    // Conexión a la base de datos
    $conexion = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    
    if ($conexion->connect_error) {
        die("Error de conexión: " . $conexion->connect_error);
    }

    // Buscar el usuario por número de documento
    $stmt = $conexion->prepare("SELECT rowid FROM dmq_user WHERE national_registration_number = ?");
    $stmt->bind_param("s", $numero_documento);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 0) {
        echo "Error: Usuario no encontrado.";
        return false;
    }

    $fila = $resultado->fetch_assoc();
    $user_id = $fila['rowid'];
    $stmt->close();

    // Iniciar transacción para mantener la integridad de los datos
    $conexion->begin_transaction();

    try {
        // Insertar en dmq_expensereport
        $stmt = $conexion->prepare("
            INSERT INTO dmq_expensereport (
                ref, entity, total_ht, total_tva, total_ttc, 
                date_debut, date_fin, date_create, tms, 
                fk_user_author, fk_user_creat, fk_user_valid, 
                model_pdf, fk_multicurrency, multicurrency_code, multicurrency_tx, 
                multicurrency_total_ht, multicurrency_total_tva, multicurrency_total_ttc
            ) 
            VALUES (
                ?, 1, ?, ?, ?, 
                CURDATE(), CURDATE(), NOW(), NOW(), 
                ?, ?, ?, 
                ?, 1, 'USD', 1.00000000, 
                ?, ?, ?
            )
        ");
        
        $ref = 'ER' . date('ymd') . '-' . rand(1000, 9999); // Generar referencia dinámica
        $model_pdf = "expensereport/$ref/$ref.pdf";

        $stmt->bind_param("sddddiisdddd",
            $ref, $total_ht, $total_tva, $total_ttc, 
            $user_id, $user_id, $user_id, 
            $model_pdf, $total_ht, $total_tva, $total_ttc
        );
        $stmt->execute();
        $expense_id = $conexion->insert_id;
        $stmt->close();

        // Insertar en dmq_expensereport_det
        $stmt = $conexion->prepare("
            INSERT INTO dmq_expensereport_det (
                fk_expensereport, comments, product_type, qty, subprice, 
                value_unit, tva_tx, total_ht, total_tva, total_ttc, 
                date
            ) 
            VALUES (?, ?, -1, 1, ?, ?, 19.0000, ?, ?, ?, CURDATE())
        ");
        
        $stmt->bind_param("isdssss", 
            $expense_id, $concepto, $total_ht, $total_ttc, 
            $total_ht, $total_tva, $total_ttc
        );
        $stmt->execute();
        $stmt->close();

        // Insertar en dmq_ecm_files
        $stmt = $conexion->prepare("
            INSERT INTO dmq_ecm_files (
                ref, label, entity, filepath, filename, src_object_type, src_object_id, 
                fullpath_orig, description, date_c, tms, fk_user_c
            ) 
            VALUES (?, 'Factura soporte técnico', 1, ?, ?, 'expensereport', ?, ?, ?, NOW(), NOW(), ?)
        ");
        
        $filename = "factura_soporte.pdf";
        $fullpath = "expensereport/$ref/$filename";
        $descripcion = "Factura adjunta para el informe de gastos";

        $stmt->bind_param("sssssss", 
            $ref, $model_pdf, $filename, $expense_id, $fullpath, $descripcion, $user_id
        );
        $stmt->execute();
        $stmt->close();

        // Confirmar la transacción
        $conexion->commit();
        echo "Registro de gasto completado correctamente.";

        return true;
    } catch (Exception $e) {
        // En caso de error, revertir la transacción
        $conexion->rollback();
        echo "Error: " . $e->getMessage();
        return false;
    } finally {
        $conexion->close();
    }
}

?>
