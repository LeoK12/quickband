<?php
function tpl_function_widgets($params,&$smarty){

    $system = &$GLOBALS['system'];
    $widgets_mdl = &$system->loadModel('content/widgets');
    $frontend = &$system->loadModel('system/frontend');
    $frontend->theme = $smarty->theme;

    $solt = intval($smarty->_wgbar[$smarty->_files[0]]++);

   return $widgets_mdl->load($smarty->_files[0],$solt,isset($params['id'])?$params['id']:null);

}

?>