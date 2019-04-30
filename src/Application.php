<?php
/**
 * Created by PhpStorm.
 * User: thewbb
 * Date: 19-1-26
 * Time: 上午11:41
 */

namespace thewbb\thinwork;


class Application {
    public function run(){
        // 从url中获得 controller 和 action
        list($uri) = explode("?", $_SERVER["REQUEST_URI"]);
        $list = explode("/", $uri);
        $controller = ucwords(empty($list[1])? "index":$list[1]);
        $action = empty($list[2])? "index":$list[2];

        global $di;
        // 找到controller对应的程序文件
        $controllerPath = $di->config["path"]["controller"].$controller."Controller.php";
        if(!is_file($controllerPath)){
            $action = $controller;
            $controller = "Index";
            $controllerPath = $di->config["path"]["controller"].$controller."Controller.php";
        }

        // 包含对应的文件，并执行里面对应的action方法
        include($controllerPath);
        $controllerClassName = $controller."Controller";
        $controllerClass = new $controllerClassName($controller, $action);

        call_user_func(array($controllerClass, $action."Action"));
    }

} 