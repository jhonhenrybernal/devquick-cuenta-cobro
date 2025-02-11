<?php
require 'vendor/autoload.php';
require 'config.php'; // Include the configuration file\


if (isset($_GET['numero'])) {
    $numero = (float) $_GET['numero'];
    echo convertirNumeroTextoConPesos($numero);
    exit; // Asegúrate de que solo se devuelva el texto convertido
}



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
        if ($numero == 100) return 'cien';
        if ($numero < 200) return 'ciento ' . convertirNumeroTexto($numero % 100);
        if ($numero % 100 === 0) return $centenas[floor($numero / 100) - 1];
        return $centenas[floor($numero / 100) - 1] . ' ' . convertirNumeroTexto($numero % 100);
    }
    if ($numero < 1000000) {
        if ($numero == 1000) return 'mil';
        if ($numero < 2000) return 'mil ' . convertirNumeroTexto($numero % 1000);
        if ($numero % 1000 === 0) return convertirNumeroTexto(floor($numero / 1000)) . ' mil';
        return convertirNumeroTexto(floor($numero / 1000)) . ' mil ' . convertirNumeroTexto($numero % 1000);
    }
    if ($numero < 1000000000) {
        $millones = floor($numero / 1000000);
        $resto = $numero % 1000000;

        // Manejo especial para "un millón" en lugar de "uno millones"
        $millonesTexto = ($millones == 1) ? 'un millón' : convertirNumeroTexto($millones) . ' millones';

        // Si no hay residuo, retornamos directamente
        if ($resto === 0) {
            return $millonesTexto;
        }

        return $millonesTexto . ' ' . convertirNumeroTexto($resto);
    }
    return 'Número fuera de rango';
}

// **Función para asegurarse de que siempre termine con "pesos"**
function convertirNumeroTextoConPesos($numero) {
    return convertirNumeroTexto($numero) . ' pesos';
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
                    document.getElementById('cantidad_texto').innerText = data;
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

            document.querySelector('[name="numero_documento"]').addEventListener('blur', function() {
                let tipoDocumento = document.querySelector('[name="tipo_documento"]').value;
                let numeroDocumento = this.value.trim();
                if (tipoDocumento && numeroDocumento) {
                    fetch('apiusid.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `tipo_documento=${tipoDocumento}&numero_documento=${numeroDocumento}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log(data); // Handle the response data as needed
                        if (data.id) {
                            let idUserInput = document.querySelector('[name="idUser"]');
                            if (!idUserInput) {
                                idUserInput = document.createElement('input');
                                idUserInput.type = 'hidden';
                                idUserInput.name = 'idUser';
                                document.getElementById('formulario').appendChild(idUserInput);
                            }
                            idUserInput.value = data.id;
                        }
                    })
                    .catch(error => {
                        Swal.fire('Alerta', 'Valide su tipo y numero de documento', 'info');
                        console.error('Error:', error);
                    });
                }
            });
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
                        document.getElementById('captcha').style.display = 'block';
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
    <form id="formulario" method="POST" enctype="multipart/form-data">
        <label>Fecha:</label> <input type="date" id="fecha" name="fecha" required>
        <label>Su nombre completo:</label> <input type="text" name="nombre" required>
        <label>Su tipo Documento:</label>
        <select name="tipo_documento">
            <option value="">Seleccione</option>
            <option value="cc">Cédula de Ciudadanía</option>
            <option value="nit">NIT</option>
        </select>
        <label>Su número Documento:</label> <input type="text" name="numero_documento" required>
        <label>Agregue la Suma de dinero:</label> <input type="text" id="cantidad" name="cantidad" oninput="convertirNumeroTexto()" onblur="formatDecimalInput(event)" required>
        <span id="cantidad_texto"></span>
        <label>Su concepto:</label> <textarea name="concepto" required></textarea>
        <label>Su Correo para generar firma digital:</label> 
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <input type="email" name="correo_destino_codigo_firma" required style="flex: 1; margin-right: 10px;">
            <button type="button" onclick="enviarDatosGenerarFirma()" style="margin-top: 5px;">Generar Firma Digital</button>
        </div>
        <div id="codigo_firma_container" style="display: none;">
            <label>Código de Firma Digital:</label>
            <input type="text" id="codigo_firma" name="codigo_firma">
        </div>
        <div id="captcha" style="display: none;">

            <label>Captcha:</label>
            <div style="display: flex; align-items: center; gap: 10px; margin-top: 10px;">
                <input type="text" name="captcha"  placeholder="Ingrese el CAPTCHA" required 
                style="width: 120px; font-size: 18px; font-weight: bold; text-align: center; letter-spacing: 3px; padding: 5px;">
                <img src="captcha.php" alt="CAPTCHA" style="border-radius: 5px;">
                <button type="button" onclick="recargarCaptcha()" style="background: none; border: none; cursor: pointer;">
                    🔄
                </button>
            </div>
        </div>
        
        <button id="btn_submit" type="submit" style="display: none;">Generar y Enviar</button>
    </form>
    <script>
        function recargarCaptcha() {
            document.querySelector("img[alt='CAPTCHA']").src = "captcha.php?" + Date.now();
        }

        document.getElementById('formulario').addEventListener('submit', function(event) {
            event.preventDefault();
           
            let captcha = document.querySelector("input[name='captcha']").value;
            var form = this;

            fetch('validate_captcha.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'captcha=' + captcha
            })
            .then(response =>response.json())
            .then(data => {
                console.log(data.success);  
                if (data.success) {
                    procesarFormulario(event); // Call the form processing function
                } else {
                    Swal.fire('Error', 'CAPTCHA incorrecto', 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'No se pudo validar el CAPTCHA', 'error');
            });
        });

        function procesarFormulario(event) {
            if (!validarFormulario()) {
                return;
            }
            const button = document.getElementById('btn_submit');
            button.textContent = 'Procesando...';
            const form = document.getElementById('formulario'); // Obtener la referencia al formulario
            const formData = new FormData(form);
            fetch('procesar_formulario.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    Swal.fire({
                    title: 'Éxito',
                    text: 'Formulario procesado correctamente',
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonText: 'Volver a generar otro',
                    cancelButtonText: 'Salir de la página',
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.reset();
                        recargarCaptcha(); // Recargar el CAPTCHA tras éxito
                        let today = new Date().toISOString().split('T')[0];
                        document.getElementById('fecha').value = today;
                    } else {
                        window.location.href = 'https://devquick.co'; // Redirigir a la página de salida
                    }
                });
                } else {
                    Swal.fire('Error', 'No se pudo procesar el formulario', 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'No se pudo procesar el formulario', 'error');
            })
            .finally(() => {
                button.textContent = 'Generar y Enviar';
            });
        }
    </script>
</body>
</html>
