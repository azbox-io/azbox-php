<?php
namespace Azbox;

define('AZBOX_VERSION', '1.0.0');

class AZ
{
	protected $api_key;
	protected $project_id;
	protected $original_language;
	protected $destination_languages;
	protected $request_uri;
	protected $home_url;
	protected $current_language;
	protected $button_options;
	protected $button_position;
	protected $exclude_url;
	
	private function __construct($options) {
		$this->api_key 			= $options['api_key'];
		$this->project_id 		= $options['project_id'];
		$this->original_language 		= $options['original_language'];
		$this->destination_languages 	= $options['destination_languages'];
		$this->request_uri 	= $_SERVER['REQUEST_URI'];
		$this->home_url 		= (isset($options['home_url']) && $options['home_url']!="") ? '/'.trim(rtrim($options['home_url'],'/'),'/'): null;
		$this->buttonOptions 	= isset($options['buttonOptions']) ? $options['buttonOptions']: null;
		// Button position: 'bottom-left', 'bottom-center', 'bottom-right', 'top-left', 'top-center', 'top-right'
		$this->button_position 	= isset($options['button_position']) ? $options['button_position']: 'bottom-right';
		$this->exclude_url 		= isset($options['exclude_url']) ? $options['exclude_url']: null;
		
        if ($this->api_key == null || mb_strlen($this->api_key) == 0) {
            throw new AZException('Azbox requires an api_key.');
        }

		if ($this->project_id == null || mb_strlen($this->project_id) == 0) {
            throw new AZException('Azbox requires an projectId.');
        }
		
		$this->current_language = $this->getCurrentLang();
		if($this->current_language!=$this->original_language) {
			$_SERVER['REQUEST_URI'] = str_replace('/'.$this->current_language,'',$this->request_uri);	
		}
		ob_start(array(&$this,'treatPage'));
	}
	
	public static function Instance($options = "")
	{
		static $inst = null;
		if($inst == null)
		{
			$inst = new AZ($options);
		}
		return $inst;
	}
	
	public function treatPage($final) {
		// Check if this is an AJAX request for translation
		$is_ajax = isset($_GET['azbox_ajax']) && $_GET['azbox_ajax'] == '1';
		$ajax_lang = isset($_GET['azbox_translate']) ? $_GET['azbox_translate'] : null;
		
		if($this->isEligibleURL($_SERVER['REQUEST_URI']) && AZUtils::is_HTML($final)) {
			// Handle AJAX translation request
			if ($is_ajax && $ajax_lang && $ajax_lang != $this->original_language) {
				try {
					// Translate the page
					$translated = $this->translatePageTo($final, $ajax_lang);
					
					// Extract body content
					$body_content = "";
					if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $translated, $body_matches)) {
						$body_content = $body_matches[1];
					} else {
						$body_content = $translated;
					}
					
					// Extract title
					$title = "";
					if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $translated, $title_matches)) {
						$title = strip_tags(trim($title_matches[1]));
					}
					
					// Return JSON with translated content
					header('Content-Type: application/json');
					header('Cache-Control: no-cache, must-revalidate');
					echo json_encode(array(
						'success' => true,
						'html' => $body_content,
						'title' => $title
					));
					exit;
				}
				catch(\Azbox\AZException $e) {
					header('Content-Type: application/json');
					header('Cache-Control: no-cache, must-revalidate');
					echo json_encode(array(
						'success' => false,
						'error' => $e->getMessage()
					));
					exit;
				}
				catch(\Exception $e) {
					header('Content-Type: application/json');
					header('Cache-Control: no-cache, must-revalidate');
					echo json_encode(array(
						'success' => false,
						'error' => $e->getMessage()
					));
					exit;
				}
			}
			
			if($this->current_language!=$this->original_language) {
				try {
					$l =  $this->current_language;
					$final = $this->translatePageTo($final,$l);
				}
				catch(\Azbox\AZException $e) {
					$final .= "<!--Azbox error : ".$e->getMessage()."-->";
				}
				catch(\Exception $e) {
					$final .= "<!--Azbox error : ".$e->getMessage()."-->";
				}	
			}
			
			/* Adds HrefLang */
			$dest = explode(",",$this->destination_languages);
			
			$full_url = ($this->current_language!=$this->original_language) ?  str_replace('/'.$this->current_language.'/','/',$this->full_url($_SERVER)):$this->full_url($_SERVER);
			$hrefs = '<link rel="alternate" hreflang="'.$this->original_language.'" href="'.$full_url.'" />'."\n";
			foreach($dest as $d) {
				$hrefs.= '<link rel="alternate" hreflang="'.$d.'" href="'.$this->replaceUrl($full_url,$d).'" />'."\n";
			}

			// Add Azbox meta tag with encrypted projectId
			$encrypted_project_id = $this->encryptProjectId($this->project_id);
			$azbox_meta = '<meta name="azbox" content="'.$encrypted_project_id.'">'."\n";
			
			$css = $this->getInlineCSS();
			$js = $this->getInlineJS();
			$final = str_replace('</head>',$azbox_meta.$hrefs.$css.'</head>',$final);
			$final = str_replace('</body>',$js.'</body>',$final);
			
			
			//Place the button if we see short code
			if (strpos($final,'<div id="azbox_here"></div>') !== false) {
				
				$button = $this->returnWidgetCode();
				$final = str_replace('<div id="azbox_here"></div>',$button,$final);
			}
			
			//Place the button if not in the page			
			if (strpos($final,'id="azbox_switcher"') === false) {
				
				$button = $this->returnWidgetCode(true);
				$final = (strpos($final, '</body>') !== false) ? AZUtils::str_lreplace('</body>',$button.'</body>',$final):AZUtils::str_lreplace('</footer>',$button.'</footer>',$final);
			}
			$length = strlen($final);
			header('Content-Length: '.$length);
			return $final;
		}
		else {
			return $final;
		}
	}
	
	
	function translatePageTo($final,$l) { 
		$translator = $this->api_key ? new \Azbox\AZClient($this->api_key, $this->project_id):null;
		$translatedPage = $translator->translateDomFromTo($final,$this->original_language,$l,); 
		
		preg_match_all('/<a([^\>]+?)?href=(\"|\')([^\s\>]+?)(\"|\')/',$translatedPage,$out, PREG_PATTERN_ORDER);	
		for($i=0;$i<count($out[0]);$i++) {
			$sometags = $out[1][$i];
			$current_url = $out[3][$i];
			$parsed_url = parse_url($current_url);
			
			if((($current_url[0] == 'h' && $parsed_url['host']==$_SERVER['HTTP_HOST']) || ($current_url[0] =='/' && $current_url[1] !='/')) 
				 && !AZUtils::endsWith($current_url,'.jpg') && !AZUtils::endsWith($current_url,'.jpeg') && !AZUtils::endsWith($current_url,'.png') && !AZUtils::endsWith($current_url,'.pdf')
				&& $this->isEligibleURL($current_url) && strpos($sometags,'az-notranslate') === false) 
			{
				$translatedPage = preg_replace('/<a'.preg_quote($sometags,'/').'href='.preg_quote($out[2][$i].$current_url.$out[4][$i],'/').'/','<a'.$sometags.'href='.$out[2][$i].$this->replaceUrl($current_url,$l).$out[4][$i],$translatedPage);
			}
		}
	
		preg_match_all('/<form (.*?)?action=(\"|\')([^\s\>]+?)(\"|\')/',$translatedPage,$out, PREG_PATTERN_ORDER);	
		for($i=0;$i<count($out[0]);$i++) {
			$sometags = $out[1][$i];
			$current_url = $out[3][$i];
			$parsed_url = parse_url($current_url);
			if((($current_url[0] == 'h' && $parsed_url['host']==$_SERVER['HTTP_HOST']) || ($current_url[0] =='/' && $current_url[1] !='/')) 
				 && !AZUtils::endsWith($current_url,'.jpg') && !AZUtils::endsWith($current_url,'.jpeg') && !AZUtils::endsWith($current_url,'.png') && !AZUtils::endsWith($current_url,'.pdf')
				&& $this->isEligibleURL($current_url) && strpos($sometags,'az-notranslate') === false) 
			{
				$translatedPage = preg_replace('/<form '.preg_quote($sometags,'/').'action='.preg_quote($out[2][$i].$current_url.$out[4][$i],'/').'/','<form '.$sometags.'action='.$out[2][$i].$this->replaceUrl($current_url,$l).$out[4][$i],$translatedPage);
			}
		}
		preg_match_all('/<option (.*?)?(\"|\')((https?:\/\/|\/)[^\s\>]*?)(\"|\')(.*?)?>/',$translatedPage,$out, PREG_PATTERN_ORDER);
		for($i=0;$i<count($out[0]);$i++) {
			$sometags = $out[1][$i];
			$current_url = $out[3][$i];
			$parsed_url = parse_url($current_url);
			if((($current_url[0] == 'h' && $parsed_url['host']==$_SERVER['HTTP_HOST']) || $current_url[0] =='/') 
				  && !AZUtils::endsWith($current_url,'.jpg') && !AZUtils::endsWith($current_url,'.jpeg') && !AZUtils::endsWith($current_url,'.png') && !AZUtils::endsWith($current_url,'.pdf')
				&& $this->isEligibleURL($current_url) && strpos($sometags,'az-notranslate') === false) 
			{
				$translatedPage = preg_replace('/<option '.preg_quote($sometags,'/').preg_quote($out[2][$i].$current_url.$out[5][$i],'/').'(.*?)?>/','<option '.$sometags.$out[2][$i].$this->replaceUrl($current_url,$l).$out[5][$i].'$2>',$translatedPage);
			}
		}
		preg_match_all('/<link rel="canonical"(.*?)?href=(\"|\')([^\s\>]+?)(\"|\')/',$translatedPage,$out, PREG_PATTERN_ORDER);
		for($i=0;$i<count($out[0]);$i++) {
			$sometags = $out[1][$i];
			$current_url = $out[3][$i];
			$parsed_url = parse_url($current_url);
			if((($current_url[0] == 'h' && $parsed_url['host']==$_SERVER['HTTP_HOST']) || $current_url[0] =='/') 
				  && !AZUtils::endsWith($current_url,'.jpg') && !AZUtils::endsWith($current_url,'.jpeg') && !AZUtils::endsWith($current_url,'.png') && !AZUtils::endsWith($current_url,'.pdf')
				&& $this->isEligibleURL($current_url) && strpos($sometags,'az-notranslate') === false) 
			{
				$translatedPage = preg_replace('/<link rel="canonical"'.preg_quote($sometags,'/').'href='.preg_quote($out[2][$i].$current_url.$out[4][$i],'/').'/','<link rel="canonical"'.$sometags.'href='.$out[2][$i].$this->replaceUrl($current_url,$l).$out[4][$i],$translatedPage);
			}
		}		
		preg_match_all('/<meta property="og:url"(.*?)?content=(\"|\')([^\s\>]+?)(\"|\')/',$translatedPage,$out, PREG_PATTERN_ORDER);
		for($i=0;$i<count($out[0]);$i++) {
			$sometags = $out[1][$i];
			$current_url = $out[3][$i];
			$parsed_url = parse_url($current_url);
			if((($current_url[0] == 'h' && $parsed_url['host']==$_SERVER['HTTP_HOST']) || $current_url[0] =='/') 
				 && !AZUtils::endsWith($current_url,'.jpg') && !AZUtils::endsWith($current_url,'.jpeg') && !AZUtils::endsWith($current_url,'.png') && !AZUtils::endsWith($current_url,'.pdf')
				&& $this->isEligibleURL($current_url) && strpos($sometags,'az-notranslate') === false) 
			{
				$translatedPage = preg_replace('/<meta property="og:url"'.preg_quote($sometags,'/').'content='.preg_quote($out[2][$i].$current_url.$out[4][$i],'/').'/','<meta property="og:url"'.$sometags.'content='.$out[2][$i].$this->replaceUrl($current_url,$l).$out[4][$i],$translatedPage);
			}
		}
		
		$translatedPage = preg_replace('/<html (.*?)?lang=(\"|\')(\S*)(\"|\')/','<html $1lang=$2'.$l.'$4',$translatedPage);
		$translatedPage = preg_replace('/property="og:locale" content=(\"|\')(\S*)(\"|\')/','property="og:locale" content=$1'.$l.'$3',$translatedPage);
		return $translatedPage;
	}
	
	public function url_origin($s, $use_forwarded_host=false) {
		$ssl = (!empty($s['HTTPS']) && $s['HTTPS'] == 'on') ? true:false;
		$sp = strtolower($s['SERVER_PROTOCOL']);
		$protocol = substr($sp, 0, strpos($sp, '/')) . (($ssl) ? 's' : '');
		$port = $s['SERVER_PORT'];
		$port = ((!$ssl && $port=='80') || ($ssl && $port=='443')) ? '' : ':'.$port;
		$host = ($use_forwarded_host && isset($s['HTTP_X_FORWARDED_HOST'])) ? $s['HTTP_X_FORWARDED_HOST'] : (isset($s['HTTP_HOST']) ? $s['HTTP_HOST'] : null);
		$host = isset($host) ? $host : $s['SERVER_NAME'] . $port;
		return $protocol . '://' . $host;
	}
	public function full_url($s, $use_forwarded_host=false) {
	   return $this->url_origin($s, $use_forwarded_host) . $this->request_uri;
	}		
	public function URLToRelative($url) {
		
		$home_dir = $this->home_url;
		if($home_dir)
			$url = str_replace($home_dir ,'',$url);
		
		if ((substr($url, 0, 7) == 'http://') || (substr($url, 0, 8) == 'https://')) {
			// the current link is an "absolute" URL - parse it to get just the path
			$parsed = parse_url($url);
			$path     = isset($parsed['path']) ? $parsed['path'] : ''; 
			$query    = isset($parsed['query']) ? '?' . $parsed['query'] : ''; 
			$fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : ''; 
			return $path.$query.$fragment;
		}
		else {
			return ($url=="") ? "/":$url;
		}
	}
	public function replaceUrl($url,$l) {
		
		if($l=='')
			return $url;
			
		
		
		$home_dir = $this->home_url;
		if($home_dir) {
			return str_replace($home_dir,$home_dir."/$l",$url);
		}
		else {
			$parsed_url = parse_url($url);
			$scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : ''; 
			$host     = isset($parsed_url['host']) ? $parsed_url['host'] : ''; 
			$port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : ''; 
			$user     = isset($parsed_url['user']) ? $parsed_url['user'] : ''; 
			$pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : ''; 
			$pass     = ($user || $pass) ? "$pass@" : ''; 
			$path     = isset($parsed_url['path']) ? $parsed_url['path'] : '/'; 
			$query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : ''; 
			$fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : ''; 

			return (strlen($path)>2 && substr($path,0,4)=="/$l/") ? "$scheme$user$pass$host$port$path$query$fragment":"$scheme$user$pass$host$port/$l$path$query$fragment";
		}
	}

	public function isEligibleURL($url) {
		$url = $this->URLToRelative($url);
		
		$exclusions = preg_replace('#\s+#',',',$this->exclude_url);
		$exclusions = $exclusions=="" ? "/amp":$exclusions.",/amp";
		$regex = explode(",",$exclusions);  

		if($exclusions!="") {
			foreach($regex as $ex) { 
				if(preg_match('/'.str_replace('/', '\/',$ex).'/',$url)==1)
					return false;
			}
			return true;
		}
		else
			return true;
	}
	
	public function getCurrentLang() {
		
		$home_trimed = $this->home_url ? trim(rtrim($this->home_url,'/'),'/')."\/":"";
		if(preg_match('/^\/'.$home_trimed.'([a-z]{2})(\/(.*))?/',$this->request_uri,$matches)) { 
			$languages = explode(",",$this->destination_languages);
			if(in_array($matches[1],$languages))
				return $matches[1];
		}
		return $this->original_language;
	}

	public function returnWidgetCode($forceNoMenu = false) { 
		$original = $this->original_language;
		
		$url = 	$_SERVER['REQUEST_URI'];

		$button_options = $this->button_options;
		$full = isset($button_options['fullname']) ? $button_options['fullname']:true;
		$withname = isset($button_options['with_name']) ? $button_options['with_name']:true;
		$is_dropdown = isset($button_options['is_dropdown']) ? $button_options['is_dropdown']:true;
		
		$current = $this->current_language;
		$list = $is_dropdown ? "<ul class=\"azbox-lang-list\">":"";
		$destEx = explode(",",$this->destination_languages);
		array_unshift($destEx,$original);
		foreach($destEx as $d) { 
			if($d!=$current) {
				$link = (($d!=$original) ? $this->replaceUrl($url,$d):$this->replaceUrl($url,''));
				$langName = $withname ? ($full ? AZUtils::getLangNameFromCode($d,false) : strtoupper($d)) : "";
				$list .= '<li class="azbox-lang-item"><a az-notranslate href="'.$link.'" class="azbox-lang-link">'.$langName.'</a></li>';
			}
		}
		$list .= $is_dropdown ? "</ul>":"";	
		$tag =  $is_dropdown ? "div":"li";

		$moreclass = $is_dropdown ? 'azbox-dropdown ':'azbox-list ';
		$currentLangName = $withname ? ($full ? AZUtils::getLangNameFromCode($current,false) : strtoupper($current)) : "";
		
		$aside1 = '<aside id="azbox_switcher" az-notranslate class="azbox-selector '.$moreclass.'azbox-closed">';
		$aside2 = '</aside>';
		
		$button = '<!--Azbox '.AZBOX_VERSION.'-->'.$aside1.'<'.$tag.' az-notranslate class="azbox-current"><a href="javascript:void(0);" class="azbox-current-link">'.$currentLangName.'</a></'.$tag.'>'.$list.$aside2;
		return $button;
	}
	
	private function getInlineCSS() {
		$position_css = $this->getButtonPositionCSS();
		return '<style id="azbox-inline-css">
#azbox_switcher {
	position: fixed;
	'.$position_css.'
	z-index: 9999;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
	font-size: 14px;
	line-height: 1.4;
}
#azbox_switcher.azbox-loading {
	opacity: 0.7;
	pointer-events: none;
}
#azbox_switcher.azbox-loading::after {
	content: "";
	position: absolute;
	top: 50%;
	left: 50%;
	width: 16px;
	height: 16px;
	margin: -8px 0 0 -8px;
	border: 2px solid #ccc;
	border-top-color: #333;
	border-radius: 50%;
	animation: azbox-spin 0.6s linear infinite;
}
@keyframes azbox-spin {
	to { transform: rotate(360deg); }
}
.azbox-selector {
	background: #fff;
	border-radius: 8px;
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
	overflow: hidden;
	transition: all 0.3s ease;
}
.azbox-current {
	margin: 0;
	padding: 0;
}
.azbox-current-link {
	display: block;
	padding: 12px 20px;
	color: #333;
	text-decoration: none;
	cursor: pointer;
	background: #fff;
	border: none;
	font-weight: 500;
	transition: background 0.2s ease;
}
.azbox-current-link:hover {
	background: #f5f5f5;
}
.azbox-dropdown.azbox-closed .azbox-lang-list {
	display: none;
}
.azbox-dropdown:not(.azbox-closed) .azbox-lang-list {
	display: block;
}
.azbox-lang-list {
	list-style: none;
	margin: 0;
	padding: 0;
	border-top: 1px solid #e0e0e0;
}
.azbox-lang-item {
	margin: 0;
	padding: 0;
}
.azbox-lang-link {
	display: block;
	padding: 10px 20px;
	color: #666;
	text-decoration: none;
	transition: background 0.2s ease, color 0.2s ease;
}
.azbox-lang-link:hover {
	background: #f5f5f5;
	color: #333;
}
.azbox-list {
	display: flex;
	flex-direction: column;
}
.azbox-list .azbox-lang-list {
	border-top: none;
	display: flex;
	flex-direction: column;
}
.azbox-list .azbox-lang-item {
	border-top: 1px solid #e0e0e0;
}
.azbox-list .azbox-lang-item:first-child {
	border-top: none;
}
@media (max-width: 768px) {
	#azbox_switcher {
		'.$this->getButtonPositionCSS(true).'
		font-size: 13px;
	}
	.azbox-current-link,
	.azbox-lang-link {
		padding: 10px 16px;
	}
}
</style>';
	}
	
	private function getInlineJS() {
		$original_lang = $this->original_language;
		$project_id = $this->project_id;
		
		// Escape JavaScript strings
		$original_lang_escaped = json_encode($original_lang);
		$project_id_escaped = json_encode($project_id);
		
		return '<script id="azbox-inline-js">
(function() {
	var AZBOX_CONFIG = {
		originalLang: '.$original_lang_escaped.',
		projectId: '.$project_id_escaped.',
		cachePrefix: "azbox_cache_",
		cacheTTL: 7 * 24 * 60 * 60 * 1000 // 7 días en milisegundos
	};
	
	// Cache utilities
	var AzboxCache = {
		getKey: function(url, lang) {
			return AZBOX_CONFIG.cachePrefix + btoa(url).replace(/[^a-zA-Z0-9]/g, "_") + "_" + lang;
		},
		
		get: function(url, lang) {
			try {
				var key = this.getKey(url, lang);
				var cached = localStorage.getItem(key);
				if (!cached) return null;
				
				var data = JSON.parse(cached);
				var now = Date.now();
				
				// Check if cache is expired
				if (data.expires && now > data.expires) {
					localStorage.removeItem(key);
					return null;
				}
				
				return data.html;
			} catch (e) {
				return null;
			}
		},
		
		set: function(url, lang, html) {
			try {
				var key = this.getKey(url, lang);
				var data = {
					html: html,
					expires: Date.now() + AZBOX_CONFIG.cacheTTL,
					timestamp: Date.now()
				};
				localStorage.setItem(key, JSON.stringify(data));
			} catch (e) {
				// Silently fail if localStorage is full or disabled
			}
		},
		
		clear: function() {
			try {
				var keys = Object.keys(localStorage);
				for (var i = 0; i < keys.length; i++) {
					if (keys[i].indexOf(AZBOX_CONFIG.cachePrefix) === 0) {
						localStorage.removeItem(keys[i]);
					}
				}
			} catch (e) {
				// Silently fail
			}
		}
	};
	
	// Translation loader
	var AzboxTranslator = {
		loadTranslation: function(url, lang, callback, errorCallback) {
			// Check cache first
			var cached = AzboxCache.get(url, lang);
			if (cached) {
				callback(cached);
				return;
			}
			
			// Load from server via AJAX
			var xhr = new XMLHttpRequest();
			var currentUrl = window.location.pathname + window.location.search;
			var separator = currentUrl.indexOf("?") === -1 ? "?" : "&";
			var ajaxUrl = currentUrl + separator + "azbox_translate=" + lang + "&azbox_ajax=1";
			
			xhr.open("GET", ajaxUrl, true);
			xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
			xhr.onreadystatechange = function() {
				if (xhr.readyState === 4) {
					if (xhr.status === 200) {
						try {
							var response = JSON.parse(xhr.responseText);
							if (response.success && response.html) {
								// Cache the result
								AzboxCache.set(url, lang, response.html);
								callback(response.html);
							} else {
								// Fallback to normal navigation
								if (errorCallback) errorCallback();
							}
						} catch (e) {
							// Fallback to normal navigation
							if (errorCallback) errorCallback();
						}
					} else {
						// Fallback to normal navigation
						if (errorCallback) errorCallback();
					}
				}
			};
			xhr.send();
		},
		
		applyTranslation: function(html, lang, targetUrl) {
			// Parse the translated HTML
			var parser = new DOMParser();
			var doc = parser.parseFromString(html, "text/html");
			
			// Update body content
			if (doc.body) {
				document.body.innerHTML = doc.body.innerHTML;
			}
			
			// Update title
			if (doc.title) {
				document.title = doc.title;
			}
			
			// Update meta tags
			var metaTags = doc.querySelectorAll("meta");
			metaTags.forEach(function(meta) {
				var name = meta.getAttribute("name") || meta.getAttribute("property");
				if (name) {
					var existing = document.querySelector("meta[name=\"" + name + "\"], meta[property=\"" + name + "\"]");
					if (existing && meta.getAttribute("content")) {
						existing.setAttribute("content", meta.getAttribute("content"));
					}
				}
			});
			
			// Update language switcher to show current language
			var switcher = document.getElementById("azbox_switcher");
			if (switcher) {
				var currentLink = switcher.querySelector(".azbox-current-link");
				if (currentLink) {
					// Update current language display (you may need to adjust this based on your language names)
					var langNames = {
						"en": "English", "es": "Español", "fr": "Français", "de": "Deutsch"
					};
					var displayName = langNames[lang] || lang.toUpperCase();
					currentLink.textContent = displayName;
				}
			}
			
			// Reinitialize any scripts that need it
			if (typeof initAzboxSelector === "function") {
				initAzboxSelector();
			}
			
			// Update URL using the target URL from the link (generated by PHP replaceUrl)
			if (targetUrl) {
				// Extract pathname from targetUrl (it is already a relative URL from PHP)
				var newPath = targetUrl;
				// If it is a full URL, extract just the path
				if (targetUrl.indexOf("http") === 0) {
					try {
						var urlObj = new URL(targetUrl);
						newPath = urlObj.pathname + urlObj.search;
					} catch (e) {
						// If URL parsing fails, use as is
						newPath = targetUrl;
					}
				}
				// Update browser URL
				if (newPath !== window.location.pathname + window.location.search) {
					window.history.pushState({lang: lang}, "", newPath);
				}
			}
		}
	};
	
	function initAzboxSelector() {
		var switcher = document.getElementById("azbox_switcher");
		if (!switcher) return;
		
		var currentLink = switcher.querySelector(".azbox-current-link");
		if (currentLink) {
			currentLink.addEventListener("click", function(e) {
				e.preventDefault();
				switcher.classList.toggle("azbox-closed");
			});
		}
		
		// Intercept language link clicks
		var langLinks = switcher.querySelectorAll(".azbox-lang-link");
		langLinks.forEach(function(link) {
			link.addEventListener("click", function(e) {
				e.preventDefault();
				var href = link.getAttribute("href");
				if (!href || href === "#" || href === "javascript:void(0)") return;
				
				// Extract language from URL - look for /XX/ or /XX at the start
				var langMatch = href.match(/^\/([a-z]{2})(?:\/|$)/);
				var lang = langMatch ? langMatch[1] : AZBOX_CONFIG.originalLang;
				
				// Show loading state
				switcher.classList.add("azbox-loading");
				
				// Get current URL (without language prefix)
				var currentUrl = window.location.pathname;
				currentUrl = currentUrl.replace(/^\/([a-z]{2})(?:\/|$)/, "/");
				if (currentUrl === "") currentUrl = "/";
				
				// Store href for URL update (PHP already generated the correct URL)
				var targetUrl = href;
				
				// Load translation
				AzboxTranslator.loadTranslation(currentUrl, lang, function(html) {
					AzboxTranslator.applyTranslation(html, lang, targetUrl);
					switcher.classList.remove("azbox-loading");
					switcher.classList.add("azbox-closed");
				}, function() {
					// Fallback on error
					window.location.href = targetUrl;
				});
			});
		});
		
		// Close dropdown when clicking outside
		document.addEventListener("click", function(e) {
			if (switcher && !switcher.contains(e.target)) {
				switcher.classList.add("azbox-closed");
			}
		});
	}
	
	// Handle browser back/forward buttons
	window.addEventListener("popstate", function(e) {
		// Reload page to get fresh translation from server
		window.location.reload();
	});
	
	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", initAzboxSelector);
	} else {
		initAzboxSelector();
	}
})();
</script>';
	}
	
	/**
	 * Get CSS for button position
	 * 
	 * @param bool $mobile Whether to return mobile spacing (10px) or desktop (20px)
	 * @return string CSS position properties
	 */
	private function getButtonPositionCSS($mobile = false) {
		$spacing = $mobile ? '10px' : '20px';
		$position = $this->button_position;
		
		// Validate position, default to bottom-right if invalid
		$valid_positions = array('bottom-left', 'bottom-center', 'bottom-right', 'top-left', 'top-center', 'top-right');
		if (!in_array($position, $valid_positions)) {
			$position = 'bottom-right';
		}
		
		$css = '';
		switch ($position) {
			case 'bottom-left':
				$css = 'bottom: '.$spacing.'; left: '.$spacing.';';
				break;
			case 'bottom-center':
				$css = 'bottom: '.$spacing.'; left: 50%; transform: translateX(-50%);';
				break;
			case 'bottom-right':
				$css = 'bottom: '.$spacing.'; right: '.$spacing.';';
				break;
			case 'top-left':
				$css = 'top: '.$spacing.'; left: '.$spacing.';';
				break;
			case 'top-center':
				$css = 'top: '.$spacing.'; left: 50%; transform: translateX(-50%);';
				break;
			case 'top-right':
				$css = 'top: '.$spacing.'; right: '.$spacing.';';
				break;
		}
		
		return $css;
	}
	
	/**
	 * Encrypt projectId using AES-256-CBC
	 * Compatible with Flutter decrypt
	 * 
	 * @param string $projectId The project ID to encrypt
	 * @return string Base64 encoded encrypted string (format: IV:encrypted_data)
	 */
	private function encryptProjectId($projectId) {
		// Encryption key - should be 32 bytes for AES-256
		// Using a key derived from a secret (you can change this)
		$secret_key = 'azbox_encryption_key_2024_secure'; // 32 bytes
		$key = substr(hash('sha256', $secret_key, true), 0, 32);
		
		// Generate random IV (16 bytes for AES)
		$iv = openssl_random_pseudo_bytes(16);
		
		// Encrypt using AES-256-CBC
		$encrypted = openssl_encrypt($projectId, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
		
		if ($encrypted === false) {
			// Fallback to base64 if encryption fails
			return base64_encode($projectId);
		}
		
		// Combine IV and encrypted data, then base64 encode
		// Format: base64(IV + encrypted_data)
		$combined = $iv . $encrypted;
		return base64_encode($combined);
	}
	
}

?>
