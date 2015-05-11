<?php
function tpl_compile_modifier_cur($attrs,&$compile) {
    //todo 需要将货币汇率也缓存
    if(!strpos($attrs,',') || false!==strpos($attrs,',')){
        $compile->_head_stack['$CURRENCY = &$this->system->loadModel(\'system/cur\')']=1;
        return $attrs = '$CURRENCY->changer('.$attrs.')';
    }
}
?>