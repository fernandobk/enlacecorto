<?php
header('Allow: OPTIONS, HEAD, GET, POST');
header('Content-type: application/json');
header('Access-Control-Allow-Origin: '.($_SERVER['HTTP_ORIGIN']??'*'));
header('Access-Control-Allow-Methods: OPTIONS,HEAD,GET,POST');
header('Access-Control-Allow-Headers: authorization,content-type');

$_GET['id'] = trim(strtolower($_GET['id']));
if( preg_match('/[^a-z0-9]/',$_GET['id']) ){ bad_request(); }

$bd = new SQLite3('bd.sqlite');
$time = time();
$id = $_GET['id'] ?? '';
$llave = str_replace('Bearer ', '', (apache_request_headers()['authorization']??''));

function respuesta(int $status, string $msj = null, string $detalles = null){    
    $statusText = array();
    $statusText[200] = 'OK';
    $statusText[201] = 'Created';
    $statusText[202] = 'Accepted';
    $statusText[400] = 'Bad Request';
    $statusText[403] = 'Unauthorized';
    $statusText[404] = 'Not Found';
    $statusText[405] = 'Method Not Allowed';
    $statusText[500] = 'Internal Server Error';
    
    if(!$detalles){
        $detalles = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? 'global';
        $detalles .= '-c' . $status;
    }
    $detalles = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'].'/doc.html#'.$detalles;

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
        respuesta(200);
    break;
    
    case 'GET':
        if( ID ){ mostrar(); }
        else{ listar_todo(); } 
    break;

    default:
        if(!ID){ respuesta(400, 'Falta ID'); }

        switch($_SERVER['REQUEST_METHOD']){
            case 'POST': 
                if( LLAVE ){ modificar_hoja(); }
                else { crear_hoja(); }
            break;
            
            case 'HEAD': 
                if( LLAVE ){ borrar_hoja(); }
                else { respuesta(403); }
            break;
            
            default: respuesta(405);
        }
}

/* ==== Funciones propias del servicio ==== */

function listar_todo(){
    $bd = new SQLite3(NOMBRE_BD);
    $result = $bd->query('SELECT * FROM tabla');
    $items = array();
    while( $item = $result->fetchArray(SQLITE3_ASSOC)){
        unset($item['llave']);
        $items[] = $item;
    }
    exit( json_encode( $items ) );
}

function mostrar(){

    $bd = new SQLite3(NOMBRE_BD);
    $sql = 'SELECT `hoja` FROM `carpeta` WHERE id = "'.ID.'"';
    $result = $bd->query($sql);
    $result = $result->fetchArray(SQLITE3_NUM);

    if( $result ){ 
        exit($result[0]); }
    else{
        respuesta(404, 'ID no encontrado.');
    }
}

function crear(){
    $id = TIME - 1646449200; // Fecha de primer commit
    $data = addslashes(file_get_contents('php://input'));
    $llave = md5((TIME/2).$id);

    $bd = new SQLite3(NOMBRE_BD);
    $sql = "INSERT INTO tabla (`id`, `data`, `llave`, `creado`) VALUES ($id, '$data', '$llave', datetime('now', 'localtime'))";
    @$result = $bd->exec($sql);
    
    if( $result ){
        respuesta(201, $llave);
    }else{
        respuesta(500, $id, '('.$bd->lastErrorCode().') '.$bd->lastErrorMsg() );
    }
}

function modificar(){
    $id = ID;
    $llave = LLAVE;
    $data = addslashes(file_get_contents('php://input'));

    $bd = new SQLite3(NOMBRE_BD);
    $sql = "UPDATE `tabla` SET `data` = '$data', `modificado` = datetime('now', 'localtime') WHERE `id` = '$id' AND  `llave` = '$llave';";
    @$result = $bd->exec($sql);
    
    if( $result OR $bd->changes() ){
        respuesta(202);
    }else{
        respuesta(500, '('.$bd->lastErrorCode().') '.$bd->lastErrorMsg() );
    }
}

function borrar(){
    $id = ID;
    $bd = new SQLite3(NOMBRE_BD);
    $sql = "DELETE FROM `tabla`  WHERE `id` = $id AND `llave` = '$llave';";
    @$result = $bd->exec($sql);
    
    if( $result OR $bd->changes() ){
        respuesta(202, $id);
    }else{
        respuesta(404, $id, '('.$bd->lastErrorCode().') '.$bd->lastErrorMsg() );
    }
}