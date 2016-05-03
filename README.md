##DimgX.Net

DimgX ([http:dimgx.net](http:dimgx.net)) provides a quick way to replace images in your WordPress demo content file with placeholders. This is also a service to quickly provide placeholder images by just adding the word **dimgx.net** after the main domain name in any image url, for example the image `http://creek.themebucket.net/wp-content/uploads/2015/09/5.jpg` can be quickly converted to a placeholder image, keeping it's size intact by changing the URL to http://creek.themebucket.net.**dimgx.net**/wp-content/uploads/2015/09/5.jpg


Cool eh?

#### So how this magic *.dimgx.net works?

At this point I think you understood that there is a wildcard DNS entry for *.dimgx.net so that I can grab anything.dimgx.net or whatever.dimgx.net or even what.the.fuzz.dimgx.net in a central place, which is the main script of our dimgx.net. 

The cloudflare entry looks like this

![CloudFlare DNS for DimgX.net](http://dimgx.net/dimgx.net.conf.png)

#### I See! What's next?

Once I have these wildcard domains pointed to the server, I need to make sure that this URL go through a php script first. Because at this point I need to check what kind of files are being requested, and then make a decision on how to process that request. DimgX is serving it's content via Nginx, so a well configured Nginx server block file is required, like this

```shell
server {
	listen 80;
	root /var/www/html/dimgx.net;

	index index.php index.html;

	server_name dimgx.net ~^(.*)\.dimgx\.net$;

	location / {
		try_files $uri /index.php$is_args$args;
	}

	location ~ \.php$ {
		include snippets/fastcgi-php.conf;
		fastcgi_pass unix:/var/run/php5-fpm.sock;
	}

}
```

In this configuration, the following block is the key part which ensures that these requests goes to our index.php file, using a try_files directive and given that these requests are by default 404. 

```
location / {
	try_files $uri /index.php$is_args$args;
}
```

#### Interesting, so what is in your index.php

DimgX.net performs a few tasks before serving a request. 

1. It retrieves the size of the remote file
2. It then checks if there's a local image file of same size already stored in our server
3. If not stored, it creates a placeholder image of same size using placehold.it which is a nice service btw. 
4. It serves the file

So how this is done in the code? Here you go

```php
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
```

#### How does it fetch remote image size?

Here's a trick. Let's think someone is requesting a file which is 8000x8000, pretty huge actually. To determine the size of this remote image, PHP has a builtin function called [getimagesize](http://php.net/manual/en/function.getimagesize.php). The only problem is that this **getimagesize** function downloads the whole image in a temporary place in your server, and then gets the size. Damn, that's not good. For 100 remote image of 10MB of size each, it will eat up 1GB bandwidth in total, and it will also work pretty slow (well, this slowness depends on your network throughput actually)

So DimgX is using a different technique to get the size of these remote images. It just fetch a valid chunk from the header of these images where the size info is hidden, and then reads from there. This way, you don't need to download the full image. 

```php
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

```

Bingo! That's neat, right?

#### Hmm, so how it's processing the WordPress content file?

This part is easy. Just some regular expression and you're done :)

```php
function processData($data){
	$pattern1 = "/<guid([^>]+)>http:\/\/([^\/]+)\/([\S]+)(\.jpg|png|gif)<\/guid>/";
	$pattern2 = "/<wp:attachment_url>http:\/\/([^\/]+)\/([\S]+)(\.jpg|png|gif)<\/wp:attachment_url>/";
	$pattern3 = "/<wp:attachment_url><!\[CDATA\[http:\/\/([^\/]+)\/([\S]+)(\.jpg|png|gif)]]><\/wp:attachment_url>/";
	$new_data = preg_replace($pattern1,'<guid${1}>http://${2}.dimgx.net/${3}${4}</guid>',$data);
	$new_data = preg_replace($pattern2,'<wp:attachment_url>http://${1}.dimgx.net/${2}${3}</wp:attachment_url>',$new_data);
	$new_data = preg_replace($pattern3,'<wp:attachment_url><![CDATA[http://${1}.dimgx.net/${2}${3}]]></wp:attachment_url>',$new_data);
	return $new_data;
}

```

#### Where can I get everything together?

Github, you ask! Just go to [https://github.com/hasinhayder/dimgx.net](https://github.com/hasinhayder/dimgx.net) and you will find everything together. 

Liked this app, or this article? Don't forget to say hi to [me@hasin.me](me.hasin.me) and make my day a little better than yesterday :)

Adios.





