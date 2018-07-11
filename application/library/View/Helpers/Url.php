<?php
/**
 * Simple URL generation class
 *
 */

namespace View\Helpers;

use Dao\Mongodb\Listing\Paysites\StasyQVR\Scenes;
use Shelby\Dao\Builder\Search;
use Shelby\Dao\Result\Mongodb\Entry;

class Url extends \Shelby\View\Helpers\Url\AbstractClass {

	// Safety protection just in case
	private $is_subscription_valid = false;

	/**
	 * CDN configuration
	 *
	 * @var array
	 */
	private static $configCdn;

	public static function setCdnConfig(array $config) {
		self::$configCdn = $config;
	}

	public function setSubscriptionValid($valid) {
		$this->is_subscription_valid = (bool)$valid;
	}

    public function isSubscriptionValid() {
        return $this->is_subscription_valid;
    }

    protected function _prepareStr($str) {
        // @TODO combine expressions
        $remove_quotes = str_replace(["'", "’", "..."], "", $str);
        $remove_spec_symbols = preg_replace('/[^A-Z0-9a-z-_]+/', ' ', $remove_quotes);
        $str = str_replace(' ', '_', strtolower(trim($remove_spec_symbols)));

        return $str;
    }

    /**
     * Checking request. If request does not match with correct url - need redirect to correct url
     * @param $scene Entry
     * @param $request string
     * @return bool
     */
    public function sceneRedirect($scene, $uri) {
        $elementsUrl = preg_split('{[\s/\?]}', $uri);
        if ($elementsUrl[4] == $this->_prepareStr($scene['_id'] . '-' . trim($scene['title']))) {
            return false;
        }

        return true;
    }

    public function scene($entry) {
        $title = $this->_prepareStr($entry['title']);
        return $this->c('virtualreality')->a('scene')->p('id', $entry['_id'] . '-' . rawurlencode($title));
    }

	public function join() {
		return $this->c('user')->a('join');
	}

    public function video($entry, $resolution, $encoding) {
	    $file = '/videos_app/' . $encoding . '/' . $entry['_id'] . '_' . $resolution . 'p.mp4';
        return $this->s3UrlSign($file, true, $_SERVER['REMOTE_ADDR']);
    }

    public function fullVideo($entry, $resolution, $encoding) {
        $file = '/full_videos_app/' . $encoding . '/' . $entry['_id'] . '_' . $resolution . 'p.mp4';
        return $this->s3urlSign($file, true, $_SERVER['REMOTE_ADDR']);
    }

	public function modelProfile($model) {
		return $this->c('pornstars')->a('model')->p('id', $model['_id'] . '-' . str_replace(' ', '-', $model['name']));
	}

	public function sorting($type) {
		return $this->add()->c('virtualreality')->a('pornstars')->p('sort', $type)->del('page');
	}

	public function letter($letter) {
		return $this->add()->c('virtualreality')->a('pornstars')->p('letter', $letter)->del('page');
	}

    /**
     * Get signed URI + S3 private file temporary signature.
     *
     * @param string $file
     * @param bool $private
     * @param string $ip
     * @param string $filename_custom
     * @return string
     */
    private function s3urlSign($file, $private = false, $ip = null, $filename_custom = null) {
        $bucket = \Bootstrap::$bucket;

        if ($private || !empty($filename_custom)) {
            $expires = time() + 3600;
            $query = ['Expires' => $expires];
            $file_sig_query = '';
            if (!empty($filename_custom)) {
                $query['response-content-disposition'] = 'attachment; filename="' . $filename_custom . '"';
                $file_sig_query .= '?response-content-disposition=' . $query['response-content-disposition'];
            }
            $s3_access = \Dao\Files\AbstractClass::getCloudOptions();
            $s3_signature = \Dao\Service\S3::signURL($bucket, $file . $file_sig_query, $s3_access['SECRET_KEY'], $expires);

            $query['Signature'] = $s3_signature;
            $query['AWSAccessKeyId'] = $s3_access['ACCESS_KEY'];
        }

        if (!empty($ip)) {
            $query['ip'] = $ip;
        }

        if (self::$configCdn['streaming']['enable']) {
            $host = 'https://cdn-vr.stasyqvr.com';
        } else {
            $host = 'https://s3.stasyqvr.com';
        }

        if (!empty($query)) {
            $path = $host . $file . '?' . http_build_query($query);
        } else {
            $path = $host . $file;
        }
        
        return $path;
    }

    private function generateFilename($entry, $resolution = 'original') {
        $filename = 'StasyQVR_' . $entry['title'] . '_' . $resolution . '_' . $entry['_id'] . '_';
        switch ($entry['format']) {
            case 'sbs2l':
                $filename .= 'LR';
                break;
            case 'sbs2r':
                $filename .= 'RL';
                break;
            case 'ab2l':
                $filename .= 'TB';
                break;
            case 'ab2r':
                $filename .= 'BT';
                break;
            case 'mono':
            default:
                $filename .= 'MONO';
        }
        if ($entry['view_angle'] <= 200) {
            $filename .= '_180';
        } else {
            $filename .= '_360';
        }

        return $filename . '.mp4';
    }

    /**
     * CORS fallback url for delight player
     *
     * @param $entry
     * @param $trigger
     * @return string
     */
    public function fallbackUrl($entry, $trigger) {
        $mixed_helper = new \View\Helpers\Mixed();

        $link = '/webvr/parse_decode.html?';
        $format = $mixed_helper->getSceneFormat($entry);
        $poster = $this->sceneCover($entry);

        $link .= 'format=' . $format;
        $link .= '&poster=' . urlencode($poster);

        switch ($trigger) {
            case 'fullvideo':
                foreach ($entry['full_video']['sizes'] as $video_size) {
                    $link_h264 = $this->fullVideo($entry, $video_size, 'h264');
                    $link .= '&video_link=' . urlencode($link_h264) . '&quality=' . $video_size . 'p';
                }
                break;
            case 'video':
            default:
                foreach ($entry['video']['sizes'] as $video_size) {
                    $link_h264 = $this->video($entry, $video_size, 'h264');
                    $link .= '&video_link=' . urlencode($link_h264) . '&quality=' . $video_size . 'p';
                }
                break;
        }

        return $this->buildStreamingUrl($link);
    }

    public function sceneCover($scene) {
        return $this->s3urlSign('/images/' . $scene['_id'] .  '/' . $scene['cover_id'] . '_cover.jpg');
    }

    public function sceneCoverThumbnail($scene) {
        return $this->s3urlSign('/images/' . $scene['_id'] .  '/' . $scene['cover_id'] . '_1076x662.jpg');
    }

    public function sceneImage($scene, $image) {
        return $this->s3urlSign('/images/' . $scene['_id'] .  '/' . $image['_id'] . '.jpg');
    }

    public function sceneImageThumbnail($scene, $image) {
        return $this->s3urlSign('/images/' . $scene['_id'] .  '/' . $image['_id'] . '_200x133.jpg');
    }

    public function sceneVideoThumbnail($entry, $format = 'mp4') {
        return $this->s3urlSign('/preview/14x1/' . $entry['_id'] . '_200p.' . $format);
    }

    public function modelOriginal(int $model_id, int $photo_id) : string {
        return $this->s3urlSign('/models/' . $model_id . '/' . $photo_id . '_original.jpg');
    }

    public function modelPhotoPreview(int $model_id, int $photo_id) : string {
        return $this->s3urlSign('/models/' . $model_id . '/' . $photo_id . '_208x390.jpg');
    }

    public function downloadStreamingFile($entry, $resolution, $codec = 'h264') {
        $filename = $this->generateFilename($entry, $resolution);

        return $this->s3urlSign("/videos_app/$codec/" . $entry['_id'] . '_' . $resolution . 'p.mp4', true, $_SERVER['REMOTE_ADDR'], $filename);
    }

    public function downloadStreamingFullFile($entry, $resolution, $codec = 'h264') {
        $filename = $this->generateFilename($entry, $resolution);

        return $this->s3UrlSign('/full_videos_app/' . $codec . '/' . $entry['_id'] . '_' . $resolution . 'p.mp4', true, $_SERVER['REMOTE_ADDR'], $filename);
    }

    public function fileDownload($entry, $file) {
        $filename = $this->generateFilename($entry);

        return $this->s3urlSign('/files/' . $entry['_id'] . '/' . rawurlencode($file['filename']), false, (empty($_SERVER['REMOTE_ADDR']) ? null : $_SERVER['REMOTE_ADDR']), $filename);
    }

    private function buildAssetUrl(string $path) : string {
        if (self::$configCdn['assets']['enable'] == true) {
            return (self::$configCdn['assets']['https'] == true ? 'https':'http') . '://' . self::$configCdn['assets']['domain'] . $path;
        } else {
            return 'https://rest.s3for.me/' . \Bootstrap::$bucket . $path;
        }
    }

    private function buildStreamingUrl(string $path) : string {
        if (self::$configCdn['streaming']['enable'] == true) {
            return (self::$configCdn['streaming']['https'] == true ? 'https':'http') . '://' . self::$configCdn['streaming']['domain'] . $path;
        } else {
            return 'https://rest.s3for.me/' . \Bootstrap::$bucket . $path;
        }
    }

}
