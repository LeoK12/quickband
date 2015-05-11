<?php
function object_insert($data,&$object){
    if(method_exists($object,'pre_insert')){
        $object->pre_insert($data);
    }
    if(method_exists($object,'post_insert')){
        $object->post_insert($data);
    }
    $rs = $object->db->exec('select * from '.$object->tableName.' where 0=1');
    $sql = $object->db->getInsertSQL($rs,$data);
    $cols = $object->getColumns();
    if($object->textColumn){
        $cols[$object->textColumn]['required'] = true;
    }
    foreach($cols as $k=>$p){
        if(!isset($p['default']) && $p['required'] && $p['extra']!='auto_increment'){
            if(!isset($data[$k])){
                trigger_error('<b>'.$p['label'].__('</b> 不能为空！'),E_USER_ERROR);
            }
        }
    }
    if($sql && $object->db->exec($sql)){
        return $object->db->lastInsertId();
    }else{
        return false;
    }
}