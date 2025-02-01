<?php
include 'db_connection.php'; // Assuming you have a file to handle DB connection
include 'config.php'; // Include the config file for mail server parameters

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Make sure PHPMailer is installed via Composer

function generateUniqueCode($length = 8) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) { // Corrected loop condition
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function isCodeExists($conn, $code) {
    $stmt = $conn->prepare("SELECT id FROM codigos_firma_digital WHERE codigo = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $stmt->store_result();
    return $stmt->num_rows > 0;
}

function sendEmail($email, $name, $code, $startDate, $endDate) {
    $mail = new PHPMailer(true);
    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = SMTP_PORT;

        //Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, 'Mailer, no responder');
        $mail->addAddress($email, $name);

        //Content
        $mail->isHTML(true);
        $mail->Subject = '=?UTF-8?B?' . base64_encode('Proceso de firmado electrónico') . '?=';
        $mail->Body    = "
        Comunicación de servicio<br>
        Se ha generado una clave dinámica<br>
        Señor (a) usuario (a): $name<br><br>
        A continuación, se entrega la clave dinámica solicitada para realizar el trámite:<br><br>
        <b>$code</b><br><br>
        Tu clave dinámica está vigente desde<br>
        $startDate<br>
        hasta<br>
        $endDate<br><br>
        Si no realizaste está solicitud comunícate de inmediato con nosotros";
        $mail->AltBody = "
        Comunicación de servicio\n
        Se ha generado una clave dinámica\n
        Señor (a) usuario (a): $name\n\n
        A continuación, se entrega la clave dinámica solicitada para realizar el trámite:\n\n
        $code\n\n
        Tu clave dinámica está vigente desde\n
        $startDate\n
        hasta\n
        $endDate\n\n
        Si no realizaste está solicitud comunícate de inmediato con nosotros";

        $mail->send();
    } catch (Exception $e) {
        error_log("Failed to send email. Mailer Error: {$mail->ErrorInfo}");
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $numero_documento = $_POST['numero_documento'];
    $nombre_persona = isset($_POST['nombre']) ? $_POST['nombre'] : '';
    $email = isset($_POST['correo_destino_codigo_firma']) ? $_POST['correo_destino_codigo_firma'] : '';

    $conn = openConnection();

    do {
        $codigo = generateUniqueCode();
    } while (isCodeExists($conn, $codigo));

    $startDate = date("d/m/Y h:i:s A");
    $endDate = date("d/m/Y h:i:s A", strtotime('+1 day'));

    $stmt = $conn->prepare("INSERT INTO codigos_firma_digital (codigo, numero_documento, nombre_persona) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $codigo, $numero_documento, $nombre_persona);
    $stmt->execute();

    sendEmail($email, $nombre_persona, $codigo, $startDate, $endDate);

    closeConnection($conn);

    echo "Código generado y enviado por correo.";
}
?>
