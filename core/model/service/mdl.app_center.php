<?php
if(!class_exists('mdl_apiclient')){
    include_once(dirname(__FILE__).'/mdl.apiclient.php');
}
    class mdl_app_center extends mdl_apiclient{

        function mdl_app_center(){
            $this->key = '371e6dceb2c34cdfb489b8537477ee1c';
            $this->url = 'http://sds.ecos.shopex.cn/api.php';
            parent::mdl_apiclient();
        }

        function get_app_compare($str){
            return $this->native_svc("engine.get_app_version",array("app_key"=>$str));
        }

        function get_tools_status(){
            return $this->native_svc("service.get_tools_status");
        }

        function get_payment_app_compare($str){
            return $this->native_svc("payment.get_pmt_version",array("pay_key"=>$str));
        }

    }


?>