<?php
function tpl_function_fixurl($params, &$smarty){
    if($params['theme']){
        $system = &$GLOBALS['system'];
        if(!$smarty->_themeURL){
            if($url = $system->getConf('site.url.themeres')){
                $smarty->_themeURL = $url;
            }elseif(false){
                $smarty->_themeURL = $system->request['action'];
            }else{
                $smarty->_themeURL =$system->base_url().'themes/';
            }
        }
        return $smarty->_themeURL.$params['theme'].'/';
    }elseif($params['widget']){
        if(!$smarty->_widgetURL){
            $system = &$GLOBALS['system'];
            if($url = $system->getConf('site.url.widgetres')){
                $smarty->_widgetURL= $url;
            }elseif(false){
                $smarty->themeURL = $url;
            }else{
                if(($p = strlen($_SERVER["QUERY_STRING"]))>0){
                    $baseUrl = substr($_SERVER['REQUEST_URI'],0,(0-$p));
                }else{
                    $baseUrl = $_SERVER['REQUEST_URI'];
                }
                if(substr($baseUrl,-1,1)=='?'){
                    $baseUrl = substr($baseUrl,0,-1);
                }
                if(substr($baseUrl,-1,1)=='/'){
                    $baseUrl = substr($baseUrl,0,-1);
                }
                $baseUrl= substr($_SERVER["REQUEST_URI"],0,-(strlen($_SERVER["QUERY_STRING"])+1));
                if(substr($baseUrl,-1,1)=='/')$baseUrl=substr($baseUrl,0,-1);
                if(substr($baseUrl,-1,1)=='/')$baseUrl=substr($baseUrl,0,-1);
                $smarty->_themeURL =$baseUrl.='/widgets/';
            }
        }
        return $smarty->themeURL.$params['widget'].'/';
    }
}
?>
