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

function generarCodigoUnico($longitud = 8) {
    $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $codigo = '';
    for ($i = 0; $i < $longitud; $i++) {
        $codigo .= $caracteres[rand(0, strlen($caracteres) - 1)];
    }
    return $codigo;
}

function guardarCodigoEnBaseDeDatos($codigo, $numero_documento) {
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
    $correo_destino = $_POST['correo_destino_codigo_firma'];
    $cantidad = (float) str_replace(['.', ','], ['', '.'], $_POST['cantidad']);
    $cantidad_texto = convertirNumeroTexto($cantidad);
    $cantidad_formateada = number_format($cantidad, 2, ',', '.');
    $concepto = $_POST['concepto'];
    $codigo_unico = $_POST['codigo_firma'];
    validarCodigo($codigo_unico);
    guardarCodigoEnBaseDeDatos($codigo_unico, $numero_documento);

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

        // Destinatarios
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress(SMTP_ADMIN_EMAIL, $nombre); // Use the email from config.php

        // Contenido del correo con PDF
        $mail->isHTML(true);
        $mail->Subject = 'Cuenta de Cobro de ' . $nombre;
        $mail->Body = 'Adjunto encontrarás la cuenta de cobro en formato PDF.<br><br>
        <a href="http://yourdomain.com/autorizar_pago.php?codigo=' . $codigo_unico . '" target="_blank" style="padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px;">Autorizar Pago</a><br><br>
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuenta de cobro</title>
    <link rel="icon" href="logo.jpg" type="image/png">
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        form { max-width: 600px; margin: auto; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 8px; margin-top: 5px; }
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

        function enviarDatosGenerarFirma() {
            if (!validarFormulario()) {
                return;
            }
            const button = document.querySelector('button[onclick="enviarDatosGenerarFirma()"]');
            button.textContent = 'Generando...';
            const formData = new FormData(document.getElementById('formulario'));
            fetch('generarFirma.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    response.text().then(data => {
                        document.getElementById('codigo_firma_container').style.display = 'block';
                        document.getElementById('btn_submit').style.display = 'block';
                    });
                    Swal.fire('Generar Firma Digital', 'Revise su correo electrónico donde enviamos su código de firma digital', 'info');
                } else {
                    return response.text().then(text => { throw new Error(text) });
                }
            })
            .catch(error => {
                Swal.fire('Error', 'No se pudo generar la firma digital', 'error');
            })
            .finally(() => {
                button.textContent = 'Generar Firma Digital';
            });
        }

        function validarFormulario() {
            const requiredFields = ['fecha', 'nombre', 'tipo_documento', 'numero_documento', 'cantidad', 'concepto', 'correo_destino_codigo_firma'];
            for (let field of requiredFields) {
                if (document.querySelector(`[name="${field}"]`).value.trim() === '') {
                    Swal.fire('Error', 'Por favor complete todos los campos', 'error');
                    return false;
                }
            }
            return true;
        }
    </script>
</head>
<body>
    <div style="text-align: center;">
        <img src="logo.jpg" alt="Logo" style="max-width: 200px;">
    </div>
    <h1>CUENTA DE COBRO</h1>
    <p>Este formulario es con fines de mejorar el proceso de generar cuentas de cobro, toda información está sujeta a ser segura por la plataforma, no se almacena lo que está diligenciando.</p>
    <form id="formulario" action="" method="POST" enctype="multipart/form-data" onsubmit="document.getElementById('btn_submit').textContent = 'Procesando...'; return validarFormulario()">
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
        <label>Concepto:</label> <textarea name="concepto" required></textarea>
        <label>Correo Destino para firma digital:</label> 
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <input type="email" name="correo_destino_codigo_firma" required style="flex: 1; margin-right: 10px;">
            <button type="button" onclick="enviarDatosGenerarFirma()" style="margin-top: 5px;">Generar Firma Digital</button>
        </div>
        <div id="codigo_firma_container" style="display: none;">
            <label>Código de Firma Digital:</label>
            <input type="text" id="codigo_firma" name="codigo_firma">
        </div>
        <button id="btn_submit" type="submit" style="display: none;">Generar y Enviar</button>
    </form>
</body>
</html>
