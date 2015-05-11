<?php
if(!class_exists('http_base')){
    require(CORE_INCLUDE_DIR.'/http_base.php');
}
if(!class_exists('mdl_http_client')){
    require(dirname(__FILE__).'/mdl.http_client.php');
}
class mdl_http_client extends mdl_http_client{

    function delete($url,$headers=null){
        return $this->action(__FUNCTION__,$url,$headers,$callback);
    }

    function put($url,$body,$headers=null){
        return $this->action(__FUNCTION__,$url,$headers,$callback);
    }

    function put_file($url,$file,$headers=null){
        return $this->action(__FUNCTION__,$url,$headers,$callback);
    }

}
?>