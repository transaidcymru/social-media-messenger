<?php
$__db = null;

function db_connect()
{
    global $__db;
    $__db = new mysqli("127.0.0.1", "dev", "devpass", "dev_database", 3306);
}

function db_query(string $query)
{
    global $__db;
    return $__db->query($query);
}
?>
