<?php
function tpl_function_goodsmenu($params, &$smarty)
{
    if($GLOBALS['runtime']['member_lv']<0){
        $params['login'] = 'nologin';
    }
    echo $smarty->_fetch_compile_include('shop:product/menu.html',$params);
}