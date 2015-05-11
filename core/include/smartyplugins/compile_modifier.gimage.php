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
function tpl_compile_modifier_gimage($ident)
{
    list($ident) = explode(',',$ident);
    return "substr($ident,0,strpos($ident,'|'))";
}
?>
