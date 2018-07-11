<?php
class Dao_Crawler_Freeones extends Dao_Crawler_Abstract {
	
	/**
	 * Return a parsed Model's biography array
	 * 
	 * @param string $url
	 * @return array
	 */
	public function getBio($url) {
		$httpObj = $this->getHttpClientSingleton();
		
		$httpObj->setUri($url);
		
		$resp = $httpObj->request(Zend_Http_Client::GET);
		
		$dom = new Zend_Dom_Query($resp->getBody());
		
		$list_params = $dom->query('table#biographyTable td.paramname');
		$list_values = $dom->query('table#biographyTable td.paramvalue');
		
		$params_to_hash = array(
			'Babe Name' => 'name',
			'Profession' => 'profession',
			'Ethnicity' => 'ethnic',
			'Aliases' => 'aliases',
			'Tattoos' => 'tattoos',
			'Piercings' => 'piercings',
			'Extra' => 'extra',
			'Filmography' => 'filmography',
			'Social Network Links' => 'social',
			'Babe Rank on Freeones' => 'rank'
		);
		$params_to_origin_hash = array(
			'Country of Origin' => 'country',
			'Province / State' => 'state',
			'Place of Birth' => 'city',
			'Date of Birth' => 'date_birth',
			'Date Deceased' => 'date_death'
		);
		$params_to_body_hash = array(
			'Eye Color' => 'eye_color',
			'Hair Color' => 'hair_color',
			'Height' => 'height',
			'Weight' => 'weight',
			'Measurements' => 'measurements',
			'Fake boobs' => 'fake_boobs',
			'Shoe Size' => 'shoe_size'
		);
		$params_to_career_hash = array(
			'Career Status' => 'status',
			'Career Start And End' => 'start_end'
		);
		$params_to_social_hash = array(
			'Twitter_link' => 'twitter',
			'Facebook_link' => 'facebook'
		);
		
		$result = array(
			'origin' => array(),
			'body' => array(),
			'career' => array()
		);
		
		while ($list_params->valid()) {
			$param = trim($list_params->current()->nodeValue, "\n\r\t: ");
			$value = trim($list_values->current()->nodeValue, "\n\r\t: " . chr(194) . chr(160));
		
			switch ($param) {
				case 'Aliases':
				case 'Filmography':
					$value = explode(',', $value);
					foreach ($value as &$t) {
						$t = trim($t);
					}
					break;
				case 'Date of Birth':
				case 'Date Deceased':
					if ($value == 'Unknown') {
						$value = null;
						break;
					}
					$pos = strpos($value, '(');
					if ($pos !== false) {
						$value = substr($value, 0, $pos-1);
					}
					
					$value = DateTime::createFromFormat('F d, Y', $value);
					if ($value !== false) {
						$value = $value->format('Ymd');
					}
					break;
				case 'Height':
					$pos = strpos($value, 'heightcm = "');
					if ($pos !== false) {
						$value = intval(substr($value, $pos + 12, 3));
					} else {
						$value = null;
					}
					break;
				case 'Weight':
					$pos = strpos($value, 'weightkg = "');
					if ($pos !== false) {
						$value = intval(substr($value, $pos + 12, 3));
					} else {
						$value = null;
					}
					break;
				case 'Fake boobs':
					$value = ($value == 'Yes');
					break;
				case 'Career Status':
					if ($value == 'Active') {
						$value = Dao_Mongodb_List_0DayPorno_Models::CAREER_STATUS_ACTIVE;
					} else if ($value == 'Retired') {
						$value = Dao_Mongodb_List_0DayPorno_Models::CAREER_STATUS_RETIRED;
					} else {
						$value = null;
					}
					break;
				case 'Shoe Size':
					$pos = strpos($value, 'if (\'');
					if ($pos !== false) {
						$value = floatval(substr($value, $pos + 5, 4));
						if ($value == 0) {
							$value = null;
						}
					} else {
						$value = null;
					}
					break;
				case 'Tattoos':
				case 'Piercings':
					if (stripos($value, 'none') === 0) {
						$value = null;
					}
					break;
				case 'Social Network Links':
					$list_soc_nets = $list_values->current()->getElementsByTagName('a');
					$len = $list_soc_nets->length;
					if ($len > 0) {
						$value = array();
						for ($i = 0; $i < $len; $i++) {
							$sn = $list_soc_nets->item($i);
							//$param = $sn->textContent . '_link';
							$value[strtolower($sn->textContent)] =
								$sn->attributes->getNamedItem('href')->textContent;
						}
					}
					break;
			}
		
			//echo '-' . $param . '- ~ -' . $value . "-\n";
			
			if (!is_null($value)) {
				if (isset($params_to_hash[$param])) {
					$result[$params_to_hash[$param]] = $value;
				} elseif (isset($params_to_origin_hash[$param])) {
					$result['origin'][$params_to_origin_hash[$param]] = $value;
				} elseif (isset($params_to_body_hash[$param])) {
					$result['body'][$params_to_body_hash[$param]] = $value;
				} elseif (isset($params_to_career_hash[$param])) {
					$result['career'][$params_to_career_hash[$param]] = $value;
				}
			}
		
			$list_params->next();
			$list_values->next();
		}
		
		if (!empty($result['body']['measurements'])) {
			$tmp = explode('-', $result['body']['measurements']);
			if (sizeof($tmp) == 3) {
				$result['body']['breast'] = $tmp[0];
				$result['body']['waist'] = $tmp[1];
				$result['body']['hip'] = $tmp[2];
			}
			unset($result['body']['measurements']);
		}
		
		if (!empty($result['career']['start_end'])) {
			$tmp = explode('-', $result['career']['start_end']);
			if (sizeof($tmp) == 2) {
				$tmp_start = trim($tmp[0]);
				
				$pos = strpos($tmp[1], '(');
				if ($pos !== false) {
					$tmp[1] = substr($tmp[1], 0, $pos);
				}
				$tmp_end = trim($tmp[1]);
				
				if ($tmp_start == 'Present') {
					$result['career']['start'] = Dao_Mongodb_List_0DayPorno_Models::CAREER_PRESENT;
				} else if (is_numeric($tmp_start)) {
					$result['career']['start'] = intval($tmp_start);
				}
				
				if ($tmp_end == 'Present') {
					$result['career']['end'] = Dao_Mongodb_List_0DayPorno_Models::CAREER_PRESENT;
				} else if (is_numeric($tmp_end)) {
					$result['career']['end'] = intval($tmp_end);
				}
			}
			unset($result['career']['start_end']);
		}
		
		return $result;
	}
	
	/**
	 * Returns an array of links to model's profiles
	 * 
	 * @param string $letter
	 * @param int $page
	 * @return array
	 */
	public function getProfilesLinks($letter, $page) {
		$httpObj = $this->getHttpClientSingleton();
		$httpObj->setUri('http://www.freeones.com/html/' . $letter . '_links/?text=1&page=' . $page);
		
		$resp = $httpObj->request(Zend_Http_Client::GET);
		
		$dom = new Zend_Dom_Query($resp->getBody());
		
		$list_divs = $dom->query('table#ListContainer div.textlink');
		
		$result = array();
		
		while ($list_divs->valid()) {
			$dom_item = $list_divs->current()->getElementsByTagName('a');

			$url_model_links = $dom_item->item(2)->attributes->getNamedItem('href')->textContent;
			
			$url_array = explode('/', $url_model_links);
			$url_model_bio = 'http://www.freeones.com/html/a_links/bio_' . $url_array[5] . '.php';
			
			$result[] = array(
				'links' => $url_model_links,
				'bio' => $url_model_bio
			);
			
			$list_divs->next();
		}
		
		return $result;
	}
	
	public function getLinks($url_links) {
		$url_links_rss = str_replace('/html/', '/rss/', $url_links);
		
		$channel = new Zend_Feed_Rss($url_links_rss);
		$result = array();
		foreach ($channel as $item) {
			$result[] = array(
				'title' => $item->title(),
				'link' => $item->link(),
				'description' => $item->description()
			);
		}
		
		return $result;
	}
	
}