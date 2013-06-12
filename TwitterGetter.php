<?php
/*****
	Simple PHP Twitter Getter
	@thatericsmith
	
	With lots of help from Stack Overflow:
	http://stackoverflow.com/questions/12916539/simplest-php-example-retrieving-user-timeline-with-twitter-api-version-1-1/15314662#15314662

	Create a Twitter App, then pass the credentials to this object.
	You can customize the cache timeout (in minutes) below as well.
	
	Usage:
	$twitter = new TwitterGetter($token,$token_secret,$consumer_key,$consumer_secret);
	echo $twitter->render('username',5);
****/
	class TwitterGetter {		
		public $cache_minutes = 9;
		public $token;
		public $token_secret;
		public $consumer_key;
		public $consumer_secret;
		
		public function __construct($token,$token_secret,$consumer_key,$consumer_secret){
			$this->token = $token;
			$this->token_secret = $token_secret;
			$this->consumer_key = $consumer_key;
			$this->consumer_secret = $consumer_secret;
		}
				
		public function render($user,$limit = 3){
			$user = str_replace('@','',$user);
			$retstr = '';
			
			if($user && $limit):			
				// check the cache file
		
				$cache_folder = 'cache';
				if(!is_dir($cache_folder))
					mkdir($cache_folder,664);
				
				$cache_file = $cache_folder.'/'.$user.'_twitter_cache';
		
				/* Start with the cache */
		
				if(file_exists($cache_file)):
					$mtime = $this->time_diff(filemtime($cache_file), time());
					$nocache = ($mtime['minutes'] > $this->cache_minutes);
				else:
					$nocache = true;
				endif;		
				
				if($nocache):															
					$host = 'api.twitter.com';
					$method = 'GET';
					$path = '/1.1/statuses/user_timeline.json'; // api call path
					
					$query = array( // query parameters
						'screen_name' => $user,
						'count' => '10'
					);
					
					$oauth = array(
						'oauth_consumer_key' => $this->consumer_key,
						'oauth_token' => $this->token,
						'oauth_nonce' => (string)mt_rand(), // a stronger nonce is recommended
						'oauth_timestamp' => time(),
						'oauth_signature_method' => 'HMAC-SHA1',
						'oauth_version' => '1.0'
					);
					
					$oauth = array_map("rawurlencode", $oauth); // must be encoded before sorting
					$query = array_map("rawurlencode", $query);
					
					$arr = array_merge($oauth, $query); // combine the values THEN sort
					
					asort($arr); // secondary sort (value)
					ksort($arr); // primary sort (key)
					
					// http_build_query automatically encodes, but our parameters
					// are already encoded, and must be by this point, so we undo
					// the encoding step
					$querystring = urldecode(http_build_query($arr, '', '&'));
					
					$url = "https://$host$path";
					
					// mash everything together for the text to hash
					$base_string = $method."&".rawurlencode($url)."&".rawurlencode($querystring);
					
					// same with the key
					$key = rawurlencode($this->consumer_secret)."&".rawurlencode($this->token_secret);
					
					// generate the hash
					$signature = rawurlencode(base64_encode(hash_hmac('sha1', $base_string, $key, true)));
					
					// this time we're using a normal GET query, and we're only encoding the query params
					// (without the oauth params)
					$url .= "?".http_build_query($query);
					$url=str_replace("&amp;","&",$url); //Patch by @Frewuill
					
					$oauth['oauth_signature'] = $signature; // don't want to abandon all that work!
					ksort($oauth); // probably not necessary, but twitter's demo does it
										
					// this is the full value of the Authorization line
					$auth = "OAuth " . urldecode(http_build_query($oauth, '', ', '));
					
					$options = array( CURLOPT_HTTPHEADER => array("Authorization: $auth"),
									  CURLOPT_HEADER => false,
									  CURLOPT_URL => $url,
									  CURLOPT_RETURNTRANSFER => true,
									  CURLOPT_SSL_VERIFYPEER => false);
					
					// do our business
					$feed = curl_init();
					curl_setopt_array($feed, $options);
					$result = curl_exec($feed);
					curl_close($feed);
															
					$cache_static = fopen($cache_file, 'wb');
					fwrite($cache_static, serialize($result));
					fclose($cache_static);
				endif;
				
				/* End of caching */
				$json = @unserialize(file_get_contents($cache_file)); // Now parse it as you'd like
				$tweets = json_decode($json);
				$i = 0;
				foreach($tweets as $tweet):
					if($tweet->text && $i < $limit):
						$retstr .='<li class="tweet">'.$this->twitter_links($tweet->text).'<small><a href="https://twitter.com/'.$user.'/status/'.$tweet->id_str.'">'.$this->relative_time(strtotime($tweet->created_at)).'</a></small></li>';
						$i++;
					endif;
				endforeach;			
			endif;

			return $retstr ? '<ul class="twitter-list">'.$retstr.'</ul>' : '';
		}
		
		private function relative_time($time){
			$SECOND=1;
			$MINUTE=60 * $SECOND;
			$HOUR= 60 * $MINUTE;
			$DAY=24 * $HOUR;
			$MONTH=30 * $DAY;
			$delta = strtotime('+2 hours') - $time;
			if ($delta < 2 * $MINUTE) {
				return "1 min ago";
			}
			if ($delta < 45 * $MINUTE) {
				return floor($delta / $MINUTE) . " min ago";
			}
			if ($delta < 90 * $MINUTE) {
				return "1 hour ago";
			}
			if ($delta < 24 * $HOUR) {
				return floor($delta / $HOUR) . " hours ago";
			}
			if ($delta < 48 * $HOUR) {
				return "yesterday";
			}
			if ($delta < 30 * $DAY) {
				return floor($delta / $DAY) . " days ago";
			}
			if ($delta < 12 * $MONTH) {
				$months = floor($delta / $DAY / 30);
				return $months <= 1 ? "1 month ago" : $months . " months ago";
			} else {
				$years = floor($delta / $DAY / 365);
				return $years <= 1 ? "1 year ago" : $years . " years ago";
			}
		}
		
		# This will return the years, months, weeks, days, hours,
		# minutes, and seconds comparing the first to the second. ($to - $compare)
		private function time_diff($compare, $to = null, $return = 'array'){
		  # No from? Its right now!
		  if(!empty($to))
			$to = time();
		  # Maybe we need to do a strtotime..?
		  if((string)$compare != (string)(int)$compare)
		  {
			$compare = @strtotime($compare);
			if($compare == false || $compare == -1)
			  return false;
		  }
		  if((string)$to != (string)(int)$to)
		  {
			$to = @strtotime($to);
			if($to == false || $to == -1)
			  return false;
		  }
		  # Do we need to switch these around..?
		  if($to < $compare)
		  {
			$tmp = $to;
			$to = $compare;
			$compare = $tmp;
			# They were switched... Lets keep that in mind
			$switched = true;
		  }
		  # Lets build our diff array.
		  $diff = array(
			'years' => 0,
			'months' => 0,
			'weeks' => 0,
			'days' => 0,
			'hours' => 0,
			'minutes' => 0,
			'seconds' => 0,
		  );
		  # Our time difference...
		  $time_diff = $to - $compare;
		  # Years first!
		  if($time_diff >= 31556926)
		  {
			$diff['years'] = floor($time_diff / 31556926);
			$time_diff = $time_diff % 31556926;
		  }
		  # Now months
		  if($time_diff >= 2629743)
		  {
			$diff['months'] = floor($time_diff / 2629743);
			$time_diff = $time_diff % 2629743;
		  }
		  # Weeks...
		  if($time_diff >= 604800)
		  {
			$diff['weeks'] = floor($time_diff / 604800);
			$time_diff = $time_diff % 604800;
		  }
		  # Days now 8D
		  if($time_diff >= 86400)
		  {
			$diff['days'] = floor($time_diff / 86400);
			$time_diff = $time_diff % 86400;
		  }
		  # Hours...
		  if($time_diff >= 3600)
		  {
			$diff['hours'] = floor($time_diff / 3600);
			$time_diff = $time_diff % 3600;
		  }
		  # We are almost done
		  if($time_diff >= 60)
		  {
			$diff['minutes'] = floor($time_diff / 60);
			$time_diff = $time_diff % 60;
		  }
		  # Poor seconds... It gets what every other time thing doesn't want
		  $diff['seconds'] = $time_diff;
		  # Were these variables switched around?
		  if(!empty($switched))
			foreach($diff as $unit => $amount)
			  $diff[$unit] *= -1;
		  # Return all the information
		  return $diff;
		}
		
		private function twitter_links($status,$targetBlank=false,$linkMaxLen=250){
			$target=$targetBlank ? " target=\"_blank\" " : "";
			// convert link to url
			$status = preg_replace("/((http:\/\/|https:\/\/)[^ )
			#
			]+)/e", "'<a href=\"$1\" title=\"$1\" $target >'. ((strlen('$1')>=$linkMaxLen ? substr('$1',0,$linkMaxLen).'...':'$1')).'</a>'", $status);
			// convert @ to follow
			$status = preg_replace("/(@([_a-z0-9\-]+))/i","<a href=\"http://twitter.com/$2\" title=\"Follow $2\" $target >$1</a>",$status);
	
			// convert # to search
			$status = preg_replace("/(?<!&)(#([_a-z0-9\-]+))/i","<a href=\"https://twitter.com/search?src=hash&q=%23$2\" title=\"Search $1\" $target >$1</a>",$status);
	
			return $status;
		}
		
	}
?>