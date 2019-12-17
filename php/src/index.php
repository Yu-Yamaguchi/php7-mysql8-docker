<?php

$dsn = 'mysql:host=mysql;dbname=yudb';
$user = 'mysqluser';
$password = 'mysqluser00';

try {
  $dbh = new PDO($dsn, $user, $password);

  $sql = "select * from m_sample";

  foreach ($dbh->query($sql, PDO::FETCH_ASSOC) as $row) {
    var_dump($row);
  }
} catch (PDOException $e) {
  var_dump($e);
}

phpinfo();
