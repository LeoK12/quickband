<?php
function object_filter_parser($filter,$tableAlias=null,$baseWhere=null,&$object){
    $tPre = ($tableAlias?$tableAlias:$object->tableName).'.';
    $where = $baseWhere?$baseWhere:array(1);

    if(isset($filter['price']))
    $filter['price']=floatval($filter['price']);
    if(isset($filter['store']))
    $filter['store']=floatval($filter['store']);
    if(isset($filter['mktprice']))
    $filter['mktprice']=floatval($filter['mktprice']);
    if(isset($filter['cost']))
    $filter['cost']=floatval($filter['cost']);
    if(isset($filter['total_amount']))
    $filter['total_amount']=floatval($filter['total_amount']);
    if(isset($filter['cost_freight']))
    $filter['cost_freight']=floatval($filter['cost_freight']);
    if(isset($filter['advance']))
    $filter['advance']=floatval($filter['advance']);
    if(isset($filter['unreadmsg']))
    $filter['unreadmsg']=floatval($filter['unreadmsg']);
    if(isset($filter['point']))
    $filter['point']=floatval($filter['point']);
    if(isset($filter['author'])&&($filter['author']!='')){
       $filter['author']=$filter['author'];
    }
    if($object->use_recycle){
        if($object->disabledMark=='normal'){
            $where[] =$tPre.'disabled = \'false\'';
        }elseif($object->disabledMark=='recycle'){
            $where[]=$tPre.'disabled = \'true\'';
        }
    }

    if(isset($filter['keywords']) && $filter['keywords']){
        if($object->keywordsColumn){
            $colum[]=$object->keywordsColumn;

        }
        if($object->textColumn){
            $colum[]=$object->textColumn;
        }
        $where[]=$object->wFilter($filter['keywords'],$colum);
    }

    if(isset($filter['tag']) && $tag = $filter['tag']){
        unset($filter['tag']);
        if(is_array($tag)){
            if(count($tag) == 0){
                unset($tag);
            }
        }else{
            $tag = array($tag);
        }
        if($tag){
            foreach($object->db->select('select tag_id  from sdb_tags where tag_name in (\''.implode('\',\'',$tag).'\')') as $row){
                $tag_id[]= $row['tag_id'];
            }
            if(count($tag_id)>0){
                if(constant('DB_OLDVERSION')){
                    $a = array();
                    foreach($object->db->select("select rel_id from sdb_tag_rel where tag_id in (".implode(',',$tag_id).")") as $r){
                        $a[] = $r['rel_id'];
                    }
                    if(count($a)>0){
                        $where[] = "{$object->idColumn} in (".implode(',',$a).")";
                    }
                }else{
                    $where[] = "{$object->idColumn} in (select rel_id from sdb_tag_rel where tag_id in (".implode(',',$tag_id).") )";
                }
            }
        }
    }
    $cols = array_merge($object->searchOptions(),$object->_columns($filter));
    if(isset($filter['_tag_'])){
        $tag = $object->system->loadModel("system/tag");
        $tag_data= $tag->tagList($object->typeName,true,$object->tableName,$object->idColumn,array(),$filter['_tag_']);
        $tbegin =  $object->idColumn." IN (";
        foreach($tag_data as $t_key=>$t_value){
            if($t_value['trel_id']!=""){
                $tmp_id[] = $t_value['trel_id'];
            }
        }
        $tbegin .= implode(",",$tmp_id).")";
        $where[] = $tbegin;
    }

    if(is_array($filter)){

        foreach($filter as $k=>$v){
            if(is_string($v))
                 $v = addslashes($v);
            if(isset($cols[$k])){

                 $ac = array();
                    if($cols[$k]['type']=='time'){
                            if(empty($filter[$k.'_dtup']))$filter[$k.'_dtup']=0;
                            if(empty($filter[$k.'_dtdown']))
                            {
                                $filter[$k.'_dtdown']=0;
                            }else{
                                $filter[$k.'_dtdown']=$filter[$k.'_dtdown']+86400;
                            }
                            $where[] = $k.' >= '.$filter[$k.'_dtup'].' AND '.$k.' <= '.$filter[$k.'_dtdown'];
                    }else if(isset($cols[$k]['filtertype'])&&isset($filter['_'.$k.'_search'])){

                            $where[] = $k.getFilterType($filter['_'.$k.'_search'],$v);
                    }else if(isset($cols[$k]['searchtype'])&&!isset($filter['object_filter'])){
                            $where[] = $k.getFilterType($cols[$k]['searchtype'],$v);
                    }else if(substr($k,0,1)!='_'){

                        if($k!='object_filter'){
                            if($k == 'ship_area'){
                                if(isset($v))
                                $v=explode(':',$v);
                                unset($v[2]);
                                $v=implode(':',$v);

                                $where[] = $tPre.$k.' like \''.$v.'%\'';

                            }
                            elseif(is_array($v)){

                                foreach($v as $m){

                                    if($m!=='_ANY_' && $m!=='' && $m!='_ALL_'){
                                        $ac[] = $cols[$k]['fuzzySearch']?($tPre.$k.' like \'%'.$m.'%\''):($tPre.$k.'=\''.$m.'\'');
                                    }else{
                                        $ac = array();
                                        break;
                                    }
                                }

                                if(count($ac)>0){
                                    $where[] = '('.implode($ac,' or ').')';
                                }
                            }elseif(isset($v)){
                                
                                if(substr($v,0,9)==='mainland:'){
                                    $ex=explode(':',$v);
                                    $result=$ex[0].':'.$ex[1];
                                    $where[] = $tPre.$k.' like \''.$result.'%\'';
                                }else{
                                    $where[] = $tPre.$k.'=\''.$v.'\'';
                                }
                            }
                        }
                    }
                }
        }
    }
    return implode($where,' AND ');
}


function getFilterType($type,$var){
    $FilterArray= array('than'=>' > '.$var,
                        'lthan'=>' < '.$var,
                        'nequal'=>' = \''.$var.'\'',
                        'tequal'=>' = \''.$var.'\'',
                        'sthan'=>' <= '.$var,
                        'bthan'=>' >= '.$var,
                        'has'=>' like \'%'.$var.'%\'',
                        'head'=>' like \''.$var.'%\'',
                        'foot'=>' like \'%'.$var.'\'',
                        'nohas'=>' not like \'%'.$var.'%\'',
                        );
    return $FilterArray[$type];
}