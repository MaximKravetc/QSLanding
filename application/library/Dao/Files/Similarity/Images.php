<?php
class Dao_Files_Similarity_Images {

	const HIST_MAX_IMG_SIZE = 100;

	/**
	 * Helper function to compute different image parameters
	 *
	 * @param resource $im
	 * @return array|bool
	 */
	public function computeImage($im) {
		$result = array();

		if ($im === false) {
			return false;
		}

		// Average hash
		$result['ahash4'] = $this->getAverageHash($im, 4);
		$result['ahash8'] = $this->getAverageHash($im, 8);
		$result['ahash16'] = $this->getAverageHash($im, 16);

		// Histogram
		$w = imagesx($im);
		$h = imagesy($im);

		if ($w > self::HIST_MAX_IMG_SIZE || $h > self::HIST_MAX_IMG_SIZE) {
			$kw = self::HIST_MAX_IMG_SIZE / $w;
			$kh = self::HIST_MAX_IMG_SIZE / $h;

			if ($kw > $kh) {
				$ks = $kh;
			} else {
				$ks = $kw;
			}

			if ($kw > $kh) {
				$sh = self::HIST_MAX_IMG_SIZE;
				$sw = floor($w * $ks);
			} else if ($kw < $kh) {
				$sw = self::HIST_MAX_IMG_SIZE;
				$sh = floor($h * $ks);
			} else {
				$sw = self::HIST_MAX_IMG_SIZE;
				$sh = self::HIST_MAX_IMG_SIZE;
			}

			$ns = imagecreatetruecolor($sw, $sh);
			imagecopyresized($ns, $im, 0, 0, 0, 0, $sw, $sh, $w, $h);
		} else {
			$ns = $im;
		}

		$result['hist8'] = $this->getHistogram($ns, 8);
		$result['hist'] = $this->getHistogramHash($result['hist8']);

		return $result;
	}

	/**
	 * Calculate a average hash from an image
	 * Size tells how big it should be.
	 * Bits = size ^ 2
	 *
	 * @param resource $im_orig
	 * @param int $size
	 * @return bool|string
	 */
	public function getAverageHash($im_orig, $size) {
		$image_pixels = $size * $size;

		if ($im_orig === false) {
			return false;
		}

		$width = imagesx($im_orig);
		$height = imagesy($im_orig);

		$im = imagecreatetruecolor($size, $size);
		imagecopyresampled($im, $im_orig, 0, 0, 0, 0, $size, $size, $width, $height);

		imagefilter($im, IMG_FILTER_GRAYSCALE);

		$sum_colors = 0;
		for($w = 0; $w < $size; $w++) {
			for($h = 0; $h < $size; $h++) {
				$c = imagecolorat($im, $w, $h);
				$sum_colors += $c;
			}
		}
		$avg_colors = $sum_colors / $image_pixels;

		$hash_dec = '';
		for($w = 0; $w < $size; $w++) {
			for($h = 0; $h < $size; $h++) {
				$c = imagecolorat($im, $h, $w);
				if ($c > $avg_colors) {
					$hash_dec .= '1';
				} else {
					$hash_dec .= '0';
				}
			}
		}

		imagedestroy($im);

		// Convert DEC value to HEX and return it
		return gmp_strval(gmp_init($hash_dec, 2), 16);
	}

	/**
	 * Get image histogram separated by color and buckets
	 *
	 * @param resource $im
	 * @param int $buckets
	 * @return array|bool
	 */
	public function getHistogram($im, $buckets) {
		if ($im === false) {
			return false;
		}

		$width = imagesx($im);
		$height = imagesy($im);

		$hist = array(
			'r' => array(),
			'g' => array(),
			'b' => array()
		);
		for ($i = 0; $i < $buckets; $i++) {
			$hist['r'][$i] = 0;
			$hist['g'][$i] = 0;
			$hist['b'][$i] = 0;
		}

		$div_by = (255 / ($buckets - 1));
		for ($h = 0; $h < $height; $h++) {
			for ($w = 0; $w < $width; $w++) {
				$rgb = imagecolorat($im, $w, $h);

				$r = intval((($rgb >> 16) & 0xFF) / $div_by);
				$g = intval((($rgb >> 8) & 0xFF) / $div_by);
				$b = intval(($rgb & 0xFF) / $div_by);

				$hist['r'][$r]++;
				$hist['g'][$g]++;
				$hist['b'][$b]++;
			}
		}

		// Normalize
		$norm_by = $width * $height;
		foreach ($hist as &$cvs) {
			foreach ($cvs as &$num) {
				$num = intval(($num / $norm_by) * 65535);
			}
		}

		return $hist;
	}

	/**
	 * Compute a 3-bytes histogram HASH from 8-buckets long RGB histogram array
	 *
	 * @param array $hist
	 * @return string
	 */
	public function getHistogramHash(Array $hist) {
		$res = '';
		foreach ($hist as $h) {
			$res = array_reduce($h, function($pv, $nv) {
				// 8191 is the magic number evolved from the fact that we normalize histogram by the factor of 65535
				// @see Dao_Files_Similarity_Images::getHistogram()
				$pv .= ($nv > 8191 ? '1':'0');
				return $pv;
			}, $res);
		}

		// Convert DEC to HEX and return the result
		return base_convert($res, 2, 16);
	}

	/**
	 * Calculate distance between 2 histograms
	 * Generally it will return summarized distance percentage for all 3 channels accumulated
	 * Theoretically it can return up to 300% distance which means opposite histograms
	 *
	 * @param array $hist1
	 * @param array $hist2
	 * @return float
	 */
	public function getHistogramDistance(Array $hist1, Array $hist2) {
		$dist = 0;

		$n = 0;
		foreach ($hist1 as $cl => $values) {
			foreach ($values as $vk => $vv) {
				$diff = abs($vv - $hist2[$cl][$vk]);
				if ($diff > 0) {
					$dist += $diff / 65535;
				}
			}

			$n++;
		}

		// Percentage of similarity, up to 300% for all 3 color channels
		return ($dist / $n) * 100;
	}

	/**
	 * Compute images difference
	 *
	 * @param array $hash1
	 * @param array $hash2
	 * @return float
	 */
	public function computeDifference(Array $hash1, Array $hash2) {
		$ham1 = gmp_init($hash1['ahash8'], 16);
		$ham2 = gmp_init($hash2['ahash8'], 16);
		$tst8 = gmp_hamdist($ham1, $ham2);

		$ham1 = gmp_init($hash1['ahash16'], 16);
		$ham2 = gmp_init($hash2['ahash16'], 16);
		$tst16 = gmp_hamdist($ham1, $ham2);

		$tst_hist8 = $this->getHistogramDistance($hash1['hist8'], $hash2['hist8']);

		return ($tst8*5 + $tst16 + $tst_hist8/1.33)/3;
	}

}