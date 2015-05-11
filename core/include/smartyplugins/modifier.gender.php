<?php
/*
* Smarty plugin
* -------------------------------------------------------------
* File:     modifier.number.php
* Type:     modifier
* Name:     capitalize
* Purpose:  transform the num value to readable string
* -------------------------------------------------------------
*/

function tpl_modifier_gender($result){
   switch($result){
    case 'male':
        return '男';
    break;
    case 'female':
        return '女';
    break;

   }
}
?>