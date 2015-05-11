<?php
function tpl_input_intbool($params,$ctl){
    $params['type'] = 'radio';
    $value = $params['value'];
    unset($params['value']);
    $id = $params['id']?$params['id']:$ctl->new_dom_id();
    $params['id']=$id.'-t';
    $return = buildTag($params,'input value="1"'.($value==1?' checked="checked"':'')).'<label for="'.$params['id'].__('">是</label>');

    $params['id']=$id.'-f';
    $return .='&nbsp'.buildTag($params,'input value="0"'.($value==0?' checked="checked"':'')).'<label for="'.$params['id'].__('">否</label>');
    return $return;
}