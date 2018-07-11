<?php

namespace Dao\Files;

class Images extends AbstractClass {

	const TYPE_OUT_JPG = 'jpg';
	const TYPE_OUT_PNG = 'png';
	const TYPE_OUT_GIF = 'gif';
	const TYPE_OUT_RESOURCE = 'resource';

	/**
	 * Создает уменьшенное изображение из оригинального. Уменьшенное изображение имеет тип jpeg
	 * В первом параметре передается путь к оригинальному изображению
	 * Во втором путь, куда сохранить уменьшенное
	 * В третьем и четвертом - ширина и высота уменьшенной копии
	 * Третим параметром передается набор дополнительных опций создаваемого изображения:
	 *  - image_type : mime-тип изображения (image/jpeg, image/jpg, image/gif, image/png), если не указан, производится попытка угадать по расширению
	 *  - image_quality : качество уменьшенной копии, если не установлено = 90
	 *  - type_out : возвращаемый тип @see self::TYPE_OUT_*
	 *
	 * Функция позвращает true в случае успеха и false в случае неудачи
	 *
	 * @param string $file_fp
	 * @param string $save_as
	 * @param int $width
	 * @param int $height
	 * @param array $extraParams
	 * @return boolean|Resource
	 */
	public function thumbnail($file_fp, $save_as, $width, $height, Array $extraParams = array()) {
		$im = $this->_getImageResource($file_fp, $extraParams);

		if (!$im) {
			return false;
		}

		//Если не установлено качество, устанавливаем по-умолчанию 90
		if (!isset($extraParams['image_quality'])) {
			$extraParams['image_quality'] = 90;
		}
		if (!isset($extraParams['type_out'])) {
			$extraParams['type_out'] = self::TYPE_OUT_JPG;
		}

		$w = imagesx($im);
		$h = imagesy($im);

		$kw = $width / $w;
		$kh = $height / $h;

		if ($kw > $kh) {
			$ks = $kh;
		} else {
			$ks = $kw;
		}

		if ($kw > $kh) {
			$sh = $height;
			$sw = floor($w * $ks);
		} else if ($kw < $kh) {
			$sw = $width;
			$sh = floor($h * $ks);
		} else {
			$sw = $width;
			$sh = $height;
		}

		$ns = imagecreatetruecolor($sw, $sh);
		imagecopyresampled($ns, $im, 0, 0, 0, 0, $sw, $sh, $w, $h);

		switch ($extraParams['type_out']) {
			case self::TYPE_OUT_PNG:
				imagepng($ns, $save_as);
				break;
			case self::TYPE_OUT_GIF:
				imagegif($ns, $save_as);
				break;
			case self::TYPE_OUT_RESOURCE:
				return $ns;
				break;
			default:
				imagejpeg($ns, $save_as, $extraParams['image_quality']);
		}

		imagedestroy($ns);

		return true;
	}

	public function thumbnailExact($file_fp, $save_as, $width, $height, Array $extraParams = array()) {
		$im = $this->_getImageResource($file_fp, $extraParams);

		if (!$im) {
			return false;
		}

		//Если не установлено качество, устанавливаем по-умолчанию 90
		if (!isset($extraParams['image_quality'])) {
			$extraParams['image_quality'] = 90;
		}
		if (!isset($extraParams['type_out'])) {
			$extraParams['type_out'] = self::TYPE_OUT_JPG;
		}

		$w = imagesx($im);
		$h = imagesy($im);

		$kk_src = $w/$h;
		$kk = $width/$height;

		$src_x = 0;
		$src_y = 0;

		if ($kk == $kk_src) {
			// Simple scaling, nothing to do here
		} elseif ($kk == 1) {
			// Scale to boxed image
			if ($kk_src < 1) {
				$src_y = round(($h - $h*$kk_src)/2);
				$h = floor($w/$kk);
			} elseif ($kk_src > 1) {
				$src_x = round(($w - $w/$kk_src)/2);
				$w = floor($h/$kk);
			}
		} elseif ($kk < 1) {
			if ($kk_src < 1) {
				// Horizontal to horizontal image
				$src_y = round(($h - $h*$kk_src/$kk)/2);
				$h = floor($w/$kk);
			} elseif ($kk_src > 1) {
				// Horizontal to vertical image
				$src_x = round(($w - ($w/$kk_src)*$kk)/2);
				$w = floor($h*$kk);
			} elseif ($kk_src == 1) {
				$src_x = round(($w - $w*$kk)/2);
				$w = floor($h*$kk);
			}
		} elseif ($kk > 1) {
			if ($kk_src < 1) {
				// Horizontal to vertical image
				$src_y = round(($h - $h*$kk_src/$kk)/2);
				$h = floor($w/$kk);
			} elseif ($kk_src > 1) {
				$src_x = round(($w - ($w/$kk_src)*$kk)/2);
				$w = floor($h*$kk);
			} elseif ($kk_src == 1) {
				$src_y = round(($h - $h/$kk)/2);
				$h = floor($w/$kk);
			}
		}

		$ns = imagecreatetruecolor($width, $height);
		imagecopyresampled($ns, $im, 0, 0, $src_x, $src_y, $width, $height, $w, $h);

		switch ($extraParams['type_out']) {
			case self::TYPE_OUT_PNG:
				imagepng($ns, $save_as);
				break;
			case self::TYPE_OUT_GIF:
				imagegif($ns, $save_as);
				break;
			case self::TYPE_OUT_RESOURCE:
				return $ns;
				break;
			default:
				imagejpeg($ns, $save_as, $extraParams['image_quality']);
		}

		imagedestroy($ns);

		return true;
	}

	/**
	 * Возвращет информацию об изображении, путь к которому указан первым параметром
	 * Возвращает массив, содержащий элементы:
	 * width - ширина
	 * height - высота
	 * mime - MIME-тип изображения
	 *
	 * @param string $filename
	 * @return array|bool
	 */
	public function getImageInfo($filename) {
		$size = getimagesize($filename);

		if ($size === false) {
			return false;
		}

		$result = array('width' => $size[0], 'height' => $size[1], 'mime' => $size['mime']);

		return $result;
	}

	private function _getImageResource($file_fp, Array $extraParams = array()) {
		if (strlen($file_fp) > 255) {
			// It's not a filename, it's an image contents

			return imagecreatefromstring($file_fp);
		}

		//Пытаемся угадать mime-тип если он не указан
		if (!isset($extraParams['image_type'])) {
			$file_info = pathinfo($file_fp, PATHINFO_EXTENSION);
			$extraParams['image_type'] = $this->getMimeByExtension($file_info);
		}

		if ((is_readable($file_fp) && is_file($file_fp) || substr($file_fp, 0, 4) == 'http')) {
			switch ($extraParams['image_type']) {
				case 'image/jpeg':
				case 'image/pjpeg': // IE :-(
				case 'image/jpg':
					$im = @imagecreatefromjpeg($file_fp);
					break;
				case 'image/gif':
					$im = @imagecreatefromgif($file_fp);
					break;
				case 'image/png':
					$im = @imagecreatefrompng($file_fp);
					break;
				default:
					return false;
			}
		} else {
			return false;
		}

		return $im;
	}

	/**
	 * Возвращает mime-тип файла по расширению файла
	 * Если не найдено, возвращает false
	 *
	 * @param string $ext
	 * @return string|boolean
	 */
	public function getMimeByExtension($ext) {
		$ext = strtolower($ext);

		switch ($ext) {
			case 'jpg':
			case 'jpeg':
				return 'image/jpeg';
				break;
			case 'gif':
				return 'image/gif';
				break;
			case 'png':
				return 'image/png';
				break;
			default:
				return false;
		}
	}

	/**
	 * Compress JPEG and PNG images.
	 * PNG compression via https://pngquant.org/
	 * Lossless JPEG compression via https://github.com/mozilla/mozjpeg
	 *
	 * @param string $filepath Full path to the file on server.
	 *
	 * @return string with the file data
	 * if the file successfully compressed
	 * or has another extension but exist;
	 * False otherwise.
	 */
	public function compress($filepath) {
		if (!file_exists($filepath)) {
			return false;
		}

		switch (exif_imagetype($filepath)) {
			case IMAGETYPE_PNG:
				$image = $this->compressionPNG($filepath);
				break;
			case IMAGETYPE_JPEG:
				$image = $this->compressionJPG($filepath);
				break;
			default:
				$image = null;
		}

		if (empty($image)) {
			$image = @file_get_contents($filepath);
		}

		return $image;
	}

	private function compressionPNG($filepath) {
		$compressed = shell_exec('pngquant --speed 1 --quality=55-75 - < ' . escapeshellarg($filepath));
		if ($compressed) {
			return $compressed;
		}

		return false;
	}

	private function compressionJPG($filepath) {
		$compressed = shell_exec('/opt/mozjpeg/bin/jpegtran -copy none -optimize -progressive ' . escapeshellarg($filepath));
		if ($compressed) {
			return $compressed;
		}

		return false;
	}

    function resizeWithoutRatio($image, $w_o = false, $h_o = false) {
        list($w_i, $h_i) = getimagesize($image);

        $fileData = @file_get_contents($image);

        if ($fileData === false) {
            return false;
        }

        $image = imagecreatefromstring($fileData);

        if (($w_o < 0) || ($h_o < 0)) {
            return false;
        }

        if (!$h_o) $h_o = $w_o / ($w_i / $h_i);
        if (!$w_o) $w_o = $h_o / ($h_i / $w_i);
        $ns = imagecreatetruecolor($w_o, $h_o);
        imagecopyresampled($ns, $image, 0, 0, 0, 0, $w_o, $h_o, $w_i, $h_i);

        imagejpeg($ns, null, 90);

        imagedestroy($ns);

        return true;
    }

}
