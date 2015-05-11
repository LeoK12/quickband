<?php
define('PAY_FAILED',-1);
define('PAY_TIMEOUT',0);
define('PAY_SUCCESS',1);
define('PAY_CANCEL',2);
define('PAY_ERROR',3);
define('PAY_PROGRESS',4);
define('PAY_INVALID',5);
define('PAY_MANUAL',0);

###IP 要用ip2long转化后存储，反之取出时要用long2ip还原###

require_once('shopObject.php');

class mdl_payment extends shopObject{
    //var $__setting;
    var $M_OrderId;        //    订单的id---支付流水号
    var $M_OrderNO;        //    订单号
    var $M_Amount;        //    订单金额        小数点后保留两位，如10或12.34
    var $M_Def_Amount;        //    订单本位币金额        小数点后保留两位，如10或12.34
    var $M_Currency;    //    支付币种
    var $M_Remark;        //    订单备注
    var $M_Time;        //    订单生成时间
    var $M_Language;    //    语言选择        表示商家使用的页面语言
    var $R_Name;        //    收货人姓名    订单支付成功后货品收货人的姓名
    var $R_Address;        //    收货人住址    订单支付成功后货品收货人的住址
    var $R_Postcode;    //    收货人邮政编码    订单支付成功后货品收货人的住址所在地的邮政编码
    var $R_Telephone;    //    收货人联系电话    订单支付成功后货品收货人的联系电话
    var $R_Mobile;        //    收货人移动电话    订单支付成功后货品收货人的移动电话
    var $R_Email;        //    收货人电子邮件地址    订单支付成功后货品收货人的邮件地址
    var $P_Name;        //    付款人姓名    支付时消费者的姓名
    var $P_Address;        //    付款人住址    进行订单支付的消费者的住址
    var $P_PostCode;    //    付款人邮政编码        进行订单支付的消费者住址的邮政编码
    var $P_Telephone;    //    付款人联系电话     进行订单支付的消费者的联系电话
    var $P_Mobile;        //    付款人移动电话     进行订单支付的消费者的移动电话
    var $P_Email;        //    付款人电子邮件地址     进行订单支付的消费者的电子邮件地址

//START
    var $adminCtl = 'order/payment';
    var $idColumn='payment_id';
    var $textColumn = 'payment_id';
    var $defaultCols = 'payment_id,money,currency,order_id,paymethod,account,bank,status,t_end';
    var $defaultOrder = array('payment_id','DESC');
    var $tableName = 'sdb_payments';
    var $plugin_case = LOWER_CASE;

    function getColumns(){
        $ret = parent::getColumns();
        //$ret['_cmd'] = array('label'=>__('操作'),'width'=>70,'html'=>'payment/finder_command.html');
        //支付类型
        $ret['pay_type']['default'] = '';
        //支付状态
        $ret['status']['default'] = '';
        return $ret;
    }
    function getFilter($p){
        $return['payment']=$this->getMethods();
        return $return;
    }

    function edit($aDetail){
        $rPayment=$this->db->query('select * from sdb_payments where payment_id='.$aDetail['payment_id']);
        unset($aDetail['payment_id']);
        $sSql=$this->db->GetUpdateSQL($rPayment,$aDetail);
        return (!$sSql || $this->db->exec($sSql));
    }

    function getOrderBillList($orderid){
        return $this->db->select('SELECT * FROM sdb_payments WHERE order_id = '.$orderid);
    }

//END

    function getMethods($type=''){
        if($type=="online"){
            $sql = ' AND pay_type NOT IN(\'OFFLINE\',\'DEPOSIT\')';
        }
        return $this->db->select('SELECT * FROM sdb_payment_cfg WHERE disabled = \'false\''.$sql.' order by orderlist desc',PAGELIMIT);
    }

    function getAllMethods($type=''){
        return $this->db->select('SELECT * FROM sdb_payment_cfg  order by orderlist desc',PAGELIMIT);
    }

    function loadMethod($payPlugin){
        if(file_exists(PLUGIN_DIR.'/app/pay_'.$payPlugin.'/pay_'.$payPlugin.'.php')){
            require_once(PLUGIN_DIR.'/app/pay_'.$payPlugin.'/pay_'.$payPlugin.'.php');
            $className = 'pay_'.$payPlugin;
            $method = new $className($this->system);
            return $method;
        }
    }

    function searchOptions(){
        $arr = parent::searchOptions();
        return array_merge($arr,array(
                'uname'=>__('会员用户名'),
                'username'=>__('操作员'),
            ));
    }

    function _filter($filter){
        $where = array(1);
        if(!empty($filter['payment_id'])){
            if(is_array($filter['payment_id'])){
                if($filter['payment_id'][0] != '_ALL_'){
                    if(!isset($filter['payment_id'][1])){
                        $where[] = 'payment_id = '.$this->db->quote($filter['payment_id'][0]).'';
                    }else{
                        $aOrder = array();
                        foreach($filter['payment_id'] as $payment_id){
                            $aOrder[] = 'payment_id='.$this->db->quote($payment_id).'';
                        }
                        $where[] = '('.implode(' OR ',$aOrder).')';
                        unset($aOrder);
                    }
                }
            }else{
                $where[] = 'payment_id = '.$this->db->quote($filter['payment_id']).'';
            }
            unset($filter['payment_id']);
        }

        if(array_key_exists('uname', $filter)&&trim($filter['uname'])!=''){
            $user_data = $this->db->select("select member_id from sdb_members where uname = '".addslashes($filter['uname'])."'");

            foreach($user_data as $tmp_user){
                $now_user[] = $tmp_user['member_id'];
            }
            $where[] = 'member_id IN (\''.implode("','",$now_user).'\')';
            unset($filter['uname']);
        }else{
            if(isset($filter['uname']))
                unset($filter['uname']);
        }
        if(isset($filter['username'])&&trim($filter['username'])){
            $op_data = $this->db->select("select op_id from sdb_operators where username = '".addslashes($filter['username'])."'");
            foreach($op_data as $tmp_op){
                $now_op[] = $tmp_op['op_id'];
            }
            $where[] = 'op_id IN (\''.implode("','",$now_op).'\')';
            unset($filter['username']);
        }else{
            if(isset($filter['username']))
                unset($filter['username']);
        }
        return parent::_filter($filter).' and '.implode(' AND ',$where);
    }


    function getPlugins(){
        $dir = PLUGIN_DIR.'/app/';//支付方式 插件  2010-3-29
        $appmgr = &$this->system->loadModel('system/appmgr');
        $disabled = 0;
        if (file_exists($dir.'disabled_payments.txt')){
            $disabledPayment = file($dir.'disabled_payments.txt');
            if (count($disabledPayment)>0){
                foreach($disabledPayment as $k => $v){
                    $disabledPayment[$k]=trim($v);
                }
                $disabled=1;
            }
        }
        if ($handle = opendir($dir)) {
            $i=50000;
            while (false !== ($app = readdir($handle))) {
                if(is_dir($dir.$app) && substr($app,0,4)=='pay_'){
                    $startApp = $appmgr->getAppName($app);
                    if ($handles = opendir($dir.$startApp)) {
                        while (false !== ($file = readdir($handles))) {
                            if(is_file($dir.$app.'/'.$file) && substr($file,0,4)=='pay_'){
                                $payName = substr($file,4,-4);
                                if($payName == strtolower($payName))
                                {
                                    include_once($dir.$app.'/'.$file);
                                    $class_vars = 'pay_'.$payName;
                                    if(class_exists($class_vars))
                                        $o = new $class_vars;
                                    $class_vars = get_object_vars($o);
                                    unset($class_vars['system']);
                                    $key = $class_vars['orderby']?$class_vars['orderby']:$i;
                                    if ($disabled){
                                        if (!in_array(trim($payName),$disabledPayment)){
                                           $return[$key] = $class_vars;
                                           $return[$key]['payment_id'] = $payName;
                                        }
                                    }
                                    else{
                                        $return[$key] = $class_vars;
                                        $return[$key]['payment_id'] = $payName;
                                    }
                                    $i++;
                                }
                            }
                        }
                    }
                }
            }
            closedir($handle);
        }
        ksort($return);
        reset($return);
        return $return;
    }

    function getSupportCur(&$oPayType){
        if(!is_object($oPayType)) return false;
        return $oPayType->supportCurrency;
    }

    function getByCur($cur=-1, $type=''){ //注：以前的 getList 现在改为了 getPlugins

        if($cur == -1 || empty($cur)){
            $defaultMark = 1;
            $cur = -1;
        }else{
            $oCur = &$this->system->loadModel('system/cur');
            $aCur = $oCur->getcur($cur, true);
            if($aCur['def_cur'] == "true"){
                $defaultMark = 1;
            }else{
                $defaultMark = 0;
            }
        }
        if($type=="online"){
            $sql = ' AND pay_type NOT IN(\'OFFLINE\',\'DEPOSIT\')';
        }
        $rows = $this->db->select('SELECT * FROM sdb_payment_cfg WHERE disabled = \'false\''.$sql.' ORDER BY orderlist desc');


        foreach($rows as $k=>$row){
            $dir = PLUGIN_DIR.'/app/pay_'.$row['pay_type'].'/';
            if(is_file($dir.'pay_'.$row['pay_type'].'.php')){
                include_once($dir.'pay_'.$row['pay_type'].'.php');
                $class_name = 'pay_'.$row['pay_type'];
                $o = new $class_name;
                $pInfo = get_object_vars($o);
                unset($pInfo['system']);
                if($cur!=-1 && is_array($pInfo['supportCurrency'])){
                    $sptCur = array();
                    foreach($pInfo['supportCurrency'] as $s_cur=>$s){
                        $sptCur[strtolower($s_cur)] = 1;
                    }
                    if(!isset($sptCur[strtolower($cur)]) && !isset($sptCur['all'])){
                        if($defaultMark && isset($sptCur['default'])){;}else{
                            unset($rows[$k]);
                        }
                        continue;
                    }
                }
                $rows[$k] = array_merge($rows[$k],$pInfo);
                $rows[$k]['custom_name'] = $rows[$k]['custom_name']?$rows[$k]['custom_name']:$rows[$k]['name'];
                $i++;  //取出var $name = '支付宝';
            }else{
                unset($rows[$k]);
            }
        }
        return $rows;
    }

    function getPluginsArr($strKey=false){//由插件的缩写，和插件的名称组成的一个二维数组
        $aTemp = $aPlugin = array();
        $aTemp = $this->getPlugins();
        if($aTemp){
            if(!$strKey){
                foreach($aTemp as $val){
                    $aPlugin[] = array('pid'=>$val['payment_id'],'name'=>$val['name'],'cur'=>$val['supportCurrency']);
                }
            }else{
                foreach($aTemp as $val){
                    $aPlugin[] = array($val['payment_id'],$val['name']);
                }
            }
        }

        return $aPlugin;

    }

    function gen_id(){
        $i = rand(0,9999);
        do{
            if(9999==$i){
                $i=0;
            }
            $i++;
            $payment_id = time().str_pad($i,4,'0',STR_PAD_LEFT);
            $row = $this->db->selectrow('select payment_id from sdb_payments where payment_id =\''.$payment_id.'\'');
        }while($row);
        return $payment_id;
    }

    //生成付款单
    function toCreate(){
        $this->payment_id = $this->gen_id();
        $this->t_begin = time();
        $this->t_end = time();
        $this->ip = remote_addr();
        //如何网关实际是不支付外币交易的，但又选择了外币支付，则支付单中的实际支付金额，就是本位币金额。
        if(!$this->cur_trading && $this->currency != 'CNY'){
            $this->cur_money = $this->money;
        }

        $oCur = &$this->system->loadModel('system/cur');
        if($payCfg = $this->db->selectrow('SELECT pay_type,fee,custom_name FROM sdb_payment_cfg WHERE id='.intval($this->payment))){
            $this->paycost = $oCur->formatNumber($this->paycost, false);
            $this->paymethod = $payCfg['custom_name'];
        }
        $aRs = $this->db->query('SELECT * FROM sdb_payments WHERE 0=1');
        $sSql = $this->db->GetInsertSQL($aRs,$this);
        if($this->db->exec($sSql)){
            return $this->payment_id;
        }else{
            return false;
        }
    }

    function getById($paymentId){
        $aTemp = $this->db->selectrow('SELECT * FROM sdb_payments WHERE payment_id=\''.$paymentId.'\'');
        if($aTemp['payment_id']) return $aTemp;
        else return false;
    }

    //设置支付单的状态（包括前台支付，充值，后台支付）
    function setPayStatus($paymentId,$status,&$payInfo){
        if(!$paymentId){
            $this->setError(10001);
            trigger_error(__('单据号传递出错'),E_USER_ERROR);
            return false;
            exit;
        }
        $aPayInfo = $this->getById($paymentId);
        if(!$aPayInfo){
            $this->setError(10001);
            trigger_error(__('支付记录不存在，可能参数传递出错'),E_USER_ERROR);
            return false;
            exit;
        }
        if($aPayInfo['status'] == 'succ'){    //如果已经支付成功，则返回;##防止重复刷新提交
            return true;
        }
        if($aPayInfo['status'] == 'progress' && $status == PAY_PROGRESS){    //如果已经支付中，则返回;
            return true;
        }
        if($aPayInfo['pay_type'] == 'recharge' && $aPayInfo['bank'] == 'deposit'){    //如果用预存款支付，充值预存款的情况;
            $payInfo['memo'] .= __('#不能用预存款支付来充值预存款！');
            $status = PAY_FAILED;
        }
        if($payInfo['cur_money'] && $aPayInfo['cur_money'] != $payInfo['money']){
            $status = PAY_ERROR;
            $payInfo['memo'] .= __('#实际支付金额与支付单中的金额不一致！');
        }

        switch($status){
            case PAY_IGNORE:
                return false;
                break;
            case PAY_FAILED:
                $payInfo['status'] = 'failed';    //支付网关传回的状态为支付失败状态
                break;
            case PAY_TIMEOUT:
                $payInfo['status'] = 'timeout';    //
                break;
            case PAY_PROGRESS:    //处理中，类似于支付到支付宝; 已经支付到中间结构，现在还没有已发货通知接口
                $aPayInfo['pay_assure'] = true;     //支付到担保交易标识
                $aPayInfo['pay_progress'] ='PAY_PROGRESS';
                $payInfo['status'] = 'progress';
                break;
            case PAY_SUCCESS:
                $payInfo['status'] = 'succ';        //支付网关返回支付成功标识
                break;
            case PAY_CANCEL:
                $payInfo['status'] = 'cancel';    //
                break;
            case PAY_ERROR:
                $payInfo['status'] = 'error';    //除了PAY_FAILED的都是错误
                break;
            case PAY_REFUND_SUCCESS:
                $Rs=$this->db->selectrow('select order_id from sdb_payments where payment_id=\''.$paymentId.'\'');
                if ($Rs){
                    $_POST['order_id'] = $Rs['order_id'];
                    if ($this->op->opid){
                        $_POST['opid'] = $this->op->opid;
                        $_POST['opname'] = $this->op->loginName;
                    }
                    else{
                        $opeRs = $this->db->selectrow('select op_id,username from sdb_operators where status=1 and super=1');
                        $_POST['opid'] = $opeRs['op_id'];
                        $_POST['opname'] = $opeRs['username'];
                    }
                    $order=$this->system->loadModel('trading/order');
                    if ($order->refund($_POST)){
                        $this->setError(10001);
                        return true;
                    }
                    else{
                        $this->setError(10002);
                        return false;
                    }
                }
                else
                    return false;
                break;
        }
        $payInfo['t_end'] = time();
        $aRs = $this->db->query('SELECT * FROM sdb_payments WHERE payment_id=\''.$paymentId.'\' AND status!=\'succ\'');
        $sSql = $this->db->GetUpdateSql($aRs,$payInfo);
        if((!$sSql || $this->db->exec($sSql)) && $this->db->affect_row()==1){
            if($status == PAY_PROGRESS || $status == PAY_SUCCESS){
                if(!$this->onSuccess($aPayInfo, $payInfo['memo'])){
                    return false;
                }
            }
            return true;
        }else{
            return false;
        }
    }

    function onSuccess($info, &$message){
        if($info['pay_type'] =='recharge'){
            $oCur = &$this->system->loadModel('system/cur');
            $aCur = $oCur->getcur($info['currency']);
            $info['money'] = $info['money'] - $info['paycost'];
            if($aCur['def_cur'] == 'false'){
                $info['money'] /= $aCur['cur_rate'];
            }
            $info['money'] = $oCur->formatNumber($info['money'], false);
            $message .= '预存款充值：支付单号{'.$info['payment_id'].'}';
            $advance = $this->system->loadModel('member/advance');
            if(!$info['pay_assure'])//非担保交易状态
                return $advance->add($info['member_id'],$info['money'],$message,$message, $info['payment_id'], '' ,$info['paymethod'] , '在线充值');
            else
                return true;
        }else{

            $order = &$this->system->loadModel('trading/order');
            return $order->payed($info, $message);
        }
    }

    function pay_install($ident,$url,$is_update=false){
       if(!$url)
           $url = 'http://app.shopex.cn/appdatas/payments/'.$ident.'.tar';
       include(CORE_DIR.'/admin/controller/service/ctl.download.php');
       $download = new ctl_download();
       if(!class_exists("ctl_payment"))include(CORE_DIR.'/admin/controller/trading/ctl.payment.php');
       $payment = new ctl_payment();
       $_POST = array(
               'download_list'=>array($url),
           'succ_url'=>'http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF'])
            .'/index.php?ctl=trading/payment&act=do_install_online'
           );

       $download->set = 'true';
       $download->start();

       if($is_update){
            $ident = date("Ymd").substr(md5(time().rand(0,9999)),0,5);
            $download->run($download->ident,0);
            $_GET['download'] =$download->ident;
            $payment->do_install_online();
       }

    }



 function progress($paymentId,$status,$info){

    $sendPay['payment'] = $paymentId;
    $sendPay['amount'] = $info['money'];
    $sendPay['order_id'] = $info['trade_no'];
    $sendPay['pay_status'] = $status;

    $system = &$GLOBALS['system'];
    $base_url = $system->base_url();
    $base_url= substr(substr($base_url, 0, strrpos($base_url, '/')), 0, strrpos(substr($base_url, 0, strrpos($base_url, '/')), '/')).'/';
    $url = $system->realUrl('paycenter',$act='result','','html',$base_url);

    $payStatus = $this->setPayStatus($paymentId,$status,$info);
    $html="<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\"
       \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">
       <html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en-US\" lang=\"en-US\" dir=\"ltr\">
       <head></header><body>Redirecting...";
    $html .= '<form id="payment" action="'.$url.'" method="post"><input type="hidden" name="payment_id" value="'.$paymentId.'">';
    $html.=<<<EOF
      </form>
      <script language="javascript">
      document.getElementById('payment').submit();
      </script>
    </html>
EOF;
     echo $html;
    }

    function addQueue($sender,$target,$title,$data,$tmpl_name,$level=5,$event_name=''){
        $sqlData = array(
            'tmpl_name'=>$tmpl_name,
            'level'=>$level,
            'event_name'=>$event_name,
            'title'=>$title,
            'target'=>$target,
            'sender'=>$sender,
            'data'=>$data
        );
        $rs = $this->db->exec('select * from sdb_msgqueue where 0=1');
        $sql = $this->db->getInsertSQL($rs,$sqlData);
        $this->db->exec($sql);

    }

    function getAccount(){
        $query = 'SELECT DISTINCT bank, account FROM sdb_payments WHERE status="succ"';
        return $this->db->select($query);
    }
    function refund($nStart,$nLimit,$aParame){
        if(!$limit)$limit = 20;
        foreach($aParame as $k=>$v){
            if($k=='t_begin' && $v!='')$sTmp.=' and '.$k.'>="'.$v.'"';
            elseif($k=='t_end' && $v!='')$sTmp.=' and '.$k.'<="'.$v.'"';
            elseif($v!='')$sTmp.=' and '.$k.'="'.$v.'"';
        }
        $aData=$this->db->selectRow('select count(*) as total from sdb_payments p,sdb_members m where p.member_id=m.member_id and type="orderrefund"'.$sTmp);
        $aData['main']=$this->db->selectLimit('select p.*,m.name as m_name from sdb_payments p,sdb_members m where p.member_id=m.member_id and type="orderrefund"'.$sTmp,intval($nLimit),intval($nStart),false,true);
        return $aData;
    }
    //后台管理部分
    function getPaymentById($id){
        return $this->db->selectrow('SELECT * FROM sdb_payment_cfg WHERE id='.intval($id));
    }

    function insertPay($aData,&$msg){
        if($aData['pay_type']){
            $obj = $this->loadMethod($aData['pay_type']);
            if($obj){
                $aField = $obj->getfields();
                $aTemp = array();
                foreach($aField as $key=>$val){
                    $aTemp[$key] = trim($aData[$key]);
                    if ($val['extendcontent']){
                        foreach($val['extendcontent'] as $k => $v){
                           $aTemp[$v['property']['name']]=$aData[$v['property']['name']];
                        }
                    }
                }
                $aTemp['method'] = $aData['paymethod'];
                if ($aData['paymethod']=='2'){
                    $aTemp['fee'] = $aData['fee'];
                    unset($aData['fee']);
                }
                $aData['config'] = serialize($aTemp);
            }
            $aRs = $this->db->query('SELECT * FROM sdb_payment_cfg WHERE 0');
            $sSql = $this->db->GetInsertSql($aRs,$aData);
            if (!$sSql || $this->db->exec($sSql)){
                $msg = __("保存成功！");
                return true;
            }else{
                $msg = __("数据库操作失败！");
                return false;
            }
        }else{
            $msg = __('参数丢失，请选择支付类型！');
            return false;
        }
    }

    function insertPaymentApp($aData){
        $aRs = $this->db->query('SELECT * FROM sdb_payment_cfg WHERE 0');
        $sSql = $this->db->GetInsertSql($aRs,$aData);
        return $this->db->exec($sSql);
    }

    function updatePay($aData,&$msg){
        $appmgr = $this->system->loadModel("system/appmgr");
        $app_model = $appmgr->load("pay_".$aData['pay_type']);
        if(method_exists($app_model,'pay_other_operation')){
            $center_return = $app_model->pay_other_operation($aData);
            if(!$center_return){
                return false;
            }
        }

        $obj = $this->loadMethod($aData['pay_type']);
        if($obj){
            $aField = $obj->getfields();
            $aTemp = array();
            $d = $this->db->selectrow('SELECT * FROM sdb_payment_cfg WHERE id ="'.$aData['id'].'"');
            if(is_array($d)){
                $d_config = unserialize($d['config']);
            }
            foreach($aField as $key=>$val){
                if ($aData[$key]<>''){
                    if(strstr(strtolower($key), 'file') && !$aData[$key] && $d_config[$key]){
                        $aTemp[$key] = trim($d_config[$key]);
                    }else{
                        if (isset($aData[$key])){
                            if($aData['pay_type']=="chinapay" && ($key == "MerPrk" || $key == "PubPk")){
                                if(($pos=strpos($aData[$key], "."))){
                                    $suffix = substr($aData[$key], $pos);
                                    $max_len = 7-strlen($suffix);
                                    if(strlen(substr($aData[$key], 0, $pos)) > $max_len){
                                        $aData[$key] = substr($aData[$key], 0, $max_len).$suffix;
                                    }
                                }
                                else{
                                    if(strlen($aData[$key]) > 7){
                                        $aData[$key] = substr($aData[$key], 0, 7);
                                    }
                                }
                            }
                            $aTemp[$key] = trim($aData[$key]);
                        }
                        else
                            $aTemp[$key] = trim($d_config[$key]);
                    }
                }
                else
                    $aTemp[$key]=trim($d_config[$key]);
                if ($val['extendcontent']){
                    foreach($val['extendcontent'] as $k => $v){
                        if ($aData[$v['property']['name']]){
                            $aTemp[$v['property']['name']]=$aData[$v['property']['name']];
                        }
                        else{
                           $aTemp[$v['property']['name']]=$dt_config[$v['property']['name']];
                        }
                    }
                }
            }

            $aTemp['method'] = $aData['paymethod'];
            if ($aData['paymethod']==2){
                $aTemp['fee'] = $aData['fee'];
                unset($aData['fee']);
            }
            else{
                $aTemp['fee']=$d_config['fee'];
            }
            unset($aData['paymethod']);
            $aData['config'] = serialize($aTemp);
        }

        if(is_array($d)){
            $aRs = $this->db->query('SELECT * FROM sdb_payment_cfg WHERE id="'.$aData['id'].'"');
            $sSql = $this->db->GetUpdateSql($aRs,$aData);
        }else{
            $aRs = $this->db->query('SELECT * FROM sdb_payment_cfg WHERE 0');
            $sSql = $this->db->GetInsertSql($aRs,$aData);
        }
        return (!$sSql || $this->db->exec($sSql));
    }


    function deletePay($sId=null){
        if($sId){
            $sSql = 'DELETE FROM sdb_payment_cfg WHERE id in ('.$sId.')';
            return (!$sSql || $this->db->exec($sSql));
        }
        return false;
    }

  function getPaymentInfo($method=''){
        $o = &$this->system->loadModel('trading/order');
        $m = &$this->system->loadModel('member/member');
        $order = $o->instance($this->order_id);
        $member = $m->instance($order['member_id']);


        $payment['M_OrderId'] = $this->payment_id;        //    订单的id---支付流水号
        $payment['M_OrderNO'] = $method=="recharge"?$this->payment_id:$this->order_id;        //    订单号
        $payment['M_Amount'] = $this->money;        //    本次支付金额        小数点后保留两位，如10或12.34
        $payment['M_Def_Amount'] = $this->money;        //    本次支付本位币金额        小数点后保留两位，如10或12.34
        $payment['M_Currency'] = $this->currency;    //    支付币种
        $payment['M_Remark'] = $order['memo'];        //    订单备注
        $payment['M_Time'] = $this->t_begin;        //    支付单生成时间
        $payment['M_Goods'] = $order['tostr'];        //    订单中商品描述
        $payment['M_Language'] = 'zh_CN';    //    语言选择        表示商家使用的页面语言
        $payment['R_Name'] = $order['ship_name'];        //    收货人姓名    订单支付成功后货品收货人的姓名
        $payment['R_Address'] = $order['ship_addr'];        //    收货人住址    订单支付成功后货品收货人的住址
        $payment['R_Postcode'] = $order['ship_zip'];    //    收货人邮政编码    订单支付成功后货品收货人的住址所在地的邮政编码
        $payment['R_Telephone'] = $order['ship_tel'];    //    收货人联系电话    订单支付成功后货品收货人的联系电话
        $payment['R_Mobile'] = $order['ship_mobile'];        //    收货人移动电话    订单支付成功后货品收货人的移动电话
        $payment['R_Email'] = $order['ship_email'];        //    收货人电子邮件地址    订单支付成功后货品收货人的邮件地址
        $payment['P_Name'] = $member['name'];        //    付款人姓名    支付时消费者的姓名
        $payment['P_Address'] = $member['addr'];        //    付款人住址    进行订单支付的消费者的住址
        $payment['P_PostCode'] = $member['zip'];    //    付款人邮政编码        进行订单支付的消费者住址的邮政编码
        $payment['P_Telephone'] = $member['tel'];    //    付款人联系电话     进行订单支付的消费者的联系电话
        $payment['P_Mobile'] = $member['mobile'];        //    付款人移动电话     进行订单支付的消费者的移动电话
        $payment['P_Email'] = $member['email'];        //    付款人电子邮件地址     进行订单支付的消费者的电子邮件地址
        $payment['K_key'] = $this->system->getConf('certificate.token');    //商店Key
        $payment['payExtend'] = unserialize($order['extend']);
        $payment['M_Method'] = $method;
        if ($this->pay_type=="recharge"){ //预存款充值
            $member=$m->instance($this->member_id);
            $payment['R_Name']=$member['name']?$member['name']:$member['uname'];
            $payment['R_Telephone']=$member['mobile']?$member['mobile']:($member['tel']?$member['tel']:'13888888888');
        }
        $configinfo = $this->getPaymentById($order['payment']);
        $pma=$this->getPaymentFileName($configinfo['config'],$configinfo['pay_type']);
        if (is_array($pma)){
            foreach($pma as $key => $val){
                $payment[$key]=$val;
            }
        }
        return $payment;
  }

    function disApp($id){
        $sql = 'update sdb_payment_cfg  set disabled=\'true\' where id = '.$id.'';
        return $this->db->exec($sql);
    }
    function startApp($id){
        $sql = 'update sdb_payment_cfg  set disabled=\'false\' where id = '.$id.'';
        return $this->db->exec($sql);
    }

    function doPay($method='',$order_id){

        $gOrder = &$this->system->loadModel('trading/order');
        if($gOrder->freez_time()=='pay'){
                $objCart = &$this->system->loadModel('trading/cart');
                $objGift = &$this->system->loadModel('trading/gift');
                if(isset($order_id)){
                    $rs = $this->db->select('SELECT product_id,nums,name  FROM sdb_order_items  WHERE order_id = '.$order_id.' ');
                    $rsG= $this->db->select('SELECT gift_id,nums  FROM sdb_gift_items   WHERE order_id = '.$order_id.' ');

                    foreach($rs as $k=>$p){
                         if(!$objCart->_checkStore($p['product_id'], $p['nums'])){
                         return false;
                         }
                    }
                    foreach($rsG as $key=>$val){
                         if (!$objGift->checkStock($val['gift_id'], $val['nums']))
                         return false;
                    }
                }
        }

        $payObj = $this->loadMethod($this->type);
        $pay_vars = get_object_vars($payObj);
        $this->cur_trading = $pay_vars['cur_trading'];
        if($this->toCreate()){
            if ($payObj->head_charset)
                header("Content-Type: text/html;charset=".$payObj->head_charset);

            $html ="<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\"
                \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">
                <html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en-US\" lang=\"en-US\" dir=\"ltr\">
                <head>
</header><body><div>Redirecting...</div>";
//            $this->money += $this->paycost;（money中 已经包含paycost）
            $payObj->_payment = $this->payment;
            $toSubmit = $payObj->toSubmit($this->getPaymentInfo($method));
            if('utf8' != strtolower($payObj->charset)){
                $charset = &$this->system->loadModel('utility/charset');
                foreach($toSubmit as $k=>$v){
                    if(!is_numeric($v)){
                        $toSubmit[$k] = $charset->utf2local($v,'zh');
                    }
                }
            }

            $html .= '<form id="payment" action="'.$payObj->submitUrl.'" method="'.$payObj->method.'">';
            foreach($toSubmit as $k=>$v){
                if ($k<>"ikey"){
                    $html.='<input name="'.urldecode($k).'" type="hidden" value="'.htmlspecialchars($v).'" />';
                    if ($v){
                        $buffer.=urldecode($k)."=".$v."&";
                    }
                }
            }
            if (strtoupper($this->type)=="TENPAYTRAD"){
                $buffer=substr($buffer,0,strlen($buffer)-1);
                $md5_sign=strtoupper(md5($buffer."&key=".$toSubmit['ikey']));

                $url=$payObj->submitUrl."?".$buffer."&sign=".$md5_sign;
                echo "<script language='javascript'>";
                echo "window.location.href='".$url."';";
                echo "</script>";
            }
            $html.='
</form>
<script language="javascript">
document.getElementById(\'payment\').submit();
</script>
</html>';
        }else{
            $html=<<<EOF
<html>
<meta http-equiv=\"Content-Type\" content=\"text/html;charset=utf-8\"/>
<script language="javascript">
alert('创建支付流水号错误！');
//location.href=document.referrer;
</script>
</html>
EOF;
        }
        echo $html;
        $this->system->_succ = true;
        exit;
    }

    function getPaymentFileByType($type){
        $tmp_ary=$this->db->selectrow('SELECT * FROM sdb_payment_cfg WHERE pay_type='.$type);
        $payment=$this->getPaymentFileName($tmp_ary['config'],$type);
        return $payment;
    }
    function getPaymentFileName($config,$ptype){//获取支付所需文件，如密钥文件、公钥文件
        if(!empty($config)){//添加
            $pmt=$this->loadMethod($ptype);
            $field=$pmt->getfields();
            $config=unserialize($config);
            if (is_array($config)){
                foreach($field as $k => $v){
                    if (strtoupper($v['type'])=="FILE"||$k=="keyPass")//判断支付网关是否有文件或者是私钥保护密码
                        $payment[$k] = $config[$k];
                }
            }
        }
        return $payment;
    }
    function isPayBillSuccess($payment_id){
        $row = $this->db->selectrow('select payment_id from sdb_payments WHERE payment_id=\''.$payment_id.'\' and status=\'succ\'');
        if ($row)
            return true;
        else
            return false;
    }
    function getSuccOrderBillList($orderid){
        return $this->db->select('SELECT * FROM sdb_payments WHERE order_id = '.$orderid.' and status IN (\'succ\',\'progress\')');
    }
    function showPayExtendCon(&$payments,&$payExtend){//在前台显示二级内容
        if ($payExtend)
            $payExtend=unserialize($payExtend);
        if ($payments){
            foreach($payments as $key => $val){
                $fields=$this->getPlugFields($val['pay_type']);
                if (!is_array($val['config']))
                    $config=unserialize($val['config']);
                else
                    $config = $val['config'];
                foreach($fields as $k => $v){
                    if ($v['extendcontent']){
                        if ($config[$k]){
                            foreach($v['extendcontent'] as $extk => $extv){
                                if($config[$extv['property']['name']]){
                                    $tmpValue=array();
                                    foreach($config[$extv['property']['name']] as $conk=>$conv){
                                        foreach($extv['value'] as $evk => $evv){
                                            if ($conv==$evv['value']){
                                                $evv['imgurl']=$evv['imgname']?"<img src=".$this->system->base_url().'plugins/payment/images/'.$evv['imgname'].">":"";
                                                if ($payExtend){
                                                    if (is_array($payExtend[$extv['property']['name']])){
                                                        if (in_array($evv['value'],$payExtend[$extv['property']['name']]))

                                                            $evv['checked'] = 'checked';
                                                    }
                                                    elseif ($payExtend[$extv['property']['name']]==$evv['value']){
                                                        $evv['checked']='checked';
                                                    }
                                                }
                                                $tmpValue[]=$evv;
                                                break;
                                            }
                                        }
                                    }
                                    $payments[$key]['extend'][]=array("name"=>$extv['property']['name'],"fronttype"=>$extv['property']['fronttype'],"frontsize"=>$extv['property']['frontsize'],"value"=>$tmpValue,"extconId"=>$extv['property']['frontname']);
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    function recgextend(&$data,&$postInfo,&$extendInfo){
        $paymentcfg=$this->system->loadModel('trading/paymentcfg');
        $cfg=$paymentcfg->instance($data['payment'],'pay_type');
        if ($cfg['pay_type']){
            $fields=$this->getPlugFields($cfg['pay_type']);
            if(is_array($fields)){
                foreach($fields as $fkey => $fval){
                    if ($fval['extendcontent']){
                        foreach($fval['extendcontent'] as $ffkey => $ffval){
                            if (isset($postInfo[$ffval['property']['name']])){
                                $extendInfo[$ffval['property']['name']]=$postInfo[$ffval['property']['name']];
                            }
                        }
                    }
                }
            }
        }
    }
    function OrdMemExtend(&$order,&$extendInfo){
        $order['pay_extend']=unserialize($order['pay_extend']);
        if (is_array($order['pay_extend'])){
            $fields=$this->getPlugFields($order['paytype']);
            $paymentcfg=$this->system->loadModel('trading/paymentcfg');
            $cfg=$paymentcfg->instance($order['payment'],'config');
            if(is_array($fields)){
                $config=unserialize($cfg['config']);

                foreach($fields as $fkey => $fval){
                    if ($fval['extendcontent']){
                        foreach($fval['extendcontent'] as $ffkey => $ffval){
                            $tmp=array();
                            if (isset($config[$ffval['property']['name']])){
                                foreach($ffval['value'] as $fffkey => $fffval){
                                    $fffval['imgname']=$fffval['imgname']?"<img src=".$this->system->base_url().'plugins/payment/images/'.$fffval['imgname'].">":"";
                                    if (in_array($fffval['value'],$config[$ffval['property']['name']])){
                                        if (is_array($order['pay_extend'][$ffval['property']['name']])){
                                            if (in_array($fffval['value'],$order['pay_extend'][$ffval['property']['name']]))
                                                $fffval['checked']='checked';
                                        }
                                        elseif ($fffval['value']==$order['pay_extend'][$ffval['property']['name']])
                                            $fffval['checked']='checked';
                                        $tmp[]=$fffval;
                                    }
                                }
                                $extendInfo[$ffval['property']['name']]=array('type'=>$ffval['property']['fronttype'],'value'=>$tmp);
                            }
                        }
                    }
                }
            }
        }
    }
    function getExtendOfPlug($payid='',$paytype='',&$extfields){
        $fields=$this->getPlugFields($paytype,$payid);
        foreach($fields as $k => $v){
            if ($v['extendcontent']){
                foreach($v['extendcontent'] as $key => $val){
                    $extfields[]=$val['property']['name'];
                }
            }
        }
    }
    function getPlugFields($paytype='',$payid=''){
        if(!$paytype){
            $paymentcfg=$this->system->loadModel('trading/paymentcfg');
            $cfg=$this->getPaymentById($payid);
            $paytype=$cfg['pay_type'];
        }
        $method=$this->loadMethod($paytype);
        $fields=$method->getfields();
        return $fields;
    }
    function getExtendCon($config,$payid){
        $config=is_array($config)?$config:unserialize($config);
        if ($config){
            $fields = $this->getPlugFields('',$payid);
            $this->getExtendOfPlug($payid,'',$extfields);
            if ($extfields){
                foreach($fields as $key => $val){
                    if ($extendContent=$val['extendcontent']){
                        foreach($extfields as $extk => $extv){
                            if ($extendContent[$extk]['value']){
                                foreach($extendContent[$extk]['value'] as $sk => $sv){
                                    if ($sv['value']==$config[$extv])
                                        $extendCon[]=$sv['imgname']?"<img src='".$this->system->base_url().'plugins/payment/images/'.$sv['imgname']."' tip='".$sv['name']."' alt='".$sv['name']."'>":$sv['name'];
                                }
                            }
                        }
                    }
                }
            }
            return $extendCon;
        }
    }
    function _getByOrderId($id){

        return $this->db->select('SELECT * FROM sdb_payments WHERE delivery_id=\''.$id.'\'');
    }
}
