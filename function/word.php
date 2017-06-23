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
function NewWord($word, $source = 2) {
	global $C, $G;
	$sth = $G["db"]->prepare("INSERT INTO `{$C['DBTBprefix']}word` (`word`, `source`) VALUES (:word, :source)");
	$sth->bindValue(":word", $word);
	$sth->bindValue(":source", $source);
	$res = $sth->execute();
	if ($res === false) {
		WriteLog("write new word fail: ".$sth->errorInfo()[2]);
	} else {
		WriteLog("new word ".$source." ".$word);
	}
}
