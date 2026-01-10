<?php
namespace Azbox;

class AZClient 
{
	protected $api_key;
	protected $project_id;

	const API_BASE = 'https://api.azbox.io/v1/';
	
	function __construct($key, $project_id) {
		$this->api_key = $key;
		$this->project_id = $project_id;
	}
	
	public function translateDomFromTo($dom,$languageFrom,$languageTo) { 		
		$words = array();
		$text_replacements = array(); 
		
		// Extract title
		$title = "";
		if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $dom, $matches)) {
			$title = strip_tags(trim($matches[1]));
		}
		if (empty($title)) {
			$title = "";
		}
		
		// Extract text from body using regex - simple and direct method
		// Buscar contenido entre tags, excluyendo script, style
		$body_content = $dom;
		if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $dom, $body_matches)) {
			$body_content = $body_matches[1];
		}
		
		// Extract text between tags (excluding script and style)
		$text_pattern = '/>([^<]+)</';
		preg_match_all($text_pattern, $body_content, $text_matches, PREG_OFFSET_CAPTURE);
		
		foreach ($text_matches[1] as $match) {
			$text = $match[0];
			$original_pos = $match[1];
			
			// Normalize text: remove line breaks and multiple spaces
			$text = preg_replace('/[\r\n]+/', ' ', $text); // Convert line breaks to spaces
			$text = preg_replace('/\s+/', ' ', $text); // Remove multiple spaces
			$text = trim($text); // Remove spaces at the beginning and end
			
			// Verify that it is not in script or style
			$before = substr($body_content, max(0, $original_pos - 100), 200);
			if (preg_match('/<(script|style)[^>]*>/i', $before)) {
				continue;
			}
			
			// Verify that it does not have az-notranslate
			if (preg_match('/az-notranslate/i', $before)) {
				continue;
			}
			
			// Filter valid text
			if ($text !== '' && 
			    strlen($text) > 2 && 
			    !is_numeric($text) && 
			    !preg_match('/^\d+%$/', $text) &&
			    !preg_match('/^[^\w\s]+$/', $text)) { // No only symbols
				$word_index = count($words);
				$words[] = array("t" => "1", "w" => $text);
				// Create unique placeholder for each occurrence (even if the text is the same)
				$placeholder = '{{AZBOX_PLACEHOLDER_' . $word_index . '}}';
				// Save the original text with spaces preserved for the search
				$original_text_with_spaces = $match[0];
				$text_replacements[] = array(
					'original' => $text,
					'placeholder' => $placeholder,
					'position' => $original_pos,
					'search' => '>' . $original_text_with_spaces . '<', // Use the original text without normalizing for the search
					'original_with_spaces' => $original_text_with_spaces // Save also to preserve spaces
				);
			}
		}
		
		// Extract placeholders from inputs
		preg_match_all('/placeholder=["\']([^"\']+)["\']/i', $dom, $placeholder_matches);
		foreach ($placeholder_matches[1] as $placeholder) {
			$placeholder = html_entity_decode($placeholder);
			// Normalize: remove line breaks and multiple spaces
			$placeholder = preg_replace('/[\r\n]+/', ' ', $placeholder);
			$placeholder = preg_replace('/\s+/', ' ', $placeholder);
			$placeholder = trim($placeholder);
			if ($placeholder !== '' && strlen($placeholder) > 2) {
				$words[] = array("t" => "3", "w" => $placeholder);
			}
		}
		
		// Extract button values
		preg_match_all('/<input[^>]*type=["\'](submit|button)["\'][^>]*value=["\']([^"\']+)["\']/i', $dom, $button_matches);
		foreach ($button_matches[2] as $button_value) {
			$button_value = html_entity_decode($button_value);
			// Normalize: remove line breaks and multiple spaces
			$button_value = preg_replace('/[\r\n]+/', ' ', $button_value);
			$button_value = preg_replace('/\s+/', ' ', $button_value);
			$button_value = trim($button_value);
			if ($button_value !== '' && strlen($button_value) > 2) {
				$words[] = array("t" => "2", "w" => $button_value);
			}
		}
		
		// Extract meta descriptions
		preg_match_all('/<meta[^>]*(name|property)=["\'](description|og:title|og:description|og:site_name|twitter:title|twitter:description)["\'][^>]*content=["\']([^"\']+)["\']/i', $dom, $meta_matches);
		foreach ($meta_matches[4] as $meta_content) {
			// Normalize: remove line breaks and multiple spaces
			$meta_content = preg_replace('/[\r\n]+/', ' ', $meta_content);
			$meta_content = preg_replace('/\s+/', ' ', $meta_content);
			$meta_content = trim($meta_content);
			if ($meta_content !== '' && strlen($meta_content) > 2) {
				$words[] = array("t" => "4", "w" => $meta_content);
			}
		}
		
		if (empty($words)) {
			throw new AZException('No text elements found to translate on the page.');
		}
		
		// Replace original texts with placeholders temporarily
		// IMPORTANT: DO NOT modify the order of $text_replacements, keep the original order of the array
		$dom_with_placeholders = $dom;
		// Create a copy ordered by descending position only for the replacement
		$replacements_for_replacement = $text_replacements;
		usort($replacements_for_replacement, function($a, $b) {
			return $b['position'] - $a['position'];
		});
		// Replace from back to front to not affect the positions
		foreach ($replacements_for_replacement as $replacement) {
			// Search the original text in the DOM and replace it with the placeholder
			$search = $replacement['search'];
			$pos = strpos($dom_with_placeholders, $search);
			if ($pos !== false) {
				$dom_with_placeholders = substr_replace(
					$dom_with_placeholders,
					'>' . $replacement['placeholder'] . '<',
					$pos,
					strlen($search)
				);
			}
		}
		
		// Prepare URL
		$absolute_url = $this->full_url($_SERVER);
		if(strpos($absolute_url,'admin-ajax.php') !== false) {
			$absolute_url = $_SERVER['HTTP_REFERER'];
			$title = "";
		}
		if (empty($absolute_url)) {
			$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
			$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
			$uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
			$absolute_url = $protocol . $host . $uri;
		}
		
		$bot = $this->bot_detected();
		
		// Call the API
		$parameters = array(
			"projectId" => (string)$this->project_id,
			"languageFrom" => (string)$languageFrom,
			"languageTo" => (string)$languageTo,
			"title" => (string)$title,
			"requestUrl" => (string)$absolute_url,
			"bot" => (int)$bot,
			"words" => $words
		);
		
		$api_url = self::API_BASE."translation?api_key=".$this->api_key;
		$results = $this->doRequest($api_url,$parameters);
		$json = json_decode($results,true);
		
		if(json_last_error() != JSON_ERROR_NONE) {
			throw new AZException('Error decoding API response: '.json_last_error_msg());
		}
		
		if(isset($json['error']) && !isset($json['succeeded'])) {
			throw new AZException('API error: ' . $json['error']);
		}
		
		if(!isset($json['succeeded']) || $json['succeeded'] != 1) {
			$error = isset($json['error']) ? $json['error'] : 'Unknown error';
			throw new AZException('API error: ' . $error);
		}
		
		if(!isset($json['answer']['to_words'])) {
			throw new AZException('The API did not return valid translations');
		}
		
		$translated_words = $json['answer']['to_words'];
		
		if(count($words) != count($translated_words)) {
			throw new AZException('The number of translated words does not match the number of words sent');
		}
		
		// Replace placeholders with translations
		// The index in $translated_words corresponds to the index in $words, not in $text_replacements
		$result = $dom_with_placeholders;
		$text_type1_index = 0; // Counter for texts type "1" in $words
		
		foreach ($words as $word_index => $word) {
			if ($word['t'] == '1') {
				// It is a normal text, search its placeholder in text_replacements
				if ($text_type1_index < count($text_replacements) && $word_index < count($translated_words)) {
					$replacement = $text_replacements[$text_type1_index];
					// Preserve spaces at the beginning and end of the original text if it had them
					$translated = $translated_words[$word_index];
					$original_with_spaces = $replacement['original_with_spaces'];
					
					// If the original text had spaces at the beginning/end, preserve them
					$leading_spaces = '';
					$trailing_spaces = '';
					if (preg_match('/^(\s+)/', $original_with_spaces, $leading_match)) {
						$leading_spaces = $leading_match[1];
					}
					if (preg_match('/(\s+)$/', $original_with_spaces, $trailing_match)) {
						$trailing_spaces = $trailing_match[1];
					}
					
					$translated_with_spaces = $leading_spaces . $translated . $trailing_spaces;
					$result = str_replace($replacement['placeholder'], $translated_with_spaces, $result);
					$text_type1_index++;
				}
			} elseif ($word['t'] == '2' || $word['t'] == '3' || $word['t'] == '4') {
				// It is a button, placeholder or meta tag
				if ($word_index < count($translated_words)) {
					$original = preg_quote($word['w'], '/');
					$translated = $translated_words[$word_index];
					
					if ($word['t'] == '2') {
						// Buttons
						$result = preg_replace('/value=["\']' . $original . '["\']/i', 'value="' . htmlspecialchars($translated, ENT_QUOTES) . '"', $result);
					} elseif ($word['t'] == '3') {
						// Inputs
						$result = preg_replace('/placeholder=["\']' . $original . '["\']/i', 'placeholder="' . htmlspecialchars($translated, ENT_QUOTES) . '"', $result);
					} elseif ($word['t'] == '4') {
						// Meta tags (description, og:title, og:description, etc.)
						$result = preg_replace('/content=["\']' . $original . '["\']/i', 'content="' . htmlspecialchars($translated, ENT_QUOTES) . '"', $result);
					}
				}
			}
		}
		
		return $result;
	}
	
	public function doRequest($url,$parameters) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($parameters));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		$result = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curl_error = curl_error($ch);
		curl_close($ch);
		
		if ($curl_error) {
			throw new AZException('Connection error: ' . $curl_error);
		}
		
		if ($http_code != 200) {
			throw new AZException('HTTP error: ' . $http_code);
		}
		
		return $result;
	}
	
	public function full_url($server) {
		$protocol = (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off') ? 'https://' : 'http://';
		$host = isset($server['HTTP_HOST']) ? $server['HTTP_HOST'] : 'localhost';
		$uri = isset($server['REQUEST_URI']) ? $server['REQUEST_URI'] : '/';
		return $protocol . $host . $uri;
	}
	
	public function bot_detected() {
		if (!isset($_SERVER['HTTP_USER_AGENT'])) {
			return 1;
		}
		$user_agent = $_SERVER['HTTP_USER_AGENT'];
		$bots = array('bot', 'crawler', 'spider', 'scraper');
		foreach ($bots as $bot) {
			if (stripos($user_agent, $bot) !== false) {
				return 1;
			}
		}
		return 0;
	}
}
