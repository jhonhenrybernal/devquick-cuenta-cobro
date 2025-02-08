<?php
session_start();

// Generar el texto del CAPTCHA
function generarCaptchaTexto($longitud = 6) {
    $caracteres = 'ABCDEFGHJKLMNPQRSTUVWXYZ123456789';
    return substr(str_shuffle($caracteres), 0, $longitud);
}

$_SESSION["captcha"] = generarCaptchaTexto();

// Crear imagen CAPTCHA
$ancho = 160;
$alto = 50;
$imagen = imagecreatetruecolor($ancho, $alto);

// Definir colores
$color_fondo = imagecolorallocate($imagen, 255, 255, 255);
$color_texto = imagecolorallocate($imagen, 0, 0, 255);
$color_lineas = imagecolorallocate($imagen, 200, 200, 200);

// Rellenar fondo
imagefilledrectangle($imagen, 0, 0, $ancho, $alto, $color_fondo);

// Dibujar líneas aleatorias
for ($i = 0; $i < 6; $i++) {
    imageline($imagen, rand(0, $ancho), rand(0, $alto), rand(0, $ancho), rand(0, $alto), $color_lineas);
}

// Intentar usar una fuente TTF, si no, usar texto estándar
$fuente = __DIR__ . '/arial.ttf'; // Asegúrate de tener este archivo en la misma carpeta
if (file_exists($fuente)) {
    imagettftext($imagen, 20, rand(-10, 10), 30, 35, $color_texto, $fuente, $_SESSION["captcha"]);
} else {
    imagestring($imagen, 5, 30, 15, $_SESSION["captcha"], $color_texto);
}

// Enviar imagen
header("Content-Type: image/png");
imagepng($imagen);
imagedestroy($imagen);
?>
