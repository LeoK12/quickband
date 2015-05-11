<?php
class http_base{

    function call($cmd,$url,$headers=null,$data=null,$exception=null){
        $dist = parse_url($url);
        if(!($request = $this->__build_request($cmd,$dist,$headers,$data))){
            return false;
        }
        if(!($fp = fsockopen($dist['host'], isset($dist['port'])?$dist['port']:80, $errno, $errstr, 3))){
            return false;
        }
        fwrite($fp, $request);

        $response = null;
        while (!feof($fp)) {
            $response.=fgets($fp, 128);
        }
        fclose($fp);
        preg_match("!HTTP/[0-9\.]+\s+([0-9]+)\s(.*?)\r\n(.*)!is",$response,$match);
        unset($request,$response);

        if($pos = strpos($match[3],"\r\n\r\n")){
            $response_body = substr($match[3],$pos+4);
            $response_head = substr($match[3],0,$pos);
        }else{
            $response_head = $match[3];
            $response_body = null;
        }

        if($match[1]==200){
            return $response_body;
        }else{
            if(isset($exception[$match[1]])){
                return call_user_func_array($exception[$match[1]],array($response_head,array('cmd'=>$cmd,'url'=>$url,'headers'=>$headers,'data'=>$data)));
            }else{
                trigger_error('HTTP Response('.$match[1].' '.$match[2].')',E_USER_WARNING);
                return false;
            }
        }
    }

    function __build_request($cmd,$dist,$headers=null,$data=null){
        if($dist['scheme']!='http'){
            trigger_error('HTTP request: Bad schema('.$dist['schema'].')');
            return false;
        }

        $uri = $dist['path'];
        if(isset($dist['query'])){
            $uri.='?'.$dist['query'];
        }

        $request = strtoupper($cmd).' '.$uri.' HTTP/1.1'."\r\n";
        $request .= 'Host: '.$dist['host']."\r\n";
        $request .= 'Connection: Close'."\r\n";

        foreach((array)$headers as $k=>$v){
            $request .= $k.': '.$v."\r\n";
        }
        if($data){
            if(is_array($data)){
               $data = http_build_query($data);
            }
            $request .= 'Content-length: '.strlen($data)."\r\n";
            return $request."\r\n".$data;
        }else{
            return $request."\r\n";
        }
    }

}

class http_full extends http_base{

    var $cache_dir = null;

    function call($cmd,$url,$headers=null,$data=null){
        return parent::call($cmd,$url,$headers,$data,array(
                '301'=>array(&$this,'__redirect'),
                '302'=>array(&$this,'__redirect'),
            ));
    }

    function __parse_header($header_txt){
        $headers = array();
        foreach(explode("\r\n",$header_txt) as $head){
            $pos = strpos($head,':');
            $headers[strtolower(substr($head,0,$pos))] = trim(substr($head,$pos+1));
        }
        return $headers;
    }

    function __redirect($header_txt,$params){
        $headers = $this->__parse_header($header_txt);
        $params['headers']['refer'] = $params['url'];
        if($headers['location']){
            return $this->call($params['cmd'],$headers['location'],$params['headers'],$params['data']);
        }else{
            trigger_error();
            return false;
        }
    }

    function get($url,$headers=null){
        return $this->call('GET',$url,$headers);
    }

    function post($url,$data,$headers=null){
        return $this->call('GET',$url,$headers,$data);
    }

}

class restcall extends http_full{

    function put($url,$data,$headers=null){
        return $this->call('PUT',$url,$headers,$data);
    }

    function delete($url,$headers=null){
        return $this->call('DELETE',$url,$headers);
    }

}