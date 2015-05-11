<?php
/*
 * Smarty plugin
 * -------------------------------------------------------------
 * File:     compiler.tplheader.php
 * Type:     compiler
 * Name:     tplheader
 * Purpose:  Output header containing the source file name and
 *           the time it was compiled.
 * -------------------------------------------------------------
 */
function tpl_compiler_math($attrs, &$smarty) {
    if(($attrs['equation']{0}=='\'' || $attrs['equation']{0}=='"') && $attrs['equation']{0}==$attrs['equation'][strlen($attrs['equation'])-1]){
        $equation = $attrs['equation'];
    }else{
        $equation = '"'.$attrs['equation'].'"';
    }

    $format = $attrs['format'];
    $assign = $attrs['assign'];

    unset($attrs['equation'],$attrs['format'],$attrs['assign']);

    foreach($attrs as $k=>$v){
        $re['/([^a-z])'.$k.'([^a-z])/i'] = '$1('.$v.')$2';
    }
    $equation = substr(preg_replace(array_keys($re),array_values($re),$equation),1,-1);
    if($format){
        $equation = 'sprintf('.$format.','.$equation.')';
    }
    if($assign){
        $equation = '$this->_vars['.$assign.']='.$equation;
    }
    return 'echo ('.$equation.');';
}
?>