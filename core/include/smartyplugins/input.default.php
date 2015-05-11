<?php
function tpl_input_default($params,$ctl){
    $ignore = array(
            'password'=>1,
            'file'=>1,
            'hidden'=>1,
        );
    if(!isset($ignore[$params['type']])){
        if(!isset($params['vtype'])){
            $params['vtype'] = $params['type'];
        }
        $params['type'] = 'text';
    }
    if(isset($params['emptytext'])){
        if(!$params['value']){
            $params['value'] = $params['emptytext'];
            $params['class'] = 'emptytext';
        }
        $params['onclick'] = '$(this).clearEmptyText()';
    }
    return buildTag($params,'input autocomplete="off" class="x-input '.$params['class'].'"');
}