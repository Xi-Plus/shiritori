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
foreach ($row as $temp) {
	$sth = $G["db"]->prepare("DELETE FROM `{$C['DBTBprefix']}input` WHERE `hash` = :hash");
	$sth->bindValue(":hash", $temp["hash"]);
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
		foreach ($res["data"] as $temp) {
			if ($temp["updated_time"] <= $updated_time) {
				break 2;
			}
			if ($temp["updated_time"] > $newesttime) {
				$newesttime = $temp["updated_time"];
			}
			foreach ($temp["participants"]["data"] as $participants) {
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
foreach ($row as $temp) {
	$input = json_decode($temp["input"], true);
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
			$data = @file_get_contents($datapath);
			if ($data === false) {
				$data = ["score" => $C['ScoreStart'], "word" => []];
			} else {
				$data = json_decode($data, true);
			}
			$input = $messaging['message']['text'];
			if ($input[0] == "/") {
				switch ($input) {
					case '/giveup':
						$last = end($data["word"]);
						$wordlist = GetWords($last, $data["word"]);
						$word = $wordlist[array_rand($wordlist)];
						if (count($data["word"]) > 0) {
							SendMessage($tmid, "您放棄了，沒有想到「".$word."」嗎？\n".
								"您分數剩下 ".$data["score"]);
							SendMessage($tmid, "我們共講出了".count($data["word"])."個詞語：\n".implode("、", $data["word"]));
						} else {
							SendMessage($tmid, "您還沒開始就放棄了，下次可以從「".$word."」開始");
						}
						$data = ["score" => $C['ScoreStart'], "word" => []];
						file_put_contents($datapath, json_encode($data, JSON_UNESCAPED_UNICODE));
						break;
					
					case '/tip':
						if (count($data["word"]) > 0) {
							$data["score"] += $C['ScoreTip'];
							$last = end($data["word"]);
							$wordlist = GetWords($last, $data["word"]);
							$word = $wordlist[array_rand($wordlist)];
							SendMessage($tmid, "試試「".$word."」？\n".
								"您分數剩下 ".$data["score"]);
							if ($data["score"] < 0) {
								SendMessage($tmid, "您的分數被扣光了，您輸了！");
								$data = ["score" => $C['ScoreStart'], "word" => []];
							}
							file_put_contents($datapath, json_encode($data, JSON_UNESCAPED_UNICODE));
						} else {
							SendMessage($tmid, "請隨便輸入一個詞語吧");
						}
						break;
					
					case '/score':
						SendMessage($tmid, "您分數剩下 ".$data["score"]);
						break;
					
					case '/rule':
						SendMessage($tmid, "分數規則\n".
							"起始分數 ".sprintf("%+d", $C['ScoreStart'])." 分\n".
							"答案不在辭典 ".sprintf("%+d", $C['ScoreNotfound'])." 分\n".
							"使用提示 ".sprintf("%+d", $C['ScoreTip'])." 分\n".
							"回答重複 ".sprintf("%+d", $C['ScoreRepeat'])." 分\n".
							"回答正確 ".sprintf("%+d", $C['ScoreAnswer'])." 分");
						break;
					
					case '/help':
						$msg = "可用命令\n".
							"/giveup 放棄結束遊戲\n".
							"/tip 取得詞語提示\n".
							"/score 顯示現在分數\n".
							"/rule 顯示分數規則\n".
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
			if (count($data["word"]) > 0) {
				$last = end($data["word"]);
				if (mb_substr($last, -1) != mb_substr($input, 0, 1)) {
					SendMessage($tmid, "您輸入的詞語不銜接上一個詞「".$last."」\n".
					"取得命令列表輸入 /help");
					continue;
				}
			}
			if (in_array($input, $data["word"])) {
				$data["score"] += $C['ScoreRepeat'];
				SendMessage($tmid, "這個詞已經用過了，請再想一個\n".
					"您分數剩下 ".$data["score"]);
				file_put_contents($datapath, json_encode($data, JSON_UNESCAPED_UNICODE));
				continue;
			}
			$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}word` WHERE `word` = :word");
			$sth->bindValue(":word", $input);
			$res = $sth->execute();
			$row = $sth->fetch(PDO::FETCH_ASSOC);
			if ($row === false) {
				$data["score"] += $C['ScoreNotfound'];
				SendMessage($tmid, "您輸入的詞語在辭典裡找不到，請再想一個\n".
					"您分數剩下 ".$data["score"]."\n".
					"想不到可輸入 /tip\n".
					"取得命令列表輸入 /help");
				if ($data["score"] < 0) {
					SendMessage($tmid, "您的分數被扣光了，您輸了！");
					$data = ["score" => $C['ScoreStart'], "word" => []];
				}
				file_put_contents($datapath, json_encode($data, JSON_UNESCAPED_UNICODE));
				continue;
			}
			$data["score"] += $C['ScoreAnswer'];
			$data["word"][] = $input;
			$wordlist = GetWords($input, $data["word"]);
			if (count($wordlist) == 0) {
				SendMessage($tmid, "已經沒有可以接的詞語了，您獲勝了！\n".
					"您剩下的分數是 ".$data["score"]);
				SendMessage($tmid, "我們共講出了".count($data["word"])."個詞語：\n".implode("、", $data["word"]));
				$data = ["score" => $C['ScoreStart'], "word" => []];
			} else {
				$word = $wordlist[array_rand($wordlist)];
				$data["word"][] = $word;
				$wordlist = GetWords($word, $data["word"]);
				$msg = $word." (".count($wordlist).")";
				if ($data["score"] % 10 == 0) {
					$msg .= "\n您達到 ".$data["score"]." 分了！";
				}
				if (count($wordlist) <= 10 && count($wordlist) > 0) {
					$msg .= "\n想不到可輸入 /tip";
				}
				SendMessage($tmid, $msg);
				if (count($wordlist) == 0) {
					SendMessage($tmid, "已經沒有可以接的詞語了，您輸了！\n".
						"您分數剩下 ".$data["score"]);
					SendMessage($tmid, "我們共講出了".count($data["word"])."個詞語：\n".implode("、", $data["word"]));
					$data = ["score" => $C['ScoreStart'], "word" => []];
				}
			}
			file_put_contents($datapath, json_encode($data, JSON_UNESCAPED_UNICODE));
		}
	}
}
