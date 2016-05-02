<?php
require_once "functions.php";
if($_SERVER["HTTP_HOST"]!="dimgx.net" && $_SERVER["HTTP_HOST"]!="www.dimgx.net") {
	$file     = "http://" . str_replace( ".dimgx.net", "", $_SERVER["HTTP_HOST"] ) . $_SERVER["REQUEST_URI"];
	$url      = $file;
	$filename = parse_url( $url );
	$pi       = pathinfo( $filename['path'], PATHINFO_EXTENSION );
	if ( ! $pi || ! in_array( $pi, array( "jpeg", "jpg", "png", "gif" ) ) ) {
		header( "location: {$url}" );
	} else {
		$size = imageSize( $url );
		if ( $size ) {
			$filename = "{$size['w']}x{$size['h']}.{$pi}";
			if ( ! file_exists( "imgs/{$filename}" ) ) {
				$dummyUrl    = "http://placehold.it/{$filename}";
				$fileContent = file_get_contents( $dummyUrl );
				file_put_contents( "imgs/{$filename}", $fileContent );
			}

			header( imgHeader( $pi ) );
			$fp = fopen( "imgs/{$filename}", "r" );
			fpassthru( $fp );
			fclose( $fp );
		}
	}
}else{
	include "home.php";
}