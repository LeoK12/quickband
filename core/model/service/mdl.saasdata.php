<?php
if(!class_exists('mdl_apiclient')){
    include_once(dirname(__FILE__).'/mdl.apiclient.php'); 
}
class mdl_saasdata extends mdl_apiclient {


    function mdl_saasdata(){
        $this->key = SAAS_NATIVE_KEY;
        $this->url = SAAS_API_URL;
        $this->city_url = SAAS_CITY_API_URL;
        parent::modelFactory();
    }

    function &getCatesById($cid, $pid=0, $c_type='c'){
        if(!isset($this->Catescache[$cid])){ 
           $params = array('cid'=>$cid,'vid'=>$pid);
            if($c_type == 'c'){
                $result = $this->native_svc('cats.getCates',$params);
            }else{
                $result = $this->native_svc('cats.getProps',$params);

            }

            if($result['result']=='true'){
                $this->Catescache[$cid] = serialize($result['result_msg']);
            }
 
        }
        return $this->Catescache[$cid];
    }

    function &getPropValuesById($cid){
        //if(!isset($this->PropValuescache[$cid])){
            $params = array('cid'=>$cid);
            $result = $this->native_svc('cats.getCatePropValues',$params); 
            if($result['result']=='true'){
                $this->PropValuescache[$cid] = serialize($result['result_msg']);
            }
        //}
         return $this->PropValuescache[$cid];
    }

    function getTypePathApi($cid){
        if(!isset($this->CatPathValuescache[$cid])){
           
           $params = array('cid'=>$cid);

           $result = $this->native_svc('cats.getCatePath',$params); 
           
           if($result['result']=='true'){
                $this->CatPathValuescache[$cid] = $result['result_msg'];
           }        

        }
        return $this->CatPathValuescache[$cid];
    }

    function getGoodsCity($cityid){
         if(!isset($this->PropValuescache[$cityid])){
            $params = array('cityid'=>$cityid);
            $result = $this->native_svc('city.get_city',$params); 
           
            if($result['result']=='true'){
                $this->GoodsCitycache[$cityid] = serialize($result['result_msg']);
            }    
         }
        return $this->GoodsCitycache[$cityid];
    }
    
    function saveImg($gid,$img,$iid,&$errMsg){
        
        if($img && $gid){
            $params = array('func'=>'upload_img',"host_id"=>HOST_ID,'info'=>json_encode($img),'iid'=>$iid,'gid'=>$gid);
            
            $return = $this->native_svc('center.upload_img',$params);

            //file_put_contents(HOME_DIR.'/rt_return.txt',print_r($return,true).print_r($params,true));
            return true;
        }
    }
    function saveGoods($data, &$errMsg){
        if (!$data['goods']['iid'])
        {
            $func = "add_goods";
            $params = array('rsp'=>$data['goods']['iid']);
        }
        else{
            $func = "up_goods";
        }
        $params = array('func'=>$func,"host_id"=>HOST_ID,'info'=>json_encode($data),'gid'=>$data['goods']['gid']);
        $return = $this->native_svc('center.op_goods',$params);  
        if($return['result']=='false'){
            if ($return['result_msg']=="goods_op_duplicate"){
                $errMsg = "数据正在上传淘宝中，请稍后再提交保存。";
                return $return['result'];
            }elseif ($return['result_msg']=="op_goods_fail"){
                $errMsg = "数据上传淘宝失败";
                return $return['result'];
            }
        } 
        
         return $return['result'];
    }

}
