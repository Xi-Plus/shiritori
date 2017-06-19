<?php
function GetWords($start, $used = array()) {
	global $C, $G;
	if (!is_string($start)) {
		$start = "";
	}
	$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}word` WHERE `word` LIKE :word");
	$sth->bindValue(":word", mb_substr($start, -1)."%");
	$res = $sth->execute();
	$data = $sth->fetchAll(PDO::FETCH_ASSOC);
	$wordlist = array();
	foreach ($data as $word) {
		if (!in_array($word["word"], $used)) {
			$wordlist[]= $word["word"];
		}
	}
	return $wordlist;
}
