<?php
class mdl_memberPoint extends modelFactory{


    function toUpdatelevel($userId) {
        $oMember = &$this->system->loadModel('member/member');
        $nPoint = $this->getMemberPoint($userId);
        $aTmp = $oMember->getLevelByPoint($nPoint,$levelId);
        $aData['member_lv_id'] = $aTmp['member_lv_id'];
        $rRs = $this->db->query('select * from sdb_members where member_id='.$userId);
        $sSql = $this->db->GetUpdateSQL($rRs, $aData);
        if ($sSql) {
            $this->db->exec($sSql);
            if ($levelId!=$aTmp['member_lv_id']){
                $shopObject = new shopObject();
                $shopObject->modelName='member/account';
                $data['member_id'] = $userId;
                $shopObject->fireEvent('changelevel',$data,$userId);
                unset($shopObject);
            }
        }
    }

    function getMemberPoint($userId,&$levelId) {
        $sSql = 'SELECT point,member_lv_id FROM sdb_members WHERE member_id='.intval($userId);
        $aUserPoint = $this->db->selectrow($sSql);
        $levelId=$aUserPoint['member_lv_id'];
        return intval($aUserPoint['point']);
    }

    //检查用户积分是否足够
    function _chgPoint($userId, $nCheckPoint) {
        if ($nCheckPoint<0) {
            $nPoint = $this->getMemberPoint($userId);
            if ($nPoint >= abs($nCheckPoint)) {
                return true;
            }else{
                return false;
            }
        }else {
            return true;
        }
    }

    function chgPoint($userId, $nPoint, $sReason, $relatedId=null,$type=0){
        //初始化
        if ($nPoint==0) {
            return true;
        }else if (!$this->_chgPoint($userId, $nPoint)) {
            $nPoint = 0 - $this->getMemberPoint($userId);
            //trigger_error(__('积分扣除超过会员已有积分'), E_USER_ERROR);
            //return false;
        }

        $oMember = &$this->system->loadModel('member/member');
        $aPoint = $oMember->getFieldById($userId, array('point'));
        $oLv = &$this->system->loadModel('member/level');
        if ($userId && ($oLv->checkMemLvType($userId) != 'wholesale')) {
            $userId = intval($userId);
            $oPointHistory = &$this->system->loadModel('trading/pointHistory');
            $aUserPoint['point'] = $this->getMemberPoint($userId);
            $aUserPoint['point'] += $nPoint;
           
            $rRs = $this->db->query('select * from sdb_members where member_id='.$userId);
            $sSql = $this->db->GetUpdateSQL($rRs, $aUserPoint);

            if($sSql)$this->db->exec($sSql); 
            if (intval($nPoint)!=0&&!$type){
                $shopObject = new shopObject();
                $shopObject->modelName='member/account';
                $data['member_id'] = $userId;
                $shopObject->fireEvent('changepoint',$data,$userId);
                unset($shopObject);
            }
            if (!$this->system->getConf('site.level_switch'))
                $this->toUpdatelevel($userId);
            $aPointHistory = array(
                                'member_id' => $userId,
                                'point' => $nPoint,
                                'reason' => $sReason,
                                'related_id' => $relatedId);
            $oPointHistory->addHistory($aPointHistory);
        }
        return true;
    }

    function payAllConsumePoint($userId, $orderId) {
        $oPointHistory = &$this->system->loadModel('trading/pointHistory');
        $oOrder = &$this->system->loadModel('trading/order');
        $aTmp = $oOrder->getFieldById($orderId, array('score_u'));
        $nPayPoint = 0 - intval($aTmp['score_u']);
        return $this->chgPoint($userId, $nPayPoint, 'order_pay_use', $orderId);
    }

    function refundAllConsumePoint($userId, $orderId) {
        $oPointHistory = &$this->system->loadModel('trading/pointHistory');
        $oOrder = &$this->system->loadModel('trading/order');
        $aTmp = $oOrder->getFieldById($orderId, array('score_u'));
        $nPayPoint = $aTmp['score_u'];
        return $this->chgPoint($userId, $nPayPoint, 'order_refund_use', $orderId);
    }
    //++++++++++所得积分
    function payAllGetPoint($userId, $orderId) {
        $oPointHistory = &$this->system->loadModel('trading/pointHistory');
        $oOrder = &$this->system->loadModel('trading/order');
        $aTmp = $oOrder->getFieldById($orderId, array('score_g'));
        $nPayPoint = $aTmp['score_g'];
        return $this->chgPoint($userId, $nPayPoint, 'order_pay_get', $orderId);
    }

    function refundPartGetPoint($userId, $orderId, $nPoint) {
        $this->chgPoint($userId, $nPoint, 'order_refund_get', $orderId);
    }

    function refundAllGetPoint($userId, $orderId) {
        $oPointHistory = &$this->system->loadModel('trading/pointHistory');
        $oOrder = &$this->system->loadModel('trading/order');
        $aTmp = $oOrder->getFieldById($orderId, array('score_g'));
        $nPayPoint = 0 - $aTmp['score_g'];
        return $this->chgPoint($userId, $nPayPoint, 'order_refund_get', $orderId);
    }

    //++++++++++取消订单,退还积分
    //todo

    function cancelOrderRefundConsumePoint($userId, $orderId) {
        $oPointHistory = &$this->system->loadModel('trading/pointHistory');
        return $this->chgPoint($userId, $oPointHistory->getOrderConsumePoint($orderId), 'order_cancel_refund_consume_gift', $orderId);
    }
}

?>
