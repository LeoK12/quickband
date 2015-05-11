<?php
function object_cols_type(&$list, &$colArray, &$key_modifier, &$object_modifier, &$type_modifier, &$object, $ident='col',$escape_html=array()){
    $highlight = method_exists($object,'is_highlight');
   foreach($list as $i=>$row){
        foreach($row as $k=>$v){
            if(isset($escape_html[$k]))
                $list[$i][$k] = htmlspecialchars($v);
            if(isset($key_modifier[$k])){
                $key_modifier[$k][$v] = $v;
                $list[$i][$k] = &$key_modifier[$k][$v];
            }elseif(is_array($colArray[$k]['type']) && !is_null($v)&&($colArray[$k]['type'][$v]!=NULL)){
                $list[$i][$k] = &$colArray[$k]['type'][$v];

            }elseif(isset($object_modifier[$colArray[$k]['type']])){
                $object_modifier[$colArray[$k]['type']][$v] = $v;
                $list[$i][$k] = &$object_modifier[$colArray[$k]['type']][$v];

            }elseif(isset($type_modifier[$colArray[$k]['type']])){

                if($k == "money" && isset($row['currency'])){
                    $type_modifier[$colArray[$k]['type']][$v] = $v."-".$row['currency'];
                }
                else{
                    $type_modifier[$colArray[$k]['type']][$v] = $v;

                }
                $list[$i][$k] = &$type_modifier[$colArray[$k]['type']][$v];

            }

            if($highlight)$list[$i]['highlight'] = $object->is_highlight($row);
              
        }

        if($object->hasTag){
            $tagMap[$row[$object->idColumn]] = array();
            $list[$i]['_tag_'] = &$tagMap[$row[$object->idColumn]];
        }
    }
    foreach($key_modifier as $key=>$val){

        if($val){
            $func = 'modifier_'.$key;
            $object->$func($val);
        }
    }

    foreach($type_modifier as $type=>$val){
      if(get_class($object)=='mdl_member'){
        if($val){
            if($type=='bool'){
               continue;
            }else{
               $func = 'type_modifier_'.$type;
               $func($val);
            }
         }
      }else{
         if($val){
               $func = 'type_modifier_'.$type;
               $func($val);
         }
      }
    }
    foreach($object_modifier as $target=>$val){

        if($val){
            list(,$o,$fkey) = explode(':',$target);
            $o = &$object->system->loadModel($o);
            if(!$fkey)$fkey = $o->textColumn;
            if(method_exists($o,'getList')) $rows = $o->getList($o->idColumn.','.$fkey,array($o->idColumn=>$val));
            foreach($rows as $r){
                $object_modifier[$target][$r[$o->idColumn]] = $r[$fkey];
            }
        }
    }

    if($object->hasTag && isset($list[0]['_tag_'])){

        foreach($object->db->select('select t.tag_name,rel_id from sdb_tag_rel r left join sdb_tags t on t.tag_id=r.tag_id where t.tag_type=\''.$object->typeName.'\' and r.rel_id in('.implode(',',array_keys($tagMap)).')') as $tag){
            $tagMap[$tag['rel_id']][] = $tag['tag_name'];
        }

        if($ident&&$ident!='xls'&&$ident!='csv'&&$ident!='txt'){
            $begin = '<b class="tag">';
            $end = '</b>';
        }
        foreach($tagMap as $i=>$r){
            if(isset($r[0])){
                $tagMap[$i] = $begin.implode($end.'&nbsp;'.$begin,$r).$end;
            }else{
                $tagMap[$i] = '-';
            }
        }
     }



}
?>