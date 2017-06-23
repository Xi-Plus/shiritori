<?php
require(__DIR__.'/config/config.php');
if (!in_array(PHP_SAPI, $C["allowsapi"])) {
	exit("No permission");
}

require($C['Module']['TW-MOE-Dict']);
require(__DIR__.'/function/sendmessage.php');
require(__DIR__.'/function/log.php');
require(__DIR__.'/function/word.php');
require(__DIR__.'/function/game.php');

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
					$sth->bindValue(":tmid", $temp["id"]);
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
$dict = new TWMOEDict();
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
			$game = new Game($sid);
			$input = $messaging['message']['text'];
			if ($input[0] == "/") {
				switch ($input) {
					case '/giveup':
						$last = $game->getlastword();
						$wordlist = GetWords($last, $game->getwordlist());
						$word = $wordlist[array_rand($wordlist)];
						if ($game->isstart()) {
							SendMessage($tmid, "您放棄了，沒有想到「".$word."」嗎？\n".
								"您分數剩下 ".$game->getscore());
							SendMessage($tmid, "我們共講出了".$game->getwordcount()."個詞語：\n".$game->printwordlist());
							$game->restart();
							unset($game);
						} else {
							SendMessage($tmid, "您還沒開始就放棄了，下次可以從「".$word."」開始");
						}
						break;
					
					case '/tip':
						if ($game->isstart()) {
							if (($score = $game->usetip()) !== false) {
								$last = $game->getlastword();
								$wordlist = GetWords($last, $game->getwordlist());
								$word = $wordlist[array_rand($wordlist)];
								SendMessage($tmid, "試試「".$word."」？\n".
									"您分數剩下 ".$game->getscore());
								unset($game);
							} else {
								SendMessage($tmid, "您沒有足夠的分數可以使用提示");
							}
						} else {
							$wordlist = GetWords("", $game->getwordlist());
							$word = $wordlist[array_rand($wordlist)];
							SendMessage($tmid, "請隨意輸入一個詞語\n".
								"想從「".$word."」開始嗎？");
						}
						break;
					
					case '/list':
						if ($game->isstart()) {
							SendMessage($tmid, "我們已講出了".$game->getwordcount()."個詞語：\n".$game->printwordlist());
						} else {
							SendMessage($tmid, "遊戲還沒開始");
						}
						break;
					
					case '/score':
						SendMessage($tmid, "您分數剩下 ".$game->getscore());
						break;
					
					case '/rule':
						$rule = $game->getrule();
						SendMessage($tmid, "分數規則\n".
							"起始分數 ".sprintf("%+d", $rule['Start'])." 分\n".
							"答案不在辭典 ".sprintf("%+d", $rule['Notfound'])." 分\n".
							"使用提示 ".sprintf("%+d", $rule['Tip'])." 分\n".
							"回答重複 ".sprintf("%+d", $rule['Repeat'])." 分\n".
							"回答正確 ".sprintf("%+d", $rule['Answer'])." 分");
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
			if (preg_match("/[A-Za-z0-9]+/", $input) || strpbrk($input, ",.\/:;'\"<>?[]{}\\|-=_+*()~!@#$%\^&") !== false) {
				SendMessage($tmid, "僅可輸入中文字及中文標點符號\n".
					"取得命令列表輸入 /help");
				continue;
			}
			if ($game->checkanadiplosis($input)) {
				SendMessage($tmid, "您輸入的詞語不銜接上一個詞「".$game->getlastword()."」\n".
					"取得命令列表輸入 /help");
				continue;
			}
			if (($score = $game->checkrepeat($input)) !== false) {
				SendMessage($tmid, "這個詞已經用過了，請再想一個\n".
					"您分數剩下 ".$score);
				unset($game);
				continue;
			}
			$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}word` WHERE `word` = :word");
			$sth->bindValue(":word", $input);
			$res = $sth->execute();
			$row = $sth->fetch(PDO::FETCH_ASSOC);
			if ($row === false) {
				if (file_get_contents("https://zh.wikipedia.org/wiki/".$input."?action=raw") !== false) {
					$row = true;
					NewWord($input, 3);
				}
			}
			if ($row === false) {
				if (file_get_contents("https://zh.wiktionary.org/wiki/".$input."?action=raw") !== false) {
					$row = true;
					NewWord($input, 4);
				}
			}
			if ($row === false) {
				WriteLog("try to check ".$input);
				$temp = $dict->search("^".$input."$", true);
				if (isset($temp["ok"]) && $temp["ok"] > 0) {
					$row = true;
					NewWord($input);
				}
			}
			if ($row === false) {
				if ($game->isstart()) {
					$score = $game->notfound();
					if ($game->checklost()) {
						SendMessage($tmid, "您輸入的詞語在辭典裡找不到\n".
							"您的分數被扣光了，您輸了");
						SendMessage($tmid, "我們共講出了".$game->getwordcount()."個詞語：\n".$game->printwordlist());
						$game->restart();
					} else {
						SendMessage($tmid, "您輸入的詞語在辭典裡找不到，請再想一個\n".
							"您分數剩下 ".$score."\n".
							"想不到可輸入 /tip\n".
							"取得命令列表輸入 /help");
					}
					unset($game);
				} else {
					$wordlist = GetWords("", $game->getwordlist());
					$word = $wordlist[array_rand($wordlist)];
					SendMessage($tmid, "您輸入的詞語在辭典裡找不到，請再想一個\n".
						"或者可以從「".$word."」開始？\n".
						"取得命令列表輸入 /help");
				}
				continue;
			}
			$score = $game->answer($input);
			$allwordlist = GetWords($input, []);
			$wordlist = array_diff($allwordlist, $game->getwordlist());
			if (count($wordlist) == 0) {
				$temp = $dict->search("^".mb_substr($input, -1), true);
				if (isset($temp["ok"]) && $temp["ok"] > 1) {
					$temp = array_keys($temp["result"]);
					$wordlist = array_diff($temp, $game->getwordlist());
					foreach ($wordlist as $key => $value) {
						if (mb_strlen($value) < 2) {
							unset($wordlist[$key]);
						} else if (!in_array($value, $allwordlist)) {
							NewWord($value);
						}
					}
					WriteLog(json_encode($temp, JSON_UNESCAPED_UNICODE));
				}
			}
			if (count($wordlist) == 0) {
				if ($game->getwordcount() <= 1) {
					$wordlist = GetWords("", $game->getwordlist());
					$word = $wordlist[array_rand($wordlist)];
					SendMessage($tmid, "沒有可以銜接這個詞的詞語，請再想一個\n".
						"或者可以從「".$word."」開始？");
					$game->restart();
					unset($game);
				} else {
					SendMessage($tmid, "已經沒有可以接的詞語了，您獲勝了！".($onlinecheck?"*":"")."\n".
						"您剩下的分數是 ".$game->getscore());
					SendMessage($tmid, "我們共講出了".$game->getwordcount()."個詞語：\n".$game->printwordlist());
					$game->restart();
					unset($game);
				}
			} else {
				$word = $wordlist[array_rand($wordlist)];
				$game->addword($word);
				$allwordlist = GetWords($word, []);
				$wordlist = array_diff($allwordlist, $game->getwordlist());
				$msg = $word;
				if (count($wordlist) == 0) {
					$temp = $dict->search("^".mb_substr($word, -1), true);
					if (isset($temp["ok"]) && $temp["ok"] > 1) {
						$temp = array_keys($temp["result"]);
						$wordlist = array_diff($temp, $game->getwordlist());
						foreach ($wordlist as $key => $value) {
							if (mb_strlen($value) < 2) {
								unset($wordlist[$key]);
							} else if (!in_array($value, $allwordlist)) {
								NewWord($value);
							}
						}
					}
				}
				if (count($wordlist) == 0) {
					SendMessage($tmid, $msg);
					SendMessage($tmid, "已經沒有可以接的詞語了，您輸了！\n".
						"您分數剩下 ".$game->getscore());
					SendMessage($tmid, "我們共講出了".$game->getwordcount()."個詞語：\n".$game->printwordlist());
					$game->restart();
					unset($game);
				} else {
					$msg .= " (".count($wordlist).")";
					if ($score % 10 == 0) {
						$msg .= "\n您達到 ".$score." 分了！";
					}
					if (count($wordlist) <= 10 && count($wordlist) > 0) {
						$msg .= "\n想不到可輸入 /tip";
					}
					SendMessage($tmid, $msg);
					unset($game);
				}
			}
		}
	}
}
