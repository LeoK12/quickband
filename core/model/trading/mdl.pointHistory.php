<?php
class mdl_pointHistory extends modelFactory{
    //type: 1.订单得积分,2.消费积分,3.无分类
    function getHistoryReason() {

        $aHistoryReason = array(
                            'order_pay_use' => array(
                                                    'describe' => __('订单消费积分'),
                                                    'type' => 1,
                                                    'related_id' => 'sdb_mall_orders',
                                                ),
                            'order_pay_get' => array(
                                                    'describe' => __('订单获得积分.'),
                                                    'type' => 2,
                                                    'related_id' => 'sdb_mall_orders',
                                                ),
                            'order_refund_use' => array(
                                                    'describe' => __('退还订单消费积分'),
                                                    'type' => 1,
                                                    'related_id' => 'sdb_mall_orders',
                                                ),
                            'order_refund_get' => array(
                                                    'describe' => __('扣掉订单所得积分'),
                                                    'type' => 2,
                                                    'related_id' => 'sdb_mall_orders',
                                                ),
                            'order_cancel_refund_consume_gift' => array(
                                                    'describe' => __('Score deduction for gifts refunded for order cancelling.'),
                                                    'type' => 1,
                                                    'related_id' => 'sdb_mall_orders',
                                                ),
                            'exchange_coupon' => array(
                                                    'describe' => __('兑换优惠券'),
                                                    'type' => 3,
                                                    'related_id' => '',
                                                ),
                            'operator_adjust' => array(
                                                    'describe' => __('管理员改变积分.'),
                                                    'type' => 3,
                                                    'related_id' => '',
                                                ),
                            'consume_gift' => array(
                                                    'describe' => __('积分换赠品.'),
                                                    'type' => 3,
                                                    'related_id' => 'sdb_mall_orders',
                                                ),
                            'fire_event' => array(
                                                      'describe' => __('网店机器人触发事件'),
                                                      'type' => 3,
                                                      'related_id' =>'',
                                                ),
            );
        return $aHistoryReason;
    }

    function addHistory($aData) {
        $aHistoryReason = $this->getHistoryReason();
        $aData['time'] = time();
        $aData['type'] = $aHistoryReason[$aData['reason']]['type'];
        $aData['describe'] = $aHistoryReason[$aData['reason']]['describe'];
        $rRs = $this->db->query('SELECT * FROM sdb_point_history WHERE 0=1');
        $sSql = $this->db->getInsertSQL($rRs, $aData);
        $this->db->exec($sSql);
    }

    function getGainedPoint($userId) {
        $aPoint = $this->db->select('SELECT SUM(point) AS point FROM sdb_point_history WHERE member_id='.$userId.' AND point>0');
        return intval($aPoint[0]['point']);
    }


    function getConsumePoint($userId)
    {
        $aPoint = $this->db->select('SELECT sum(point) AS point FROM sdb_point_history WHERE member_id='.$userId.' AND point<0');
        return intval($aPoint[0]['point']);
    }

    //2---------------------------------
    function getOrderConsumePoint($orderId) {
        $sSql = 'select score_u from sdb_orders where order_id=\''.$orderId.'\'';
        $aData = $this->db->selectrow($sSql);
        return intval($aData['score_u']);
    }
    function getOrderConsumeExperience($orderId) {
        return $this->getOrderConsumePoint($orderId);
    }
    function getOrderHistoryGetPoint($orderId) {
        $sSql = 'select sum(point) as point from sdb_point_history where related_id=\''.$orderId.'\' and type=1';
        $aData = $this->db->selectrow($sSql);
        return intval($aData['point']);
    }
    //---------------------------------


    function getPointHistoryList($userId) {
        $aData = $this->db->select('SELECT time, reason, point FROM sdb_point_history WHERE member_id='.$userId.' ORDER BY time DESC');
        $aHistoryReason = $this->getHistoryReason();
        if ($aData) {
            foreach ($aData as $k => $aItem) {
                $aData[$k]['describe'] = $aHistoryReason[$aItem['reason']]['describe'];
            }
        }
        return $aData;

    }



    //todo 挪到shop/model
    function getFrontPointHistoryList($userId,$nPage){
        $aData = $this->db->selectPager('SELECT time, reason, point FROM sdb_point_history WHERE member_id='.$userId.' ORDER BY time DESC',$nPage,PERPAGE);
        $aHistoryReason = $this->getHistoryReason();
        if ($aData['data']) {
            foreach ($aData['data'] as $k => $aItem) {
                $aData['data'][$k]['describe'] = $aHistoryReason[$aItem['reason']]['describe'];
            }
        }
        return $aData;
    }
}
?>