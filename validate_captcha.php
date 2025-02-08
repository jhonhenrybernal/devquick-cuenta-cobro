<?php
session_start();
header('Content-Type: application/json');

if (!isset($_POST['captcha']) || !isset($_SESSION['captcha'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Solicitud inválida, falta el captcha']);
    exit;
}

if ($_POST['captcha'] === $_SESSION['captcha']) {
    http_response_code(200); // OK
    unset($_SESSION['captcha']); // Elimina el CAPTCHA de la sesión una vez validado
    echo json_encode(['success' => true, 'message' => 'Captcha correcto']);
} else {
    http_response_code(403); // Forbidden (Captcha incorrecto)
    echo json_encode(['success' => false, 'message' => 'Captcha incorrecto']);
}
exit;
?>

