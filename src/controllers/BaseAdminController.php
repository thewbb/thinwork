<?php
namespace thewbb\thinwork\controllers;

class BaseAdminController extends \thewbb\thinwork\controllers\BaseController
{
    public $model_view_path = "";

    public $tableName = "";

    public $export = false;

    public function beforeExecuteRoute($dispatcher)
    {
        $this->model_view_path = __DIR__."/../views/";
        parent::beforeExecuteRoute($dispatcher);
    }

    public function indexAction(){

        $this->dashboardAction();
    }

    public function dashboardAction($template = null){
        if(empty($template)){
            $template = $this->model_view_path."template/dashboard";
        }

        $this->view->pick($template);
        $this->view->setVars(array(
            "sidebarTemplate" => $this->sidebarTemplate(),
            "headerTemplate" => $this->headerTemplate(),
            "footerTemplate" => $this->footerTemplate(),
            "title" => "我的主页",
        ));
    }

    public function listAction($search_fields = [], $fields = [], $sqlCount = null, $sqlMain = null, $template = null, $batchActions = null){
        // url中的参数作用如下：
        // sort_order : 排序顺序
        // model : 不显示左侧菜单
        // key : 选中后，会将当前页面的这个id的控件值，填写为选中行的id
        // data : 选中后，会将当前页面这个id的空间之，填写为选中行的show指定的列
        // show : 指定返回选中行的哪个字段，与data合并使用

        if(empty($template)){
            $template = __DIR__."/../views/template/list";
        }
        $perPage = $this->getQuery("per_page", 25);
        $page = $this->getQuery("page", 1);
        $sortBy = $this->getQuery("sort_by", "id");
        $sortOrder = $this->getQuery("sort_order", "desc");

        $recordCount = 0;
        $pageCount = 0;

        $where = "";
        $searchParam = "";
        $leftJoin = "";
        $leftJoinField = "";

        foreach($search_fields as $key => &$field){
            switch($field['type']){
                case 'datetime_range':
                case 'date_range':
                    $field['datetime_begin'] = $this->getQuery("search_{$key}_begin", $field['datetime_begin']);
                    $field['datetime_end'] = $this->getQuery("search_{$key}_end", $field['datetime_end']);
                    $searchParam .= "&search_{$key}_begin=".$field['datetime_begin'];
                    $searchParam .= "&search_{$key}_end=".$field['datetime_end'];
                    if(!empty($field['datetime_begin'])){
                        $where .= " and $this->tableName.$key > '{$field['datetime_begin']}'";
                    }
                    if(!empty($field['datetime_end'])){
                        $where .= " and $this->tableName.$key < '{$field['datetime_end']}'";
                    }
                    break;
                case 'select':
                    $field['data'] = $this->getQuery('search_'.$key, $field['data']);
                    $searchParam .= '&search_'.$key."=".$field['data'];
                    if(isset($field['data'])){
                        if($field['data'] != ""){
                            if($field['data'] == 'null'){
                                $where .= " and $this->tableName.$key is null";
                            }
                            else{
                                $where .= " and $this->tableName.$key = '{$field['data']}'";
                            }

                        }
                        else{
                            // select both
                        }
                    }
                    break;
                case 'many_to_one':
                    // 判断左联接的参数是否存在
                    if(empty($field['refer'])){throw new Exception("many_to_one need refer.");}
                    if(empty($field['field'])){throw new Exception("many_to_one need field.");}
                    $field['data'] = $this->getQuery('search_'.str_replace(".", "_", $key), $field['data']);
                    $searchParam .= '&search_'.$key."=".$field['data'];
                    list($joinTable, $joinField) = explode(".", $key);
                    if(!empty($field['data'])){
                        $where .= " and $joinTable.$joinField like '%{$field["data"]}%'";
                    }
                    break;
                default:
                    $field['data'] = $this->getQuery('search_'.$key, $field['data']);
                    $searchParam .= '&search_'.$key."=".$field['data'];
                    if(!empty($field['data'])){
                        $where .= " and $this->tableName.$key like '%{$field["data"]}%'";
                    }
            }
        }

        if(!empty($this->field_logic_remove)){
            $where .= " and $this->field_logic_remove = 0 ";
        }

        $joinTables = [];
        foreach($fields as $key => &$field){
            switch($field['type']){
                case 'many_to_one':
                    // 判断左联接的参数是否存在
                    // 目前只有列表中显示的项目才能使用左联接查询，所以需要查询选项的话，需要把被查询的字段同时添加到查询和列表里面
                    if(empty($field['refer'])){throw new Exception("many_to_one need refer.");}
                    if(empty($field['field'])){throw new Exception("many_to_one need field.");}
                    list($joinTable, $joinField) = explode(".", $key);
                    if(array_search($joinTable, $joinTables) === false){
                        $joinTables[] = $joinTable;
                        $leftJoin .= " left join $joinTable on $this->tableName.{$field['field']} = $joinTable.{$field['refer']} ";
                    }
                    $leftJoinField .= ", $joinTable.$joinField as {$joinTable}__$joinField ";
                    break;
            }
        }

        // 如果listAction没有设置sqlCount语句，那么使用默认sqlCount语句来计算数据条数
        if(empty($sqlCount)){
            $sqlCount = "select count(*) from $this->tableName $leftJoin where 1 ";
        }

        // 如果listAction没有设置sqlMain语句，那么使用默认sqlMain语句来计算数据条数
        if(empty($sqlMain)){
            $sqlMain = "select $this->tableName.* $leftJoinField from $this->tableName $leftJoin where 1 ";
        }

        // 将右侧的搜索窗口的搜索条件拼接到sql语句的where条件中，再计算筛选后的数据条数
        $sql = $sqlCount." ".$where;
        $recordCount = $this->fetchColumn($sql);

        // 根据数据条数和每页显示的数据量，来计算页数
        $pageCount = ceil($recordCount / $perPage);
        $sql = "$sqlMain $where order by $sortBy $sortOrder limit $perPage offset ".(($page - 1) * $perPage);
        $records = $this->fetchAll($sql);

        // save current list query sql for export action
        $this->session->set($this->controller."-".$this->action."-sql", "$sqlMain $where order by $sortBy $sortOrder");

        // 如果list action里面没有设置field参数，那么默认显示表中的所有字段
        if(empty($fields)){
            $database_name = $this->database->dbname;
            $records = $this->fetchAll("select COLUMN_NAME from information_schema.COLUMNS where table_name = '$this->tableName' and table_schema = '$database_name'");
            if(empty($records)){
                throw new Exception("table not exist");
            }

            foreach($records as $field){
                $fields[] = $field;
            }
        }

        if($_GET["model"] != 1){
            $this->session->set("HTTP_REFERER", $_SERVER["REQUEST_URI"]);
        }

        if($batchActions == null){
            $batchActions = [
                "remove" => ['label' => "批量删除", 'title' => '确认删除', 'message' => '确定要删除这些记录吗？', 'class' => 'btn-danger btn-need-confirm'],
            ];
        }

        $this->view->pick($template);
        $this->view->setVars(array(
            "current" => $this,
            "perPage" => $perPage,
            "page" => $page,
            "pageCount" => $pageCount,
            "sortBy" => $sortBy,
            "sortOrder" => $sortOrder,
            "recordCount" => $recordCount,
            "searchFields" => $search_fields,
            "searchParam" => $searchParam,
            "fields" => $fields,
            "batchActions" => $batchActions,
            "list" => $records,
            "title" => "列表",
        ));
    }

    public function showAction($fields = [], $template = null){
        if(empty($template)){
            $template = __DIR__."/../views/template/show";
        }
        $this->view->pick($template);
        $id = $this->getQuery("id");
        $sql = "select * from $this->tableName where id = $id";
        $record = $this->fetchOne($sql);

        if(empty($fields)){
            foreach($record as $key => $value){
                $fields[$key] = ['label' => $key, 'data' => $value];
            }
        }
        else{
            foreach($fields as $key => &$value){
                switch($value["type"]){
                    case "many_to_one":
                        // 从对应的表中取值
                        // 从字段中取出表名和字段名
                        list($table_name, $field_name) = explode(".", $key);
                        $sql = "select * from $table_name where {$value["refer"]} = '{$record[$value["field"]]}'";
                        $row = $this->fetchOne($sql);
                        $value["data"] = $row[$field_name];
                        break;
                    case "one_to_many":
                        // 从对应的表中取值
                        // 将选出的数据变成一个html的table，然后输出
                        if(is_null($value["rows"])){
                            $sql = "select ".implode(",", array_keys($value["columns"]))." from $key where {$value["refer"]} = '{$record[$value["field"]]}'";
                            $rows = $this->fetchAll($sql);
                        }
                        else{
                            $rows = $value["rows"];
                        }

                        $showData = "";
                        $showData .= "<table style='border: 1px solid #efefef;'>";
                        $showData .= "<tr style='border: 1px solid #efefef;'>";
                        foreach($value["columns"] as $row_field_name){
                            $showData .= "<td style='text-align: center;border: 1px solid #efefef;padding: 4px;'>{$row_field_name["label"]}</td>";
                        }

                        $showData .= "</tr>";

                        foreach($rows as $row){
                            $showData .= "<tr>";
                            foreach($value["columns"] as $key => $row_field_name){
                                $showData .= "<td style='min-width:100px;text-align: center;border: 1px solid #efefef;padding: 4px;'>".$row[$key]."</td>";
                            }
                            $showData .= "</tr>";
                        }
                        $showData .= "</table>";
                        $value["data"] = $showData;
                        break;
                    case "show_data":
                        break;
                    default:
                        $value["data"] = $record[$key];
                }
            }
        }

        $this->view->setVars(array(
            "current" => $this,
            "fields" => $fields,
            "title" => "详情",
        ));
    }

    public function addAction($fields = [], $template = null){
        if(empty($template)){
            $template = __DIR__."/../views/template/add";
        }
        $this->view->pick($template);
        if($this->request->isPost()){
            // check csrf attack
//            if (!$this->security->checkToken()) {
//                return $this->dispatcher->forward(["controller" => "index", "action" => "show404"]);
//            }
            //echo print_r($fields);exit();
            $params = [];
            $itemParams = [];
            foreach($fields as $key => $field){
                $param = $this->getPost("post-".str_replace(".", "_", $key));
                if(!is_null($param)){
                    switch($field["type"]){
                        case "many_to_one":
                            $params[$field["field"]] = $param;
                            break;
                        case "one_to_many":
                            $param = json_decode($param, 1);
                            foreach($param["items"] as $item_key => $item_value){
                                $itemParam = [];
                                foreach($field["columns"] as $field_key => $field_value){
                                    if($item_value[$field_key] != ""){
                                        $itemParam[$field_key] = $item_value[$field_key];
                                    }
                                }
                                // 将需要插入到关联表的数据放在数组中，等待数据插入后，生成好id，再将对应的数据插入到关联表中
                                $itemParams[] = ["table_name" => $key, "refer" => $field["refer"], "data" => $itemParam];
                            }
                            break;
                        case "boolean":
                            $params[$key] = ($param == "on"? 1:0);
                            break;
                        default:
                            $params[$key] = $param;
                    }

                    if(isset($field["pre_post"])){
                        if($field["type"] == "one_to_many"){
                            // 提交前的修改参数，根据用户传递进来的函数参数，在提交到数据库里之前修改这个值
                            $itemParams = call_user_func([$this, $field["pre_post"]], $itemParams);
                        }else{
                            // 提交前的修改参数，根据用户传递进来的函数参数，在提交到数据库里之前修改这个值
                            $params[$key] = call_user_func([$this, $field["pre_post"]], $params[$key]);
                        }

                    }
                }
            }
            $user = $this->getLoginUser();
            if(!empty($this->field_operator)){
                $params[$this->field_operator] = $user["id"];
            }

            foreach($params as $key => $value){
                if($value == ""){
                    unset($params[$key]);
                }
            }
            $this->begin();
            if($lastInsertId = $this->insert($this->tableName, $params)){
                // 成功插入后，再插入关联表数据
                foreach($itemParams as $param){
                    $this->insert($param["table_name"], array_merge($param["data"], [$param["refer"] => $lastInsertId]));
                }
                $this->commit();
                $this->returnSuccess("添加成功！");
            }
            $this->rollback();
        }
        else{
            $database_name = $this->database->dbname;
            $records = $this->fetchAll("select * from information_schema.COLUMNS where table_name = '$this->tableName' and table_schema = '$database_name'");

            if(empty($records)){
                throw new Exception("table not exist");
            }
        }

        $this->view->setVars(array(
            "current" => $this,
            "title" => "添加",
            "fields" => $fields,
        ));
    }

    public function editAction($fields = [], $template = null){
        if(empty($template)){
            $template = __DIR__."/../views/template/edit";
        }
        $this->view->pick($template);

        if(empty($fields)){
            $database_name = $this->database->dbname;
            $sql = "select COLUMN_NAME from information_schema.COLUMNS where table_name = '$this->tableName' and table_schema = '$database_name'";
            $records = $this->fetchAll($sql);
            foreach($records as $record){
                $fields[$record["COLUMN_NAME"]] = ['label' => $record["COLUMN_NAME"]];
            }
        }

        if($this->request->isPost()){
            $effectRow = 0;
            // check csrf attack
            if (!$this->security->checkToken()) {
                return $this->dispatcher->forward(["controller" => "index", "action" => "show404"]);
            }
            $id = $this->getPost("id");
            $params = [];

            foreach($fields as $key => $field){
                $param = $this->getPost("post-".str_replace(".", "_", $key));

                //handle prepost here
                //this is the json representation
                if(isset($field["pre_post"])){
                    // 提交前的修改参数，根据用户传递进来的函数参数，在提交到数据库里之前修改这个值
                    $param = call_user_func([$this, $field["pre_post"]], $param);
                }


                switch($field["type"]){
                    case "many_to_one":
                        if(empty($param)){
                            $params[$field["field"]] = null;
                        }
                        else{
                            $params[$field["field"]] = $param;
                        }
                        break;
                    case "one_to_many":
                        $param = json_decode($param, 1);
                        $sql = "select {$field["field"]} from $key where {$field["refer"]} = $id";
                        $records = $this->fetchAll($sql);
                        $removeList = [];
                        // 从数据库中取出所有关联字段，用于比较哪条记录被删除掉了。
                        foreach($records as $r){
                            $removeList[] = $r[$field["field"]];
                        }

                        foreach($param["items"] as $item_value){
                            $itemParam = [];
                            // id赋值
                            if(!empty($item_value[$field["field"]])){
                                $itemParam[$field["field"]] = $item_value[$field["field"]];
                            }
                            // 插入关联表的id
                            $itemParam[$field["refer"]] = $id;
                            // 去掉没用的字段，只更新需要的字段
                            foreach($field["columns"] as $field_key => $field_value){
                                if($item_value[$field_key] != ""){
                                    $itemParam[$field_key] = $item_value[$field_key];
                                }
                            }

                            // id为空，说明这条是新加的，做插入操作
                            if(empty($itemParam[$field["field"]])){
                                $effectRow += $this->insert($key, $itemParam);
                            }
                            else{
                                // id不为空，则更新数据，然后将这个id从待删除列表中出去
                                $index = array_search($item_value[$field["field"]], $removeList);
                                if($index >= 0){
                                    unset($removeList[$index]);
                                    $effectRow += $this->update($key, $itemParam, "{$field["field"]} = {$itemParam[$field["field"]]}");
                                }
                            }
                            // 将需要插入到关联表的数据放在数组中，等待数据插入后，生成好id，再将对应的数据插入到关联表中
                            //$itemParams[] = ["table_name" => $key, "refer" => $field["refer"], "data" => $itemParam];
                        }
                        if($removeList){
                            $effectRow += $this->execute("delete from $key where id in (".implode(",", $removeList).")");
                        }
                        break;
                    case "boolean":
                        $params[$key] = ($param == "on"? 1:0);
                        break;
                    default:
//                        if($key == "operation_type"){
//                            echo $param;exit();
//                        }
                        if(is_null($param) || ($param === "")){
                            $params[$key] = null;
                        }
                        else{
                            $params[$key] = $param;
                        }
                }
            }
            $user = $this->getLoginUser();
            if(!empty($this->field_operator)){
                $params[$this->field_operator] = $user["id"];
            }

            $effectRow += $this->update($this->tableName, $params, "id = $id");
            if($effectRow <= 0){
                // 更新失败，提示错误信息

            }
            else if($effectRow > 0){
                $this->returnSuccess("修改成功！");
            }
            else if($effectRow === 0){
                // 数据没有变动
                $this->returnSuccess("没有改动！");
            }
            else{
                // 不存在这个情况
            }
        }
        else{
            $id = $this->getQuery("id");
            $sql = "select * from $this->tableName where id = $id";
            $record = $this->fetchOne($sql);
            if(empty($record)){
                throw new Exception("table not exist");
            }

            foreach($fields as $key => &$field){
                switch($field["type"]){
                    case "many_to_one":
                        list($table_name, $table_field) = explode(".", $key);
                        $data = $record[$field["field"]];
                        $sql = "select $table_field from $table_name where {$field["refer"]} = '$data'";
                        $field["show"] = $this->fetchColumn($sql);
                        $field["data"] = $data;
                        break;
                    case "one_to_many":
                        // 从对应的表中取值
                        // 将选出的数据变成一个html的table，然后输出
                        $sql = "select * from $key where {$field["refer"]} = '{$record[$field["field"]]}'";
                        $rows = $this->fetchAll($sql);
                        $field["data"] = $rows;
                        break;
                    default:
                        $field["data"] = $record[$key];
                }

                //handle predisplay
                if(isset($field["pre_display"])){
                    // 提交前的修改参数，根据用户传递进来的函数参数，在显示到phtml之前修改这个值
                    $field["data"] = call_user_func([$this, $field["pre_display"]], $field["data"]);

                }
            }
        }
        $this->view->setVars(array(
            "current" => $this,
            "id" => $id,
            "fields" => $fields,
            "title" => "修改",
        ));
    }

    public function removeAction(){
        $user = $this->getLoginUser();              // check login
        $id = $this->getQuery("id");
        if(!empty($this->field_operator)){
            $set_operator = ", $this->field_operator = {$user["id"]} ";
        }

        if(empty($this->field_logic_remove)){
            // physical remove
            $sql = "delete from $this->tableName where id = $id";
            $rowCount = $this->execute($sql);
        }
        else{
            // logic remove
            $sql = "update $this->tableName set $this->field_logic_remove = 1 $set_operator where id = $id";
            $rowCount = $this->execute($sql);
        }
        $this->returnSuccess("删除成功！$rowCount 条");

    }

    public function exportAction($fields = [], $records = null){
        if(is_null($records)){
            $sql = $this->session->get($this->controller."-list-sql");
            $records = $this->fetchAll($sql);
        }

        // Create new PHPExcel object
        $objPHPExcel = new PHPExcel();
        $sheet = $objPHPExcel->setActiveSheetIndex(0);

        if(empty($fields)){
            if(empty($records)){
                // 返回空excel
                header('Content-Type: application/vnd.ms-excel');
                header('Content-Disposition: attachment;filename="export_'.$this->controller."_".date("YmdHis").'.xls"');
                header('Cache-Control: max-age=0');
                $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
                $objWriter->save('php://output');
                exit;
            }

            // 为空时，默认输出sql语句的查询字段名
            foreach($records[0] as $key => $value){
                $fields[$key] = ['label' => $key];
            }
        }

        $column = "A";
        foreach($fields as $key => $value){
            $sheet->setCellValue($column++.'1', $value['label']);
        }

        for($i = 0; $i < count($records); $i++){
            $column = "A";
            foreach($fields as $key => $value){
                switch($value["type"]){
                    case "boolean":
                        if($records[$i][str_replace(".", "__", $key)] == 0){
                            $sheet->setCellValue($column++.($i+2), "no");
                        }
                        else{
                            $sheet->setCellValue($column++.($i+2), "yes");
                        }
                        break;
                    default:
                        $sheet->setCellValue($column++.($i+2), $records[$i][str_replace(".", "__", $key)]);
                }
            }
        }

        // Redirect output to a client’s web browser (Excel5)
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="export_'.$this->controller."_".date("YmdHis").'.xls"');
        header('Cache-Control: max-age=0');

        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;
    }

    public function batchAction($actions = null){
        $operation = $this->getPost("batch-operation");
        $tableRecords = $_POST["table_records"];
        $url = $this->session->get("HTTP_REFERER");

        if(empty($tableRecords)){
            $this->returnError("没有可以操作的数据");
        }

        switch($operation){
            case "remove":
                $rowCount = 0;
                foreach($tableRecords as $key => $value){
                    $sql = "delete from $this->tableName where id = $value";
                    $result = $this->execute($sql);
                    $rowCount += $result;
                }
                $this->flashSession->success("成功删除 $rowCount 条数据！");
                break;
            default:

        }
        header("location:$url");
    }

    public function headerTemplate($params = [], $template = null){
        if(empty($template)){
            $template = __DIR__."/../views/template/header";
        }
        $user = $this->getLoginUser();
        $params["user"] = $user;

        $view = new \Phalcon\Mvc\View\Simple();
        return $view->render($template, $params);
    }

    public function footerTemplate($params = [], $template = null){
        if(empty($template)){
            $template = __DIR__."/../views/template/footer";
        }
        $view = new \Phalcon\Mvc\View\Simple();
        return $view->render($template, $params);
    }

    public function sidebarTemplate($params = [], $template = null){
        if(empty($template)){
            $template = __DIR__."/../views/template/sidebar";
        }

        $sql = "select id, name from acl_permission_classification order by order_id";
        $groups = $this->fetchAll($sql);

        $user = $this->getLoginUser();
        if($user["is_super_admin"]){
            foreach($groups as &$group){
                $sql = "select *
                    from acl_permission
                    where group_id = {$group["id"]}
                    and display = 1
                    order by order_id";
                $permissions = $this->fetchAll($sql);
                $group["permissions"] = $permissions;
            }
        }
        else{
            foreach($groups as &$group){
                $sql = "select a.*
                    from acl_permission as a
                    left join acl_group_has_permission as b on a.id = b.permission_id
                    left join acl_group_has_user as c on b.group_id = c.group_id
                    where a.group_id = {$group["id"]}
                    and a.display = 1
                    and c.user_id = {$user["id"]}
                    group by a.id
                    order by a.order_id";
                $permissions = $this->fetchAll($sql);
                $group["permissions"] = $permissions;
            }

            for($i = count($groups) - 1; $i >= 0; $i--){
                if(empty($groups[$i]["permissions"])){

                    unset($groups[$i]);
                }
            }
        }

        $params["groups"] = $groups;
        $params["user"] = $user;

        $view = new \Phalcon\Mvc\View\Simple();

        return $view->render($template, $params);
    }

    public function getType($type){
        switch($type){
            // 数字类型
            case "int":
            case "tinyint":
            case "smallint":
            case "mediumint":
            case "bigint":
            case "decimal":
            case "float":
            case "double":
                return "num";
                break;
            // 字符类型
            case "char":
            case "varchar":
            case "tinytext":
            case "text":
            case "mediumtext":
            case "longtext":
                return "string";
                break;
            // 二进制类型
            case "bit":
            case "binary":
            case "varbinary":
            case "tinyblob":
            case "mediumblob":
            case "blob":
            case "longblob":
                return "binary";
                break;
//            // 枚举(单选)
//            case "enum":
//            // 集合(多选)
//            case "set":
//            // 时间类型
//            case "year":
//            case "date":
//            case "datetime":
//            case "time":
//            case "timestamp":
//
            default:
                return $type;
        }
    }
}

