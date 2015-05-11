<?php
require(CORE_DIR.'/kernel.php');
require(CORE_DIR.'/func_ext.php');
class shopdav extends kernel{

    function shopdav(){
        parent::kernel();
        $this->run();
    }

    function run(){
        $path = explode('/',$_GET['dav']);
        $type = array_shift($path);
        $item = array_pop($path);

        list($item_id,$file_type) = explode('.',$item);
        $entity_model = $this->loadModel('system/entity');
        $mime_array = array(
                'xml'=>'text/xml',
                'json'=>'text/plain',
            );

        //header('Content-type: '.$mime_array[$file_type].';charset=utf8');
        echo $entity_model->get_sdf($type,$item_id,$file_type);
    }

}
