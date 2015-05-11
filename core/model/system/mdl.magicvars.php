<?php
if(!class_exists('shopObject')){
    require('shopObject.php');
}
class mdl_magicvars extends shopObject {
    var $defaultCols ='var_name,var_title,var_value,var_remark';
    var $idColumn = 'var_name';
    var $defaultOrder = array('var_name','desc');
    var $tableName = 'sdb_magicvars';
    var $filter = '';

    function getColumns($filter){
        $ret = array('_cmd'=>array('label'=>__('操作'),'width'=>70,'html'=>'system/magicvars/finder_command.html'));
        return array_merge($ret,parent::getColumns());
    }

    function _filter($filter){
        if($this->filter){
            return parent::_filter($filter).$this->filter;
        }else{
            return parent::_filter($filter);
        }
    }
    function modifier_var_value(&$rows){
        foreach($rows as $key=>$val){
            $rows[$key] = preg_replace('/images\//',$this->system->base_url().'images/',$val);
        }
    }
    function insert($data,&$message){
        if (!$this->findError($data,$message)){
            return false;
        }
        parent::insert($data);
        return true;
    }
    function update($data,$filter,&$message){
        if (!$this->findError($data,$message)){
            return false;
        }
        parent::update($data,$filter);
        return true;
    }
    function findError($data,&$message){
        if(substr($data['var_name'],0,1)!="{"||substr($data['var_name'],-1)!="}"){
           $message=__('变量名不符合格式，请重新更换填写');
           return false;
        }
        $vars = $this->getList('var_name', '', 0, -1);
        foreach($vars as $k =>$val){
            $tempvars[] = $val['var_name'];
        }
        if(!$data['is_editing'] && in_array($data['var_name'],$tempvars)){
            $message=__('该变量名已经存在，请重新更换填写');
            return false;
        }
        return true;
    }
}