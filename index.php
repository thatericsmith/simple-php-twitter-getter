<?php
	include 'TwitterGetter.php';
	// You can find these values after signing up for a Twitter App here:
	// http://dev.twitter.com/apps
	// After you create your App, be sure to click "Create my access token" (the button on bottom of first page). 
	$token = "PASTEHERE";
	$token_secret = "PASTEHERE";
	$consumer_key = "PASTEHERE";
	$consumer_secret = "PASTEHERE";
	$use_file_caching = false;
	$twitter = new TwitterGetter($token, $token_secret, $consumer_key, $consumer_secret, $use_file_caching);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Simple PHP Twitter Getter Demo</title>
<link href='http://fonts.googleapis.com/css?family=Alef' rel='stylesheet' type='text/css'>
<!--[if lt IE 9]>
    <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
<![endif]-->
<style>
body{font:400 1em/1.3 Alef,sans-serif; background:#f3f3f3; color:#222; padding:20px;}
h1{font-weight:400;}
a{color:#09F;}
a:hover{color:#1af;}
</style>
</head>
<body>
<h1>Demo of <a href="https://github.com/thatericsmith/simple-php-twitter-getter">Simple PHP Twitter Getter</a></h1>
<p>Here are some recent tweets:</p>
<?php
	echo $twitter->render('thatericsmith',4);
?>
<p><small>from <a href="http://thatericsmith.com">@thatericsmith</a></small></p>
</body>
</html>
