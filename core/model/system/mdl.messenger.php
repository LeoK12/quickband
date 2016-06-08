<?php
/**
 * kernel
 *
 * @package
 * @version $Id: mdl.messenger.php 1429 2008-04-08 05:22:50Z flaboy $
 * @copyright 2003-2007 ShopEx
 * @author ShopEx
 * @license Commercial
 */
//adapted into plugin style by Alex 2008-3-14
require_once('plugin.php');

class mdl_messenger extends plugin{

    var $plugin_type = 'dir';
    var $plugin_name = 'messenger';
    var $prefix = 'messenger.';

    function outbox($sender){
        return $this->db->select('select out_id,tmpl_name,creattime,sendcount,content,subject from sdb_sendbox where sender=\''.$sender.'\'');
    }

    function &_load($sender){
        if(!$this->_sender[$sender]){
            $obj = $this->load($sender);
            $this->_sender[$sender] = &$obj;
            if(method_exists($obj,'getOptions')||method_exists($obj,'getoptions'))
                $obj->config = $this->getOptions($sender,true);
            if(method_exists($obj,'outgoingConfig')||method_exists($obj,'outgoingconfig'))
                $obj->outgoingOptions = $this->outgoingConfig($sender,true);
        }else{
            $obj = &$this->_sender[$sender];
        }
        return $obj;
    }

    function _ready(&$obj){
        if(!$obj->_isReady){
            if(method_exists($obj,'ready')) $obj->ready($obj->config);
            if(method_exists($obj,'finish')){
                if(!$this->_finishCall){
                    register_shutdown_function(array(&$this,'_finish'));
                    $this->_finishCall=array();
                }
                $this->_finishCall[] = &$obj;
            }
            $obj->_isReady = true;
        }
    }

    function _send($sendMethod,$tmpl_name,$target,$data,$type,$title=null){
        $sender = &$this->_load($sendMethod);
        $this->_ready($sender);

        if(!$this->_systmpl){
            $this->_systmpl = &$this->system->loadModel('content/systmpl');
        }

        $content = $this->_systmpl->fetch($tmpl_name,$data);
        $ret = $sender->hasTitle?$sender->send($target,
            $title?$title:$this->loadTitle($type,$sendMethod,'',$data)
            ,$content,$sender->config):$sender->send($target,$content,$sender->config);

        return ($ret || !is_bool($ret));


    }

    function _finish(){
        foreach($this->_finishCall as $obj){
            $obj->finish($obj->config);
        }
    }

    function _target($sender,$contectInfo,$member_id){
        $obj = &$this->_load($sender);
        if(($dataname = $obj->dataname) && $contectInfo[$dataname]){
            return $contectInfo[$dataname];
        }else{
            $row = $this->db->selectrow('select email,member_id,uname,custom,mobile from sdb_members where member_id='.intval($member_id));
            if($dataname){
                return $row[$dataname];
            }elseif($custom = unserialize($row['custom'])){
                return $custom[$sender];
            }else{
                return false;
            }
        }
    }

    /**
     * actionSend
     *
     * @param mixed $type 类型
     * @param mixed $contectInfo  联系数组
     * @param mixed $member_id 会员id
     * @param mixed $data 信息
     * @access public
     * @return void
     */
    function actionSend($type,$data,$member_id=null){
        $actions = $this->actions();
        $senders = $this->getSenders($type);
        $level = $actions[$type]['level'];
        $desc = $actions[$type]['label'];
        foreach($senders as $sender){
            $tmpl_name = 'messenger:'.$sender.'/'.$type;
            $contractInfo = $data;
            if($sender && ($target = $this->_target($sender,$contractInfo,$member_id))){
                if($level < 9){ //队列
                    $this->addQueue($sender,$target,$desc,$data,$tmpl_name,$level,$type);
                }else{ //直接发送
                    $this->_send($sender,$tmpl_name,$target,$data,$type);
                }
            }
        }
    }

    function _useQueue($sender){
        $sender = &$this->_load($sender);
        $s = !$sender->withoutQueue;
        return $s;
    }

    function getSenders($act){
        $ret = $this->system->getConf('messenger.actions.'.$act);
        return explode(',',$ret);
    }

    function saveActions($actions){

        foreach($this->actions() as $act=>$info){
            if(!$actions[$act]){
                $actions[$act] = array();
            }
        }

        foreach($actions as $act=>$call){
            $this->system->setConf('messenger.actions.'.$act,implode(',',array_keys($call)));
        }
        return true;
    }

    /**
     * actions
     * 所有自动消息发送列表，只要触发匹配格式的事件就会发送
     *
     * 格式：
     *            对象-事件 => array(label=>名称 , level=>紧急程度)
     *
     * 如果不存在匹配的事件，则需要手动通过send()方法发送
     *
     * @access public
     * @return void
     */
    function actions(){
        $actions = array(
            'account-lostPw'=>array('label'=>__('会员找回密码'),'level'=>9,'varmap'=>__('用户名&nbsp;<{$uname}>&nbsp;&nbsp;&nbsp;&nbsp;密码&nbsp;<{$passwd}>&nbsp;&nbsp;&nbsp;&nbsp;姓名&nbsp;<{$name}>')),
            'order-shipping'=>array('label'=>__('订单发货时'),'level'=>9,'varmap'=>__('订单号&nbsp;<{$order_id}>&nbsp;&nbsp;&nbsp;&nbsp;实际费用&nbsp;<{$delivery.money}>&nbsp;&nbsp;&nbsp;&nbsp;配送方式&nbsp;<{$delivery.delivery}><br>物流公司&nbsp;<{$ship_corp}>&nbsp;&nbsp;&nbsp;&nbsp;物流单号&nbsp;<{$ship_billno}>&nbsp;&nbsp;&nbsp;&nbsp;收货人姓名&nbsp;<{$delivery.ship_name}><br>收货人地址&nbsp;<{$delivery.ship_addr}>&nbsp;&nbsp;&nbsp;&nbsp;收货人邮编&nbsp;<{$delivery.ship_zip}>&nbsp;&nbsp;&nbsp;&nbsp;收货人电话&nbsp;<{$delivery.ship_tel}><br>收货人手机&nbsp;<{$delivery.ship_mobile}>&nbsp;&nbsp;&nbsp;&nbsp;收货人Email&nbsp;<{$delivery.ship_email}>&nbsp;&nbsp;&nbsp;&nbsp;操作者&nbsp;<{$delivery.op_name}><br>备注&nbsp;<{$delivery.memo}>')),
            'order-create'=>array('label'=>__('订单创建时'),'level'=>9,'varmap'=>__('订单号&nbsp;<{$order_id}>&nbsp;&nbsp;&nbsp;&nbsp;总价&nbsp;<{$total_amount}>&nbsp;&nbsp;&nbsp;&nbsp;物流公司&nbsp;<{$shipping}><br>收货人手机&nbsp;<{$ship_mobile}>&nbsp;&nbsp;&nbsp;&nbsp;收货人电话&nbsp;<{$ship_tel}>&nbsp;&nbsp;&nbsp;&nbsp;收货人地址&nbsp;<{$ship_addr}><Br>收货人Email&nbsp;<{$ship_email}>&nbsp;&nbsp;&nbsp;&nbsp;收货人邮编&nbsp;<{$ship_zip}>&nbsp;&nbsp;&nbsp;&nbsp;收货人姓名&nbsp;<{$ship_name}>')),
            'order-payed'=>array('label'=>__('订单付款时'),'level'=>9,'varmap'=>__('订单号&nbsp;<{$order_id}>&nbsp;&nbsp;&nbsp;&nbsp;付款时间&nbsp;<{$pay_time}>&nbsp;&nbsp;&nbsp;&nbsp;付款金额&nbsp;<{$money}>')),
            'order-returned'=>array('label'=>__('订单退货时'),'level'=>9,'varmap'=>__('订单号&nbsp;<{$order_id}>')),
            'order-refund'=>array('label'=>__('订单退款时'),'level'=>9,'varmap'=>__('订单号&nbsp;<{$order_id}>')),
            'goods-notify'=>array('label'=>__('商品到货通知'),'level'=>6,'varmap'=>__('商品名称&nbsp;<{$goods_name}>&nbsp;&nbsp;&nbsp;&nbsp;会员名称&nbsp;<{$username}>')),
            /*             'goods-replay'=>array('label'=>'商品评论回复','level'=>9), todo */
            'account-register'=>array('label'=>__('会员注册时'),'level'=>9,'varmap'=>__('用户名&nbsp;<{$uname}>&nbsp;&nbsp;&nbsp;&nbsp;email&nbsp;<{$email}>&nbsp;&nbsp;&nbsp;&nbsp;密码&nbsp;<{$passwd}>')),
            'account-chgpass'=>array('label'=>__('会员更改密码时'),'level'=>9,'varmap'=>__('密码&nbsp;<{$passwd}>&nbsp;&nbsp;&nbsp;&nbsp;登录名&nbsp;<{$uname}>&nbsp;&nbsp;&nbsp;&nbsp;用户名<&nbsp;{$uname}>&nbsp;&nbsp;&nbsp;&nbsp;email&nbsp;<{$email}>')),
            /*             'comment-replay'=>array('label'=>'留言回复时','level'=>9,'varmap'=>''), todo */
            /*             'indexorder-pay'=>array('label'=>'前台订单支付','level'=>9), */
            /*             'comment-new'=>array('label'=>'订单生成通知商家','level'=>9), */
            'order-cancel'=>array('label'=>__('订单作废'),'level'=>9,'varmap'=>__('订单号&nbsp;<{$order_id}>')),
        );

        return $actions;
    }

    function addSendBox($data){
        $data['creattime'] = time();
        $rs = $this->db->exec('select * from sdb_sendbox where 0=1');
        $sql = $this->db->getInsertSQL($rs,$data);
        return $this->db->exec($sql);
    }

    function loadTmpl($action,$msg,$lang=''){
        $systmpl = &$this->system->loadModel('content/systmpl');
        return $systmpl->get('messenger:'.$msg.'/'.$action);
    }

    function loadTitle($action,$msg,$lang='',$data=""){

        $tmpArr=$data;
        $title = $this->system->getConf('messenger.title.'.$action.'.'.$msg);

        if($data!=""){
            preg_match_all('/<\{\$(\S+)\}>/iU', $title, $result);

            foreach($result[1] as $k => $v){
               $v=explode('.',$v);
               $data=$tmpArr;

               foreach($v as $key => $val){

                     $data=$data[$val];

                     if(is_array($data))
                     continue ;
                     else{

                         $title = str_replace($result[0][$k],$data,$title);

                     }

                 }
             }

         }

        return $title;
    }

    function addQueue($sender,$target,$title,$data,$tmpl_name,$level=5,$event_name=''){

        if(!$this->_useQueue($sender)){
            $this->_send($sender,$tmpl_name,$target,$data,$event_name,$title);
            return true;
        }


        $sqlData = array(
            'tmpl_name'=>$tmpl_name,
            'level'=>$level,
            'event_name'=>$event_name,
            'title'=>$title,
            'target'=>$target,
            'sender'=>$sender,
            'data'=>$data
        );

        if($count = $this->system->getConf('messenger.stat.'.$senders.'.counts')){
            $sqlData['sender_order'] = $this->system->getConf('messenger.stat.'.$senders.'.time')/$count;
        }
        $rs = $this->db->exec('select * from sdb_msgqueue where 0=1');
        $sql = $this->db->getInsertSQL($rs,$sqlData);
        $this->db->exec($sql);

    }

    function runQueue(){
        $row = $this->db->selectrow("select modified from sdb_cachemgr where cname='MSG_MUTEX'");

            if( time() - $row['modified'] > 900){
                $this->db->exec("replace into sdb_cachemgr (cname,modified) values ('MSG_MUTEX',".time().")");
                register_shutdown_function(array(&$this,'removeMutex'));
                $systmpl = &$this->system->loadModel('content/systmpl');
                foreach($this->db->selectlimit('select queue_id,data,tmpl_name,target,title,event_name,sender from sdb_msgqueue order by level,sender_order',100) as $queue){
                    $queue['data'] = unserialize($queue['data']);
                    if($this->_send($queue['sender'],$queue['tmpl_name'],$queue['target'],$queue['data'],$queue['event_name'],$queue['title'])){
                        $this->db->exec('delete from sdb_msgqueue where queue_id='.$queue['queue_id']);
                    }else{
                        return '';
                    }
                }
            }

   
    }

    function removeMutex(){
        return $this->db->exec("delete from sdb_cachemgr where cname='MSG_MUTEX'");
    }

    function saveContent($action,$msg,$data){
        $systmpl = &$this->system->loadModel('content/systmpl');
        $info = $this->getParams($msg);
        if($info['hasTitle']) $this->system->setConf('messenger.title.'.$action.'.'.$msg,$data['title']);
        return $systmpl->set('messenger:'.$msg.'/'.$action,$data['content']);
    }

    function getQueue($sender){
        return $this->db->selectPager('SELECT queue_id,target,level,event_name,title FROM sdb_msgqueue WHERE sender = \''.$sender.'\'', $nPage, 100);
    }
}
?>
