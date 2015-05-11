<?php
require_once('shopObject.php');
define('TEST_INCLUDE',1);
define('TEST_EXCLUDE',2);
define('TEST_BEGIN',3);
define('TEST_END',4);

define('TEST_EQUAL',7);
define('TEST_NOT_EQUAL',8);
define('TEST_GREAT_THAN',9);
define('TEST_LESS_THAN',10);

define('TEST_EARLY_THAN',11);
define('TEST_LATE_THAN',12);
define('TEST_IS_WORKDAY',13);
define('TEST_WEEKEND',14);

class mdl_trigger extends shopObject{

    var $defaultCols = 'trigger_event,trigger_memo,filter_str,action_str,active';
    var $idColumn = 'trigger_id'; //表示id的列
    var $textColumn = 'filter_str';
    var $tableName = 'sdb_triggers';

    function mdl_trigger(){
        parent::shopObject();
        $this->trigger_points = array(
//            'goods/products'=>__('商品'),
            'trading/order'=>__('订单'),
            'member/account'=>__('会员'),
//            'system'=>__('系统'),
        );
        $this->listeners = $this->system->getConf('system.event_listener');
    }

    function getColumns(){
        $ret = array('_cmd'=>array('label'=>__('操作'),'width'=>75,'html'=>'system/trigger/command.html'));
        return array_merge($ret,parent::getColumns());
    }

    function _filter($filter){
        if($filter['target']){
            $where = array('trigger_event like "'.$filter['target'].':%"');
            unset($filter['target']);
        }else{
            $where = array();
        }
        return parent::_filter($filter,$tbase,$where);
    }

    function modifier_trigger_event($rows){
        $event_map = array();
        foreach($rows as $k=>$r){
            list($target,$event) = explode(':',$r);
            if($target=='system'){
                $rows[$k] = __('人工访问');
            }else{
                if(!$event_map[$target]){
                    $obj = &$this->system->loadModel($target);
                    $event_map[$target] = $obj->events();
                    $obj = null;
                }
                $rows[$k] = $this->trigger_points[$target].$event_map[$target][$event]['label'];
            }
        }
    }

    /**
     * object_fire_event
     * 执行对象事件
     *
     * @param mixed $action
     * @param mixed $object
     * @param mixed $member_id
     * @param mixed $target
     * @access public
     * @return void
     */
    function object_fire_event($action , &$object, $member_id,&$target){
        //ob_start();'system.event_listener'
        if(false===strpos($action,':')){
            $trigger_event = $target->modelName.':'.$action;
            $modelName = $target->modelName;
        }else{
            $trigger_event = $action;
            list($modelName,$action) = explode(':',$action);
        }
        $type = $target->typeName;
        $this->system->messenger = &$this->system->loadModel('system/messenger');
        $this->system->_msgList = $this->system->messenger->actions();
        if($this->system->_msgList[$type.'-'.$action]){
            $this->system->messenger->actionSend($type.'-'.$action,$object,$member_id);
        }
        if(defined('DISABLE_TRIGGER') && DISABLE_TRIGGER){
            return true;
        }else{

            $all_triggers = $this->db->select('select trigger_define from sdb_triggers where trigger_event="'.$trigger_event.'" and active="true" and disabled="false" order by trigger_order desc');

            if($all_triggers){
                $events = $target->events();
                if (!$events){
                    $instance=$this->system->loadModel($modelName);
                    $events = $instance->events();
                }else{
                    $instance = $target;
                }

                $object['_event_date_'] = time();
                $object['ip'] = remote_addr();
                foreach($all_triggers as $trigger){
                    $trigger = unserialize($trigger['trigger_define']);

                    if($this->__test_role($trigger['filter_mode'],$trigger['filter'],$object,$events[$action]['params'],$instance)){
                        $this->__call_actions($trigger['actions'],$object);
                    }
                }

            }


            $appmgr = &$this->system->loadModel('system/appmgr');
            $data=array_merge((array)$this->listeners['*'],
                (array)$this->listeners[$target->modelName.':*'],
                (array)$this->listeners[$target->modelName.':'.$action]);
            foreach($data as $func){
                list($mod,$func) = $appmgr->get_func($func);
                if($func)$mod->$func($action,$object);
            }
            return true;
        }
        //$log = ob_get_contents();
        //ob_end_clean();
    }

    /**
     * __test_role
     * 测试一个过滤器组
     *
     * @param mixed $mode
     * @param mixed $filter
     * @param mixed $data TRIGGER_LOG
     * @param mixed $params_define
     * @access protected
     * @return void
     */
    function __test_role($mode , $filter , &$data , &$params_define,&$target){
        if($mode=='every'){
            return true;
        }else{
            if($mode=='any'){
                foreach($filter as $test){
                    if(!$this->__prepare_k_data($val,$data,$test['key'],$target)) continue;
                    if($this->__test_filter($val,$params_define[$test['key']]['type'],$test['test'],$test['val'])){
                        return true;
                    }
                }
                return false;
            }elseif($mode=='all'){
                foreach($filter as $test){

                    if(!$this->__prepare_k_data($val,$data,$test['key'],$target)) return false;
                    if(!$this->__test_filter($val,$params_define[$test['key']]['type'],$test['test'],$test['val'])){
                        return false;
                    }
                }
                return true;
            }
        }
    }

    function __prepare_k_data(&$val,&$data,$key,&$target){
        if(isset($data[$key])){
            $val = $data[$key];
            return true;
        }else{

            if(method_exists($target,$func = '_get_'.$key)){
                $val = $data[$key] = $target->$func($data[$target->idColumn]);
                return true;
            }else{
                return false;
            }
        }
    }

    /**
     * __test_filter
     * 测试单条条件是否成立
     *
     * @param mixed $data
     * @param mixed $type
     * @param mixed $test
     * @param mixed $val
     * @access protected
     * @return void
     */
    function __test_filter($data,$type,$test=null,$val=null){
        switch($type){
        case 'bool':
            if(!is_bool($test)) eval( '$test = '.$test.';' );   //如果是非bool行，需要转换成bool
            return ($data == $test);

        case 'string':
            switch($test){
            case TEST_INCLUDE:
                return (false === strpos($data,$val));
            case TEST_EXCLUDE:
                return (false !== strpos($data,$val));
            case TEST_BEGIN:
                return ($val == substr($data,0,strlen($val)));
            case TEST_END:
                return ($val == substr($data,0-strlen($val)));
            default:
                return false;
            }

        case 'number':
            switch($test){
            case TEST_GREAT_THAN:
                return ($data > $val);
            case TEST_LESS_THAN:
                return ($data < $val);
            case TEST_EQUAL:
                return ($data == $val);
            case TEST_NOT_EQUAL:
                return ($data != $val);
            default:
                return false;
            }

        case 'ip':
            if(!function_exists('shop_match_network')){
                require(CORE_INCLUDE_DIR.'/shop/core.match_network.php');
            }
            switch($test){
            case TEST_INCLUDE:
                return shop_match_network($val,$data);
            case TEST_EXCLUDE:
                return !shop_match_network($val,$data);
            default:
                return false;
            }

        case 'date':
            switch($test){
            case TEST_EQUAL:
                return ($data == $val);
            case TEST_NOT_EQUAL:
                return ($data !== $val);
            case TEST_EARLY_THAN:
                return ($data < $val);
            case TEST_LATE_THAN:
                return ($data > $val);
            case TEST_WEEKEND:
                $d = date('w',$date);
                return ($d==5 || $d==6);
            case TEST_IS_WORKDAY:
                $d = date('w',$date);
                return ($d!=5 && $d!=6);
            default:
                return false;
            }

        default:
            return $data;
        }
    }

    /**
     * __call_actions
     * 执行网店机器人动作
     *
     * @param mixed $action
     * @param mixed $data
     * @access protected
     * @return void
     */
    function __call_actions($action,&$data){
        $plugin = &$this->system->loadModel('system/addons');
        foreach($action as $act){
            list($part,$func) = explode(':',$act['act']);
            if(!isset($this->_action_mod[$part])){
                $this->_action_mod[$part] = $plugin->load($part,'action');
            }
            $args = is_array($data)?$data:array(&$data);
            $args = is_array($act['args'])?array_merge($args,$act['args']):$args;
            call_user_func_array(array($this->_action_mod[$part],$func),array($args,$act['args']));
        }
    }

    /**
     * param_types
     * 获取所有属性类型定义
     *
     * @access public
     * @return void
     */
    function param_types(){
        return array(
            'bool'=>array('true'=>array('label'=>__('是')),'false'=>array('label'=>__('否'))),
            'string'=>array(
                TEST_INCLUDE=>array('label'=>__('包含'),'input'=>'text'),
                TEST_EXCLUDE=>array('label'=>__('不包含'),'input'=>'text'),
                TEST_BEGIN=>array('label'=>__('起始字符'),'input'=>'text'),
                TEST_END=>array('label'=>__('结束字符'),'input'=>'text')
            ),
            'ip'=>array(
                TEST_INCLUDE=>array('label'=>__('位于网段'),'input'=>'text'),
                TEST_EXCLUDE=>array('label'=>__('不位于网段'),'input'=>'text')
            ),
            'number'=>array(
                TEST_GREAT_THAN=>array('label'=>__('大于'),'input'=>'number'),
                TEST_LESS_THAN=>array('label'=>__('小于'),'input'=>'number'),
                TEST_EQUAL=>array('label'=>__('等于'),'input'=>'number'),
                TEST_NOT_EQUAL=>array('label'=>__('不等于'),'input'=>'number'),
            ),
            'date'=>array(
                TEST_EQUAL=>array('label'=>__('是'),'input'=>'date'),
                TEST_NOT_EQUAL=>array('label'=>__('不是'),'input'=>'date'),
                TEST_EARLY_THAN=>array('label'=>__('早于'),'input'=>'date'),
                TEST_LATE_THAN=>array('label'=>__('晚于'),'input'=>'date'),
                TEST_WEEKEND=>array('label'=>__('是周末')),
                TEST_IS_WORKDAY=>array('label'=>__('是工作日'))
            ),
        );
    }

}
