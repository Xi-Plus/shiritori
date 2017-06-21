<?php
class Game {
	private $datapath = "";
	private $rule = [
		"Start" => 20,
		"Notfound" => -3,
		"Tip" => -10,
		"Repeat" => -5,
		"Answer" => 1
	];
	private $score = 0;
	private $word = [];
	function __construct($sid) {
		global $C;
		foreach ($C["Rule"] as $key => $value) {
			if (isset($this->rule[$key])) {
				$this->rule[$key] = $value;
			}
		}
		$this->score = $this->rule["Start"];
		$this->datapath = $C['DataPath'].$sid.".json";
		$data = @file_get_contents($this->datapath);
		if ($data !== false) {
			$data = json_decode($data, true);
			$this->score = $data["score"];
			$this->word = $data["word"];
		}
	}
	function __destruct() {
		$res = file_put_contents($this->datapath, json_encode([
			"score"=>$this->score,
			"word"=>$this->word
		]));
		WriteLog("write to ".$this->datapath);
		if ($res === false) {
			WriteLog("write file fail");
		}
	}
	function isstart() {
		return count($this->word) > 0;
	}
	function getlastword() {
		if (count($this->word) > 0) {
			return end($this->word);
		} else {
			return "";
		}
	}
	function getscore() {
		return $this->score;
	}
	function getwordcount() {
		return count($this->word);
	}
	function getwordlist() {
		return $this->word;
	}
	function printwordlist() {
		return implode("、", $this->word);
	}
	function usetip() {
		if ($this->score + $this->rule["Tip"] >= 0) {
			$this->score += $this->rule["Tip"];
			return $this->score;
		} else {
			return false;
		}
	}
	function getrule() {
		return $this->rule;
	}
	function checkanadiplosis($word) {
		if ($this->isstart()) {
			$lastword = $this->getlastword();
			if (mb_substr($lastword, -1) !== mb_substr($word, 0, 1)) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	function checkrepeat($word) {
		if ($this->isstart() && in_array($word, $this->word)) {
			$this->score += $this->rule["Repeat"];
			return $this->score;
		} else {
			return false;
		}
	}
	function notfound() {
		$this->score += $this->rule["Notfound"];
		return $this->score;
	}
	function answer($word) {
		$this->score += $this->rule["Answer"];
		$this->addword($word);
		return $this->score;
	}
	function addword($word) {
		$this->word []= $word;
	}
	function checklost() {
		return $this->score < 0;
	}
	function restart() {
		$this->score = $this->rule["Start"];
		$this->word = [];
	}
}
