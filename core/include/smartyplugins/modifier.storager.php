<?php
/*
 * Smarty plugin
 * -------------------------------------------------------------
 * File:     modifier.t.php
 * Type:     modifier
 * Name:     capitalize
 * Purpose:  capitalize words in the string
 * -------------------------------------------------------------
 */
function tpl_modifier_storager($ident,$type){
    $p = strpos($ident,'|');
    if($p!==false){
        $ident = substr($ident,0,$p);
    }
    $system = &$GLOBALS['system'];
    if (!$GLOBALS['_gimage']){
        $gimage=$system->loadModel('goods/gimage');
        $GLOBALS['_gimage']=&$gimage;
    } 
    else{
        $gimage=&$GLOBALS['_gimage'];
    }
    $imgurl=$gimage->getUrl($ident,$type);
    return $imgurl;
}