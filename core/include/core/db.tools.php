<?php
function db_get_update_sql(&$db,&$rs, $data, $InsertIfNoResult = false,$insertData=null,$ignore=false){
    if(!$rs['rs']){
        trigger_error('GetUpdateSQL: '.$rs['sql'].' error :'.mysqli_error($db), E_USER_WARNING);
    }
    mysqli_data_seek($rs['rs'],0);
    $row = mysqli_fetch_assoc($rs['rs']);
    if($InsertIfNoResult && !$row){
        return db_get_insert_sql($db,$rs,$data);
    }

    if (preg_match('/FROM\s+([]0-9a-z_:"`.@[-]*)/is', $rs['sql'], $tableName)){
        $tableName = $tableName[1];
    }

    if(is_object($data)){
        $data = get_object_vars($data);
    }

    foreach($data as $key=>$value){
        $data[strtolower($key)]=$value;
    }

    $UpdateValues = array();
    $col_count = mysqli_num_fields($rs['rs']);
    for($i=0;$i<$col_count;$i++) {
        $column = mysqli_fetch_field($rs['rs']);
        if(array_key_exists($column->name, $data) && ($ignore || $data[$column->name]!==$row[$column->name] || $column->type == 'bool')){
            if(is_array($data[$column->name]) || is_object($data[$column->name])){
                if(serialize($data[$column->name])==$row[$column->name]){
                    continue;
                }
            }
            $UpdateValues[] ='`'.$column->name.'`='.db_quotevalue($db,$data[$column->name],$column->type);
        }
    }
    mysqli_field_seek($rs['rs'],0);
    if (count($UpdateValues)>0) {
        $whereClause = db_whereClause($rs['sql']);
        $UpdateValues=implode(',',$UpdateValues);
        $sql = 'UPDATE `'.$tableName.'` SET '.$UpdateValues;
        if (strlen($whereClause) > 0)
            $sql .= ' WHERE '.$whereClause;
        return $sql;
    } else {
        return '';
    }
}

function db_get_insert_sql($db,&$rs, &$data,$autoup=false) {
    if(!$rs['rs']){
        trigger_error('GetInsertSQL: '.$rs['sql'].' error :'.mysqli_error($db), E_USER_WARNING);
        return false;
    }
    mysqli_field_seek($rs['rs'],0);
    if(is_object($data)){
        $data = get_object_vars($data);
    }

    foreach($data as $key=>$value){
        $data[strtolower($key)]=$value;
    }
    if (preg_match('/FROM\s+([]0-9a-z_:"`.@[-]*)/is', $rs['sql'], $tableName)){
        $tableName = $tableName[1];
    }

    if($autoup){
        $keyColumn = mysqli_fetch_field($rs['rs']);
        if(!$data[strtolower($keyColumn->name)]){
            $rs = $db->exec('SELECT MAX('.$keyColumn->name.') AS keyid FROM '.$tableName);
            $result = $db->selectrow('SELECT MAX('.$keyColumn->name.') AS keyid FROM '.$tableName);
            $data[$keyColumn->name]= $result['keyid'] + 1;
        }
    }
    $insertValues = array();
    $col_count = mysqli_num_fields($rs['rs']);
    for($i=0;$i<$col_count;$i++) {
        $column = mysqli_fetch_field($rs['rs']);

        if(isset($data[$column->name])){

            $insertValues[$column->name] = db_quotevalue($db,$data[$column->name],$column->type);


        }
    }
    $strValue = implode(',',$insertValues);
    $strFields = implode('`,`',array_keys($insertValues));
    mysqli_field_seek($rs['rs'],0);
   return 'INSERT INTO `'.$tableName.'` ( `'.$strFields.'` ) VALUES ( '.$strValue.' )';
}

function db_quotevalue(&$db,$value,$valuedef){
    if(null===$value){
        return 'null';
    }

    switch($valuedef){
    case 'bool':
        return (strtolower($value)!='false' && $value || (is_int($value) && $value>0))?'true':'false';
        break;

    case 'real':
    case 'int':
        $value = trim($value);
        if($value===''){
            return 'null';
        }else{
            return $value;
        }
        break;

    default:
        if(is_array($value)){
            return $db->quote(serialize($value));
        }else{
            return $db->quote(trim($value));
        }
        break;
    }
}


function db_whereClause($queryString){

    preg_match('/\sWHERE\s(.*)/is', $queryString, $whereClause);

    $discard = false;
    if ($whereClause) {
        if (preg_match('/\s(ORDER\s.*)/is', $whereClause[1], $discard));
        else if (preg_match('/\s(LIMIT\s.*)/is', $whereClause[1], $discard));
        else preg_match('/\s(FOR UPDATE.*)/is', $whereClause[1], $discard);
    } else
        $whereClause = array(false,false);

    if ($discard)
        $whereClause[1] = substr($whereClause[1], 0, strlen($whereClause[1]) - strlen($discard[1]));
    return $whereClause[1];
}
