<?php
function SendMessage($sid, $message) {
	global $C, $G;
	$post = [
		"recipient" => [
			"id" => $sid
		],
		"message" => [
			"text" => $message
		],
		"access_token" => $C['FBpagetoken']
	];
	$res = cURL($C['FBAPI']."me/messages", $post);
	$res = json_decode($res, true);
	if (isset($res["error"])) {
		WriteLog("[smsg][error] res=".json_encode($res)." sid=".$sid." msg=".$message);
		return false;
	}
	return true;
}
