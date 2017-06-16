<?php
function SendMessage($tmid, $message) {
	global $C, $G;
	$post = array(
		"message" => $message,
		"access_token" => $C['FBpagetoken']
	);
	$res = cURL($C['FBAPI'].$tmid."/messages", $post);
	$res = json_decode($res, true);
	if (isset($res["error"])) {
		WriteLog("[smsg][error] res=".json_encode($res)." tmid=".$tmid." msg=".$message);
		if ($res["error"]["code"] === 230) {
			$sth = $G["db"]->prepare("UPDATE `{$C['DBTBprefix']}user` SET `mark` = -1 WHERE `tmid` = :tmid");
			$sth->bindValue(":tmid", $tmid);
			$sth->execute();
			WriteLog("[fbmsg][info][block] tmid=".$tmid);
		}
		return $res["error"];
	}
	return true;
}
