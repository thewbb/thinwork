<?php
/**
 * Created by PhpStorm.
 * User: thewbb
 * Date: 19-1-29
 * Time: 下午9:09
 */

namespace thewbb\thinwork\console;

use Di;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * @property Di $di
 */
class ThinworkDatabaseBackup extends Command {

    private $di;

    function __construct($di) {
        $this->di = $di;

        parent::__construct("thinwork:database:backup");
    }


    protected function configure()
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('backup all tables to json file.')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('backup all tables to json file...')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // 查询数据库中现有的表，备份表结构
        $dbName = $this->di->config["database"]["dbname"];
        $tables = $this->di->mysql->fetchAll("select TABLE_NAME, TABLE_COLLATION, TABLE_COMMENT, AUTO_INCREMENT from information_schema.tables where table_schema='$dbName'");

        // 将这些表的数据结构保存到本地，以json的形式
        // 保存内容包括：
        // 1、表中的字段、类型、长度、是否为空、默认值
        // 2、索引信息
        // 3、引用信息
        // 4、设置信息：TABLE_TYPE、AUTO_INCREMENT、ENGINE、TABLE_COLLATION
        foreach($tables as &$table){
            $columns = $this->di->mysql->fetchAll("SELECT   COLUMN_NAME,DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE,COLUMN_DEFAULT,COLUMN_COMMENT FROM  INFORMATION_SCHEMA.COLUMNS WHERE  table_schema='$dbName' and TABLE_NAME = '{$table["TABLE_NAME"]}'");
            foreach($columns as $column){
                $columnName = $column["COLUMN_NAME"];
                unset($column["COLUMN_NAME"]);
                $table["COLUMNS"][$columnName] = $column;
            }
        }
        $data = [
            "TABLES" => $tables,

        ];

        $fileName = "./app/database/db".date("YmdHis").".json";
        if(file_put_contents($fileName, json_encode($data, JSON_PRETTY_PRINT))){
            echo "database backup success : $fileName\n";
        }
        else{
            echo "backup failure.\n";
        }
    }
} 