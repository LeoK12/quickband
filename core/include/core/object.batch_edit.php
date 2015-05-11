<?php
function object_betch_edit($filter,&$object){
    $ret = $object->getColumns($filter);

    foreach($ret as $k=>$col){
        if(in_array($k,$object->colsColumnEdit)){
            $c[] = "count(DISTINCT $k) as $k";
        }else{
            unset($ret[$k]);
        }
    }

    $r = $object->db->selectrow('select count('.$object->idColumn.') as count from '.$object->tableName.' where '.$object->_filter($filter));
    $rowCount = $r['count'];

    //如果所编辑的条目小于1000，则将获得相同值得列。
    if($rowCount<1000){
        $sql = 'select '.implode(',',$c).' from '.$object->tableName.' where '.$object->_filter($filter);
        $c = array();
        if($r = $object->db->selectrow($sql)){
            foreach($r as $col=>$count){
                if($count<2){
                    $c[] = $col;
                }
            }
            foreach($object->db->selectrow('select '.implode(',',$c).' from '.$object->tableName.' where '.$object->_filter($filter)) as $k=>$v){
                if(substr($ret[$k]['type'],0,5)=='time:'||$ret[$k]['type']=='time'){
                    $options = explode(':',$ret[$k]['type']);
                    array_shift($options);
                    $rows = array($v);
                    $object->modifier_time($rows,$options);
                    $v = $rows[0];
                }
                $ret[$k]['value'] = $v;
            }
        }
    }

    return array('cols'=>$ret,'count'=>$rowCount);
}