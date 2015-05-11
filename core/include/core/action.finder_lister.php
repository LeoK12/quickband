<?php
if(!function_exists('type_modifier_date')){
    require(CORE_INCLUDE_DIR.'/modifiers.php');
}
function action_finder_lister(&$controller){
    $finder = &$controller->_vars['_finder'];
    $object = &$controller->model;
   
    if($_GET['act']!='recycleIndex' && ($views=$controller->_views())){
        $finder['views'] = array_keys($views);
        if(!$_GET['view']){
            $_GET['view'] = 0;
        }
 
        $finder['params'] = array_merge((array)$finder['params'],(array)$views[$finder['views'][$_GET['view']+0]]);
    }

    if($_GET['p']&&get_class($controller)==='ctl_articles'){
          $finder['params'] = array('node_id'=>$_GET['p'][0]);
    }

    if($finder['current_view'] = $_POST['lister']){
        $controller->system->set_op_conf('lister.'.$controller->object,$finder['current_view']);
    }else{
        $finder['current_view'] = $controller->system->get_op_conf('lister.'.$controller->object);
    }
    if(!$finder['current_view']){
        $finder['current_view'] = $controller->default_lister;
    }

    $cols = $finder['listViews'][$finder['current_view']]['cols'];
    if(!$cols){
       $cols = $controller->system->get_op_conf('view.'.$controller->object);
    }
    if(!$cols) $cols = $controller->finder_default_cols?$controller->finder_default_cols:$object->defaultCols;
    if(isset($_POST['_finder']['orderBy'])){
        $finder['order'] = array($_POST['_finder']['orderBy'],$_POST['_finder']['orderType']);
    }else{
        $finder['order'] = $object->defaultOrder;
    }

    if(isset($_POST['plimit']) && $_POST['plimit']){
        $controller->system->set_op_conf('lister.pagelimit',$_POST['plimit']);
        $finder['plimit'] = $_POST['plimit'];
    }else{
        $finder['plimit'] = $controller->system->get_op_conf('lister.pagelimit');
        if(!$finder['plimit'])$finder['plimit'] = 20;
    }

    $finder['plimit_in_sel'] = array(100,50,20,10);

    $finder['count'] = $object->count($finder['params']);
    $totalPages = ceil($finder['count']/$finder['plimit']);

    $page=$_GET['page']?$_GET['page']:1;
    if($page <0 || ($page >1 && $page > $totalPages)){
        $page = 1;
    }

    $allCols = &$object->getColumns($finder['params']);
    if($object->hasTag){
        $allCols = array_merge(array('_tag_'=>array('label'=>__('标签'),'width'=>150,'noOrder'=>true)),$allCols);
    }

    $modifiers = array();
    $col_width_set = $object->system->get_op_conf('colwith.'.$_GET['ctl']);
    $type_modifier = array();
    $key_modifier = array();
    $object_modifier = array();
    foreach(explode(',',$cols) as $col){
        if($col=='_cmd' && $_GET['act']=='recycleIndex'){
            continue;
        }
        if(isset($allCols[$col])){
            $colArray[$col] = &$allCols[$col];
            if($allCols[$col]['escape_html']){
                $escape_html[$col] = array();
            }
            if(method_exists($object,'modifier_'.$col)){
                $key_modifier[$col] = array();
            }elseif(substr($colArray[$col]['type'],0,7)=='object:'){
                $object_modifier[$colArray[$col]['type']] = array();
            }elseif(function_exists('type_modifier_'.$colArray[$col]['type'])){
                $type_modifier[$colArray[$col]['type']] = array();
            }

            if(isset($col_width_set[$col]))$colArray[$col]['width'] = $col_width_set[$col];
            if($allCols[$col]['html']){
                $sql[] = '1 as '.$col;
                $allCols[$col]['readonly'] = 1;
            }elseif(isset($allCols[$col]['sql'])){
                $sql[] = $allCols[$col]['sql'].' as '.$col;
            }elseif($col=='_tag_'){
                $sql[] = $object->idColumn.' as _tag_';
            }else{
                $sql[] = $col;
            }
        }
    }

    if(!isset($colArray[$object->idColumn])) array_unshift($sql,$object->idColumn);
    if($finder['params']===-1){
        $list = array();
    }else{
        set_error_handler('object_finder_errhandler',E_USER_WARNING);
        $GLOBALS['last_error'] = null;

        $list = $object->getList(implode(',',$sql),$finder['params'],($page-1)*$finder['plimit'],$finder['plimit'],$finder['order']);
        restore_error_handler();
        
        if($GLOBALS['last_error']){
            $controller->_vars['sql_error'] = $GLOBALS['last_error'];
            $controller->_vars['items'] = false;
        }elseif($list===false){
            $controller->_vars['items'] = false;
        }elseif($list){
            if(!function_exists('object_cols_type')) require(CORE_INCLUDE_DIR.'/core/object.cols.type.php');
            object_cols_type($list, $colArray, $key_modifier, $object_modifier, $type_modifier, $object,'col',$escape_html);
        }


        $controller->_vars['items'] = array(
            'list'=>&$list,
            'cols'=>&$colArray,
        );

        $finder['detail_url'] = method_exists($controller,'_detail');
        $finder['pager'] = array(
            'current'=> $page,
            'total'=> ceil($finder['count']/$finder['plimit']),
            'link'=> 'javascript:'.$finder['var'].'.page(_PPP_)',
            'token'=> '_PPP_'
        );
    }
}

function object_finder_errhandler($errno, $errstr, $errfile, $errline){
    restore_error_handler();
    if(strpos($errfile,'AloneDB.php')){
        $GLOBALS['last_error'] = $errstr;
    }else{
        return false;
    }
}