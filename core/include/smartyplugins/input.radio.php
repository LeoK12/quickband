<?php
function tpl_input_radio($params,$ctl){

    $params['type'] = 'radio';
    $options = $params['options'];
    $value = $params['value'];
    unset($params['options'],$params['value']);
    $input_tpl = buildTag($params,'input ',true);
    $id_base = $params['id']?$params['id']:$ctl->new_dom_id();
    foreach($options as $k=>$item){
        $id = $id_base.($i++);
        if($value==$k){
            $html .= str_replace('/>',' id="'.$id.'" value="'.htmlspecialchars($k).'" checked="checked" />',$input_tpl);
        }else{
            $html .= str_replace('/>',' id="'.$id.'" value="'.htmlspecialchars($k).'" />',$input_tpl);
        }
        $params['separator'] = $params['separator']?$params['separator']:'<br>';
        $html .= '<label for="'.$id.'">'.htmlspecialchars($item).'</label>'.$params['separator'];
    }

    return $html;
}
