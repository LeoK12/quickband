<?php
class mdl_dataio extends modelFactory{

    var $privateImport = false;
    var $privateExport = false;

    function exporter($type){
        $list = &$this->loadList();
        foreach($list as $k=>$o){
            if(isset($o['methods']['export_rows'])){
                if(defined('DEVELOPING')&&DEVELOPING&&$o['developing']){
                    continue;
                }
                if($o['exportforObjects']){
                    $a = array_flip(explode(',',$o['exportforObjects']));
                    if(isset($a[$type])){
                        $ret[$k] = $o;
                    }
                }else{
                    if(!$this->privateExport){
                        $ret[$k] = $o;
                    }
                }
            }
        }
        return $ret;
    }

    function importer($type){
        $list = &$this->loadList();
        foreach($list as $k=>$o){
            if(isset($o['methods']['import_rows'])){
                if((!defined('DEVELOPING')||!DEVELOPING)&&$o['developing']){
                    continue;
                }
                if($o['importforObjects']){
                    $a = array_flip(explode(',',$o['importforObjects']));
                    if(isset($a[$type])){
                        $ret[$k] = $o;
                    }
                }else{
                    if(!$this->privateImport){
                        $ret[$k] = $o;
                    }
                }
            }
        }
        return $ret;
    }

    function columns($io){
        $mod = &$this->load($io);
        return $mod->columns;
    }

    function &loadList(){
        if(!$this->list){
            if ($handle = opendir(PLUGIN_DIR.'/dataio')) {
                while (false !== ($file = readdir($handle))) {
                    if(preg_match('/^io\.([a-z0-9\_]+)\.php$/i',$file,$match) && is_file(PLUGIN_DIR.'/dataio/'.$file)){
                        $class = 'io_'.$match[1];
                        include(PLUGIN_DIR.'/dataio/'.$file);
                        if(class_exists($class)){
                            $o = new $class;
                            $return[$match[1]] = get_object_vars($o);
                            $return[$match[1]]['methods'] = array_flip(get_class_methods($class));
                        }
                    }
                }
                closedir($handle);
            }
            $this->list = &$return;
        }
        return $this->list;
    }

    function export_begin($io,$keys,$type,$count){
        $this->system->__session_close(false);
        set_time_limit(0);
        $mod = &$this->load($io);
        if(method_exists($mod,'export_begin')){
            $mod->export_begin($keys,$type,$count);
        }
    }

    function import_row($io,&$handle){
        $mod = &$this->load($io);
        if(method_exists($mod,'import_row')){
            return $mod->import_row($handle);
        }
    }

    function import_rows($io,&$handle){
        $mod = &$this->load($io);
        if(method_exists($mod,'import_rows')){
            return $mod->import_rows($handle);
        }
    }

    function &load($io){
        if(!$this->mod[$io]){
            if(include_once(PLUGIN_DIR.'/dataio/io.'.$io.'.php')){
                $class = 'io_'.$io;
                if(class_exists($class)) {
                    $this->mod[$io] = new $class;
                    $mod = &$this->mod[$io];
                    $mod->charset = &$this->system->loadModel('utility/charset');
                } else{
                     return false;
                }
            }else{
                return false;
            }
        }
        return $this->mod[$io];
    }

    function export_rows($io,&$rows){
        $mod = &$this->load($io);
        if(method_exists($mod,'export_rows')){
            $mod->export_rows($rows);
        }
    }

    function export_finish($io){
        $mod = &$this->load($io);
        if(method_exists($mod,'export_finish')){
            $mod->export_finish($keys);
        }
    }

}
?>
