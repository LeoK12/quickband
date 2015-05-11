<?php
/**
 * mdl_operator
 *
 * @uses modelFactory
 * @package
 * @version $Id: mdl.operator.php 1985 2008-04-28 06:36:02Z flaboy $
 * @copyright 2003-2007 ShopEx
 * @author Likunpeng <leoleegood@zovatech.com>
 * @license Commercial
 */
include_once('shopObject.php');
class mdl_operator extends shopObject{

    var $idColumn = 'op_id';
    var $textColumn = 'username';
    var $defaultCols = 'username,name,lastlogin,department,status,logincount,roles';
    var $adminCtl = 'admin/operator';
    var $defaultOrder = array('op_id', 'DESC');
    var $tableName = 'sdb_operators';

    function getColumns(){
        $ret = array('_cmd'=>array('label'=>__('操作'),'width'=>75,'html'=>'admin/finder_command.html'),'roles'=>array('label'=>__('角色'),'sql'=>'op_id','width'=>270,'readonly'=>1));
        return array_merge($ret,parent::getColumns());
    }

    function modifier_roles(&$rows){
        $role_list = $this->db->select('select l.op_id,r.role_name
            from sdb_lnk_roles l
            left join sdb_admin_roles r on r.role_id=l.role_id
            where l.op_id in('.implode(',',$rows).') and r.disabled!="true"');
        $rst = array();
        foreach($role_list as $r){
            $rst[$r['op_id']][] = $r['role_name'];
        }
        foreach($rows as $k=>$r){
            $rows[$k] = is_array($rst[$k])?(implode(',',$rst[$k])):'';
        }
    }

    function delete($filter,$current_op_id=false){
        if(method_exists($this,'pre_delete')){
            $this->pre_delete($filter);
        }
        if(method_exists($this,'post_delete')){
            $this->post_delete($filter);
        }
        $this->disabledMark = 'normal';
        $sql = 'delete from '.$this->tableName.' where '.$this->_filter($filter);
        if($current_op_id){
            $sql.=' and op_id != '.intval($current_op_id);
        }
        if($this->db->exec($sql)){
            if($this->db->affect_row()){
               return $this->db->affect_row();
            }else{
               return true;
            }
        }else{
             return false;
        }
    }

    function getUsedRoles($op_id){
        $rows = $this->db->select('select role_id from sdb_lnk_roles where op_id='.intval($op_id));
        foreach($rows as $r){
            $rtn[$r['role_id']] = $r['role_id'];
        }
        return $rtn;
    }


    function update($data,$filter){
        if(isset($data['userpass'])){
            $data['userpass'] = md5($data['userpass']);
        }

        $c = parent::update($data,$filter);

        if(!isset($data['roles'])){
            return $c;
        }

        if($filter['op_id']){
            $op_id = array();
            foreach($this->getList('op_id',$filter) as $r){
                $op_id[] = $r['op_id'];
            }
        }else{
            $op_id = $filter['op_id'];
        }

        if(count($op_id)==1){
            $rows = $this->db->select('select role_id from sdb_lnk_roles where op_id in ('.implode(',',$op_id).')');
            $in_db = array();
            foreach($rows as $r){
                $in_db[] = $r['role_id'];
            }
            $to_add = array_diff($data['roles'],$in_db);
            $to_del = array_diff($in_db,$data['roles']);

            if(count($to_add)>0){
                $sql = 'INSERT INTO `sdb_lnk_roles` (`op_id`,`role_id`) VALUES ';
                foreach($to_add as $role_id){
                    $actions[] = "({$op_id[0]},$role_id)";
                }
                $sql .= implode($actions,',').';';
                $a = $this->db->exec($sql);
            }

            if(count($to_del)>0){
                $this->db->exec('delete from sdb_lnk_roles where role_id in ('.implode(',',$to_del).') and op_id='.intval($op_id[0]));
            }
        }else{
        }

        return $c;
    }

    function insert($data){
        $data['userpass'] = md5(trim($data['userpass']));
        $op_id = parent::insert($data);
        if($op_id && is_array($data['roles']) && isset($data['roles'][0])){
            $sql = 'INSERT INTO `sdb_lnk_roles` (`op_id`,`role_id`) VALUES ';
            foreach($data['roles'] as $role_id){
                $roles[] = "($op_id,$role_id)";
            }
            $sql .= implode($roles,',').';';
            $a = $this->db->exec($sql);
        }
        return $op_id;
    }

    function tryLogin($aValue,$issuper=false){
        if($aValue['passwd']=='+_-_-_+'){
            $aValue['passwd']='';
        }
        $sql = "SELECT * FROM sdb_operators WHERE username = ".$this->db->quote($aValue['usrname'])." AND userpass = '".md5($aValue['passwd'])."' AND disabled='false'";
        if($issuper){
            $sql.=" AND super='1'";
        }
        return $this->db->selectrow($sql);
    }


    /**
     * toUpdateSelf
     *
     * @param mixed $aValue,$aSetting
     * @access public
     * @return array
     */
    //+
    function toUpdateSelf($aValue,$aSetting){
        $aSetting['lang'] = $aValue['language'];
        $aSetting['timezone'] = $aValue['timezone'];
        $aValue['config'] = $aSetting;
        if(isset($aValue['userpass'])){
            $aValue['userpass'] = md5($aValue['userpass']);
        }

        $aRs = $this->db->query("SELECT * FROM sdb_operators WHERE op_id=".$aValue['op_id']);
        $sSql = $this->db->GetUpdateSql($aRs,$aValue);
        return !$sSql || $this->db->exec($sSql);
    }

    function check_role($op_id,$workground){
        if(!$workground)return true;

        $role = &$this->system->loadModel('admin/adminroles');
        $opt = $role->rolemap();
        $r = $this->db->selectrow('SELECT a.action_id
            FROM sdb_lnk_roles s
            INNER JOIN sdb_lnk_acts a ON a.role_id=s.role_id
            where op_id='.intval($op_id).' and action_id='.intval($opt[$workground]));
        return $r;
    }

    function &getActions($op_id){
        if(!isset($this->actmap[$op_id])){
            $allow_wground = array();
            $sql = 'SELECT distinct(a.action_id)
                FROM sdb_lnk_roles s
                    INNER JOIN sdb_admin_roles r ON r.role_id=s.role_id AND r.disabled!="true"
                    LEFT JOIN sdb_lnk_acts a ON a.role_id=r.role_id
                    where s.op_id='.intval($op_id);
            foreach($this->db->select($sql) as $r){
                        $allow_wground[$r['action_id']] = $r['action_id'];
                    }
            $this->actmap[$op_id] = &$allow_wground;
        }
        return $this->actmap[$op_id];
    }
    function setLogInfo($data,$op_id){
        $rs=$this->db->exec('select lastlogin,logincount from sdb_operators where op_id='.intval($op_id));
        $sSql=$this->db->getUpdateSql($rs,$data);
        $this->db->exec($sSql);
    }
}
?>
