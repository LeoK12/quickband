<?php
/**
 * mdl_account
 * 账户相关类，只有账户层次的操作才会用到此类
 *
 * @uses modelFactory
 * @package member
 * @version $Id: mdl.account.php 1965 2008-04-26 15:21:50Z flaboy $
 * @copyright 2003-2007 ShopEx
 * @author Wanglei <flaboy@zovatech.com>
 * @license Commercial
 */
include_once("shopObject.php");
class mdl_account extends shopObject {

    var $name='会员';

    function check_uname($uname,&$message){
        $uname = trim($uname);
        $len = strlen($uname);
        if($len<3){
            $message = __('用户名过短!');
            return false;
        }elseif($len>20){
            $message = __('用户名过长!');
            return false;
        }elseif(!preg_match('/^([@\.]|[^\x00-\x2f^\x3a-\x40]){2,20}$/i', $uname)){
            $message = __('用户名包含非法字符!');
            return false;
        }else{
            $row = $this->db->selectrow("select uname from sdb_members where uname='{$uname}'");
            if($row['uname']){
               $message = __('重复的用户名!');
               return false;
            }else{
               if ($this->check_name_inuc($uname)==1)
                  return true;
               else
                  return false;
            }
        }
    }

    function check_email($email,&$message){
        if(!(eregi('^.+@.+$',$email))){
            $message = __('邮箱输入有误！');
            return false;
        }else{
            return true;
        }
    }

    function events(){
        $ret = array(
            'register'=>array('label'=>__('注册'),'globals'=>0,'params'=>array(
                    'has_sup'=>array('label'=>__('有推荐人'),'type'=>'bool'),
                    'reg_node'=>array('label'=>__('注册序号/编码'),'type'=>'number'),
                    //'reg_date'=>array('label'=>__('注册日期'),'type'=>'number'),
                    //'reg_prev'=>array('label'=>__('某时间周期注册的前X名'),'type'=>'number'),
                    //'reg_cur'=>array('label'=>__('某时间周期注册的第X名'),'type'=>'number'),
                )),
            'login'=>array('label'=>__('登录'),'globals'=>0,'params'=>array(
                    'login_times'=>array('label'=>__('登录次数'),'type'=>'number'),
                    'login_birthay'=>array('label'=>__('生日登录'),'type'=>'bool'),
                    //'login_afterregist'=>array('label'=>__('登陆等于注册后'),'type'=>'number'),
                    //'login_date'=>array('label'=>__('登陆日期'),'type'=>'number'),
                    //'login_zq'=>array('label'=>__('某时间周期登录'),'type'=>'number'),
                )),
            /*'lostPw'=>array('label'=>__('取回密码'),'globals'=>0,'params'=>array(
                    'login_times'=>array('label'=>__('登陆次数'),'type'=>'number'),
                    'login_birthay'=>array('label'=>__('生日登陆'),'type'=>'bool'),
                    'login_afterregist'=>array('label'=>__('登陆等于注册后'),'type'=>'number'),
                    'login_date'=>array('label'=>__('登陆日期'),'type'=>'number'),
                    'login_zq'=>array('label'=>__('某时间周期登录'),'type'=>'number'),
                )),*/
            'changelevel'=>array('label'=>__('会员等级变化'),'globals'=>0,'params'=>array(
                    'level_change'=>array('label'=>__('会员等级变化'),'type'=>'bool'),
                    //'level_changeto'=>array('label'=>__('会员等级转为X时'),'type'=>'string'),
                    //'level_changetype'=>array('label'=>__('会员类型转为X时(普通/批发)'),'type'=>'string'),
                )),
            'changepoint'=>array('label'=>__('消费积分变化'),'globals'=>0,'params'=>array(
                    //'point_range'=>array('label'=>__('积分'),'type'=>'number'),
                    'point_change'=>array('label'=>__('消费积分变化'),'type'=>'bool')
                )),
            'changeadvance'=>array('label'=>__('预存款变化'),'globals'=>0,'params'=>array(
                    'advance_relay'=>array('label'=>__('余额'),'type'=>'number'),
                    'advance_onetime'=>array('label'=>__('单次金额'),'type'=>'number'),
            )),
            /*'advisorychange'=>array('label'=>__('咨询'),'globals'=>0,'params'=>array(
                'advisory_replay'=>array('label'=>__('咨询被回复'),'type'=>'bool'),
                'advisory_new'=>array('label'=>__('新咨询'),'type'=>'bool'),
                'advisory_del'=>array('label'=>__('咨询被删除'),'type'=>'bool'),
            )),*/
            'advisory_new'=>array('label'=>__('新咨询'),'globals'=>0,'params'=>array(
                'advisory_new'=>array('label'=>__('新咨询'),'type'=>'bool'),
            )),
            'advisory_replay'=>array('label'=>__('咨询被回复'),'globals'=>0,'params'=>array(
                'advisory_replay'=>array('label'=>__('咨询被回复'),'type'=>'bool'),
            )),
            'advisory_del'=>array('label'=>__('咨询被删除'),'globals'=>0,'params'=>array(
                'advisory_del'=>array('label'=>__('咨询被删除'),'type'=>'bool'),
            )),
            /*'discuzz'=>array('label'=>__('评论'),'globals'=>0,'params'=>array(
                'discuzz_check'=>array('label'=>__('评论被审核'),'type'=>'bool'),
                'discuzz_del'=>array('label'=>__('评论被删除'),'type'=>'bool'),
                //'discuzz_to_reply'=>array('label'=>__('评论被转为留言'),'type'=>'bool'),
                'discuzz_new'=>array('label'=>__('新评论'),'type'=>'bool'),
            )),*/
            'discuzz_new'=>array('label'=>__('新评论'),'globals'=>0,'params'=>array(
                'discuzz_new'=>array('label'=>__('新评论'),'type'=>'bool'),
            )),
            'discuzz_check'=>array('label'=>__('评论被审核'),'globals'=>0,'params'=>array(
                'discuzz_check'=>array('label'=>__('评论被审核'),'type'=>'bool'),
            )),
            'discuzz_del'=>array('label'=>__('评论被删除'),'globals'=>0,'params'=>array(
                'discuzz_del'=>array('label'=>__('评论被删除'),'type'=>'bool'),
            )),
            /*'shortmessage'=>array('label'=>__('消息'),'globals'=>0,'params'=>array(
                'shortmessage_new'=>array('label'=>__('新消息'),'type'=>'bool'),
                'shortmessage_reply'=>array('label'=>__('消息被回复'),'type'=>'bool'),
                'shortmessage_del'=>array('label'=>__('消息被删除'),'type'=>'bool'),
            )),*/
            'shortmessage_new'=>array('label'=>__('新消息'),'globals'=>0,'params'=>array(
                'shortmessage_new'=>array('label'=>__('新消息'),'type'=>'bool'),
            )),
            'shortmessage_reply'=>array('label'=>__('消息被回复'),'globals'=>0,'params'=>array(
                'shortmessage_reply'=>array('label'=>__('消息被回复'),'type'=>'bool'),
            )),
            'shortmessage_del'=>array('label'=>__('消息被删除'),'globals'=>0,'params'=>array(
                'shortmessage_del'=>array('label'=>__('消息被删除'),'type'=>'bool'),
            )),
            'saleservice'=>array('label'=>__('售后服务'),'globals'=>0,'params'=>array(
                'service_apply'=>array('label'=>__('申请'),'type'=>'bool'),
                'service_manage'=>array('label'=>__('处理'),'type'=>'bool'),
            )),
            /*'shopmessage'=>array('label'=>__('商店留言'),'globals'=>0,'params'=>array(
                'shopmessage_new'=>array('label'=>__('新留言'),'type'=>'bool'),
                'shopmessage_reply'=>array('label'=>__('留言被回复'),'type'=>'bool'),
                'shopmessage_del'=>array('label'=>__('留言被删除'),'type'=>'bool'),
            )),*/
            'shopmessage_new'=>array('label'=>__('新留言'),'globals'=>0,'params'=>array(
                'shopmessage_new'=>array('label'=>__('新留言'),'type'=>'bool'),
            )),
            'shopmessage_reply'=>array('label'=>__('留言被回复'),'globals'=>0,'params'=>array(
                'shopmessage_reply'=>array('label'=>__('留言被回复'),'type'=>'bool'),
            )),
            'shopmessage_del'=>array('label'=>__('留言被删除'),'globals'=>0,'params'=>array(
                'shopmessage_del'=>array('label'=>__('留言被删除'),'type'=>'bool'),
            )),
            );
        $global_params = array(
                'ip'=>array('label'=>__('ip地址'),'type'=>'ip'),
                'reg_days'=>array('label'=>__('注册后总天数'),'type'=>'number'),
                'is_birthday'=>array('label'=>__('生日当天'),'type'=>'bool'),
                __LINE__=>array('label'=>__('购买总量'),'type'=>'money'),
                __LINE__=>array('label'=>__('推荐用户数'),'type'=>'number'),
            );
        foreach($ret as $k=>$v){
            if($ret[$k]['params']){
                if ($v['globals'])
                    $ret[$k]['params'] = array_merge($ret[$k]['params'],$global_params);
            }else{
                $ret[$k]['params'] = &$global_params;
            }
        }
        return $ret;
    }

    /**
     * 创建账户
     *
     * @param mixed $data
     * @param mixed $message
     * @access public
     * @return void
     */
    function create($data,&$message){

        $data['uname'] = trim(strtolower($data['uname']));
        $data['email'] = trim(strtolower($data['email']));
        $data['reg_ip'] = remote_addr();
        $data['regtime'] = time();

        if(!$this->check_uname($data['uname'],$message)){
            return false;
        }
        if(!$this->check_email($data['email'],$message)){
            return false;
        }

        if($data['passwd']!=$data['passwd_r']){
            $message = __('两次密码输入不一致！');
            return false;
        }
        $row = $this->db->selectrow('select * from sdb_member_lv where default_lv="1"');
        $data['member_lv_id'] = $row['member_lv_id']?$row['member_lv_id']:0;

        $defcur = $this->db->selectrow('select cur_code from sdb_currency where def_cur="true"');
        $data['cur'] = $defcur['cur_code'];
        $rs = $this->db->exec('select * from sdb_members where uname='.$this->db->quote($data['uname']));

        //判断用户是否存在，返回falas或者getInsertSQL
        if(!$rs || $this->db->getRows($rs)){
            trigger_error(__('存在重复的用户id'),E_USER_ERROR);
            return false;
        }
        $data['password'] = md5($data['passwd']);
        $data['login_count'] = 1;
        getRefer($data);

        $sql = $this->db->getInsertSQL($rs,$data);;
        if($this->db->exec($sql)){
            $userId = $this->db->lastInsertId();
            $status = &$this->system->loadModel('system/status');
            $status->add('MEMBER_REG');
            $this->init($userId);
            $sql = 'select member_id,member_lv_id,email,uname,password,unreadmsg,cur,lang,point from sdb_members where member_id='.$userId;
            $row = $this->db->selectrow($sql);
            $row['secstr'] = $this->cookieValue($userId);
            $this->idColumn='member_id';
            $data['member_id'] = $userId;
            $this->fireEvent('register',$data,$userId);//会员注册成功事件
            return $row;
        }else{
            return false;
        }
    }
	
    function cookieValue($memberID){
        $row=$this->db->selectrow('select uname,password from sdb_members where member_id='.$memberID);
        $row['uname']=md5($row['uname']);
        return $memberID.'-'.utf8_encode($row['uname']).'-'.md5($row['password'].STORE_KEY).'-'.time();
    }
    function checkMember($data){
        // OR email="'.$data['email'].'"
        $row=$this->db->selectrow('select member_id,uname,email from sdb_members where uname="'.$data['uname'].'"');
        if($row['member_id'] && $row['uname'] == $data['uname']){
                return true;
        }else{
            return false;
        }
    }

    function verify($memberId,$code){
        $row = $this->db->selectrow('select member_id,member_lv_id,email,uname,b_year,b_month,b_day,password,unreadmsg,cur,lang,point,experience from sdb_members where member_id='.intval($memberId));
        if($row && md5($row['password'].STORE_KEY)==$code){
            $oMsg = &$this->system->loadModel('resources/msgbox');
            $row['unreadmsg'] = $oMsg->getNewMessageNum($memberId);
            unset($row['password']);
            return $row;
        }else{
            return false;
        }
    }

    function init($memberId){
        if($member = $this->db->selectrow( 'select * from sdb_members where member_id='.intval($memberId))){
            foreach($this->listFilters($member) as $filter){
                $this->applyFilter($member,$filter);
            }
        }else{
            return false;
        }
    }

    function verifyLogin($login,$passwd,&$message,$passport=null){
        $login = trim(strtolower($login));
        if(!$passport){
            if(strlen($login)==0){
                $message = __('请填写登录信息。');
                return false;
            }else{
                $sql = 'select member_id,member_lv_id,email,uname,b_year,b_month,b_day,password,unreadmsg,cur,lang,point,login_count,addon from sdb_members where uname='.$this->db->quote($login).' and password='.$this->db->quote(md5($passwd))." and disabled='false'";
            }
            if($row = $this->db->selectrow($sql)){
                $row['login_count'] = $data['login_count'] = $row['login_count'] + 1;;
                $rs=$this->db->exec('select login_count from sdb_members where member_id='.intval($row['member_id']));
                $sSql=$this->db->GetUpdateSQL($rs,$data);
                $this->db->exec($sSql);
                $row['secstr'] = $this->cookieValue($row['member_id']);
                $oMsg = &$this->system->loadModel('resources/msgbox');
                $row['unreadmsg'] = $oMsg->getNewMessageNum($row['member_id']);
                $this->idColumn='member_id';
                $this->fireEvent('login',$row,$row['member_id']);
                return $row;
            }else{
                return false;
            }
        }else{//passport登录验证
            $objPasspt = &$this->system->loadModel('member/passport');
            $objPasspt->verifyLogin($passport,$login,$passwd);
        }
    }
    /**
    *
    */
    function verifyPassportLogin($member){
            $sql = 'select member_id,member_lv_id,email,uname,password,unreadmsg,cur,lang,point from sdb_members where uname='.$this->db->quote($member['username']);
            $row = $this->db->selectrow($sql);
            if($row){
                $sql = 'update sdb_members set password='.$this->db->quote($member['password']).' where uname='.$this->db->quote($member['username']);
                $this->db->exec($sql);
                return $row;
            }
            return false;
    }
    function toLogin($member){
        if(empty($member['username'])){
            return false;
        }
        $sql = 'select member_id,member_lv_id,email,uname,password,unreadmsg,cur,lang,point from sdb_members where uname='.$this->db->quote($member['username']);
        $row = $this->db->selectrow($sql);
        $row['secstr'] = $this->cookieValue($row['member_id']);
        return $row;
    }

    function createPassport($member){
        $row = $this->db->selectrow('select * from sdb_member_lv where default_lv="1"');
        $member['member_lv_id'] = $row['member_lv_id']?$row['member_lv_id']:0;
        $sql = "insert into sdb_members (member_lv_id,uname,password,email,reg_ip,regtime) values ('".$member['member_lv_id']."','".$member['username']."','".$member['password']."','".$member['email']."','".$member['regip']."','".$member['regdate']."')";
        if(!$this->db->exec($sql)){
            return false;
        }
        return $member['username'];
    }


    function passportCallback($passport){//passport登录，回叫登录
        $objPasspt = &$this->system->loadModel('member/passport');
        $memberInfo = $objPasspt->decode($passort,array_merge($_GET,$_POST));
        $sql = 'select member_id,uname from sdb_members where user='.$this->db->quote($memberId['login']).' and passport='.$this->db->quote($memberId['login']);
        if($row = $this->db->selectrow($sql)){
            return $this->cookieValue($row['member_id']);
        }else{
            $memberInfo['password_r'] = $memberInfo['password'] = substr(md5(rand(time())),0,6);
            return $this->create($memberInfo);
        }
    }

    //密码修改
    function saveSecurity($nMemberId,$aData,&$msg){
        if(!($aTemp = $this->db->selectrow("SELECT password,pw_question,pw_answer,uname,name,email FROM sdb_members WHERE  member_id=".intval($nMemberId)))){
            $msg='无效的用户Id';
            return false;
        }

        if(empty($aData['passwd'])){
            if( !$aData['pw_answer'] || !$aData['pw_question'] ){
                $msg='安全问题修改失败！';
                return false;
            }
            return $this->db->exec("UPDATE sdb_members SET pw_answer = ".$this->db->quote($aData['pw_answer'])." ,pw_question = ".$this->db->quote($aData['pw_question'])." WHERE member_id = ".intval($nMemberId));
        }
        else{   // if(($aData['pw_question'] == $aTemp['pw_question']) && ($aData['pw_answer'] == $aTemp['pw_answer']))
            $pObj=&$this->system->loadModel('member/passport');
            if ($obj=$pObj->function_judge('edituser')){
                $res = $obj->edituser($aTemp['uname'],$aData['old_passwd'],$aData['passwd'],$aTemp['email']);
                if ($res>0){
                    $aSet['password'] = md5($aData['passwd']);
                    $aRs = $this->db->query("SELECT password FROM sdb_members WHERE  member_id=".intval($nMemberId));
                    $sSql = $this->db->getUpdateSql($aRs,$aSet);
                    if($this->db->query($sSql)){
                        $this->system->setCookie('MEMBER',$this->cookieValue($nMemberId));
                        return true;
                    }
                    else{
                        return false;
                    }
                }
                else{
                    $msg='输入的旧密码与原密码不符！';
                    return false;
                }
            }
            else{
                //$passwdLen=strlen($aData['passwd']);
                if(md5($aData['old_passwd']) == $aTemp['password']){
                    if($aData['passwd'] == $aData['passwd_re']){
                        if(isset($aData['passwd']{3})){
                            if(!isset($aData['passwd']{20})){
                        $aSet['password'] = md5($aData['passwd']);
                        $aRs = $this->db->query("SELECT password FROM sdb_members WHERE  member_id=".intval($nMemberId));
                        $sSql = $this->db->getUpdateSql($aRs,$aSet);
                                if(!$sSql || $this->db->exec($sSql)){
                                    $aData = array_merge($aTemp,$aData);
                                    $this->fireEvent('chgpass',$aData,$nMemberId);        //会员更改密码事件
                                    $this->system->setCookie('MEMBER',$this->cookieValue($nMemberId));
            //                        $message = __('密码修改成功！');
                                    return true;
                                }else{
                                    $msg='密码修改失败！';
                                    return false;
                                }

                            }else{
                                $msg='密码长度不能大于20';
                                return false;
                            }
                        }else{
                            $msg='密码长度不能小于4';
                            return false;
                        }

                    }else{
                        $msg='两次输入的密码不一致！';
                        return false;
                    }
                }else{
                    $msg='输入的旧密码与原密码不符！';
                    return false;
                }
            }
        }


    }

    function remove($memberId){
        return $this->db->exec('delete from sdb_members where member_id='.intval($memberId));
    }

    function applyFilter(&$who,&$filter){
    }

    function listFilters($who=null){
        return array();
    }

    function getFilter($filterId){
    }

    //载入member实体
    function load($memberId){
        $member = &$this->system->loadModel('member/member');
        return $member->load($memberId)?$member:false;
    }

    function getMemberById($member_id) { ; }

    function getMemberByUser($user) { ; }

    function addMemberPrice($data) { ; }

    function getLevelByPoint($point) { ; }

    function getNextLevel($levelid=0){
        $aRet = $this->db->selectrow('SELECT * FROM sdb_member_lv WHERE pre_id='.intval($levelid));
        return $aRet['member_lv_id'];
    }

    function getPreLevel($levelid=0){
        $aRet = $this->db->selectrow('SELECT * FROM sdb_member_lv WHERE levelid='.intval($levelid));
        return $aRet['member_lv_id'];
    }

    function check_name_inuc($uname){
        $passport = &$this->system->loadModel('member/passport');
        if ($obj=$passport->function_judge('checkuser')){
            return $obj->checkuser($uname);
        }
        else{
            return true;
        }
    }
     function getMemberPluginUser($username){
        $row = $this->db->selectrow("SELECT * FROM sdb_members WHERE uname = ".$this->db->quote($username));
        if ($row){
            $row['secstr'] = $this->cookieValue($row['member_id']);
            return $row;
        }
        else{
            return false;
        }
    }
    function createUserFromPluin($data,&$message,$uid,$email=''){
        if ($data['passwd_r']){
            if($data['passwd']!=$data['passwd_r']){
                $message = __('两次密码输入不一致！');
                return false;
            }
        }
        $data['uname'] = trim(strtolower($data['uname']));
        $data['email'] = trim(strtolower($data['email']));
        $data['reg_ip'] = remote_addr();
        $data['regtime'] = time();
        $data['foreign_id']=$uid;
        $row = $this->db->selectrow('select * from sdb_member_lv where default_lv="1"');
        $data['member_lv_id'] = $row['member_lv_id']?$row['member_lv_id']:0;
        $defcur = $this->db->selectrow('select cur_code from sdb_currency where def_cur="true"');
        $data['cur'] = $defcur['cur_code'];
        $rs = $this->db->exec('select * from sdb_members where uname='.$this->db->quote($data['uname']).' or email='.$this->db->quote($data['email']));
        $data['password'] = md5($data['passwd']);
        getRefer($data);
        $sql = $this->db->getInsertSQL($rs,$data);
        if($this->db->exec($sql)){
            $userId = $this->db->lastInsertId();
            $status = &$this->system->loadModel('system/status');
            $status->add('MEMBER_REG');
            $this->init($userId);
            $data['member_id'] = $userId;
            $this->fireEvent('register',$data,$userId);        //会员注册成功事件
            $sql = 'select member_id,member_lv_id,email,uname,password,unreadmsg,cur,lang,point,foreign_id from sdb_members where member_id='.intval($userId);
            $row = $this->db->selectrow($sql);
            $row['secstr'] = $this->cookieValue($userId);
            return $row;
        }else{
            return false;
        }
    }
    function PlugUserExit(){
         $this->system->setCookie('MEMBER', '', time()-1000);
         $this->system->setCookie('MLV', '', time()-1000);
         $this->system->setCookie('CART', '', time()-1000);
         $this->system->setCookie('UNAME', '', time()-1000);
    }
    function PlugUserSetCookie($row){
        $this->system->setCookie('MEMBER',$row['secstr'],null);
        $this->system->setCookie('UNAME',$row['uname'],null);
        $this->system->setCookie('MLV',$row['member_lv_id'],null);
        $this->system->setCookie('CUR',$row['cur'],null);
        $this->system->setCookie('LANG',$row['lang'],null);
    }
    function PlugUserRegist($userdb='',$memberid='',$username='',$password='',$email=''){
        if (is_array($userdb)){
            $res=$this->db->selectrow('SELECT * FROM sdb_members where uname='.$this->db->quote($userdb['username']));
            if (!$res){
                $data['uname'] = trim($userdb['username']);
                $data['reg_ip'] = remote_addr();
                $data['regtime'] = $userdb['time'];
                $data['password'] = $userdb['password'];
                $data['email'] = $userdb['email'];
                $defcur = $this->db->selectrow('select cur_code from sdb_currency where def_cur="true"');
                $data['cur'] = $defcur['cur_code'];
                getRefer($data);
                $row = $this->db->selectrow('select * from sdb_member_lv where default_lv="1"');
                $data['member_lv_id'] = $row['member_lv_id']?$row['member_lv_id']:0;
                $rs = $this->db->exec('select * from sdb_members where 0=1');
                $sql = $this->db->getInsertSQL($rs,$data);
                if (!$sql || $this->db->exec($sql)){
                    $userId=$this->db->lastInsertId();
                    $status = &$this->system->loadModel('system/status');
                    $status->add('MEMBER_REG');
                    $this->init($userId);
                    $data['member_id'] = $userId;
                    $this->fireEvent('register',$data,$userId);        //会员注册成功事件
                }
            }
            else
                $this->PlugUserUpdate($userdb);
            $username = $userdb['username'];
        }
        else{
            $res=$this->db->selectrow('SELECT * FROM sdb_members where foreign_id='.$memberid);
            if (!$res){
                $data['foreign_id'] = $memberid;
                $data['uname'] = trim(strtolower($username));
                $data['reg_ip'] = remote_addr();
                $data['regtime'] = trim(time());
                $data['password'] = md5('123456');
                $data['email'] = $email;
                $defcur = $this->db->selectrow('select cur_code from sdb_currency where def_cur="true"');
                $data['cur'] = $defcur['cur_code'];
                getRefer($data);
                $row = $this->db->selectrow('select * from sdb_member_lv where default_lv="1"');
                $data['member_lv_id'] = $row['member_lv_id']?$row['member_lv_id']:0;
                $rs = $this->db->exec('select * from sdb_members where 0=1');
                $sql = $this->db->getInsertSQL($rs,$data);
                if (!$sql || $this->db->exec($sql)){
                    $userId=$this->db->lastInsertId();
                    $this->init($userId);
                    $data['member_id'] = $userId;
                    $this->fireEvent('register',$data,$userId);        //会员注册成功事件
                }
                $plugsql='select member_id,member_lv_id,email,uname,b_year,b_month,b_day,password,unreadmsg,cur,lang,point from sdb_members where member_id='.$userId;
            }
            else
                $plugsql='select member_id,member_lv_id,email,uname,b_year,b_month,b_day,password,unreadmsg,cur,lang,point from sdb_members where foreign_id='.$memberid;
        }
        !$plugsql?$plugsql = 'select member_id,member_lv_id,email,uname,b_year,b_month,b_day,password,unreadmsg,cur,lang,point from sdb_members where uname='.$this->db->quote($username):'';
        if($row = $this->db->selectrow($plugsql)){
            $row['secstr'] = $this->cookieValue($row['member_id']);
            $oMsg = &$this->system->loadModel('resources/msgbox');
            $row['unreadmsg'] = $oMsg->getNewMessageNum($row['member_id']);
            $this->PlugUserSetCookie($row);
        }
        return false;
    }


    function createotherlogin($row){
        if(!$row['open_id']){
            echo '由于网络传输原因，该功能暂不可用，请稍后再试';
            exit;
        }
        $random = rand(0,99);
        $random = strlen($random)==2?$random:'0'.$random;
        $rand = time().$random;

        $user['uname'] = $rand;

        $member_get = $this->system->loadModel('system/appmgr');
        $username = $member_get->login_refer($row);

        if($username){
          if($data = $this->db->selectrow("SELECT mm.member_id,mm.member_lv_id,mm.cur,mm.lang,mm.disabled,ol.uname FROM sdb_members mm LEFT JOIN sdb_trust_login ol ON mm.member_id = ol.member_id WHERE ol.member_id =".$username['member_id'])){
            if($data['disabled']=='false'){
              unset($data['disabled']);
              $user = $data;
            }else{
              echo '账号登录失败，请联系网店管理员';
              exit;
            }
          }
      
        }else{            
            $defcur = $this->db->selectrow('select cur_code from sdb_currency where def_cur="true"');
            $user['cur'] = $defcur['cur_code'];
            $mem_level = $this->system->loadModel('member/level');
            $user['member_lv_id'] = $mem_level->getDefauleLv();
            $user['member_refer'] = $row['open_type'];
            $user['password'] = md5(time());
            $user['lang']="123";
            $user['email'] = $row['email']?$row['email']:'*@*.com';
            $rs = $this->db->exec('select * from sdb_members where uname="'.$user['uname'].'"');
            $sql = $this->db->getInsertSQL($rs,$user);
            $this->db->exec($sql);
            $user['member_id'] = $this->db->lastInsertId();

            $ol_data['member_id'] = $user['member_id'];
            $ol_data['show_uname'] = $row['open_type']."_".$row['open_id'];
            $ol_data['uname'] = $row['open_id'];
            $ol_data['member_refer'] = $row['open_type'];
            $ol = $this->db->exec('select * from sdb_trust_login where 1=1');
            $sql_ol = $this->db->getInsertSQL($ol,$ol_data);
            $this->db->exec($sql_ol);

        }

        $user['uname'] = $row['open_type']."_".$row['open_id'];

        $user = array_merge($user,$row);

        $user['secstr'] = $this->cookieValue($user['member_id']);
        $this->PlugUserSetCookie($user);
        $appmgr = $this->system->loadModel("system/appmgr");
        $app_model = $appmgr->load("openid_".$row['open_type']);
        if(method_exists($app_model,'openid_login')){
            $r_array = $app_model->openid_login($user);
        }
        $oCart = &$this->system->loadModel('trading/cart');
        $oCart->memberLogin = false;
        $cartCookie = $oCart->getCart();
        $oCart->checkMember($user);
        $oCart->memberLogin = true;
        $oCart->save('all', $cartCookie);
        $this->system->setCookie('LOGIN_TYPE',$row['open_type'],null);
        return $r_array;

    }
    function PlugUserUpdate($userdb){
        $data['password'] = $userdb['password'];
        $data['email'] = $userdb['email'];
        $data['reg_ip'] = remote_addr();
        $data['regtime'] = $userdb['time'];
        $rs = $this->db->exec('SELECT * FROM sdb_members where uname='.$this->db->quote($userdb['username']));
        $sql = $this->db->getUpdateSQL($rs,$data);
        if (!$sql || $this->db->exec($sql)){}else return false;
    }
    function PlugUserDelete($param){
        if($param){
            $sql="delete from sdb_members where member_id in ($param)";
            $this->db->exec($sql);
        }
    }
    function setPlugCookie($k,$v){
        $this->system->setCookie($k,$v);
    }
    function getPlugCookie($k){
        return $_COOKIE[$k];
    }
    function adminUpdateMemberPassword($nMId,$aData,$sendemail){
        $pObj=$this->system->loadModel('member/passport');
        if ($obj=$pObj->function_judge('edituser')){
            $res = $obj->edituser($aData['uname'],$aData['old_passwd'],$aData['passwd'],$aTemp['email'],1);
            if ($res<=0)
                return $res;
        }
        $rs = $this->db->exec("select password from sdb_members where member_id='".$nMId."'");
        $sql = $this->db->getUpdateSQL($rs,$aData);
        if (!$sql || $this->db->exec($sql)){
            if ($sendemail)
                $this->fireEvent('chgpass',$aData,$nMId);
            return true;
        }
        else return false;
    }

    function UpdateForeignId($data){
        foreach($data as $key => $val){
            $this->db->exec('Update sdb_members set foreign_id='.$val.' where member_id='.$key);
        }
    }
    function _get_level_change(){
        return true;
    }
    function _get_has_sup(){
        return false;
    }
    function _get_reg_node($member_id){
        return $member_id;
    }
    function _get_advisory_new(){
        return true;
    }
    function _get_advisory_replay(){
        return true;
    }
    function _get_advisory_del(){
        return true;
    }
    function _get_shortmessage_new(){
        return true;
    }
    function _get_shortmessage_reply(){
        return true;
    }
    function _get_shortmessage_del(){
        return true;
    }
    function _get_shopmessage_new(){
        return true;
    }
    function _get_shopmessage_reply(){
        return true;
    }
    function _get_shopmessage_del(){
        return true;
    }
    function _get_discuzz_check(){
        return true;
    }
    function _get_discuzz_del(){
        return true;
    }
    function _get_discuzz_to_reply(){
        return true;
    }
    function _get_discuzz_new(){
        return true;
    }
    function _get_login_birthay($member_id){
        $mem=$this->system->loadModel('member/member');
        $row=$mem->instance($member_id,'b_month,b_day');
        if ($row['b_month']==date("m")&&$row['b_day']==date("d"))
            return true;
        else
            return false;
    }
    function _get_login_times($member_id){
        $mem=$this->system->loadModel('member/member');
        $row=$mem->instance($member_id,'login_count');
        return $row['login_count'];
    }
    function _get_service_apply(){
        return true;
    }
    function _get_service_manage(){
        return true;
    }
    function _get_point_change(){
        return true;
    }
    function _get_advance_relay($log_id){
        $adv=$this->system->loadModel('member/advance');
        $row=$adv->instance($log_id,'member_id');
        $advance=$adv->get($row['member_id']);
        return $advance;
    }
    function _get_advance_onetime($log_id){
        $adv=$this->system->loadModel('member/advance');
        $row=$adv->instance($log_id,'money');
        return $row['money'];
    }
}
?>
