<?php
/**
 * Created by PhpStorm.
 * User: thewbb
 * Date: 19-1-29
 * Time: 下午11:13
 */

namespace thewbb\thinwork\libs;


use Exception;
use PDO;

class MySql {
    public $queryParams;
    public $executeParams;
    private $dbQuery;
    private $dbExecute;

    function __construct($queryParams, $executeParams = null) {
        $this->queryParams = $queryParams;
        if(is_null($executeParams)){
            $this->executeParams = $queryParams;
        }
        else{
            $this->executeParams = $executeParams;
        }
    }
//------------------------------------------------------------------------------------------------------------------
    // 数据库操作函数

    private function dbQuery(){
        if(is_null($this->dbQuery)){
            $this->dbQuery = new PDO("mysql:dbname={$this->queryParams["dbname"]};host={$this->queryParams["host"]}",
                $this->queryParams["username"],
                $this->queryParams["password"]);
        }
        return $this->dbQuery;
    }


    private function dbExecute(){
        if(is_null($this->dbExecute)){
            $this->dbExecute = new PDO("mysql:dbname={$this->executeParams["dbname"]};host={$this->executeParams["host"]}",
                $this->executeParams["username"],
                $this->executeParams["password"]);
        }
        return $this->dbExecute;
    }

    public function fetchAll($sql, $cacheTime = null){
        $sth = $this->dbQuery()->prepare($sql);
        $sth->execute();
        $result = $sth->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    // 从数据库取出一条数据
    public function fetchOne($sql, $cacheTime = null){
        $sth = $this->dbQuery()->prepare($sql);
        $sth->execute();
        $result = $sth->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    public function fetchColumn($sql, $cacheTime = null){
        $sth = $this->dbQuery()->prepare($sql);
        $sth->execute();
        $result = $sth->fetchColumn();
        return $result;
    }

    public function execute($sql){
        $sth = $this->dbExecute()->prepare($sql);
        $sth->execute();
        return $sth->rowCount();
    }

    function update($table, $data, $where){
        $database_name = $this->executeParams["dbname"];
        $records = $this->fetchAll("select * from information_schema.COLUMNS where table_name = '$table' and table_schema = '$database_name'");
        if(empty($records)){
            throw new Exception("table $table not exist");
        }

        // 对于非空字段进行检查
        foreach($records as $field){
            if(empty($data[$field["COLUMN_NAME"]])){
                continue;
            }
            if($field["IS_NULLABLE"] == "NO"){
                // 如果字段没有设置默认值，并且不是auto_increment的话，则需要进行内容判断
                if(is_null($field["COLUMN_DEFAULT"]) && (strpos($field["EXTRA"], "auto_increment") === false)){
                    // 先判断内容字段内容不能为空
                    if(is_null($data[$field["COLUMN_NAME"]]) || ($data[$field["COLUMN_NAME"]] == "")){
                        $fieldName = empty($field["COLUMN_COMMENT"])?$field["COLUMN_NAME"]:$field["COLUMN_COMMENT"];
                        throw new Exception("$fieldName is null");
                        return false;
                    }

                    // 如果是下列数值类型，则进行数值类型检测
                    if($field["DATA_TYPE"] == "int"
                        || $field["DATA_TYPE"] == "tinyint"
                        || $field["DATA_TYPE"] == "smallint"
                        || $field["DATA_TYPE"] == "mediumint"
                        || $field["DATA_TYPE"] == "bigint"
                        || $field["DATA_TYPE"] == "decimal"
                        || $field["DATA_TYPE"] == "float"
                        || $field["DATA_TYPE"] == "double"
                        || $field["DATA_TYPE"] == "bit"
                        || $field["DATA_TYPE"] == "tinyint"
                        || $field["DATA_TYPE"] == "bigint"
                        || $field["DATA_TYPE"] == "float"){
                        if(!is_numeric($data[$field["COLUMN_NAME"]])){
                            $fieldName = empty($field["COLUMN_COMMENT"])?$field["COLUMN_NAME"]:$field["COLUMN_COMMENT"];
                            throw new Exception("$fieldName is not numeric");
                            return false;
                        }
                    }
                }
            }
            else{
                // 如果是下列数值类型，则进行数值类型检测
                if( $field["DATA_TYPE"] == "int"
                    || $field["DATA_TYPE"] == "tinyint"
                    || $field["DATA_TYPE"] == "smallint"
                    || $field["DATA_TYPE"] == "mediumint"
                    || $field["DATA_TYPE"] == "bigint"
                    || $field["DATA_TYPE"] == "decimal"
                    || $field["DATA_TYPE"] == "float"
                    || $field["DATA_TYPE"] == "double"
                    || $field["DATA_TYPE"] == "bit"
                    || $field["DATA_TYPE"] == "tinyint"
                    || $field["DATA_TYPE"] == "bigint"
                    || $field["DATA_TYPE"] == "float"){
                    if(!is_numeric($data[$field["COLUMN_NAME"]])){
                        $fieldName = empty($field["COLUMN_COMMENT"])?$field["COLUMN_NAME"]:$field["COLUMN_COMMENT"];
                        throw new Exception("$fieldName is not numeric");
                        return false;
                    }
                }
            }
        }

        // 筛选出数据表中存在的字段
        $fields = array();
        foreach ($data as $key => $value) {
            $index = array_search($key, array_column($records, 'COLUMN_NAME'));
            if(is_numeric($index)){
                $fields[$key] = $value;
            }
        }

        // 拼接sql字符串
        $strFields = "";
        foreach ($fields as $key => $value) {
            $strFields .= "$key = :$key,";
        }
        if(empty($strFields)){
            throw new Exception("error fields");
        }

        if(empty($where)){
            throw new Exception("where must not be null");
        }

        $strFields = substr($strFields, 0, -1);
        $sth = $this->dbExecute()->prepare("update $table set $strFields where $where");

        $params = array();
        foreach ($fields as $key => $value) {
            $params[":".$key] = $value;
        }

        // 执行sql
        $sth->execute($params);
        return $sth->rowCount();
    }

    function insert($table, $data, $ignore = false){
        $database_name = $this->executeParams["dbname"];
        $records = $this->fetchAll("select * from information_schema.COLUMNS where table_name = '$table' and table_schema = '$database_name'");
        if(empty($records)){
            throw new Exception("table `$table` not exist");
        }

        // 对于非空字段进行检查
        foreach($records as $field){
            if($field["IS_NULLABLE"] == "NO"){
                // 如果字段没有设置默认值，并且不是auto_increment的话，则需要进行内容判断
                if(is_null($field["COLUMN_DEFAULT"]) && (strpos($field["EXTRA"], "auto_increment") === false)){
                    // 先判断内容字段内容不能为空
                    if(is_null($data[$field["COLUMN_NAME"]]) || ($data[$field["COLUMN_NAME"]] === "")){
                        $fieldName = empty($field["COLUMN_COMMENT"])?$field["COLUMN_NAME"]:$field["COLUMN_COMMENT"];
                        throw new Exception("$fieldName is null");
                        return false;
                    }

                    // 如果是下列数值类型，则进行数值类型检测
                    if($field["DATA_TYPE"] == "int"
                        || $field["DATA_TYPE"] == "tinyint"
                        || $field["DATA_TYPE"] == "smallint"
                        || $field["DATA_TYPE"] == "mediumint"
                        || $field["DATA_TYPE"] == "bigint"
                        || $field["DATA_TYPE"] == "decimal"
                        || $field["DATA_TYPE"] == "float"
                        || $field["DATA_TYPE"] == "double"
                        || $field["DATA_TYPE"] == "bit"
                        || $field["DATA_TYPE"] == "tinyint"
                        || $field["DATA_TYPE"] == "bigint"
                        || $field["DATA_TYPE"] == "float"){

                        if(!is_numeric($data[$field["COLUMN_NAME"]])){
                            $fieldName = empty($field["COLUMN_COMMENT"])?$field["COLUMN_NAME"]:$field["COLUMN_COMMENT"];
                            throw new Exception("$fieldName is null");
                            return false;
                        }
                    }
                }
            }
        }

        // 筛选出数据表中存在的字段
        $fields = array();
        foreach ($data as $key => $value) {
            $index = array_search($key, array_column($records, 'COLUMN_NAME'));
            if(is_numeric($index)){
                $fields[$key] = $value;
            }
        }

        // 拼接sql字符串
        $strFields = "";
        $strValues = "";
        foreach ($fields as $key => $value) {
            $strFields .= "$key,";
            $strValues .= ":$key,";
        }
        if(empty($strFields)){
            throw new Exception("error fields");
        }

        $strIgnore = "";
        if($ignore == true){
            $strIgnore = "ignore";
        }

        $strFields = substr($strFields, 0, -1);
        $strValues = substr($strValues, 0, -1);
        $sth = $this->dbExecute()->prepare("insert $strIgnore into $table($strFields) values($strValues)");

        $params = array();
        foreach ($fields as $key => $value) {
            $params[":".$key] = $value;
        }
        // 执行sql
        $sth->execute($params);
        return $this->dbExecute()->lastInsertId();
    }

    public function begin(){
        $this->dbExecute()->beginTransaction();
    }

    public function commit(){
        $this->dbExecute()->commit();
    }

    public function rollback(){
        $this->dbExecute()->rollBack();
    }

    public function lastInsertId(){
        return $this->dbExecute()->lastInsertId();
    }
} 