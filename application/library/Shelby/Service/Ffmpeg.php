<?php
/**
 * Class Shelby_Service_Ffmpeg
 * Low level FFMpeg interactions class
 * Doesn't perform any checks, executes everything as is
 */

namespace Shelby\Service;

class Ffmpeg {

	const CONVERT_STATUS_OK = 0;

	const VCODEC_H264 = 'libx264';
	const VCODEC_H265 = 'libx265';
	const ACODEC_AAC = 'aac';

	const VCODEC_VP8 = 'libvpx';
	const VCODEC_VP9 = 'libvpx-vp9';
	const ACODEC_VORBIS = 'libvorbis';

	const VCODEC_COPY = 'copy';
	const ACODEC_COPY = 'copy';

	private $path_ffmpeg = '/usr/bin/ffmpeg';
	private $path_ffprobe = '/usr/bin/ffprobe';

	private $last_command = '';

	/**
	 * Get video information array
	 * Returns empty array on error
	 * Set second parameter to true to get extended information, basic info will be returned otherwise
	 *
	 * @param string $path
	 * @param bool $extended
	 * @return array
	 */
	public function getVideoInfo($path, $extended = false) {
		if (strpos($path, 'http') !== 0 && !is_readable($path)) {
			return array();
		}

		$result = array();
		$out = array();
		$status = 0;
		$run = $this->path_ffprobe . ' -v error -show_format -show_streams -print_format json "' . $path . '"';
		exec($run, $out, $status);

		$out = implode("\n", $out);
		$this->last_command = $run . "\n\n" . $out;

		if ($status != 0) {
			return array();
		}

		$out = json_decode($out, true);

		if ($extended === true) {
			// Extended info
			if (!empty($out['format'])) {
				$result['format'] = $out['format'];
				$result['format']['duration'] = floatval($result['format']['duration']);
				unset($result['format']['filename']);

				if (!empty($out['streams'])) {
					$result['video'] = array();
					$result['audio'] = array();
					foreach ($out['streams'] as $val) {
						if ($val['codec_type'] == 'video') {
							if ($val['avg_frame_rate'] != '0/0') {
								$tmp = explode('/', $val['avg_frame_rate']);
							} else {
								$tmp = explode('/', $val['r_frame_rate']);
							}
							$val['frame_rate'] = $tmp[0]/$tmp[1];
							$val['duration'] = floatval($val['duration']);

							$result['video'][] = $val;
						} elseif ($val['codec_type'] == 'audio') {
							$val['duration'] = floatval($val['duration']);

							$result['audio'][] = $val;
						}
					}
				}
			}
		} else {
			// Basic info
			if (!empty($out['streams'])) {
				foreach ($out['streams'] as $val) {
					if ($val['codec_type'] == 'video' && empty($result['vcodec'])) {
						$result['vcodec'] = $val['codec_name'];
						if ($val['avg_frame_rate'] != '0/0') {
							$tmp = explode('/', $val['avg_frame_rate']);
						} else {
							$tmp = explode('/', $val['r_frame_rate']);
						}
						$result['fps'] = $tmp[0]/$tmp[1];
						$result['width'] = $val['width'];
						$result['height'] = $val['height'];
						$result['vbitrate'] = (isset($val['bit_rate']) ? $val['bit_rate']:-1); //vp8 doesn't have it
					} elseif ($val['codec_type'] == 'audio' && empty($result['acodec'])) {
						$result['acodec'] = $val['codec_name'];
						$result['afreq'] = $val['sample_rate'];
						$result['achannels'] = (isset($val['channel_layout']) ? $val['channel_layout']:'');
						$result['abitrate'] = (isset($val['bit_rate']) ? $val['bit_rate']:-1); //vp8 doesn't have it
					}
				}
			}

			if (!empty($out['format'])) {
				$result['length'] = floatval($out['format']['duration']);

				if (isset($out['format']['bit_rate'])) {
					$result['bitrate'] = $out['format']['bit_rate'];
				} else {
					// Sometimes $out['format']['bit_rate'] is not set, sum all streams bitrates
					$result['bitrate'] = 0;
					foreach ($out['streams'] as $val) {
						if (isset($val['bit_rate'])) {
							$result['bitrate'] += $val['bit_rate'];
						}
					}
				}
			}

		}

		return $result;
	}

	/**
	 * Convert video file
	 * Options:
	 * - threads option will limit number of parallel encoding threads
	 * - time_from - cut video from time
	 * - time_to - cut video to time
	 * - time_duration - duration of transcoded video
	 * - format - container format, e.g. mp4, webm, etc.
	 * - vcodec - video codec, see Dao_Service_Ffmpeg::VCODEC_* constants
	 * - acodec - audio codec, see Dao_Service_Ffmpeg::ACODEC_* constants
	 * - vbitrate - video bitrate in kilobits/sec
	 * - vmaxrate - maximum video bitrate for CRF
	 * - abitrate - audio bitrate in kilobits/sec
	 * - vprofile - video profile, high by default
	 * - vpreset - video preset, slow by default
	 * - noaudio - disable audio for this video
	 * - bufsize - buffer size to calculate average bitrate across it in kilobits/sec
	 * - crf - CRF mode/value
	 * - fps - frames per second
	 * - fps_filter - frames per second filter, can be defined as formula. e.g. 100/4432
	 * - vframes - number of frames to extract/convert
	 * - pix_fmt - Chroma subsampling yuv420p or yuv422p
	 * - scale_width - ...
	 * - scale_height - ...
	 * - crop - height, width, x, y
	 * - progress_log - send program-friendly progress information to file or URL (broken)
	 * - h264_coder - entropy encoder ac (CABAC) or vlc (CAVLC)
	 *
	 * @param string $file
	 * @param string $save
	 * @param array $options
	 * @return int
	 */
	public function convert($file, $save, Array $options) {

		// Additional convert options
		$options_add = '';

		// Format
		if (!empty($options['vcodec']) && empty($options['format'])) {
			switch ($options['vcodec']) {
				case self::VCODEC_H264:
				case self::VCODEC_H265:
					$options['format'] = 'mp4';
					break;
				case self::VCODEC_VP8:
					$options['format'] = 'webm';
					break;
			}
		}

		if (!isset($options['bufsize'])) {
			if (isset($options['vbitrate'])) {
				$options['bufsize'] = $options['vbitrate']*10; // 10 seconds buffer to calculate average bitrate
			} elseif (isset($options['vmaxrate'])) {
				$options['bufsize'] = $options['vmaxrate']*10; // 10 seconds buffer to calculate average bitrate
			}
		}

		// Additional video codec options
		if (isset($options['vcodec'])) {
			switch ($options['vcodec']) {
				case self::VCODEC_H264:
					// Constant Rate Factor 0-51
					// it keeps up a constant quality by compressing every frame of the same type the same amount
					// You can also also use a crf with a maximum bit rate by specifying both crf *and* maxrate settings, like
					// ffmpeg -i input -c:v libx264 -crf 20 -maxrate 400k -bufsize 1835k output.mp4
					// You may need to use -pix_fmt yuv420p for your output to work in QuickTime and most other players.
					// +write_colr -- write color information to the metadata to avoid "color_range=N/A"
					// -color_range 1 -- force color_range=tv to get a full (0-255) color range  in client players
					//					 color_range=pc yields limited (16-235) color range for some reason
					$options_add = ' -movflags +faststart+write_colr -color_range 1 -preset:v ' .
						(!empty($options['vpreset']) ? $options['vpreset']:'slow') .
						(!empty($options['h264_coder']) ? ' -coder ' . $options['h264_coder']:'');
					break;
				case self::VCODEC_H265:
					$options_add = ' -movflags +faststart+write_colr -preset:v ' .
						(!empty($options['vpreset']) ? $options['vpreset']:'slow');
					$x265_params = array(
						'range=limited' # color_range=tv for a full color range in Windows Media Foundation
					);
					if (isset($options['vmaxrate'])) {
						$x265_params[] = 'vbv-maxrate=' . $options['vmaxrate'];
					}
					if (isset($options['bufsize'])) {
						$x265_params[] = 'vbv-bufsize=' . $options['bufsize'];
					}
					if (!empty($options['threads'])) {
						$x265_params[] = 'pools=' . $options['threads'];
					}
					if (!empty($x265_params)) {
						$options_add .= ' -x265-params "' . implode(':', $x265_params) . '"';
					}
					break;
				case self::VCODEC_VP8:
				case self::VCODEC_VP9:
					// To set constant bitrate, set b:v, maxrate and minrate to the same values
					// To use variable quality and just specify the upper bound for the bitrate, then set both b:v and crf
					// @see http://www.webmproject.org/docs/encoder-parameters/
					$options_add = ' -qmin 0 -qmax 50 -quality good -cpu-used 1';
					break;
			}
		}

		// Scale
		$scale = array('-1', '-1');
		if (!empty($options['scale_width'])) {
			$scale[0] = $options['scale_width'];
		}
		if (!empty($options['scale_height'])) {
			$scale[1] = $options['scale_height'];
		}
		if ($scale[0] == '-1' && $scale[1] == '-1') {
			$scale = array();
		} else {
			if ($scale[0] == '-1') {
				$scale[0] = 'trunc(oh*a/2)*2';
			}
			if ($scale[1] == '-1') {
				$scale[1] = 'trunc(ow/a/2)*2';
			}
		}

		// Filters
		$filters_array = array();
		if (isset($options['fps_filter'])) {
			$filters_array[] = 'fps=fps=' . $options['fps_filter'];
		}
		if (!empty($scale)) {
			$filters_array[] = 'scale=' . implode(':', $scale);
		}
		if (!empty($options['crop'])) {
			$filters_array[] = 'crop=' . $options['crop']['width'] . ':' . $options['crop']['height'] . ':' .
				$options['crop']['x'] . ':' . $options['crop']['y'];
		}
		if (!empty($options['interpolate'])) {
			$filters_array[] = 'minterpolate=\'fps=' . $options['interpolate']['fps'] . ':mi_mode=mci:mc_mode=aobmc:me_mode=bidir:vsbmc=1\'';
		}

		$filter = '';
		if (!empty($filters_array)) {
			$filter = implode(', ', $filters_array);
		}

		// No audio
		if (!empty($options['noaudio'])) {
			unset($options['acodec'], $options['abitrate']);
		}

		$run = $this->path_ffmpeg . ' -nostats -analyzeduration 100M -probesize 100M' .
			// Use key frames for seeking
			(!empty($options['time_from']) ? ' -ss ' . $options['time_from']:'') .
			# -dst_range 1 -- Always use full color range for input files
			' -i "' . $file . '" -strict -2 -dst_range 1' .
			(!empty($options['threads']) ? ' -threads ' . $options['threads']:'') .

			(!empty($options['time_duration']) ? ' -t ' . $options['time_duration']:'') .
			(!empty($options['time_to']) ? ' -copyts -to ' . $options['time_to']:'') .
			(!empty($options['vframes']) ? ' -vframes ' . $options['vframes']:'') .

			(!empty($options['vcodec']) ? ' -vcodec ' . $options['vcodec']:'') .
			(!empty($options['acodec']) ? ' -acodec ' . $options['acodec']:'') .

			(!empty($options['vbitrate']) ? ' -b:v ' . $options['vbitrate'] . 'k':'') .
			(!empty($options['vmaxrate']) ? ' -maxrate ' . $options['vmaxrate'] . 'k':'') .
			(!empty($options['bufsize']) ? ' -bufsize ' . $options['bufsize'] . 'k':'') .
			(!empty($options['crf']) ? ' -crf ' . $options['crf']:'') .
			(!empty($options['pix_fmt']) ? ' -pix_fmt ' . $options['pix_fmt']:'') .
			(!empty($options['vprofile']) ? ' -profile:v ' . $options['vprofile']:'') .

			(!empty($options['abitrate']) ? ' -b:a ' . $options['abitrate'] . 'k':'') .
			(!empty($options['noaudio']) ? ' -an':'') .

			(!empty($options['fps']) ? ' -r ' . $options['fps']:'') .

			(!empty($filter) ? ' -filter:v "' . $filter . '"':'') .
			(!empty($options['format']) ? ' -f ' . $options['format']:'') .

			(!empty($options['progress_log']) ? ' -progress "' . $options['progress_log'] . '"':'') .

			$options_add .
			' -y "' . $save . '" 2>&1';

		$out = array();
		$status = 0;
		exec($run, $out, $status);
		$this->last_command = $run . "\n\n" . join("\n", $out);

		return $status;
	}

	/**
	 * Concatenate several video files into one
	 * If you cut with stream copy (-c copy) you need to use the -avoid_negative_ts 1 option
	 * if you want to use that segment with the ​concat demuxer.
	 *
	 * @param array $files
	 * @param string $save_to
	 * @param string $format
	 * @return int
	 */
	public function concatenate(Array $files, $save_to, $format) {
		$file_list = tempnam(sys_get_temp_dir(),'ffmpeg');
		$list = '';
		foreach ($files as $f) {
			$list .= "file '" . $f . "'\n";
		}
		file_put_contents($file_list, $list);

		$run = $this->path_ffmpeg . ' -f concat -safe 0 -i ' . $file_list .
			' -c copy -f ' . $format . ' -y ' . $save_to . ' 2>&1';

		$out = array();
		$status = 0;
		exec($run, $out, $status);
		$this->last_command = $run . "\n\n" . join("\n", $out);

		unlink($file_list);

		return $status;
	}

	public function getLastCommandOutput() {
		return $this->last_command;
	}

	/**
	 * Set ffmpeg path
	 *
	 * @param string $path
	 */
	public function setFfmpegPath($path) {
		$this->path_ffmpeg = $path;
	}

	/**
	 * Set ffprobe path
	 *
	 * Used for getVideoInfo() function
	 *
	 * @param string $path
	 */
	public function setFfprobePath($path) {
		$this->path_ffprobe = $path;
	}

}
