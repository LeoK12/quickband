<?php
function tpl_compile_modifier_default($attrs,$compiler,$bondle_var_only){
    list($string, $default ) = explode(',',$attrs);
    if($default===''){
        $default = '\'\'';
    }
    if($bondle_var_only){
        $compiler->_end_fix_quote($string);
        eval($s='$rst ='.str_replace('$this->bundle_vars','$compiler->bundle_vars',$string).';');
        if($rst){
            return var_export($rst,1);
        }else{
            return $default;
        }
    }else{
        return '((isset('.$string.') && \'\'!=='.$string.')?'.$string.':'.$default.')';
    }
}
?>