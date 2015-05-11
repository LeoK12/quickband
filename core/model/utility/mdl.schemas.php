<?php
class mdl_schemas extends modelFactory{

    function mdl_schemas(){
        parent::modelFactory();
        require(CORE_INCLUDE_DIR.'/datatypes.php');
        $types = array();
        foreach($datatypes as $k=>$v){
            if($v['sql']){
                $types[$k] = $v['sql'];
            }
        }
        $this->all_types = &$types;
    }

    function get_system_schemas($type=false){
        $ret = array();
        $this->_read_dir($ret,'',$type);
        return $ret;
    }

    function _read_dir(&$ret,$path='',$plugin_dir=false){
        $read_path = CORE_DIR.'/schemas/'.$path;
        if($plugin_dir){
            $read_path  = PLUGIN_DIR.'/app/'.$plugin_dir.'/dbschema/'.$path;
        }
        if ($handle = opendir($read_path)) {
            while (false !== ($file = readdir($handle))) {
                if($file{0}!='.'){
                    if(is_dir($read_path.$file)){
                        $this->_read_dir($ret,$path.$file.'/');
                    }elseif(substr($file,-4,4)=='.php'){
                        $ret[substr($file,0,-4)]=$path.$file;
                    }
                }
            }
            closedir($handle);
        }
    }

    function &load_array($define){
        $define['pkeys'] = array();
        foreach($define['columns'] as $k=>$v){
            $define['columns'][$k] = $this->_prepare_column($v);
            if(isset($v['pkey']) && $v['pkey']){
                $define['pkeys'][$k] = $k;
                unset($v['pkey']);
            }
        }
        return $define;
    }

    function load($schema_file,$plugin_dir=false){
        $table_name = basename($schema_file,'.php');
        if(!isset($this->_table_def[$table_name])||$plugin_dir){
            include($schema_file);
            return $this->_table_def[$table_name] = &$this->load_array($db[$table_name]);
        }else{
            return $this->_table_def[$table_name];
        }
    }

    function get_column_define($v){
        $r = $v['type'];
        if(isset($v['required']) && $v['required']){
            $r.=' not null';
        }
        if(isset($v['default'])){
            if($v['default']===null){
                $r.=' default null';
            }elseif(is_string($v['default'])){
                $r.=' default \''.$v['default'].'\'';
            }else{
                $r.=' default '.$v['default'];
            }
        }
        if(isset($v['extra'])){
            $r.=' '.$v['extra'];
        }
        return $r;
    }

    function get_sql($table_name,$define){
        $rows = array();
        foreach($define['columns'] as $k=>$v){
            $rows[] = '`'.$k.'` '.$this->get_column_define($v);
        }
        if($define['pkeys']){
            $rows[] = 'primary key ('.implode(',',$define['pkeys']).')';
        }
        if(is_array($define['index'])){
            foreach($define['index'] as $key=>$value){
                $rows[] = 'INDEX '.$key.'(`'
                .implode('`,`',$value['columns']).'`)';
            }
        }

        $sql = 'CREATE TABLE `'.$this->db->prefix.$table_name."` (\n\t".implode(",\n\t",$rows)."\n)";
        $engine = isset($define['engine'])?$define['engine']:'MyISAM';
        if($this->dbver == 3){
            $sql.= 'type = '.$engine.';';
        }else{
            $sql.= 'ENGINE = '.$engine.' DEFAULT CHARACTER SET utf8;';
        }
        return $sql;
    }

    function get_insert_sql($schema_file){
        $define = $this->load($schema_file);
        $table_name = basename($schema_file,'.php');
        return $this->get_sql($table_name,$define);
    }

    function _prepare_column($col_set){
        if(is_array($col_set['type'])){
            $col_set['type'] = 'enum(\''.implode('\',\'',array_keys($col_set['type'])).'\')';
        }elseif(substr($col_set['type'],0,7)=='object:'){
            list(,$objname,$fkey) = explode(':',$col_set['type']);
            $obj = &$this->system->loadModel($objname);
            $def = $this->load(CORE_DIR.'/schemas/'.substr($obj->tableName,4).'.php');
            if(!$fkey)$fkey = $obj->idColumn;
            $col_set['type'] = $def['columns'][$fkey]['type'];
        }elseif(substr($col_set['type'],0,6)=='table:'){
            list(,$tablename,$columns) = explode(':',$col_set['type']);

            $def = $this->load(CORE_DIR.'/schemas/'.$tablename.'.php');
            if(!$columns){
                $col_set['type'] = $def['columns'][key($def['columns'])]['type'];
            }else{
                $col_set['type'] = $def['columns'][$columns]['type'];
            }
        }elseif(isset($this->all_types[$col_set['type']])){
            $col_set['type'] = $this->all_types[$col_set['type']];
        }

        if(substr(trim($col_set['type']),-4,4)=='text'){
            unset($col_set['default']);
        }else{
            //int
            $col_set['type'] = str_replace('integer','int',$col_set['type']);
            if(false===strpos($col_set['type'],'(')){
                $int_length = 0;
                if(false!==strpos($col_set['type'],'tinyint')){
                    $int_length = 4;
                }elseif(false!==strpos($col_set['type'],'smallint')){
                    $int_length = 6;
                }elseif(false!==strpos($col_set['type'],'mediumint')){
                    $int_length = 9;
                }elseif(false!==strpos($col_set['type'],'bigint')){
                    $int_length = 20;
                }elseif(false!==strpos($col_set['type'],'int')){
                    $int_length = 11;
                }
                if($int_length){
                    if($int_length<20 && false!==strpos($col_set['type'],'unsigned')){
                        $int_length--;
                    }
                    $col_set['type'] = str_replace('int','int('.$int_length.')',$col_set['type']);
                }
            }
        }
        return $col_set;
    }

    function get_define($tbname){
        if($this->db->exec("select * from ".$this->db->prefix.$tbname)){
            $rows = @$this->db->select('SHOW COLUMNS FROM '.$this->db->prefix.$tbname);
            $columns = array();
            $pkeys = array();
            if($rows){
                foreach($rows as $c){
                    $columns[$c['Field']] = array(
                        'type'=>$c['Type'],
                        'default'=>$c['Default'],
                        'required'=>!($c['Null']=='YES'),
                    );
                    if(strpos($c['Key'],'PRI')!==false){
                        $pkeys[$c['Field']] = $c['Field'];
                    }
                    //Key,Extra
                }
            }
            return array('columns'=>$columns,'pkeys'=>$pkeys);
        }else{
            return false;
        }
    }

    //todo index change
    function diff($tbname,$schema,$plugin_dir=false){
        $diff=array();
        $new = array();
        $alter = array();
        $tbdefine = $this->load(CORE_DIR.'/schemas/'.$schema);
        if($plugin_dir){
            $tbdefine = $this->load(PLUGIN_DIR.'/app/'.$plugin_dir.'/dbschema/'.$schema,$plugin_dir);
        }
        if($old_define = $this->get_define($tbname)){
            $cp_old=$old_define;
            $last = null;
            foreach($tbdefine['columns'] as $key=>$col){
                if(!isset($old_define['columns'][$key])){
                    if(false!==strpos($col['extra'],'auto_increment')){
                        $drop_pkey = true;
                    }
                    $new[]='ADD COLUMN `'.$key.'` '.$this->get_column_define($col).' '.($last?('AFTER '.$last):'FIRST');
                }elseif(($old_define['columns'][$key]['type'] != $col['type'])
                    || ($old_define['columns'][$key]['default'] != $col['default'])){
                            $alter[]='MODIFY COLUMN `'.$key.'` '.$this->get_column_define($col);
                }elseif($old_define['columns'][$key]['required'] != $col['required']){
                    if($col['required']){
                        if(!isset($col['default'])){
                            $default="''";
                        }else{
                            $default=$col['default'];
                        }
                        $diff[] = "update {$this->db->prefix}{$tbname} set `{$key}`={$default} where `{$key}`=null;\n";
                    }
                    $alter[]='MODIFY COLUMN `'.$key.'` '.$this->get_column_define($col);
                }
                unset($cp_old['columns'][$key]);
                $last = $key;
            }
            if(is_array($cp_old['columns'])){
                foreach($cp_old['columns'] as $key=>$value){
                    foreach($tbdefine['index']['columns'] as $index_value){
                        if(strpos($key,$index_value)!==false){
                            $diff[]="ALTER TABLE {$this->db->prefix}{$tbname} DROP INDEX {$tbdefine['index']['name']}";
                        }
                    }
                    if($value['required']){
                        $diff[]="ALTER TABLE {$this->db->prefix}{$tbname} MODIFY `{$key}` {$value['type']} NULL";
                    }
                }

            }
            unset($cp_old);
            $alter = array_merge($alter,$new);
            $old_table_has_pkey = count($old_define['pkeys'])>0;
            if(!$drop_pkey){
                foreach($tbdefine['pkeys'] as $pkeys){
                    if(isset($old_define['pkeys'][$pkeys])){
                        unset($old_define['pkeys'][$pkeys]);
                    }else{
                        $drop_pkey = true;
                        break;
                    }
                }
                $drop_pkey = $old_define['pkeys'];
            }
            if($drop_pkey){
                $alter[]='ADD PRIMARY KEY ('.implode(',',$tbdefine['pkeys']).')';
            }
            if($drop_pkey && $old_table_has_pkey){
                array_unshift($alter,'DROP PRIMARY KEY');
            }

            if($alter){
                $diff[]='ALTER IGNORE TABLE `'.$this->db->prefix.$tbname.'` '.implode(",\n\t",$alter);
            }
        }else{
            $diff[]= $this->get_sql($tbname,$tbdefine);
        }

        return $diff;

    }

}
