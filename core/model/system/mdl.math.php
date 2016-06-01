<?php

class mdl_math extends modelFactory  {

    var $operationDecimals = 0;   //运算精度
    var $operationCarryset = 0;    //运算进位方式
    var $goodsShowDecimals = 0;   //商品显示精度
    var $operationFunc = null;         //运算function

    function mdl_math(){
        parent::modelFactory();
        $this->operationDecimals = $this->system->getConf('system.money.operation.decimals');    //运算精度
        $this->operationCarryset = $this->system->getConf('system.money.operation.carryset');       //运算进位方式
        $this->goodsShowDecimals = $this->system->getConf('system.money.decimals');                 //商品显示精度
        $this->getFunc();
    }


    function getFunc(){
        switch($this->operationCarryset ){
            case "0":          //四舍五入
                $this->operationFunc = 'round';
                break;

            case "1":          //向上取整
                $this->operationFunc = 'ceil';
                break;

            case "2":          //向下取整
                $this->operationFunc = 'floor';
                break;
        }
    }

    //加
    function plus($numbers){
        if(!is_array($numbers))
            return $this->getOperationNumber($numbers);
        $rs = 0;
        foreach( $numbers as $n ){
            $rs += $this->getOperationNumber($n);
        }
        return $rs;
    }

    //减
    function minus( $numbers ){
        if(!is_array($numbers))
            return $this->getOperationNumber($numbers);
        $rs = $this->getOperationNumber( $numbers[0] );
        for( $i = 1; $i<count($numbers); $i++ ){
            $rs -= $this->getOperationNumber( $numbers[$i] );
        }
        return $rs;
    }

    //乘
    function multiple($numbers){
        if(!is_array($numbers))
            return $this->getOperationNumber($numbers);

        $rs = 1;
        foreach( $numbers as $n ){
            $rs  = $this->getOperationNumber( $rs * $this->getOperationNumber($n) );
        }
        return $rs;
    }

    /**
    *get 取得系统设定的 商品 价格进位方式后的数值
    * return 进位后 商品 价格
    */
    function get( $number ){
        return call_user_func_array( "floor" , array($number * pow( 10 , $this->goodsShowDecimals)) )/pow( 10 , $this->goodsShowDecimals);
    }

    /**
    *getOperationNumber 取得系统设定的 运算 价格进位方式后的数值
    * return 进位后 运算 价格
    */
    function getOperationNumber( $number ){
        return call_user_func_array( $this->operationFunc , array($number * pow( 10 , $this->operationDecimals)) )/pow( 10 , $this->operationDecimals);
    }
}

?>