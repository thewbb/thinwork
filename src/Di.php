<?php
/**
 * Created by PhpStorm.
 * User: thewbb
 * Date: 19-2-12
 * Time: 下午10:03
 */

namespace thewbb\thinwork;

class Di {
    public $config;
    public $elements = [];
    public $variables = [];

    function __construct($config) {
        $this->config = $config;
    }

    function __get($property){

        $element = $this->elements[$property];

        if(is_callable($element)){
            $this->elements[$property] = $element->bindTo($this)();
        }
        return $this->elements[$property];
    }

    function __set($property, $value){
        $this->elements[$property] = $value;
    }

    function get($key){
        return $this->variables[$key];
    }

    function set($key, $value){
        $this->variables[$key] = $value;
    }

    function getConfig(){
        return $this->config;
    }
}