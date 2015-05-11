<?php
function tpl_input_bool($params,$ctl){
    $params['type'] = 'radio';
    $value = $params['value'];
    unset($params['value']);
    $id = $params['id']?$params['id']:$ctl->new_dom_id();
    $params['id']=$id.'-t';
    $return = buildTag($params,'input value="'.(($params['name']=="is_sec")?"false":"true").'"'.(($value!=='false' && $value!=='0' && $value)?' checked="checked"':'')).'<label for="'.$params['id'].__('">是</label>');

    $params['id']=$id.'-f';
    $return .='&nbsp'.buildTag($params,'input value="'.(($params['name']=="is_sec")?"true":"false").'"'.(($value==='false' || !$value )?' checked="checked"':'')).'<label for="'.$params['id'].__('">否</label>');
    return $return.'<input type="hidden" name="_DTYPE_BOOL[]" value="'.htmlspecialchars($params['name']).'" />';
}