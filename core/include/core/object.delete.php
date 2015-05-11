<?php
function object_delete($filter,&$object){
    if(method_exists($object,'pre_delete')){
        $object->pre_delete($filter);
    }
    if(method_exists($object,'post_delete')){
        $object->post_delete($filter);
    }
    $object->disabledMark = 'recycle';
    $sql = 'delete from '.$object->tableName.' where '.$object->_filter($filter);
    if($object->db->exec($sql)){
        if($object->db->affect_row()){
           return $object->db->affect_row();
        }else{
           return true;
        }
    }else{
         return false;
    }
}