# Proyecto Cuenta de Cobro

Este proyecto genera una factura en formato PDF (Cuenta de Cobro) y la envía por correo electrónico utilizando PHPMailer. El proyecto utiliza Dompdf para la generación de PDF y SweetAlert2 para mostrar alertas.

## Requisitos

- PHP 7.4 o superior
- Composer
- Un servidor web (por ejemplo, WAMP, XAMPP)
- Conexión a Internet para enviar correos electrónicos

## Instalación

1. Clona el repositorio en tu máquina local.
2. Navega al directorio del proyecto.
3. Ejecuta `composer install` para instalar las dependencias requeridas.

## Configuración

Las credenciales de correo electrónico y otra información sensible se almacenan en un archivo de configuración separado (`config.php`). Este archivo se incluye en el script principal para mantener las credenciales seguras.

### Configuración de Credenciales de Correo Electrónico

1. Copia el archivo `config_example.php` y renómbralo a `config.php`.
2. Abre el archivo `config.php` y completa las credenciales de correo electrónico requeridas:

```php
define('SMTP_HOST', 'tu_smtp_host');
define('SMTP_USERNAME', 'tu_correo@example.com');
define('SMTP_PASSWORD', 'tu_contraseña_de_correo');
define('SMTP_PORT', 465);
define('SMTP_FROM_EMAIL', 'tu_correo@example.com');
define('SMTP_FROM_NAME', 'Tu Nombre o Empresa');
```

## Uso

1. Inicia tu servidor web y navega al directorio del proyecto.
2. Abre el archivo `index.php` en tu navegador.
3. Completa el formulario con la información requerida y sube una imagen de la firma.
4. Haz clic en el botón "Generar y Enviar" para generar el PDF y enviar el correo electrónico.

## Características

- Convierte números a texto en español.
- Genera una factura en formato PDF con la información proporcionada.
- Envía la factura en formato PDF por correo electrónico utilizando PHPMailer.
- Muestra alertas de éxito o error utilizando SweetAlert2.

## Licencia

Este proyecto está licenciado bajo la Licencia MIT.
