<?php
function shop_front_api(&$controller,$type){

    if(defined('STORE_KEY') && (!isset($_POST['STORE_KEY']) || md5(STORE_KEY)!=$_POST['STORE_KEY'])){
        $controller->system->_succ = true;
        $controller->system->responseCode(401);
        echo 'Error 401: Unauthorized Request.';
        exit;
    }

    switch($type){
    case 'json':
        ob_clean();
        $controller->contentType = 'text/plain';
        echo json_encode($this->pagedata);
        break;

    case 'xml':
        ob_clean();
        $controller->contentType = 'text/xml';
        $xmlModel = &$this->system->loadModel('utility/xml');
        $xml ='<'.'?xml version="1.0" encoding="utf-8"?'.'>';
        $xml .= $xmlModel->array2xml($this->pagedata,'root');
        echo $xml;
        break;
    }
}
