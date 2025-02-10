<?php
require 'config.php';

// Configuración de credenciales de la API
$api_url = API_URL;
$api_url_login = $api_url."/login";


// Obtener el token de autenticación
$api_token = API_TOKEN;

// Configuración de usuarios de la API
$tipo_documento = $_POST['tipo_documento'];
$numero_documento = $_POST['numero_documento'];
$curl = curl_init();
// Construir el número de documento
$document = $tipo_documento . $numero_documento;
$base_url = $api_url."/users";
$filter = urlencode("(t.national_registration_number:like:'$document')");
$url = "$base_url?sortfield=t.rowid&sqlfilters=$filter";

curl_setopt_array($curl, array(
  CURLOPT_URL => $url,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'GET',
  CURLOPT_HTTPHEADER => array(
    'DOLAPIKEY: ' . $api_token,
  ),
));

$response = curl_exec($curl);
curl_close($curl);

$response_data = json_decode($response, true);

if (empty($response_data)) {
    http_response_code(404);
    echo json_encode(["error" => "No records found"]);
} elseif (count($response_data) > 1) {
    http_response_code(404);
    echo json_encode(["error" => "More than one record found"]);
} else {
    http_response_code(200);
    $first_record_id = $response_data[0]['id'];
    echo json_encode(["id" => $first_record_id]);
}
