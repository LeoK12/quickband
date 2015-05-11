<?php
  /**
   * 
   * 数据验证
   *
   * @package  ShopEx网上商店系统
   * @version  4.8.4
   * @author   ShopEx.cn <bryant@shopex.cn>
   * @url        http://www.shopex.cn/
   * @since    PHP 4.3
   * @copyright ShopEx.cn
   *
   **/

class mdl_data_verify {
    /**
     * 判断字符是否在枚举类型中
     *
     * @author Luis Pater
     * @date 2009-05-11
     * @param string 需要判断的字符
     * @param string 枚举文本，例如：enum('a', 'b', 'c')
     * @return boolean
     */
    function inEnum($str_text, $str_enum) {
        $str_enum = str_replace("enum", "array", $str_enum);
        eval('$array_enum = '.$str_enum.';');
        if (in_array($str_text, $array_enum)) {
            return true;
        }
        return false;
    }

    /**
     * 判断参数是否合法
     *
     * @author Luis Pater
     * @date 2009-05-11
     * @param string 判断参数表
     * @param array 需要判断的数组
     * @return mixed 如果全部合法则返回true，如果参数表非法返回false，否则返回错误的字段
     */
    function checkParams($str_input, &$array_data) {
        $str_input = str_replace("\r", "", $str_input);
        $array_input = explode("\n", $str_input);
        foreach ($array_input as $str_key => $str_params) {
            $array_params = explode("    ", $str_params);
            $array_keys[$array_params[0]] = $array_params[0];
            if ((strtoupper(trim($array_params[2]))=="Y") && (!isset($array_data[$array_params[0]]))) { //判断必填项是否已经填写
                return $array_params[0];
            }
            elseif (isset($array_data[$array_params[0]])) { //如果不是必填项，但是填写了               
                $str_preg = '/^(\w+)\(?(.*?)\)?$/'; //获取数据类型
                if ($int_match = preg_match($str_preg, trim($array_params[1]), $array_match)) {
                    $str_data = $array_data[$array_params[0]];
                    switch ($array_match[1]) {
                    case "string":
                        break;
                    case "int":
                    case "integer":
                        if (!$this->isInt($str_data)) {
                            return $array_params[0];
                        }
                        break;
                    case "varchar":
                        if (strlenChinaese($str_data)>$array_match[2]) {
                            return $array_params[0];
                        }
                        break;
                    case "enum":

                        if (!$this->inEnum($str_data, $array_match[0])) {
                            return $array_params[0];
                        }
                        break;
                    case "decimal":
                        if (!$this->isFloat($str_data, $array_match[2])) {
                            return $array_params[0];
                        }
                        break;
                    case "array":
                        if(!is_array($str_data)){
                            return $array_params[0];
                        }
                        break;
                    }
                }
                else {
                    return false;
                }           
            }
        }
        foreach ($array_data as $str_key=>$str_value) {
            if (array_search($str_key, $array_keys)===false) {
                unset($array_data[$str_key]);
            }
        }
        return true;
    }

    /**
     * 判断字符是否是浮点数
     *
     * @author Luis Pater
     * @date 2009-05-11
     * @param string 需要判断的字符
     * @param string 浮点数长度标示，例如：8,2
     * @return boolean
     */
    function isFloat($str_text, $str_len) {
        $int_int = substr($str_len, 0, strpos($str_len, ","));
        $int_float = substr($str_len, strpos($str_len, ",")+1);
        $str_preg = '/^(\d{1,'.$int_int.'})\.?(\d{1,'.$int_float.'})?$/';
        if ($int_matched = preg_match($str_preg, $str_text, $array_match)) {
            return true;
        }
        return false;
    }
    
    function isInt($str_text) {
        $str_preg = '/^(\d+)$/';
        if ($int_matched = preg_match($str_preg, $str_text, $array_match)) {
            return true;
        }
        return false;
    }    
}

?>
