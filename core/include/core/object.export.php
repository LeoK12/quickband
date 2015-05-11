<?php
function &object_export($list,&$object){
    $colarray = $object->getColumns();

    foreach($list as $i=>$row){
        foreach($row as $k=>$v){
            if($k=="_tag_"){
                $v = strip_tags($v);
            }
            if($colarray[$k]['type'] && !is_null($v) && $colarray[$k]['export'] != 'false'){
                $modifier_key = $colarray[$k]['options']?$colarray[$k]['type'].'|'.serialize($colarray[$k]['options']):$colarray[$k]['type'];
                $modifiers[$modifier_key][$v] = $v;
                $list[$i][$k] = &$modifiers[$modifier_key][$v];
            }
        }
    }

    foreach($modifiers as $type=>$rows){
        $params = explode('|',$type,2);
        $options = explode(':',$params[0]);
        if(count($params)>1){
            $options['options'] = unserialize($params[1]);
        }
        if(method_exists($object,$func = 'exporter_'.$type_part)){
            $object->$func($modifiers[$type],$options);
        }elseif(method_exists($object,$func = 'modifier_'.$type_part)){
            $object->$func($modifiers[$type],$options);
        }
    }

    return $list;
}