<?php
class mdl_data_install extends modelFactory {
    var $error;
    function do_install($sqlContent){
          if($sqlContent){
            foreach($this->db->splitSql($sqlContent) as $sql){
                $sql = str_replace('{shopexdump_table_prefix}',DB_PREFIX,$sql);
                if(!constant("DB_OLDVERSION"))
                    $sql = str_replace('{shopexdump_create_specification}',' DEFAULT CHARACTER SET utf8',$sql);
                else
                    $sql = str_replace('{shopexdump_create_specification}','',$sql);
                if(!$this->db->exec($sql,true)){
                    $this->error='exec '.$sql.' have error';
                    return false;
                }
            }
            return true;
          }
    }
}
?>