<?php
if(!defined('PAYMENT_LOADED')){
    define('PAYMENT_LOADED',true);
    class paymentPlugin{

        var $method="post";
        var $charset = 'utf8';
        var $name = null;
        var $logo = null;
        var $version = null;
        var $applyUrl = null;
        var $intro = null;
        var $callbackUrl = null;
        var $orderby = null;
        var $_config = array();
        var $_payment = 0;

        function paymentPlugin(&$system){
            $this->system = &$GLOBALS['system'];
            $sUrl = $this->system->base_url();
            $sUrl = str_replace('plugins/','',$sUrl);
            $this->callbackUrl = $sUrl.'plugins/app/'.get_class($this).'/'.get_class($this).'.php';
            $this->serverCallbackUrl = substr($this->callbackUrl,0,strlen($this->callbackUrl)-3).'server.php';
        }

        function toSubmit(){ return false; }
        function callback(){ return false; }

        function infoPad(){
         $cilent = &$this->system->loadModel('service/apiclient');
         $cilent->url = 'http://sds.ecos.shopex.cn/api.php';
         $cilent->key = '371e6dceb2c34cdfb489b8537477ee1c';
         $payment = $cilent->native_svc("payment.get_all_payments");

         foreach($payment['result_msg'] as $key=>$val){    
             if($val['pay_ident'] == get_class($this))
             $html = $val['pay_contents'];
         
         }
     
         if($this->applyUrl){
                $applyFields['agenturl'] = $this->applyUrl;
                if ($this->applyUrlAgain){
                    $applyFields['agenturlAgain'] = $this->applyUrlAgain;
                }
                $applyFields['payagentname'] = $this->name;
                $applyFields['payagentkey'] = strtoupper(str_replace('pay_','',get_class($this)));
                $applyFields['regIp'] = isset($_SERVER['SERVER_ADDR'])?$_SERVER['SERVER_ADDR']:$_SERVER['HTTP_HOST'];
                $applyFields['domain'] = $this->system->base_url();
                if ($this->applyProp)
                    $applyFields=array_merge($this->applyProp,$applyFields);
                if(is_callable(array(&$this,'applyForm'))){
                    $html .= $this->applyForm($applyFields);
                }
            }
            return $html;
        }

        function getConf($paymentid, $key,$value=null){
            if(count($this->_config)==0){
                $p = &$this->system->loadModel('trading/payment');
                if(!$this->_payment){
                    $payment = $p->getById($paymentid);
                    $this->_payment = $payment['payment'];
                }
                $payment_cfg = $p->getPaymentById($this->_payment);
                $this->_config = unserialize($payment_cfg['config']);
            }
            return $this->_config[$key];
        }
    }
}
?>
