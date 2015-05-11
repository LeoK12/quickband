<?php
function tpl_input_gender($params,$ctl){
    $params['type'] = 'radio';
    $value = $params['value'];
    unset($params['value']);
    $id = $params['id']?$params['id']:$ctl->new_dom_id();
    $params['id']=$id.'-m';
    $return = buildTag($params,'input value="male"'.($value=='male'?' checked="checked"':'')).'<label for="'.$params['id'].__('">男</label>');

    $params['id']=$id.'-fm';
    $return .='&nbsp'.buildTag($params,'input value="female"'.($value=='female'?' checked="checked"':'')).'<label for="'.$params['id'].__('">女</label>');
    return $return;
}