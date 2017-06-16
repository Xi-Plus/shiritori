<?php
require(__DIR__.'/config/config.php');
if (!in_array(PHP_SAPI, $C["allowsapi"])) {
	exit("No permission");
}

require(__DIR__.'/function/curl.php');
require(__DIR__.'/function/sendmessage.php');
require(__DIR__.'/function/log.php');
require(__DIR__.'/function/word.php');

$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}input` ORDER BY `time` ASC");
$res = $sth->execute();
$row = $sth->fetchAll(PDO::FETCH_ASSOC);
foreach ($row as $data) {
	$sth = $G["db"]->prepare("DELETE FROM `{$C['DBTBprefix']}input` WHERE `hash` = :hash");
	$sth->bindValue(":hash", $data["hash"]);
	$res = $sth->execute();
}
function GetTmid() {
	global $C, $G;
	$res = cURL($C['FBAPI']."me/conversations?fields=participants,updated_time&access_token=".$C['FBpagetoken']);
	$updated_time = @file_get_contents(__DIR__."/data/updated_time.txt");
	$newesttime = $updated_time;
	while (true) {
		if ($res === false) {
			WriteLog("[follow][error][getuid]");
			break;
		}
		$res = json_decode($res, true);
		if (count($res["data"]) == 0) {
			break;
		}
		foreach ($res["data"] as $data) {
			if ($data["updated_time"] <= $updated_time) {
				break 2;
			}
			if ($data["updated_time"] > $newesttime) {
				$newesttime = $data["updated_time"];
			}
			foreach ($data["participants"]["data"] as $participants) {
				if ($participants["id"] != $C['FBpageid']) {
					$sth = $G["db"]->prepare("INSERT INTO `{$C['DBTBprefix']}user` (`uid`, `tmid`, `name`) VALUES (:uid, :tmid, :name)");
					$sth->bindValue(":uid", $participants["id"]);
					$sth->bindValue(":tmid", $data["id"]);
					$sth->bindValue(":name", $participants["name"]);
					$res = $sth->execute();
					break;
				}
			}
		}
		$res = cURL($res["paging"]["next"]);
	}
	file_put_contents(__DIR__."/data/updated_time.txt", $newesttime);
}
foreach ($row as $data) {
	$input = json_decode($data["input"], true);
	foreach ($input['entry'] as $entry) {
		foreach ($entry['messaging'] as $messaging) {
			$sid = $messaging['sender']['id'];
			$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}user` WHERE `sid` = :sid");
			$sth->bindValue(":sid", $sid);
			$sth->execute();
			$row = $sth->fetch(PDO::FETCH_ASSOC);
			if ($row === false) {
				GetTmid();
				$mmid = "m_".$messaging['message']['mid'];
				$res = cURL($C['FBAPI'].$mmid."?fields=from&access_token=".$C['FBpagetoken']);
				$res = json_decode($res, true);
				$uid = $res["from"]["id"];
				$sthsid = $G["db"]->prepare("UPDATE `{$C['DBTBprefix']}user` SET `sid` = :sid WHERE `uid` = :uid");
				$sthsid->bindValue(":sid", $sid);
				$sthsid->bindValue(":uid", $uid);
				$sthsid->execute();

				$sth->execute();
				$row = $sth->fetch(PDO::FETCH_ASSOC);
				if ($row === false) {
					WriteLog("[follow][error][uid404] sid=".$sid." uid=".$uid);
					continue;
				} else {
					WriteLog("[follow][info][newuser] sid=".$sid." uid=".$uid);
				}
			}
			$tmid = $row["tmid"];
			if (!isset($messaging['message']['text'])) {
				SendMessage($tmid, "僅可輸入文字");
				continue;
			}
			$datapath = __DIR__."/data/".$sid.".json";
			$used = @file_get_contents($datapath);
			if ($used === false) {
				$used = array();
			} else {
				$used = json_decode($used, true);
			}
			$input = $messaging['message']['text'];
			if ($input[0] == "/") {
				switch ($input) {
					case '/giveup':
						$last = end($used);
						$wordlist = GetWords($last, $used);
						$word = $wordlist[array_rand($wordlist)];
						SendMessage($tmid, "您放棄了，沒有想到「".$word."」嗎？");
						SendMessage($tmid, "我們共講出了".count($used)."個詞語：\n".implode("、", $used));
						$used = [];
						file_put_contents($datapath, json_encode($used, JSON_UNESCAPED_UNICODE));
						break;
					
					case '/tip':
						if (count($used) > 0) {
							$last = end($used);
							$wordlist = GetWords($last, $used);
							$word = $wordlist[array_rand($wordlist)];
							SendMessage($tmid, "試試「".$word."」？");
						} else {
							SendMessage($tmid, "請隨便輸入一個詞語吧");
						}
						break;
					
					case '/help':
						$msg = "可用命令\n".
							"/giveup 放棄結束遊戲\n".
							"/tip 取得詞語提示\n".
							"/help 顯示本命令列表";
						SendMessage($tmid, $msg);
						break;
					
					default:
						SendMessage($tmid, "沒有這個命令");
						break;
				}
				continue;
			}
			if (mb_strlen($input) < 2) {
				SendMessage($tmid, "必須輸入2個字或以上");
				continue;
			}
			if (count($used) > 0) {
				$last = end($used);
				if (mb_substr($last, -1) != mb_substr($input, 0, 1)) {
					SendMessage($tmid, "您輸入的詞語不銜接上一個詞「".$last."」\n".
					"取得命令列表輸入 /help");
					continue;
				}
			}
			if (in_array($input, $used)) {
				SendMessage($tmid, "這個詞已經用過了，請再想一個");
				continue;
			}
			$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}word` WHERE `word` = :word");
			$sth->bindValue(":word", $input);
			$res = $sth->execute();
			$data = $sth->fetch(PDO::FETCH_ASSOC);
			if ($data === false) {
				SendMessage($tmid, "您輸入的詞語在辭典裡找不到，請再想一個\n".
					"取得命令列表輸入 /help");
				continue;
			}
			$used[] = $input;
			$wordlist = GetWords($input, $used);
			if (count($wordlist) == 0) {
				SendMessage($tmid, "已經沒有可以接的詞語了，您獲勝了！");
				SendMessage($tmid, "我們共講出了".count($used)."個詞語：\n".implode("、", $used));
				$used = [];
			} else {
				$word = $wordlist[array_rand($wordlist)];
				SendMessage($tmid, $word);
				$used[] = $word;
				$wordlist = GetWords($word, $used);
				if (count($wordlist) == 0) {
					SendMessage($tmid, "已經沒有可以接的詞語了，您輸了！");
					SendMessage($tmid, "我們共講出了".count($used)."個詞語：\n".implode("、", $used));
					$used = [];
				}
			}
			file_put_contents($datapath, json_encode($used, JSON_UNESCAPED_UNICODE));
		}
	}
}
