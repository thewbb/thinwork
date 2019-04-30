<?php
namespace thewbb\thinwork\controllers;

class BaseController {
    public $di;
    public $controller;
    public $action;

    public function __get($name){
        if(property_exists($this, $name)){
            return $this->$name;
        }
        else{
            return $this->di->$name;
        }
    }

    //------------------------------------------------------------------------------------------------------------------
    function __construct($controller, $action) {
        $this->controller = $controller;
        $this->action = $action;
        global $di;
        $this->di = $di;

//        $uri = "/".$this->controller."/".$this->action;
//        // 检查访问权限
//        $sql = "select * from acl_permission where uri = '$uri'";
//        $permission = $this->fetchOne($sql);
//
//        if($permission){
//            $user = $this->getLoginUser();
//
//            if($this->isGranted($user, $uri) == false){
//                $this->return401("您没有足够的访问权限。");
//            }
//        }
    }



//    public function isGranted($user, $uri){
//        if($user["is_super_admin"] == 1){
//            return true;
//        }
//        $sql = "select * from acl_permission where uri = '$uri'";
//        $permission = $this->fetchOne($sql);
//
//        if($permission){
//            // 查看该用户组是否有授权
//            $sql = "select a.id from acl_user as a join acl_group_has_user as b on a.id = b.user_id join acl_group_has_permission as c on b.group_id = c.group_id where a.id = {$user["id"]} and c.permission_id = {$permission["id"]}";
//            $granted = $this->fetchAll($sql);
//
//            if($granted){
//                return true;
//            }
//
//            // 查看单个用户是否有授权
//            $sql = "select a.id from acl_user as a left join acl_user_has_permission as b on a.id = b.user_id where a.id = {$user["id"]} and b.permission_id = {$permission["id"]}";
//            $granted = $this->fetchAll($sql);
//
//            if($granted){
//                return true;
//            }
//        }
//
//        if(empty($permission)){
//            if($this->admin["default_permission"] == "allow"){
//                return true;
//            }
//            else if($this->admin["default_permission"] == "deny"){
//                return false;
//            }
//        }
//    }

    //------------------------------------------------------------------------------------------------------------------
    // 添加模板函数
    function view($filename, $params = []){
        extract($params);
        $flash_message = $this->di->common->getFlashMessageString();
        include($this->di->config["path"]["view"].$filename);
    }

    // 返回json
    public function returnJson($data = [], $status = 0, $message = ''){
        header("Content-Type:application/json; charset=UTF-8");
        $response = json_encode(array('status' => $status, 'message' => $message, 'data' => $data));
        header("content-length:".strlen($response));
        echo $response;
        exit();
    }

    //------------------------------------------------------------------------------------------------------------------
    // 显示flash message
    private $flashMessage = [];
    public function flashMessage($message, $class = ""){
        $this->flashMessage[] = "<div class='flash-message {$class}'>$message</div>";
    }

    public function flashMessageInfo($message){
        $this->flashMessage($message, "flash-message-info");
    }

    public function flashMessageSuccess($message){
        $this->flashMessage($message, "flash-message-success");
    }

    public function flashMessageWarning($message){
        $this->flashMessage($message, "flash-message-warning");
    }

    public function flashMessageError($message){
        $this->flashMessage($message, "flash-message-error");
    }

    // 返回flash message拼成的字符串
    public function getFlashMessageString(){
        $str = "";
        foreach($this->flashMessage as $message){
            $str .= $message;
        }
        return $str;
    }
}
