<?php
function tpl_function_javascript($params){
    $system   = &$GLOBALS['system'];
    $base_url = $system->base_url();
    if(constant('DEBUG_JS')){
        $script_src.='statics/script_src/'.$params['file'];
    }else{
        $script_src.='statics/script/'.$params['file'];
    }
    if($params['getcontents']){
        return file_get_contents(BASE_DIR.'/'.$script_src);
    }
    return '<script type="text/javascript" src="'.$base_url.$script_src.'"></script>';
    
}


?>
