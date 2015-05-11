<?php
if(!class_exists('mdl_apiclient')){
    include_once(dirname(__FILE__).'/mdl.apiclient.php'); 
}
class mdl_saas extends mdl_apiclient {

    function mdl_saas(){
        $this->key = SAAS_NATIVE_KEY;
        $this->url = SAAS_API_URL;
        parent::modelFactory();
    }
    

    /*域名信息获取*/
    function get_info(){
        $params = array(
            'host_id'=>$this->get_host_id(),
            'ip'=>remote_addr()
        );
        $result = $this->native_svc(SAAS_API_URL,'host.get_info',$params);
        if(!$result){
            $this->debug_msg();
            return __('域名信息读取失败，请稍候再试。');
        }
        if($result['result']=='false'){
            return $this->msg($result['result_msg']);
        }
        return $result['result_msg'];
    }

    function get_host_id(){
        return 1;
    }
    /*取消绑定域名*/
    function del_alias($alias){
        $params = array(
            'host_id'=>'1',
            'alias'=>$alias,
            'ip'=>remote_addr()
        );
        $result = $this->native_svc(SAAS_API_URL,'alias.del_alias',$params);
        if(!$result){
            $this->debug_msg();
            return __('域名信息读取失败，请稍候再试。');
        }
        if($result['result']=='false'){
            return $this->msg($result['result_msg']);
        }
        return true;
        
    }
    /*增加域名绑定*/
    function add_alias($ali_name){
    
        $params = array(
            'host_id'=>'1',
            'alias'=>$ali_name,
            'ip'=>remote_addr()
        );
        $result = $this->native_svc(SAAS_API_URL,'alias.add_alias',$params);
        if(!$result){
            $this->debug_msg();
            return __('域名信息读取失败，请稍候再试。');    
        }
        if($result['result']=='false'){
            return $this->msg($result['result_msg']);
        }
        return true;
    }
    /*取服务端返回的错误数据信息*/
    function msg($mesaage){
        $message_list = array('format_error'=>__('域名格式错误'),
                              'not_exists'=>__('别名不存在'),
                              'del_error'=>__('删除失败'),
                              'add_replication'=>__('重复别名'),
                              'add_error'=>__('添加失败'),
                              'cname_error'=>__('CNAME错误'),
                              'format_error'=>__('域名格式错误'),
                              'add_not_allow'=>__('你已绑定两个域名,无法继续绑定')
                             );
        return $message_list[$mesaage];
    }
    function debug_msg(){
        echo $this->net_result;
    }

    function retry_alias($ali_name){
        $params = array(
            'host_id'=>'1',
            'alias'=>$ali_name,
            'ip'=>remote_addr()
        );
        $result = $this->native_svc(SAAS_API_URL,'alias.retry_alias',$params);
        if(!$result){
            $this->debug_msg();
            return __('域名信息读取失败，请稍候再试。');    
        }
        if($result['result']=='false'){
            return $this->msg($result['result_msg']);
        }
        return true;

    }
}
?>
