<?php

namespace View\Helpers;

use Dao\Mongodb\Listing\Slr\Scenes;
use MongoDB\BSON\UTCDateTime;
use Shelby\Dao\Result\Mongodb\Entry;

class Mixed {

    public $countryCodes = ['BD' => 'Bangladesh', 'BE' => 'Belgium', 'BF' => 'Burkina Faso', 'BG' => 'Bulgaria', 'BA' => 'Bosnia and Herzegovina', 'BB' => 'Barbados', 'WF' => 'Wallis and Futuna', 'BL' => 'Saint Barthelemy', 'BM' => 'Bermuda', 'BN' => 'Brunei', 'BO' => 'Bolivia', 'BH' => 'Bahrain', 'BI' => 'Burundi', 'BJ' => 'Benin', 'BT' => 'Bhutan', 'JM' => 'Jamaica', 'BV' => 'Bouvet Island', 'BW' => 'Botswana', 'WS' => 'Samoa', 'BQ' => 'Bonaire, Saint Eustatius and Saba', 'BR' => 'Brazil', 'BS' => 'Bahamas', 'JE' => 'Jersey', 'BY' => 'Belarus', 'BZ' => 'Belize', 'RU' => 'Russia', 'RW' => 'Rwanda', 'RS' => 'Serbia', 'TL' => 'East Timor', 'RE' => 'Reunion', 'TM' => 'Turkmenistan', 'TJ' => 'Tajikistan', 'RO' => 'Romania', 'TK' => 'Tokelau', 'GW' => 'Guinea-Bissau', 'GU' => 'Guam', 'GT' => 'Guatemala', 'GS' => 'South Georgia and the South Sandwich Islands', 'GR' => 'Greece', 'GQ' => 'Equatorial Guinea', 'GP' => 'Guadeloupe', 'JP' => 'Japan', 'GY' => 'Guyana', 'GG' => 'Guernsey', 'GF' => 'French Guiana', 'GE' => 'Georgia', 'GD' => 'Grenada', 'GB' => 'United Kingdom', 'GA' => 'Gabon', 'SV' => 'El Salvador', 'GN' => 'Guinea', 'GM' => 'Gambia', 'GL' => 'Greenland', 'GI' => 'Gibraltar', 'GH' => 'Ghana', 'OM' => 'Oman', 'TN' => 'Tunisia', 'JO' => 'Jordan', 'HR' => 'Croatia', 'HT' => 'Haiti', 'HU' => 'Hungary', 'HK' => 'Hong Kong', 'HN' => 'Honduras', 'HM' => 'Heard Island and McDonald Islands', 'VE' => 'Venezuela', 'PR' => 'Puerto Rico', 'PS' => 'Palestinian Territory', 'PW' => 'Palau', 'PT' => 'Portugal', 'SJ' => 'Svalbard and Jan Mayen', 'PY' => 'Paraguay', 'IQ' => 'Iraq', 'PA' => 'Panama', 'PF' => 'French Polynesia', 'PG' => 'Papua New Guinea', 'PE' => 'Peru', 'PK' => 'Pakistan', 'PH' => 'Philippines', 'PN' => 'Pitcairn', 'PL' => 'Poland', 'PM' => 'Saint Pierre and Miquelon', 'ZM' => 'Zambia', 'EH' => 'Western Sahara', 'EE' => 'Estonia', 'EG' => 'Egypt', 'ZA' => 'South Africa', 'EC' => 'Ecuador', 'IT' => 'Italy', 'VN' => 'Vietnam', 'SB' => 'Solomon Islands', 'ET' => 'Ethiopia', 'SO' => 'Somalia', 'ZW' => 'Zimbabwe', 'SA' => 'Saudi Arabia', 'ES' => 'Spain', 'ER' => 'Eritrea', 'ME' => 'Montenegro', 'MD' => 'Moldova', 'MG' => 'Madagascar', 'MF' => 'Saint Martin', 'MA' => 'Morocco', 'MC' => 'Monaco', 'UZ' => 'Uzbekistan', 'MM' => 'Myanmar', 'ML' => 'Mali', 'MO' => 'Macao', 'MN' => 'Mongolia', 'MH' => 'Marshall Islands', 'MK' => 'Macedonia', 'MU' => 'Mauritius', 'MT' => 'Malta', 'MW' => 'Malawi', 'MV' => 'Maldives', 'MQ' => 'Martinique', 'MP' => 'Northern Mariana Islands', 'MS' => 'Montserrat', 'MR' => 'Mauritania', 'IM' => 'Isle of Man', 'UG' => 'Uganda', 'TZ' => 'Tanzania', 'MY' => 'Malaysia', 'MX' => 'Mexico', 'IL' => 'Israel', 'FR' => 'France', 'IO' => 'British Indian Ocean Territory', 'SH' => 'Saint Helena', 'FI' => 'Finland', 'FJ' => 'Fiji', 'FK' => 'Falkland Islands', 'FM' => 'Micronesia', 'FO' => 'Faroe Islands', 'NI' => 'Nicaragua', 'NL' => 'Netherlands', 'NO' => 'Norway', 'NA' => 'Namibia', 'VU' => 'Vanuatu', 'NC' => 'New Caledonia', 'NE' => 'Niger', 'NF' => 'Norfolk Island', 'NG' => 'Nigeria', 'NZ' => 'New Zealand', 'NP' => 'Nepal', 'NR' => 'Nauru', 'NU' => 'Niue', 'CK' => 'Cook Islands', 'CI' => 'Ivory Coast', 'CH' => 'Switzerland', 'CO' => 'Colombia', 'CN' => 'China', 'CM' => 'Cameroon', 'CL' => 'Chile', 'CC' => 'Cocos Islands', 'CA' => 'Canada', 'CG' => 'Republic of the Congo', 'CF' => 'Central African Republic', 'CD' => 'Democratic Republic of the Congo', 'CZ' => 'Czech Republic', 'CY' => 'Cyprus', 'CX' => 'Christmas Island', 'CR' => 'Costa Rica', 'CW' => 'Curacao', 'CV' => 'Cape Verde', 'CU' => 'Cuba', 'SZ' => 'Swaziland', 'SY' => 'Syria', 'SX' => 'Sint Maarten', 'KG' => 'Kyrgyzstan', 'KE' => 'Kenya', 'SS' => 'South Sudan', 'SR' => 'Suriname', 'KI' => 'Kiribati', 'KH' => 'Cambodia', 'KN' => 'Saint Kitts and Nevis', 'KM' => 'Comoros', 'ST' => 'Sao Tome and Principe', 'SK' => 'Slovakia', 'KR' => 'South Korea', 'SI' => 'Slovenia', 'KP' => 'North Korea', 'KW' => 'Kuwait', 'SN' => 'Senegal', 'SM' => 'San Marino', 'SL' => 'Sierra Leone', 'SC' => 'Seychelles', 'KZ' => 'Kazakhstan', 'KY' => 'Cayman Islands', 'SG' => 'Singapore', 'SE' => 'Sweden', 'SD' => 'Sudan', 'DO' => 'Dominican Republic', 'DM' => 'Dominica', 'DJ' => 'Djibouti', 'DK' => 'Denmark', 'VG' => 'British Virgin Islands', 'DE' => 'Germany', 'YE' => 'Yemen', 'DZ' => 'Algeria', 'US' => 'United States', 'UY' => 'Uruguay', 'YT' => 'Mayotte', 'UM' => 'United States Minor Outlying Islands', 'LB' => 'Lebanon', 'LC' => 'Saint Lucia', 'LA' => 'Laos', 'TV' => 'Tuvalu', 'TW' => 'Taiwan', 'TT' => 'Trinidad and Tobago', 'TR' => 'Turkey', 'LK' => 'Sri Lanka', 'LI' => 'Liechtenstein', 'LV' => 'Latvia', 'TO' => 'Tonga', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'LR' => 'Liberia', 'LS' => 'Lesotho', 'TH' => 'Thailand', 'TF' => 'French Southern Territories', 'TG' => 'Togo', 'TD' => 'Chad', 'TC' => 'Turks and Caicos Islands', 'LY' => 'Libya', 'VA' => 'Vatican', 'VC' => 'Saint Vincent and the Grenadines', 'AE' => 'United Arab Emirates', 'AD' => 'Andorra', 'AG' => 'Antigua and Barbuda', 'AF' => 'Afghanistan', 'AI' => 'Anguilla', 'VI' => 'U.S. Virgin Islands', 'IS' => 'Iceland', 'IR' => 'Iran', 'AM' => 'Armenia', 'AL' => 'Albania', 'AO' => 'Angola', 'AQ' => 'Antarctica', 'AS' => 'American Samoa', 'AR' => 'Argentina', 'AU' => 'Australia', 'AT' => 'Austria', 'AW' => 'Aruba', 'IN' => 'India', 'AX' => 'Aland Islands', 'AZ' => 'Azerbaijan', 'IE' => 'Ireland', 'ID' => 'Indonesia', 'UA' => 'Ukraine', 'QA' => 'Qatar', 'MZ' => 'Mozambique', 'XK' => 'Kosovo'];

	/**
	 * Возвращает распечатку переденных данных
	 * Полезна при отладке
	 *
	 * @param mixed $data
	 * @return string
	 */
	public function dump($data) {
		return \Zend_Debug::dump($data, null, false);
	}
	
	/**
	 * Generates 404 Not Found error
	 */
	public function notFound() {
		throw new \Yaf_Exception_LoadFailed('Not found', YAF_ERR_NOTFOUND_MODULE);
	}

	/**
	 * @param UTCDateTime $date
	 * @return string
	 */
	public function getDatePeriod(UTCDateTime $date) {
		$diff = time() - $date->toDateTime()->getTimestamp();
		if ($diff <= 60) {
			return $diff . ($diff == 1 ? ' second':' seconds') . ' ago';
		} else if ($diff <= 3600) {
			$min = round($diff / 60, 0);
			return $min . ($min == 1 ? ' minute ':' minutes') .' ago';
		} else if ($diff <= 86400) {
			$hours = round($diff / 3600, 0);
			return $hours . ($hours == 1 ? ' hour':' hours') . ' ago';
		} else if ($diff <= 2592000) {
			$days = round($diff / 86400, 0);
			return $days . ($days == 1 ? ' day':' days') . ' ago';
		} else {
			return date('d.m.Y', $date->toDateTime()->getTimestamp());
		}
	}

	public function getCountryCode($entry) {
		if (!isset($entry['additional']['country'])) {
			return false;
		}

		$codes = [
			'bd' => 'bangladesh',
			'be' => 'belgium',
			'bf' => 'burkina faso',
			'bg' => 'bulgaria',
			'ba' => 'bosnia and herzegovina',
			'bb' => 'barbados',
			'wf' => 'wallis and futuna',
			'bl' => 'saint barthelemy',
			'bm' => 'bermuda',
			'bn' => 'brunei',
			'bo' => 'bolivia',
			'bh' => 'bahrain',
			'bi' => 'burundi',
			'bj' => 'benin',
			'bt' => 'bhutan',
			'jm' => 'jamaica',
			'bv' => 'bouvet island',
			'bw' => 'botswana',
			'ws' => 'samoa',
			'bq' => 'bonaire, saint eustatius and saba',
			'br' => 'brazil',
			'bs' => 'bahamas',
			'je' => 'jersey',
			'by' => 'belarus',
			'bz' => 'belize',
			'ru' => 'russia',
			'rw' => 'rwanda',
			'rs' => 'serbia',
			'tl' => 'east timor',
			're' => 'reunion',
			'tm' => 'turkmenistan',
			'tj' => 'tajikistan',
			'ro' => 'romania',
			'tk' => 'tokelau',
			'gw' => 'guinea-bissau',
			'gu' => 'guam',
			'gt' => 'guatemala',
			'gs' => 'south georgia and the south sandwich islands',
			'gr' => 'greece',
			'gq' => 'equatorial guinea',
			'gp' => 'guadeloupe',
			'jp' => 'japan',
			'gy' => 'guyana',
			'gg' => 'guernsey',
			'gf' => 'french guiana',
			'ge' => 'georgia',
			'gd' => 'grenada',
			'gb' => 'united kingdom',
			'ga' => 'gabon',
			'sv' => 'el salvador',
			'gn' => 'guinea',
			'gm' => 'gambia',
			'gl' => 'greenland',
			'gi' => 'gibraltar',
			'gh' => 'ghana',
			'om' => 'oman',
			'tn' => 'tunisia',
			'jo' => 'jordan',
			'hr' => 'croatia',
			'ht' => 'haiti',
			'hu' => 'hungary',
			'hk' => 'hong kong',
			'hn' => 'honduras',
			'hm' => 'heard island and mcdonald islands',
			've' => 'venezuela',
			'pr' => 'puerto rico',
			'ps' => 'palestinian territory',
			'pw' => 'palau',
			'pt' => 'portugal',
			'sj' => 'svalbard and jan mayen',
			'py' => 'paraguay',
			'iq' => 'iraq',
			'pa' => 'panama',
			'pf' => 'french polynesia',
			'pg' => 'papua new guinea',
			'pe' => 'peru',
			'pk' => 'pakistan',
			'ph' => 'philippines',
			'pn' => 'pitcairn',
			'pl' => 'poland',
			'pm' => 'saint pierre and miquelon',
			'zm' => 'zambia',
			'eh' => 'western sahara',
			'ee' => 'estonia',
			'eg' => 'egypt',
			'za' => 'south africa',
			'ec' => 'ecuador',
			'it' => 'italy',
			'vn' => 'vietnam',
			'sb' => 'solomon islands',
			'et' => 'ethiopia',
			'so' => 'somalia',
			'zw' => 'zimbabwe',
			'sa' => 'saudi arabia',
			'es' => 'spain',
			'er' => 'eritrea',
			'me' => 'montenegro',
			'md' => 'moldova',
			'mg' => 'madagascar',
			'mf' => 'saint martin',
			'ma' => 'morocco',
			'mc' => 'monaco',
			'uz' => 'uzbekistan',
			'mm' => 'myanmar',
			'ml' => 'mali',
			'mo' => 'macao',
			'mn' => 'mongolia',
			'mh' => 'marshall islands',
			'mk' => 'macedonia',
			'mu' => 'mauritius',
			'mt' => 'malta',
			'mw' => 'malawi',
			'mv' => 'maldives',
			'mq' => 'martinique',
			'mp' => 'northern mariana islands',
			'ms' => 'montserrat',
			'mr' => 'mauritania',
			'im' => 'isle of man',
			'ug' => 'uganda',
			'tz' => 'tanzania',
			'my' => 'malaysia',
			'mx' => 'mexico',
			'il' => 'israel',
			'fr' => 'france',
			'io' => 'british indian ocean territory',
			'sh' => 'saint helena',
			'fi' => 'finland',
			'fj' => 'fiji',
			'fk' => 'falkland islands',
			'fm' => 'micronesia',
			'fo' => 'faroe islands',
			'ni' => 'nicaragua',
			'nl' => 'netherlands',
			'no' => 'norway',
			'na' => 'namibia',
			'vu' => 'vanuatu',
			'nc' => 'new caledonia',
			'ne' => 'niger',
			'nf' => 'norfolk island',
			'ng' => 'nigeria',
			'nz' => 'new zealand',
			'np' => 'nepal',
			'nr' => 'nauru',
			'nu' => 'niue',
			'ck' => 'cook islands',
			'xk' => 'kosovo',
			'ci' => 'ivory coast',
			'ch' => 'switzerland',
			'co' => 'colombia',
			'cn' => 'china',
			'cm' => 'cameroon',
			'cl' => 'chile',
			'cc' => 'cocos islands',
			'ca' => 'canada',
			'cg' => 'republic of the congo',
			'cf' => 'central african republic',
			'cd' => 'democratic republic of the congo',
			'cz' => 'czech republic',
			'cy' => 'cyprus',
			'cx' => 'christmas island',
			'cr' => 'costa rica',
			'cw' => 'curacao',
			'cv' => 'cape verde',
			'cu' => 'cuba',
			'sz' => 'swaziland',
			'sy' => 'syria',
			'sx' => 'sint maarten',
			'kg' => 'kyrgyzstan',
			'ke' => 'kenya',
			'ss' => 'south sudan',
			'sr' => 'suriname',
			'ki' => 'kiribati',
			'kh' => 'cambodia',
			'kn' => 'saint kitts and nevis',
			'km' => 'comoros',
			'st' => 'sao tome and principe',
			'sk' => 'slovakia',
			'kr' => 'south korea',
			'si' => 'slovenia',
			'kp' => 'north korea',
			'kw' => 'kuwait',
			'sn' => 'senegal',
			'sm' => 'san marino',
			'sl' => 'sierra leone',
			'sc' => 'seychelles',
			'kz' => 'kazakhstan',
			'ky' => 'cayman islands',
			'sg' => 'singapore',
			'se' => 'sweden',
			'sd' => 'sudan',
			'do' => 'dominican republic',
			'dm' => 'dominica',
			'dj' => 'djibouti',
			'dk' => 'denmark',
			'vg' => 'british virgin islands',
			'de' => 'germany',
			'ye' => 'yemen',
			'dz' => 'algeria',
			'us' => 'united states',
			'uy' => 'uruguay',
			'yt' => 'mayotte',
			'um' => 'united states minor outlying islands',
			'lb' => 'lebanon',
			'lc' => 'saint lucia',
			'la' => 'laos',
			'tv' => 'tuvalu',
			'tw' => 'taiwan',
			'tt' => 'trinidad and tobago',
			'tr' => 'turkey',
			'lk' => 'sri lanka',
			'li' => 'liechtenstein',
			'lv' => 'latvia',
			'to' => 'tonga',
			'lt' => 'lithuania',
			'lu' => 'luxembourg',
			'lr' => 'liberia',
			'ls' => 'lesotho',
			'th' => 'thailand',
			'tf' => 'french southern territories',
			'tg' => 'togo',
			'td' => 'chad',
			'tc' => 'turks and caicos islands',
			'ly' => 'libya',
			'va' => 'vatican',
			'vc' => 'saint vincent and the grenadines',
			'ae' => 'united arab emirates',
			'ad' => 'andorra',
			'ag' => 'antigua and barbuda',
			'af' => 'afghanistan',
			'ai' => 'anguilla',
			'vi' => 'u.s. virgin islands',
			'is' => 'iceland',
			'ir' => 'iran',
			'am' => 'armenia',
			'al' => 'albania',
			'ao' => 'angola',
			'aq' => 'antarctica',
			'as' => 'american samoa',
			'ar' => 'argentina',
			'au' => 'australia',
			'at' => 'austria',
			'aw' => 'aruba',
			'in' => 'india',
			'ax' => 'aland islands',
			'az' => 'azerbaijan',
			'ie' => 'ireland',
			'id' => 'indonesia',
			'ua' => 'ukraine',
			'qa' => 'qatar',
			'mz' => 'mozambique'
		];

		return array_search(strtolower($entry['additional']['country']), $codes);
	}

	public function isFullAvailable($scene, $user) {
	    if ($scene['_id'] == 21) {
	        return true;
        }

		if (empty($user)) {
			return false;
		}

		if ((($user['subscription']['expire']->toDateTime()->getTimestamp() > time() ||
            $user['subscription']['expire']->toDateTime()->getTimestamp() === 1) &&
            $scene['full_video']['ready'])) {
		    
			return true;
		}

		return false;
	}

    public function getSceneFormat($entry) {
        if ($entry['format'] == 'mono') {
            $format = 'MONO_360';
        } else {
            if ($entry['view_angle'] > 180) {
                $format = 'STEREO_360';
            } else {
                $format = 'STEREO_180';
            }

            if ($entry['format'] == 'sbs2l') {
                $format .= '_LR';
            } elseif ($entry['format'] == 'ab2l') {
                $format .= '_TB';
            }
        }

        return $format;
    }

    public function getVideoLength($entry, $unit = true) {
        if (isset($entry['full_length'])) {
            if ($entry['full_length'] < 3600) {
                $full_length = (int)gmdate('i', $entry['full_length']);
                if ($unit) {
                    $full_length .= ' min.';
                }
            } else {
                $full_length = gmdate('H:i:s', $entry['full_length']);
            }

            return $full_length;
        }

        return false;
    }

    public function getMonthsCount($subscription_type) {
        switch ($subscription_type['days']) {
            case 30:
                $count = 1;
                break;
            case 90:
                $count = 3;
                break;
            case 365:
                $count = 12;
                break;
            default:
                $count = null;
        }

        return $count;
    }

    public function getMonthPrice($subscription_type) {
        $months = $this->getMonthsCount($subscription_type);

        return explode('.', strval(number_format($subscription_type['price'] / $months, 2)));
    }

    public function getDiscount($bigger_price, $subscription_type) {
        $months = $this->getMonthsCount($subscription_type);

        return 100 - round(100 * $subscription_type['price'] / $months / $bigger_price);
    }

    public function fileSize($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public function getRatingPercent($rating) {
	    if ($rating == floor($rating)) {
            return 100;
        } else {
            return (($rating - floor($rating)) * 100);
        }
    }

    public function sceneTitle($entry) {
        if (isset($entry['heading']) && !empty($entry['heading'])) {
            return $entry['heading'];
        }

        return $entry['title'];
    }

    public function getFileCodec($file, $full_movie = false) {
        if ($full_movie) {
            $entry = \Dao\Mongodb\Listing\FullVrPorn\Scenes\Files::getInstance()->getEntry($file['_id']);
        } else {
            $entry = \Dao\Mongodb\Listing\Paysites\StasyQVR\Scenes\Files::getInstance()->getEntry($file['_id']);
        }

        if (isset($entry['vcodec']) && !empty($entry['vcodec'])) {
            if ($entry['vcodec'] == 'h264') {
                return 'h.264';
            } elseif ($entry['vcodec'] == 'hevc') {
                return 'h.265';
            } else {
                return $entry['vcodec'];
            }
        }

        return null;
    }

    public function isNotPS4ProWidth($entry, $height) {
        if (isset($entry['video']['orig_res']) &&
            isset($entry['video']['orig_res']['h']) &&
            isset($entry['video']['orig_res']['w']) &&
            !empty($entry['video']['orig_res']['h']) &&
            !empty($entry['video']['orig_res']['w'])) {

            $width = ($entry['video']['orig_res']['w'] * $height) / $entry['video']['orig_res']['h'];

            if ($width > 2880 || $height > 2160) {
                return true;
            }

            return false;
        }

        return false;
    }

    public function getVideoResolution($scene, $height) {
	    $width = (!empty($scene['video']['orig_res']))
            ? ($scene['video']['orig_res']['w'] * $height) / $scene['video']['orig_res']['h']
            : 0;

        return $width . 'x' . $height;
    }

    public function getStreamingFiles(Entry $scene, $full_video = false) : array {

	    if ($full_video) {
	        $video = $scene['full_video'];
        } else {
	        $video = $scene['video'];
        }

        $list = \Dao\Service\Transcoder\SLRAppStreaming::getStreamingFiles($scene['_id'], $video);

        foreach ($list as &$val) {
            krsort($val);
        }

        return $list;
    }

}
