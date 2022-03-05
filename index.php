<?php

define('NOMBRE_BD', 'bd.sqlite');
define('RUTA_DOC', $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'].'/doc.html');

define('ID', $_GET['id']??'');
define('LLAVE', str_replace('Bearer ', '', (apache_request_headers()['authorization']??'')));

header('Allow: OPTIONS, HEAD, GET, POST');
header('Content-type: application/json');
header('Access-Control-Allow-Origin: '.($_SERVER['HTTP_ORIGIN']??'*'));
header('Access-Control-Allow-Methods: OPTIONS,HEAD,GET,POST');
header('Access-Control-Allow-Headers: authorization,content-type');

$_GET['id'] = trim(strtolower($_GET['id']));
if( preg_match('/[^a-z0-9]/',$_GET['id']) ){ bad_request(); }

function respuesta(int $status, string $msj = null, string $detalles = null){    
    $statusText = array();
    $statusText[200] = 'OK';
    $statusText[201] = 'Created';
    $statusText[202] = 'Accepted';
    $statusText[204] = 'No Content';
    $statusText[205] = 'Reset Content';
    $statusText[400] = 'Bad Request';
    $statusText[404] = 'Not Found';
    $statusText[405] = 'Method Not Allowed';
    $statusText[415] = 'Unsupported Media Type';
    $statusText[500] = 'Internal Server Error';
    
    if(!$detalles){
        $detalles = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? 'global';
        $detalles .= '-c' . $status;
    }
    $detalles = RUTA_DOC.'#'.$detalles;

    header('HTTP/1.1 '.$status.' '.$statusText[$status]);
    header('X-Detalles: '.$detalles);

    switch($status){
        case 201: header('X-Llave: '.$msj); break;
        case 202: echo file_get_contents('php://input'); break;
        default: if($msj){ header('X-Mensaje: '.$msj); }
    }
    exit;
}

 /* ==== Controlador frontal ==== */

 switch($_SERVER['REQUEST_METHOD']){
    case 'OPTIONS':
        header('HTTP/1.0 200 OK');
        exit;
    break;
    
    case 'GET':
        if( ID ){ mostrar_hoja(); }
        else{ listar_todo(); }
    break;

    default:
        if(!ID){ respuesta(400, 'Falta ID'); }

        switch($_SERVER['REQUEST_METHOD']){
            case 'POST': modificar_hoja(); break;
            
            case 'HEAD': 
                if( LLAVE ){ borrar_hoja(); }
                else { crear_hoja(); }
            break;
            
            default: respuesta(405);
        }
}

/* ==== Funciones propias del servicio ==== */
