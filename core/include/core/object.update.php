<?php
if(!function_exists('db_quotevalue')){
    require(CORE_INCLUDE_DIR.'/core/db.tools.php');
}
function object_update($data,$filter,&$object){

    if(count((array)$data)==0){
        return true;
    }

    $result = $object->db->exec('select * from '.$object->tableName.' where 0=1');
    $col_count = mysql_num_fields($result['rs']);
    $columnsList = &$object->_columns();

    for($i=0;$i<$col_count;$i++){
        $column = mysql_fetch_field($result['rs'],$i);
        if(isset($data[$column->name])){
            if($column->type=='unknown'){
                $column->type = 'real'; //PHP_BUG http://bugs.php.net/bug.php?id=36069
            }

            if(!$columnsList[$column->name]['default'] && $columnsList[$column->name]['required'] && $data[$column->name]===null){

                trigger_error($columnsList[$column->name]['label'].'('.$column->name.__(')不能为空。'),E_USER_WARNING);
                $GLOBALS['php_errormsg'] = $php_errormsg;
                return false;
            }
            if($columnsList[$column->name]['vtype']=='positive' && $data[$column->name]<0){
                trigger_error($columnsList[$column->name]['label'].'('.$column->name.__(')一定要为正数。'),E_USER_WARNING);
                $GLOBALS['php_errormsg'] = $php_errormsg;
                return false;
            }
            if($columnsList[$column->name]['default']&&$data[$column->name]===""){
                $data[$column->name]=$columnsList[$column->name]['default'];
            }
            $UpdateValues[] ='`'.$column->name.'`='.db_quotevalue($object->db,$data[$column->name],$column->type);
        }
    }


    if(count($UpdateValues)>0){
        $sql = 'update '.$object->tableName.' set '.implode(',',$UpdateValues).' where '.$object->_filter($filter);

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
}