    <?php
/**
 * mdl_systmpl
 *
 * 增加了一种smarty资源: systmpl
 * 会自动从数据库中取模板那内容，当模板不存在时，会去相应的目录取html文件
 *
 * 特殊的修正符':'描述着子类，子类可以重定义目录位置，目前唯一的子类就是messenger
 *
 * systmpl:index   -> core/html/index.html
 * systmpl:page/a   -> core/html/page/a.html
 * systmpl:messenger:email/order-create   -> plugins/messenger/email/order-create.html
 *
 *
 * 今后可能会增加版本管理的功能
 *
 * @uses modelFactory
 * @package
 * @version $Id: mdl.systmpl.php 1867 2008-04-23 04:00:24Z flaboy $
 * @copyright 2003-2007 ShopEx
 * @author Wanglei <flaboy@zovatech.com>
 * @license Commercial
 */
class mdl_systmpl extends modelFactory {

    function fetch($tplname,$data=null){
        $smarty = &$this->system->loadModel('system/frontend');
        $data['shopname'] = $this->system->getConf('system.shopname');
        $data['pay_time'] = date('Y-m-d H:i:s',$data['acttime']);//订单付款时间
        $smarty->_vars = &$data;
        $output = $smarty->fetch('systmpl:'.$tplname);

        unset($smarty);
        return $output;
    }

    function getTitle($ident){
        $row = $this->db->select('select title,path from sdb_sitemaps where action=\'page:'.$ident.'\'');
        if($row[0]['path']){
            $row[0]['path']=substr($row[0]['path'],0,strlen($row[0]['path'])-1);
            $parentRow=$this->db->select('select title,action as link from sdb_sitemaps where node_id in ('.$row[0]['path'].')');
            $parentRow[]=array('title'=>$row[0]['title'],'link'=>$row[0]['action']);
            return $parentRow;
        }

        return $row;
    }

    function _file($name){
        if($p = strpos($name,':')){
            $type = substr($name,0,$p);
            $name=substr($name,$p+1);
            if($type=='messenger'){
                return PLUGIN_DIR.'/messenger/'.$name.'.html';
            }
        }else{
            if(defined('CUSTOM_CORE_DIR') && is_file(CUSTOM_CORE_DIR.'/html/'.$name.'.html')){
                return CUSTOM_CORE_DIR.'/html/'.$name.'.html';
            }else{
                return CORE_DIR.'/html/'.$name.'.html';
            }
        }
    }

    function get($name){
        if($aRet = $this->db->selectrow("SELECT content FROM sdb_systmpl WHERE active='true' and tmpl_name = '$name'")){
            return $aRet['content'];
        }else{
            return file_get_contents($this->_file($name));
        }
    }

    function clear($name){
        $rs = $this->db->exec("select * from sdb_systmpl where tmpl_name='$name'");
        $sql = $this->db->getUpdateSQL($rs,array(
            'edittime'=>time(),
            'active'=>'false',
            ));
        return $this->db->exec($sql);
    }

    function tpl_src($matches){
        return '<{'.html_entity_decode($matches[1]).'}>';
    }

    function set($name,$body){
        file_put_contents($this->_file($name),$body);
        $body = str_replace(array('&lt;{','}&gt;'),array('<{','}>'),$body);
        $body = preg_replace_callback('/<{(.+?)}>/',array(&$this,'tpl_src'),$body);
        $rs = $this->db->exec("select * from sdb_systmpl where tmpl_name='$name'");
        $sql = $this->db->getUpdateSQL($rs,array(
            'tmpl_name'=>$name,
            'edittime'=>time(),
            'active'=>'true',
            'content'=>$body
            ),true);
        return $this->db->exec($sql);
    }

    function getByType($type){
        $aRet = $this->db->selectrow("SELECT content FROM sdb_systmpl WHERE tmpl_name = '$type'");
        return $aRet['content'];
    }

    function updateContent($type, $txt){
        $rs = $this->db->exec("select * from sdb_systmpl where tmpl_name='$type'");
        $aData['content'] = $txt;
        $aData['t'] = time();
        $aData['tmpl_name'] = $type;
        $sql = $this->db->getUpdateSQL($rs, $aData, true);
        return $this->db->exec($sql);
    }
}