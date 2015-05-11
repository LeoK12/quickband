<?php
if(!function_exists('type_modifier_date')){
    require(CORE_INCLUDE_DIR.'/modifiers.php');
}
function action_export($ident,&$controller){
    $addons = &$controller->system->loadModel('system/addons');
    $exporter = $addons->load($ident,'io');
    $exporter->charset = &$controller->system->loadModel('utility/charset');
    $object = &$controller->model;

    if(!class_exists('shopObject')) require('shopObject.php');
    $allCols = $controller->model->getColumns();
    $step = -1;
    $offset=0;
    $cols = $controller->system->get_op_conf('view.'.$controller->object);
    
    if(!$cols){
         $cols = $controller->model->defaultCols;    
    }elseif($cols = explode(',',$cols)){
        $cols = array_flip($cols);
        unset($cols['_tag_']);
        $cols = implode(',',array_flip($cols));
    }
    $cols=str_replace('print_status,','',$cols);
    $type_modifier = array();
    $key_modifier = array();
    $object_modifier = array();
    foreach(explode(',',$cols) as $col){
        if($col=='_cmd' && $_GET['act']=='recycleIndex'){
            continue;
        }
        if(!isset($disabledCols[$col])){
            if(isset($allCols[$col]) && !$allCols[$col]['html']){
                $allCols[$col]['used'] = true;
                $colArray[$col] = &$allCols[$col];
                if(isset($allCols[$col]['sql'])){
                    $sql[] = $allCols[$col]['sql'].' as '.$col;
                }elseif($col=='_tag_'){
                    $sql[] = $controller->idColumn.' as _tag_';
                }else{
                    $sql[] = $col;
                }

                if(method_exists($object,'modifier_'.$col)){
                    $key_modifier[$col] = array();
                }elseif(substr($colArray[$col]['type'],0,7)=='object:'){
                    $object_modifier[$colArray[$col]['type']] = array();
                }elseif(function_exists('type_modifier_'.$colArray[$col]['type']) && $colArray[$col]['type'] != 'money'){
                    $type_modifier[$colArray[$col]['type']] = array();
                }
            }
        }
    }
    $count = $controller->model->count($_POST);
    if($count == $controller->model->count()){
        $step = -1;
    }
    $list = &$controller->model->export($controller->model->getList(implode(',',$sql),$_POST,$offset,$step));
    $allCols = $controller->model->getColumns();

    if(!function_exists('object_cols_type')) require(CORE_INCLUDE_DIR.'/core/object.cols.type.php');
    object_cols_type($list, $colArray, $key_modifier, $object_modifier, $type_modifier, $object, $ident);
    $headCols = array();
    foreach($list[0] as $k=>$v){
        if($k == '_tag_'){
            $headCols[] = '标签('.$k.')';
        }else{
            $headCols[] = $allCols[$k]['label'].'('.$k.')';
        }
    }
    if(method_exists($exporter,'export_begin')){
        $exporter->export_begin($headCols,substr(get_class($controller),4),$count);
    }

    $exporter->export_rows($list);
    if(!$step){
        while($count>($offset+=$step)){
            $list = &$controller->model->export($controller->model->getList(implode(',',$sql),$_POST,$offset,$step));
            object_cols_type($list, $colArray, $key_modifier, $object_modifier, $type_modifier, $object, $ident);
            $exporter->export_rows($list);
        }
    }

    if(method_exists($exporter,'export_finish')){
        $exporter->export_finish();
    }

}
