<?php
class mdl_kft extends modelFactory {
    function getCerti(){
        if($this->system->getConf('certificate.id')){
            return $this->system->getConf('certificate.id');
        }else{
            return false;
        }
    }
    function getToken(){
        if($this->system->getConf('certificate.token')){
            return $this->system->getConf('certificate.token');
        }else{
            return false;
        }
    }
    function getAction(){
        if($this->system->getConf('certificate.kft.action')){
            return $this->system->getConf('certificate.kft.action');
        }else{
            return false;
        }
    }
    function setKftUrl($url){
        $this->system->setConf('certificate.kft.url',$url);
    }
    function getKftUrl(){
        if($this->system->getConf('certificate.kft.url')){
            return $this->system->getConf('certificate.kft.url');
        }else{
            return false;
        }
    }
    function setAction($action){
        $this->system->setConf('certificate.kft.action',$action);
    }
    function apply($url,$function,$arr){
        $check='ShopEx_API';
        $submit['certi_id']=$this->getCerti();
        $submit['function']= $function;
        foreach($arr as $index => $value){
            $submit[$index]=$value;
        }
        $submit['ac']=md5($submit['certi_id'].$this->getToken().$check);
        $result=$this->submit($url,$submit);
        return $result;
    }
    function submit($url,$submit){
        $net = &$this->system->loadModel('utility/http_client');
        return $this->net->post($url,$submit);
    }
    function checkstr($str){
        $tmp=explode('||',$str);
        return $tmp[1];
    }
    function checkLicense(){
        if( $this->getCerti() && $this->getToken()){
            return true;
        }else{
            return false;
        }
    }
}
?>