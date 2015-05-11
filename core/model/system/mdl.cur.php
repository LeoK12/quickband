<?php
include_once('shopObject.php');
class mdl_cur extends shopObject {

    var $tableName = 'sdb_currency';
    var $idColumn = 'cur_code';
    var $textColumn = 'cur_name';

    function mdl_cur($system){
        parent::modelFactory($system);
        if (defined('IN_INSTALLER')) return;
        $this->_money_format=array(
            'decimals'=>$this->system->getConf('system.money.operation.decimals'),
            'dec_point'=>$this->system->getConf('system.money.dec_point'),
            'thousands_sep'=>$this->system->getConf('system.money.thousands_sep'),
            'fonttend_decimal_type'=>$this->system->getConf('system.money.operation.carryset'),
            'fonttend_decimal_remain'=>$this->system->getConf('system.money.operation.decimals')
        );
    }

    /**
    * getSysCur
    *
    * @access privite
    * @return void
    */
    function getSysCur(){
        $CON_CURRENCY['CNY'] = __("人民币");
        $CON_CURRENCY['USD'] = __("美元");
        $CON_CURRENCY['EUR'] = __("欧元");
        $CON_CURRENCY['GBP'] = __("英磅");
        $CON_CURRENCY['CAD'] = __("加拿大元");
        $CON_CURRENCY['AUD'] = __("澳元");
        $CON_CURRENCY['RUB'] = __("卢布");
        $CON_CURRENCY['HKD'] = __("港币");
        $CON_CURRENCY['TWD'] = __("新台币");
        $CON_CURRENCY['KRW'] = __("韩元");
        $CON_CURRENCY['SGD'] = __("新加坡元");
        $CON_CURRENCY['NZD'] = __("新西兰元");
        $CON_CURRENCY['JPY'] = __("日元");
        $CON_CURRENCY['MYR'] = __("马元");
        $CON_CURRENCY['CHF'] = __("瑞士法郎");
        $CON_CURRENCY['SEK'] = __("瑞典克朗");
        $CON_CURRENCY['DKK'] = __("丹麦克朗");
        $CON_CURRENCY['PLZ'] = __("兹罗提");
        $CON_CURRENCY['NOK'] = __("挪威克朗");
        $CON_CURRENCY['HUF'] = __("福林");
        $CON_CURRENCY['CSK'] = __("捷克克朗");
        $CON_CURRENCY['MOP'] = __("葡币");
        return $CON_CURRENCY;
    }

    function curAdd($data){
        if ($data['def_cur']=='true') {
            $sql="select cur_code from sdb_currency where def_cur=1 and cur_code<>'{$data['cur_code']}'";
            $dat=$this->db->select($sql);
            if (!empty($dat[0]['cur_code'])) {
                $this->setError(2005001);
                trigger_error(__('不可重复设定默认货币'),E_USER_ERROR);
                return false;
            }
        }
        $rs=$this->db->query('select * from sdb_currency where 0=1');
        $sql=$this->db->GetInsertSQL($rs,$data);
        if(!$sql || $this->db->query($sql)){
            return true;
        }else{
            $this->setError(2005002);
            trigger_error(__('数据库插入失败'));
            return false;
        }
    }

    function curAll(){
        return $this->db->select('select * from sdb_currency');
    }

    function curDel($id){
        $sql = 'delete from sdb_currency where cur_code="'.$id.'"';
        if($this->db->exec($sql)){
            return true;
        }else{
            return false;
        }
    }

    function getcur($id, $getDef=false){
        $aCur = $this->db->selectrow('select * FROM sdb_currency where cur_code="'.$id.'"');
        if($aCur['cur_code'] || !$getDef){
            return $this->_in_cur[$id] = $aCur;
        }elseif($this->_default_cur){
            return $this->_default_cur;
        }else{
            $this->_default_cur = $this->getDefault();
            return $this->_in_cur[$this->_default_cur['cur_code']] = &$this->_default_cur;
        }
    }

    function getDefault(){
        if($cur = $this->db->selectrow('select * from sdb_currency where def_cur=1')){
            return $cur;
        }else{    //if have no default currency, read the first currency as default value
            return $this->db->selectrow('select * FROM sdb_currency');
        }
    }

    function curEdit($id,$data,$old_cur_code){
        if ($data['def_cur']=='true') {
            $sql="select cur_code from sdb_currency where def_cur=1 and cur_code<>'{$old_cur_code}'";
            $dat=$this->db->select($sql);
            if (!empty($dat[0]['cur_code'])) {
                $this->setError(2005003);
                trigger_error(__('不可重复设定默认货币'),E_USER_ERROR);
                return false;
            }
        }
        $rs=$this->db->query('select * from sdb_currency where cur_code="'.$old_cur_code.'"');
        $sql=$this->db->GetUpdateSQL($rs,$data);
        if($sql){
            if($this->db->exec($sql)){
                return true;
            }else{
                trigger_error(__('输入参数有误'),E_USER_ERROR);
                return false;
            }
        }else return true;
    }

    //一下方法需要调整
    function getFormat($cur){
        $ret = array();
        $cursign = $this->getcur($cur, true);

        $ret = $this->_money_format;
        $ret['sign'] = $cursign['cur_sign'];
        return $ret;
    }

    /**
     * 商店币别金额显示统一调用函数
     * @param float $money 金额
     * @param string $currency 显示的货币种类如：CNY/USD
     * @param bool $is_sign 是否不带货币符号显示
     * @param bool $is_rate 是否按汇率显示
     * @return bool
     */
    function changer($money, $currency='', $is_sign=false, $is_rate=false)
    {
        $cur_money = $this->get_cur_money($money, $currency);
        if($is_rate){
            $cur_money = $money;
        }
        if($is_sign){
            return $this->formatNumber($cur_money, false);
        }else{
            return $this->_in_cur['cur_sign'].$this->formatNumber($cur_money);
        }
    }

    /**
     * 读取货币金额
     * @param float $money 金额
     * @param string $currency 货币种类如：CNY/USD
     * @return bool
     */
    function get_cur_money($money, $currency=''){
        if(empty($currency)) $currency = $this->system->request['cur'];
        if($currency || empty($this->_in_cur['cur_rate'])){
            $this->_in_cur = $this->getcur($currency, true);
        }
        return $money*($this->_in_cur['cur_rate'] ? $this->_in_cur['cur_rate'] : 1);
    }

    function amount($money,$currency='',$basicFormat=false,$chgval=true,$is_order){

        if(empty($currency)) $currency = $this->system->request['cur'];
        if($currency || empty($this->_in_cur['cur_rate'])){
            $this->_in_cur = $this->getcur($currency, true);
        }
        if($chgval){
            $money = $money*($this->_in_cur['cur_rate'] ? $this->_in_cur['cur_rate'] : 1);
        }

        $oMath = $this->system->loadModel('system/math');
        if($is_order){
            $this->is_order = true;
            $oMath->operationCarryset=$this->system->getConf("site.decimal_type");
            $oMath->getFunc();
        }
        $money = $oMath->getOperationNumber($money);
        $money = $this->formatNumber($money);
        if($basicFormat){
            return $money;
        }
        $precision = $this->system->getConf('site.decimal_digit');
        $decimal_type = $this->system->getConf('site.decimal_type');
        $mul = '1'.str_repeat('0',$precision);
        switch($decimal_type){
            case 0:
                $money = round($money,$precision);
            break;
            case 1:
                $money = ceil(trim($money)*$mul) / $mul;
            break;
            case 2:
                $money = floor(trim($money)*$mul) / $mul;

            break;
        }

        return $this->_in_cur['cur_sign'].sprintf("%.0{$precision}f",$money);

    }
    function formatNumber($number, $is_str=true){
               //取回格式化的数据，供运算使用
        $oMath = $this->system->loadModel('system/math');
        if($this->is_order){
            $oMath->operationCarryset=$this->system->getConf("site.decimal_type");
            $oMath->getFunc();
        }
        $number = $oMath->getOperationNumber($number);
        if($is_str)
            return number_format(trim($number),
                $this->_money_format['decimals'],
                $this->_money_format['dec_point'],$this->_money_format['thousands_sep']);
        else
            return number_format(trim($number), $this->_money_format['decimals'], '.', '');
    }
}
?>