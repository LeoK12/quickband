<?php
/**
 * mdl_promotion
 *
 * @uses shopObject
 * @package trading
 * @version $Id: mdl.promotion.php 1985 2008-04-28 06:36:02Z flaboy $
 * @copyright 2003-2007 ShopEx
 * @author bryant <bryant@shopex.cn>
 * @license Commercial
 */
require_once('shopObject.php');

class mdl_promotion extends shopObject {
    var $idColumn = 'pmt_id'; //表示id的列
    var $textColumn = 'pmt_id';
    var $defaultCols = 'pmt_time_begin,pmt_time_end,pmt_describe';
    var $adminCtl = 'sale/promotion';
    var $defaultOrder = 'pmt_id desc';
    var $tableName = 'sdb_promotion';

    function _filter($filter){
        $where=array(1);
        $where[] = 'pmt_type=\'0\'';
        return parent::_filter($filter).' and '.implode($where,' and ');
    }

    //---------------------------------------------------------------------------
    function apply_action($func, &$trading, $config) {
        call_user_func_array(array(&$this,'pmt_goods_'.$func),array(&$trading,$config));
    }

    function pmt_goods_moreScore(&$trading,$rate){
        $trading['score']= ($trading['score']*$rate>0)?($trading['score']*$rate):0;
    }

    function pmt_goods_giveGift(&$trading,$gifts){
        if (is_array($gifts)&&!empty($gifts)){
            $trading['gift'] = $gifts;
        }
    }

    function pmt_goods_generateCoupon(&$trading,$coupons){
        if ($this->mlvid && is_array($coupons) && !empty($coupons)){
            $trading['coupon'] = $coupons;
        }
    }

    function pmt_goods_discount(&$trading,$discount){
        $trading['price'] = ($trading['price'] * $discount>0)?($trading['price'] * $discount):0;

    }

    function pmt_goods_lessMoney(&$trading,$money){
        $trading['price'] = ($trading['price'] - $money>0)?($trading['price'] - $money):0;
    }

    function pmt_goods_exemptFreight(&$trading,$ifExemptFreight){
        if ($ifExemptFreight) {
            $trading['exemptFreight'] = true;
        }
    }

    function _apply_pmt(&$trading, $solution) {
        if (is_array($solution['method'])) {
            foreach($solution['method'] as $method) {
                $this->apply_action($method[0], $trading, $method[1]);
            }
        }else{
            return false;
        }
    }

    function apply_single_pdt_pmt(&$trading, $solution, $mlvid) {
        $this->mlvid= $mlvid;
        return $this->_apply_pmt($trading, $solution);
    }
    //应用单条订单促销规则
    function _apply_order_pmt(&$trading, $onePmt) {
        $aGiftPresent = &$trading['gift_p'];
        $aCoupon = &$trading['coupon_p'];
        $old_totalPrice = $trading['pmt_o']['totalPrice'];
        $tmpTrading = array (
            'price' => $trading['pmt_o']['totalPrice'],
            'score' => $trading['pmt_o']['totalGainScore'],
            'gift'  => array(),
            'coupon' => array(),
            'exemptFreight' => false);
        $this->_apply_pmt($tmpTrading, unserialize($onePmt['pmt_solution']));
        $trading['pmt_o']['totalPrice'] = $tmpTrading['price'];
        $trading['pmt_o']['totalGainScore'] = $tmpTrading['score'];
        $trading['pmt_o']['exemptFreight'] = $trading['pmt_o']['exemptFreight'] || $tmpTrading['exemptFreight'];

        if ($order_gift = $tmpTrading['gift']) {
            foreach($order_gift as $nGift) {
                $aGiftPresent[$nGift] = intval($aGiftPresent[$nGift]) + 1;
            }
        }

        if ($item_coupon = $tmpTrading['coupon']) {
            foreach($item_coupon as $sCoupon) {
                $aCoupon[$sCoupon] = intval($aCoupon[$sCoupon]) + 1;
            }
        }
        $trading['pmt_o']['pmt_ids'][] = $onePmt['pmt_id'];//todo 优化
        $trading['pmt_o']['pmt_money'][] = $old_totalPrice - $trading['pmt_o']['totalPrice'];
        $trading['ifCoupon'] = ($trading['ifCoupon'] && $onePmt['pmt_ifcoupon'])?1:0;
    }
    //应用所有订单促销规则

    function apply_order_pmt(&$trading, $mlvid) {
        //增加优惠券的订单促销方案
        $this->mlvid = $mlvid;
        $aPmts = $this->getOrderPromotion($trading['totalPrice'], $mlvid);
        $trading['pmt_o'] = $trading['pmt_g'];
        $trading['pmt_o']['totalPrice'] = $trading['totalPrice'];
        $trading['pmt_o']['totalGainScore'] = $trading['totalGainScore'];
        $aTmp = array();
        if ($aPmts){
            foreach ($aPmts as $onePmt) {
                $this->_apply_order_pmt($trading, $onePmt);
            }
        }
        $aCoupon = $trading['coupon_u'];
        if (is_array($aCoupon)) {
            foreach ($aCoupon as $couponCode=>$c) {
                if ($c['type']=='order') {
                    $oCoupon = &$this->system->loadModel('trading/coupon');
                    if ($couponPmt = $oCoupon->useMemberCoupon($couponCode, $mlvid, null, null, null, $trading['pmt_o']['totalPrice'])) {
                        $trading['coupon_u'] = array_merge($trading['coupon_u'], $couponPmt);//加强版coupon信息
                        list(, $couponPmt) = each($couponPmt);
                        if ($couponPmt['type']=='order') {
                            $sSql = 'select pmt_id,pmta_id,pmt_solution from sdb_promotion where pmt_id='.$couponPmt['pmt_id'];
                            $aCouponPmt = $this->db->selectRow($sSql);
                            $this->_apply_order_pmt($trading, $aCouponPmt);
                        }
                    }else{
                        unset($trading['coupon_u'][$couponCode]);
                    }
                }
            }
        }
        $trading['totalPrice'] = $trading['pmt_o']['totalPrice'];
        $trading['totalGainScore'] = $trading['pmt_o']['totalGainScore'];
        $trading['exemptFreight'] = $trading['exemptFreight'] || $trading['pmt_o']['exemptFreight'];
    }
    //应用相关商品规则
    function apply_pdt_pmt(&$trading, $mlvid) {
        $this->mlvid = $mlvid;
        $items = &$trading['products'];
        foreach($items as $k=>$p) {                //循环所购货品
            $items[$k]['_pmt'] = array (
                'price' => $items[$k]['price'],
                'score' => $items[$k]['score'],
                'amount' => $items[$k]['amount'],
                'gift'  => array(),
                'coupon' => array());
            if ($p['pmt_id']>0) {    //如果本货品关联有营销规则
                $this->_apply_pdt_pmt($trading, $k, $p['pmt_id']);
            }
            $trading['pmt_g']['totalPrice'] += $items[$k]['_pmt']['amount'];
            $trading['pmt_g']['totalGainScore'] += $items[$k]['_pmt']['score']*$items[$k]['nums'];
        }
        $trading['totalPrice'] = $trading['pmt_g']['totalPrice']+$trading['totalPkgPrice'];
        $trading['totalGainScore'] = $trading['pmt_g']['totalGainScore']+$trading['totalPkgScore'];
    }

    function _apply_pdt_pmt(&$trading, $flowId, $pmtId) {
        $items = &$trading['products'];
        $aGiftPresent = &$trading['gift_p'];
        $aCoupon = &$trading['coupon_p'];
        //根据促销id选出促销规则
        $aTmp = $this->db->selectRow('select pmt_solution,pmt_describe,pmt_ifcoupon from sdb_promotion where pmt_id='.$pmtId);
        $sPmtSolution = $aTmp['pmt_solution'];
        $this->_apply_pmt($items[$flowId]['_pmt'], unserialize($sPmtSolution));
        if ($item_gift = $items[$flowId]['_pmt']['gift']) {
            foreach($item_gift as $nGift) {
                $aGiftPresent[$nGift] = intval($aGiftPresent[$nGift]) + $items[$flowId]['nums'];
            }
        }
        if ($item_coupon = $items[$flowId]['_pmt']['coupon']) {
            foreach($item_coupon as $sCoupon) {
                $aCoupon[$sCoupon] = intval($aCoupon[$sCoupon]) + $items[$flowId]['nums'];
            }
        }
        if ($items[$flowId]['_pmt']['exemptFreight']) {
            $trading['exemptFreight'] = $trading['exemptFreight'] || $items[$flowId]['_pmt']['exemptFreight'];
        }
        //计算後总价 pmt_g代表经过商品促销後的结果,pmt_o代表经过订单促销後的结果
        $items[$flowId]['_pmt']['amount'] = $items[$flowId]['_pmt']['price'] * $items[$flowId]['nums'];
        $items[$flowId]['_pmt']['describe'] = $aTmp['pmt_describe'];
        $items[$flowId]['_pmt']['pmt_id'] = $pmtId;
        $trading['ifCoupon'] = ($trading['ifCoupon'] && $aTmp['pmt_ifcoupon'])?1:0;
    }

    function apply_coupon_pmt(&$trading, $couponCode, $mlvid) {
        $cart_c = array();
        //todo 验证有效性
        $oCoupon = &$this->system->loadModel('trading/coupon');
        foreach($trading['products'] as $p) {
            $goods_ids[] = $p['goods_id'];
            $brand_ids[] = $p['brand_id'];
            $cat_ids[] = $p['cat_id'];
        }
        if ($couponPmt = $oCoupon->useMemberCoupon($couponCode, $mlvid, $goods_ids, $brand_ids, $cat_ids, $trading['totalPrice'])) {
            $cart_c  = array_merge($cart_c, $couponPmt);
            $oCart = &$this->system->loadModel('trading/cart');
            return $oCart->addToCart('c', $cart_c);
        }else {
            return false;
        }
    }

    function pmt_filter_goods($gid, $catid, $brandid ) {
        $aPmt = array();
        $aTmpPmt_a = $this->db->select('select pmt_id from sdb_pmt_goods where goods_id='.$gid);
        $aTmpPmt_b = $this->db->select('select pmt_id from sdb_pmt_goods_cat where (cat_id='.$catid.' and brand_id=0) or (brand_id='.$brandid.' and cat_id=0) or (cat_id='.$catid.' and brand_id='.$brandid.')' );
        $aTmpPmt = array_merge($aTmpPmt_a, $aTmpPmt_b);
        foreach($aTmpPmt as $v) {
            if (!in_array($v['pmt_id'], $aTmpPmt)) {
                array_push($aPmt, $v['pmt_id']);
            }
        }
        return $aPmt;
    }

    function filter_goods($pmtId, $bondType, $goods_ids, $brand_ids, $cat_ids) {
        $aResult = array();
        switch ($bondType) {  //判断coupon的商品关联方式
        case 1:
            $sSql = 'select * from sdb_pmt_goods where pmt_id='.intval($pmtId);
            $aGoodsSwp = $this->db->select($sSql);
            foreach($aGoodsSwp as $r) {
                if (in_array($r['goods_id'], $goods_ids)) {
                    $aResult[] = $r['goods_id'];
                }
            }
            break;
        case 2:
            $sSql = 'select * from sdb_pmt_goods_cat where pmt_id='.intval($pmtId);
            $aGoodsCatSwp = $this->db->select($sSql);
            foreach($goods_ids as $k => $gid) {
                foreach($aGoodsCatSwp as $r) {
                    if (($r['cat_id']==0 || $r['cat_id']==$cat_ids[$k]) && ($r['brand_id']==0 || $r['brand_id']==$brand_ids[$k])) {
                        $aResult[] = $goods_ids[$k];
                    }
                }
            }
            break;
        }
        if (!empty($aResult)) {
            return $aResult;
        }else{
            return false;
        }
    }

    function getGoodsPromotionId($gid, $mlvid) {
        $oGoods = &$this->system->loadModel('trading/goods');
        $aGoods = $oGoods->getFieldById($gid, array('cat_id,brand_id'));
        $oPromotion = &$this->system->loadModel('trading/promotion');
        $aPmt = $oPromotion->getGoodsPromotion($gid, $aGoods['cat_id'], $aGoods['brand_id'], $mlvid);
        $pmtid = $aPmt['pmt_id'];
        return $pmtid;
    }

    function getGoodsPromotion($gid, $catid, $brandid, $mlvid) {
        //初始化
        if (intval($gid) ==0) {
            return false;
        }
        $catid = intval($catid);
        $brandid = intval($brandid);

        $aPmt = $this->pmt_filter_goods($gid,$catid,$brandid);
        if (!$aPmt) {
            $aPmt = array(0);
        }
        $curTime = time();

        /*
         * 代码需要仔细测试
         * */
        if(constant('DB_OLDVERSION')){
                $sSql = 'select p.pmt_id,p.pmta_id,p.pmt_solution from sdb_promotion as p
                left join sdb_promotion_activity as a on a.pmta_id=p.pmta_id
                where
                a.pmta_enabled=\'true\' and pmt_type=\'0\' and
                ((pmt_bond_type<>0 and pmt_id in ('.implode(',',$aPmt).')) or (pmt_bond_type=\'0\' and pmt_basic_type=\'goods\')) and
                pmt_time_begin <= '.$curTime.' and pmt_time_end >'.$curTime.'
                order by pmt_update_time desc';
                $rows = $this->db->select($sSql);
                $m = array();
                foreach($rows as $k=>$r){
                    $m[$r['pmt_id']] = $r[$k];
                }
                $sn = array();
                if(count($m)){
                    foreach($this->db->select('select member_lv_id from sdb_pmt_member_lv where pmt_id in ('.implode(',',array_keys($m)).')') as $sr){
                        if(isset($m[$sr['pmt_id']])){
                            $sn[] = $m[$sr['pmt_id']];
                        }
                    }
                    if(count($sn)){
                        return $sn;
                    }else{
                        return false;
                    }
                }
        }else{
            $sSql = 'select p.pmt_id,p.pmta_id,p.pmt_solution from sdb_promotion as p
                left join sdb_promotion_activity as a on a.pmta_id=p.pmta_id
                where
                a.pmta_enabled=\'true\' and pmt_type=\'0\' and
                ((pmt_bond_type<>0 and pmt_id in ('.implode(',',$aPmt).')) or (pmt_bond_type=\'0\' and pmt_basic_type=\'goods\')) and
                ('.$mlvid.' in (select member_lv_id from sdb_pmt_member_lv where pmt_id=p.pmt_id)) and pmt_time_begin <= '.$curTime.' and pmt_time_end >'.$curTime.'
                order by pmt_update_time desc';
            return $this->db->selectRow($sSql);
        }
    }

    function getOrderPromotion($totalPrice, $mlvid) {
        $mlvid = intval($mlvid);
        $curTime = time();
        if(constant('DB_OLDVERSION')){
                $sSql = 'select p.pmt_id,p.pmta_id,p.pmt_solution,p.pmt_ifcoupon from sdb_promotion as p
                left join sdb_promotion_activity as a on a.pmta_id=p.pmta_id
                where
                pmt_type=\'0\' and
                a.pmta_enabled=\'true\' and
                order_money_from<='.$totalPrice.' and order_money_to>'.$totalPrice.' and pmt_basic_type=\'order\' and pmt_time_begin <= '.$curTime.' and pmt_time_end >'.$curTime.'
                order by pmt_update_time desc';
                $rows = $this->db->select($sSql);
                $m = array();
                foreach($rows as $k=>$r){
                    $m[$r['pmt_id']] = $r[$k];
                }
                $sn = array();
                if(count($m)){
                    foreach($this->db->select('select member_lv_id from sdb_pmt_member_lv where pmt_id in ('.implode(',',array_keys($m)).')') as $sr){
                        if(isset($m[$sr['pmt_id']])){
                            $sn[] = $m[$sr['pmt_id']];
                        }
                    }
                    if(count($sn)){
                        return $sn;
                    }else{
                        return false;
                    }
                }else{
                    return false;
                }
        }else{
            $sSql = 'select p.pmt_id,p.pmta_id,p.pmt_solution,p.pmt_ifcoupon from sdb_promotion as p
                left join sdb_promotion_activity as a on a.pmta_id=p.pmta_id
                where
                pmt_type=\'0\' and
                a.pmta_enabled=\'true\' and
                order_money_from<='.$totalPrice.' and order_money_to>'.$totalPrice.' and pmt_basic_type=\'order\' and
                ('.$mlvid.' in (select member_lv_id from sdb_pmt_member_lv where pmt_id=p.pmt_id)) and pmt_time_begin <= '.$curTime.' and pmt_time_end >'.$curTime.'
                order by pmt_update_time desc';
            return $this->db->select($sSql);
        }
    }

    //后台-------------------------------------------------

    function getPromotionFieldById($id,$aPara) {
        return $this->db->selectrow('select '.implode(',',$aPara).' from sdb_promotion where pmt_id='.intval($id));
    }

    function getPromotionByIds($aPara) {
        return $this->db->select('select * from sdb_promotion where pmt_id in  ('.implode(',',$aPara).')');
    }

    function getPromotionList($pmtaId) {
        return $this->db->select('select * from sdb_promotion where pmta_id='.$pmtaId.' and pmt_type="0" order by pmt_id desc');
    }

    function bindLevel($promotionId, $aLevel=array()) {
        $sSql = 'DELETE FROM sdb_pmt_member_lv WHERE pmt_id='.intval($promotionId);
        $this->db->exec($sSql);
        if (is_array($aLevel)) {
            foreach ($aLevel as $levelId)
            {
                $sSql = "INSERT INTO sdb_pmt_member_lv(member_lv_id,pmt_id) VALUES({$levelId},{$promotionId})";
                $this->db->query($sSql);
            }
        }
    }

    function bindGoods($pmtId, $bindGoods) {
        foreach ($bindGoods as $goodsId) {
            $aData['pmt_id'] = $pmtId;
            $aData['goods_id'] = $goodsId;
            $aRs = $this->db->query("SELECT * FROM sdb_pmt_goods WHERE 0");
            $sSql = $this->db->getInsertSql($aRs,$aData);
            if (!$this->db->exec($sSql)) {
                return false;
            }
        }
    }

    function getBondGoods($pmtId) {
        $aTmp = $this->db->select('select * from sdb_pmt_goods where pmt_id='.intval($pmtId));
        return array_item($aTmp, 'goods_id');
    }

    function addPromotion($aData) {
        $aPmtSolution = $aData['pmt_solution'];
        $aData['pmt_solution'] = serialize($aData['pmt_solution']);
        if(!$aData['pmt_time_begin']){
            unset($aData['pmt_time_begin']);
        }
        if(!$aData['pmt_time_end']){
            unset($aData['pmt_time_end']);
        }
        $aData['pmt_update_time'] = time();
        $aData['pmt_ifcoupon'] = intval($aData['pmt_ifcoupon']);

        if ($aData['pmt_id']) {
            unset($aData['pmta_id']);            //更新去掉pmta_id
            $pmtId = $aData['pmt_id'];
            $aRs = $this->db->query('SELECT * FROM sdb_promotion WHERE pmt_id='.intval($pmtId));
            $sSql = $this->db->getUpdateSql($aRs,$aData);
            if ($sSql) {
                $this->db->exec($sSql);
            }
        }else{
            $aRs = $this->db->query("SELECT * FROM sdb_promotion WHERE 0");
            $sSql = $this->db->getInsertSql($aRs,$aData);
            if ($this->db->exec($sSql)){
                $pmtId = $this->db->lastInsertId();
            }else{
                return false;
            }
        }

        foreach($aPmtSolution['condition'] as $condition) {
            if ($condition[0] == 'mLev') {
                $this->bindLevel($pmtId, $condition[1]);
            }else if ($condition[0] == 'orderMoney_from'){
                $aData = array('order_money_from'=>$condition[1]);
                $aRs = $this->db->query('SELECT * FROM sdb_promotion WHERE pmt_id='.intval($pmtId));
                $sSql = $this->db->getUpdateSql($aRs,$aData);
                if ($sSql) {
                    $this->db->exec($sSql);
                }
            }else if ($condition[0] == 'orderMoney_to'){
                $aData = array('order_money_to'=>$condition[1]);
                $aRs = $this->db->query('SELECT * FROM sdb_promotion WHERE pmt_id='.intval($pmtId));
                $sSql = $this->db->getUpdateSql($aRs,$aData);
                if ($sSql) {
                    $this->db->exec($sSql);
                }
            }
        }

        foreach($aPmtSolution['method'] as $method) {
            switch ($method[0]) {
            case 'giveGift':
                break;
            case 'generateCoupon':
                if ( is_array($method[1]) && !empty($method[1]) ){
                    $this->db->exec('delete from sdb_pmt_gen_coupon where pmt_id='.intval($pmtId));
                    foreach($method[1] as $cpnsId){
                        $this->db->exec('insert into sdb_pmt_gen_coupon(cpns_id,pmt_id) values(\''.intval($cpnsId).'\',\''.$pmtId.'\')');
                    }
                }
                break;
            default:
                break;
            }
        }

        $this->db->exec('delete from sdb_pmt_goods where pmt_id='.intval($pmtId));    //删除活动规则的相关商品
        switch ($aData['pmt_bond_type']) {
        case 0:
            break;
        case 1:
            //todo 商品绑定 1,2,3
            $this->bindGoods($pmtId, $aData['bind_goods']);
            break;
        case 2:
            //todo 分类品牌绑定
            /*
                array(
                0 =>
                array(
                brand_id =>
                pmt_id    =>
                )
            );*/
            break;
        default:
            break;
        }
        return $pmtId;
    }

    function delete($arrId,&$msg) {
        $arrId = $arrId['pmt_id'];
        if ($arrId) {
            $sSql = 'delete from sdb_promotion where  pmt_id in ('.implode($arrId, ',').')';
            if ($this->db->exec($sSql)) {
                $related_tables = array('sdb_pmt_goods_cat', 'sdb_pmt_goods', 'sdb_pmt_gen_coupon', 'sdb_pmt_member_lv');
                foreach($related_tables as $table) {
                    $this->db->exec('delete from '.$table.' where  pmt_id in ('.implode($arrId, ',').')');
                }
                return true;
            } else {
                $msg = __('数据删除失败！');
                return false;
            }
        }else{
            $msg = 'no select';
            return false;
        }
    }
}
?>
