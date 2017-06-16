<?php
require(__DIR__.'/config/config.php');
if (!in_array(PHP_SAPI, $C["allowsapi"])) {
	exit("No permission");
}

$sth = $G["db"]->prepare("DELETE FROM `{$C['DBTBprefix']}log` WHERE `time` < :time");
$sth->bindValue(":time", date("Y-m-d H:i:s", time()-$C['LogKeep']));
$sth->execute();
