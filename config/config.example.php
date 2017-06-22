<?php

$C['FBpageid'] = 'page_id';
$C['FBpagetoken'] = 'page_token';
$C['FBWHtoken'] = 'Webhooks_token';
$C['FBAPI'] = 'https://graph.facebook.com/v2.8/';

$C["DBhost"] = 'localhost';
$C['DBname'] = 'dbname';
$C['DBuser'] = 'user';
$C['DBpass'] = 'pass';
$C['DBTBprefix'] = 'shiritori_';

$C['Rule']['Start'] = 20;
$C['Rule']['Notfound'] = -3;
$C['Rule']['Tip'] = -10;
$C['Rule']['Repeat'] = -5;
$C['Rule']['Answer'] = 1;

$C['LogKeep'] = 86400*7;

$C["allowsapi"] = array("cli");

$C['Module']['TW-MOE-Dict'] = __DIR__."/../function/TW-MOE-Dict/dict.php";

$C['DataPath'] = __DIR__."/../data/";

$G["db"] = new PDO ('mysql:host='.$C["DBhost"].';dbname='.$C["DBname"].';charset=utf8', $C["DBuser"], $C["DBpass"]);
