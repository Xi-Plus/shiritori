<?php
function cURL($url, $post=false) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	if ($post !== false) {
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch,CURLOPT_POSTFIELDS, http_build_query($post));
	}
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
	$res = curl_exec($ch);
	curl_close($ch);
	if ($res === false) {
		return false;
	}
	return $res;
}
