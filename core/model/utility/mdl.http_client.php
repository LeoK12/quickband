<?php
if(!defined('CORE_INCLUDE_DIR')){
    define('CORE_INCLUDE_DIR',CORE_DIR.
        ((!defined('SHOP_DEVELOPER') || !constant('SHOP_DEVELOPER')) && version_compare(PHP_VERSION,'5.0','>=')?'/include_v5':'/include'));
}
if(!class_exists('http_base')){
    require(CORE_INCLUDE_DIR.'/http.php');
}
class mdl_http_client extends http_base{

    function get($url,$headers=null,$callback=null){
        return $this->action(__FUNCTION__,$url,$headers,$callback);
    }

    function post($url,$data,$headers=null,$callback=null){
        return $this->action(__FUNCTION__,$url,$headers,$callback,$data);
    }

    function ping($url){
        return $this->action('GET',$url,null,array(&$this,'_void'));
    }

    function upload($url,$files,$data,$headers=null,$callback=null){
        $boundary = '----ShopExFormBoundaryEsor2rdD1hne8INi';
        $headers['Content-Type']='multipart/form-data; boundary='.$boundary;
        $formData = array();
        $this->_http_query($formData,$data);

		$output ='';
        foreach($formData as $k=>$v){
			$output .= '--'.$boundary."\r\n";
            $output .= 'Content-Disposition: form-data; name="'
                .str_replace('"','\\\"',$k)."\"\r\n\r\n";
            $output .= $v."\r\n";
        }
        foreach($files as $k=>$v){
			$output .= '--'.$boundary."\r\n";
            $output .= 'Content-Disposition: form-data; name="'
                .str_replace('"','\\\"',$k).'"; filename="'.basename($v)."\"\r\n";
            $mime = function_exists('mime_content_type')?mime_content_type($v):'application/octet-stream';
            $output .= "Content-Type: $mime\r\n\r\n";
            $output .= file_get_contents($v)."\r\n";
        }
		$output .= '--'.$boundary."--\r\n";

        return $this->action('post',$url,$headers,$callback,$output);
    }

    function _http_query(&$return,$data,$prefix=null,$key='')
    {
        $ret = array();
        foreach((array)$data as $k => $v){
            if(is_int($k) && $prefix != null){
                $k = $prefix.$k;
            }
            if(!empty($key)){
                $k = $key."[".$k."]";
            }

            if(is_array($v) || is_object($v)){
                $this->_http_query($return,$v,"",$k);
            }else{
                $return[$k]=$v;
            }
        }
    }

    function _void(){ return false; }

}
?>
