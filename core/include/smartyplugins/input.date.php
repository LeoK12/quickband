<?php
function tpl_input_date($params,$ctl){
    if(!$params['id']){
        $params['id'] = $ctl->new_dom_id();
    }
    if(!$params['type']){
        $params['type'] = 'date';
    }
    if(!$params['vtype']){
        $params['vtype'] = 'date';
    }
    if(is_numeric($params['value'])){
        $params['value'] = mydate('Y-m-d',$params['value']);
    }
	if(isset($params['concat'])){
        $params['name'] .= $params['concat'];
        unset($params['concat']);
	}
    if(!$params['format'] || $params['format']=='timestamp'){
        $prefix = '<input type="hidden" name="_DTYPE_'.strtoupper($params['type']).'[]" value="'.htmlspecialchars($params['name']).'" />';
    }else{
        $prefix = '';
    }

    $params['type'] = 'text';
    $return = buildTag($params,'input class="cal '.$params['class'].'" size="10" maxlength="10" autocomplete="off"');
    return $prefix.$return.'<script>$("'.$params['id'].'").makeCalable();</script>';
}
