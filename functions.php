<?php
function imageSize($url){

	$filename = parse_url($url);
	$pi = pathinfo($filename['path'],PATHINFO_EXTENSION);

	switch ($pi) {
		case "jpg" :
			$range = "0-50000";
			break;
		case "jpeg" :
			$range = "0-50000";
			break;
		case "png":
			$range = "0-10000";
			break;
		case "gif":
			$range = "0-10";
			break;
		default:
			$range = "0-15000";
			break;
	}

	$ch = curl_init ($url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
	curl_setopt($ch, CURLOPT_RANGE, $range);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

	$fn = "/tmp/imgs/img".ceil(mt_rand(0,100000)).time().$pi;
	$raw = curl_exec($ch);
	$result = array();

	if(file_exists($fn)){
		unlink($fn);
	}

	if ($raw !== false) {

		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if ($status == 200 || $status == 206) {

			$result["w"] = 0;
			$result["h"] = 0;

			$fp = fopen($fn, 'x');
			fwrite($fp, $raw);
			fclose($fp);

			$size = getImageSize($fn);

			if ($size===false) {
				//  Cannot get file size information
			} else {
				//  Return width and height
				list($result["w"], $result["h"]) = $size;

			}

		}
	}

	curl_close ($ch);
	unlink($fn);
	return $result;
}

function imgHeader($type){
	return "Content-Type: image/{$type}";
}

function is_https_cloudflare() {
	return isset($_SERVER['HTTPS']) ||
	       ($visitor = json_decode($_SERVER['HTTP_CF_VISITOR'])) &&
	       $visitor->scheme == 'https';
}

function processData($data){
	$pattern1 = "/<guid([^>]+)>http:\/\/([^\/]+)\/([\S]+)(\.jpg|png|gif)<\/guid>/";
	$pattern2 = "/<wp:attachment_url>http:\/\/([^\/]+)\/([\S]+)(\.jpg|png|gif)<\/wp:attachment_url>/";
	$new_data = preg_replace($pattern1,'<guid${1}>http://${2}.dimgx.net/${3}${4}</guid>',$data);
	$new_data = preg_replace($pattern2,'<wp:attachment_url>http://${1}.dimgx.net/${2}${3}</wp:attachment_url>',$new_data);
	return $new_data;
}