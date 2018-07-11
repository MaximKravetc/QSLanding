<?php
/**
 * Class Dao_Service_Transcoder_SLRAppStreaming
 *
 * Process full videos, trailers, previews and mosaic images for SLR App
 */

namespace Dao\Service\Transcoder;

use Shelby\Dao\Builder\Search;
use Shelby\Dao\Builder\Sort;

class SLRAppStreaming {

	const TYPE_SLR = 'SLR';
	const TYPE_PGV = 'PGV';
	const TYPE_TPV = 'TPV';
	const TYPE_VP  = 'VP';

	const MIN_RESOLUTION_H = 1000;

	const PREVIEW_VIDEO_WIDTH = 330;
	const PREVIEW_VIDEO_HEIGHT = 200;

	const MOSAIC_WIDTH = 3072;
	const MOSAIC_COLUMNS = 4;
	const MOSAIC_ROWS = 12;
	const MOSAIC_MARGIN = 1;
	const MOSAIC_FILE_DESC_HEIGHT = 330;
	const MOSAIC_FONT = ROOT_PATH . '/console/app/assets/CenturyGothic.ttf';
	const MOSAIC_LOGO = ROOT_PATH . '/console/app/assets/logo.png';
	const MOSAIC_WATERMARK = ROOT_PATH . '/console/app/assets/watermark.png';

	// SLR MP4 H264 1080p @ 30fps
	const PRESET_MP4_1080p_30 = '59031923ed70f23d0655860d';
	// SLR MP4 H264 Preview @ 24fps
	const PRESET_MP4_24 = '591c7121fdfc9d12153520af';
	const PRESET_THUMBS_PNG = '589a0e0d65718353e70fcbe5';

	private $daoScenes;
	private $daoFiles;
	private $ffmpegObj;

	private $storage;
	/**
	 * @var \Zend_Service_Amazon_S3
	 */
	private $storage_s3;

	private $clientTranscoding;

	private $bucket;

	private $pipeline_trailers;
	private $pipeline_full;

	private $video_formats = array();
	private $video_formats_original = array();

	/**
	 * @var \Closure
	 */
	private $video_app_complete = null;
	/**
	 * @var \Closure
	 */
	private $full_video_app_complete = null;

	private $debug_echo = false;

	public function __construct($type, array $transcoderConfig) {
		switch ($type) {
			case self::TYPE_SLR:
				$this->daoScenes = new \Dao\Mongodb\Listing\Slr\Scenes();
				$this->daoFiles = new \Dao\Mongodb\Listing\Slr\Scenes\Files();
				$this->bucket = 's3.sexlikereal.com';
				$this->pipeline_trailers = '5765ae9e267a0a9b6a838689';
				$this->pipeline_full = '57c5eb18d74f8d27a4571338';
				break;
				break;
			case self::TYPE_PGV;
				$this->daoScenes = new \Dao\Mongodb\Listing\Pvg\Scenes();
				$this->daoFiles = new \Dao\Mongodb\Listing\Pvg\Scenes\Files();
				$this->bucket = 's3.porngayvr.com';
				$this->pipeline_trailers = '576809c44992e4b009dcc2bc';
				$this->pipeline_full = '58556c7a65718332905e1cb3';
				break;
			case self::TYPE_TPV;
				$this->daoScenes = new \Dao\Mongodb\Listing\Tpv\Scenes();
				$this->daoFiles = new \Dao\Mongodb\Listing\Tpv\Scenes\Files();
				$this->bucket = 's3.trannypornvr.com';
				$this->pipeline_trailers = '5769a0e26646f34c30470c2f';
				$this->pipeline_full = '5855622612b70d702c239a11';
				break;
			case self::TYPE_VP;
				$this->daoScenes = new \Dao\Mongodb\Listing\VirtuaPorno\Scenes();
				$this->daoFiles = new \Dao\Mongodb\Listing\VirtuaPorno\Scenes\Files();
				$this->bucket = 's3.virtuaporno.com';
				$this->pipeline_trailers = '59c820d41a2e721b8815f5ea';
				$this->pipeline_full = '-';
				break;
			default:
				new \Exception('Unknown type - ' . $type);
		}

		$this->ffmpegObj = new \Shelby\Service\Ffmpeg();

		$this->clientTranscoding = \Aws\ElasticTranscoder\ElasticTranscoderClient::factory($transcoderConfig);

		$this->storage = \Dao\Files\AbstractClass::getCloudStorage($this->bucket);
		$this->storage_s3 = $this->storage->getClient();
		$this->storage_s3->getHttpClient()->setConfig(array('timeout' => 60));

		$this->video_formats = self::getTranscoderPresets();
		// Original resolution presets, depending on number of pixels
		$this->video_formats_original = self::getTranscoderPresetsOriginal();
	}

	/**
	 * Reprocess specific scene id
	 *
	 * @param int $id
	 * @return bool
	 */
	public function videoAppReprocess($id) {
		$this->daoScenes->updateEntry($id,
			array(
				'video.ready' => false,
				'video.job_id' => null
			)
		);

		sleep(1);

		return $this->videoAppRun($id);
	}

	/**
	 * Process all trailers in the queue
	 *
	 * @param null|int $id
	 * @return bool
	 */
	public function videoAppRun($id = null) {
		$extraParams = Search::instance()
			->equals('video.ready', false);

		if (!is_null($id)) {
			$extraParams->equals('_id', $id);
		}

		$list_scenes = $this->daoScenes
			->getList(null, null, $extraParams, Sort::instance()->descending('_id'));

		foreach ($list_scenes as $scene) {
			$this->log($scene['_id'] . ":\t");

			if (!empty($scene['video']['job_id'])) {
				// Check if job is completed
				/** @var \Guzzle\Service\Resource\Model $result */
				$result = $this->clientTranscoding->readJob(
					array('Id' => (string)$scene['video']['job_id'])
				);

				if ($result['Job']['Status'] == 'Complete') {

					$fsizes = array();
					foreach ($result['Job']['Outputs'] as $output) {
						$tmp = explode('/', $output['Key']);
						$encoding = $tmp[0];
						$resolution = (int)substr($tmp[1], strpos($tmp[1], '_')+1);
						$fsizes[$encoding][$resolution] = $output['FileSize'];
					}

					$this->log("Complete\n");
					$this->daoScenes->updateEntry(
						$scene['_id'],
						array(
							'video.ready' => true,
							'video.encodings.vp8' => false,
							'video.encodings.h265' => true,
							'video.encodings.h264' => true,
							'video.fsizes' => $fsizes
						)
					);

					if ($this->video_app_complete instanceof \Closure) {
						$cl = $this->video_app_complete;
						$cl($scene);
					}
				} elseif (in_array($result['Job']['Status'], array('Canceled', 'Error'))) {
					$this->log(print_r($result->getAll(), true), \Zend_Log::WARN);
					$this->log("Error, restarting\n");
					$this->daoScenes->updateEntry($scene['_id'], array('video.job_id' => null));
				} else {
					$this->log("Progressing...\n");
				}

				continue;
			}

			$entry_file = $this->daoFiles->getFirstEntry(
				Search::instance()
					->equals('scene._id', $scene['_id'])
					->equals('upload_status', \Dao\Mongodb\Listing\Slr\Scenes\Files::UPLOAD_STATUS_UPLOADED)
					->equals('title', 'Oculus')
			);
			if ($entry_file->exists() === false) {
				$this->log("No files found for this scene\n");
				continue;
			}

			$url_video_path = 'files_src/' . $scene['_id'] . '/' . $entry_file['filename'];
			$res = $this->storage_s3->getInfo($this->bucket . '/' . $url_video_path);
			if ($res === false) {
				// Source video doesn't exists
				$url_video_path = 'files/' . $scene['_id'] . '/' . $entry_file['filename'];
				$url_video_path_signed = $this->urlSign(
					'/files/' . $scene['_id'] . '/' . rawurlencode($entry_file['filename'])
				);
			} else {
				$url_video_path_signed = $this->urlSign(
					'/files_src/' . $scene['_id'] . '/' . rawurlencode($entry_file['filename'])
				);
			}

			$video_info = $this->ffmpegObj->getVideoInfo($url_video_path_signed);
			if (empty($video_info['length'])) {
				$this->log("Unable to get video info\n", \Zend_Log::ERR);
				$this->log($url_video_path_signed . "\n", \Zend_Log::ERR);
				return false;
			}
			$this->log('Source video format: ' . $video_info['width'] . 'x' . $video_info['height'] . ', ' . $video_info['length'] . " sec\n");

			$jobTask = array(
				// PipelineId is required
				'PipelineId' => $this->pipeline_trailers, // Trailers pipeline
				// Input is required
				'Input' => array(
					'Key' => $url_video_path
				),
				'OutputKeyPrefix' => 'videos_app',
				'Outputs' => array()
			);

			// H264 1080p @ 30 fps for low end phones
			$jobTask['Outputs'][] = array(
				'Key' => 'h264_30/' . $scene['_id'] . '_1080p.mp4',
				'PresetId' => self::PRESET_MP4_1080p_30
			);
			$this->storage->deleteItem('videos_app/h264_30/' . $scene['_id'] . '_1080p.mp4');

			$video_formats = $this->video_formats;
			foreach ($video_formats as &$format) {
				if ($format['height'] > $video_info['height']) {
					$format['ok'] = false;
					// Skip transcoding if source video is smaller than the target
					continue;
				}

				$format['ok'] = true;

				// H265
				if (!empty($format['PresetId_H265'])) {
					$key = 'h265/' . $scene['_id'] . '_' . $format['height'] . 'p.mp4';
					$jobTask['Outputs'][] = array(
						'Key' => $key,
						'PresetId' => $format['PresetId_H265']
					);
					// Remove object because transcoding task will fail if it is already exists
					$this->storage->deleteItem('videos_app/' . $key);
				}

				// H264
				if (!empty($format['PresetId_H264'])) {
					$key = 'h264/' . $scene['_id'] . '_' . $format['height'] . 'p.mp4';
					$jobTask['Outputs'][] = array(
						'Key' => $key,
						'PresetId' => $format['PresetId_H264']
					);
					// Remove object because transcoding task will fail if it is already exists
					$this->storage->deleteItem('videos_app/' . $key);
				}
			}
			unset($format);

			// Original resolution output if it make sense
			$original = true;
			foreach ($video_formats as $format) {
				if ($format['height'] == $video_info['height']) {
					$original = false;
					break;
				}
			}
			if ($original == true) {
				$pixels = $video_info['height']*$video_info['width'];
				foreach ($this->video_formats_original as $format) {
					if ($format['min'] < $pixels && $format['max'] >= $pixels) {
						// H265
						if (!empty($format['PresetId_H265'])) {
							$key = 'h265/' . $scene['_id'] . '_' . $video_info['height'] . 'p.mp4';
							$jobTask['Outputs'][] = array(
								'Key' => $key,
								'PresetId' => $format['PresetId_H265']
							);
							$this->storage->deleteItem('videos_app/' . $key);
						}

						// H264
						if (!empty($format['PresetId_H264'])) {
							$key = 'h264/' . $scene['_id'] . '_' . $video_info['height'] . 'p.mp4';
							$jobTask['Outputs'][] = array(
								'Key' => $key,
								'PresetId' => $format['PresetId_H264']
							);
							$this->storage->deleteItem('videos_app/' . $key);
						}

						break;
					}
				}
			}

			$outputs_size = sizeof($jobTask['Outputs']);
			if ($outputs_size >= 30) {
				$jobTask_half = $jobTask;
				$jobTask_half['Outputs'] = array_slice($jobTask['Outputs'], 0, round($outputs_size/2));

				$result = $this->clientTranscoding->createJob($jobTask_half);
				if (empty($result)) {
					$this->log("Unable to add transcoding job\n", \Zend_Log::ERR);
					$this->log(print_r($jobTask, true), \Zend_Log::ERR);
					continue;
				}

				$jobTask['Outputs'] = array_slice($jobTask['Outputs'], round($outputs_size/2));
			}

			$result = $this->clientTranscoding->createJob($jobTask);

			if (empty($result)) {
				$this->log("Unable to add transcoding job\n", \Zend_Log::ERR);
				$this->log(print_r($jobTask, true), \Zend_Log::ERR);
				continue;
			}

			$stereo_format = 'sbs2l';
			if (isset($entry_file['format']) && !empty($entry_file['format'])) {
				$stereo_format = $entry_file['format'];
			} elseif (isset($scene['video']['format']) && !empty($scene['video']['format'])) {
				$stereo_format = $scene['video']['format'];
			}

			// Preview
			$jobTask = array(
				// PipelineId is required
				'PipelineId' => $this->pipeline_trailers, // Trailers pipeline
				// Input is required
				'Input' => array(
					'Key' => $jobTask['Input']['Key']
				),
				'OutputKeyPrefix' => 'preview/500f_trailer',
				'Outputs' => array()
			);

			$video_params = self::calculateCrop(
				$stereo_format,
				$video_info['width'], $video_info['height'],
				self::PREVIEW_VIDEO_WIDTH, self::PREVIEW_VIDEO_HEIGHT
			);

			$jobTask['Outputs'][] = array(
				'Key' => $scene['_id'] . '_200p.mp4',
				'PresetId' => self::PRESET_MP4_24,
				'Video' => array(
					'Scale' => array(
						'Width' => $video_params['scale_width'],
						'Height' => $video_params['scale_height']
					),
					'Crop' => array(
						'Width' => self::PREVIEW_VIDEO_WIDTH,
						'Height' => self::PREVIEW_VIDEO_HEIGHT,
						'X' => $video_params['crop_x'],
						'Y' => $video_params['crop_y']
					),
					'Composition' => array(),
					'FrameRate' => round(500/$video_info['length'], 4)
				)
			);
			$this->storage->deleteItem('preview/500f_trailer/' . $scene['_id'] . '_200p.mp4');
			$this->clientTranscoding->createJob($jobTask);
			// Preview End

			$video_obj = array(
				'ready' => false,
				'format' => $stereo_format,
				'length' => $video_info['length'],
				'job_id' => $result['Id'],
				'sizes' => array(),
				'orig_res' => array('w' => $video_info['width'], 'h' => $video_info['height'])
			);
			foreach ($video_formats as $f) {
				if (empty($f['ok'])) {
					continue;
				}
				$video_obj[$f['code']] = $f['ok'];
				$video_obj['sizes'][] = $f['height'];
			}
			if ($original == true) {
				$video_obj['original'] = true;
				$video_obj['sizes'][] = $video_info['height'];
			}

			$video_obj = array_merge($scene['video'], $video_obj);

			$this->daoScenes->updateEntry($scene['_id'], array('video' => $video_obj));
		}

		return true;
	}

	/**
	 * Set callback function for trailer video complete event
	 * Closure will be called with a scene entry array as a first parameter
	 *
	 * @param \Closure $callback
	 */
	public function onVideoAppComplete(\Closure $callback) {
		$this->video_app_complete = $callback;
	}

	/**
	 * Reprocess specific scene id
	 *
	 * @param int $id
	 * @return bool
	 */
	public function fullVideoAppReprocess($id) {
		$this->daoScenes->updateEntry($id,
			array(
				'full_video.ready' => false,
				'full_video.job_id' => null
			)
		);

		sleep(1);

		return $this->fullVideoAppRun($id);
	}

	/**
	 * Process all full videos in the queue
	 *
	 * @param null|int $id
	 * @return bool
	 */
	public function fullVideoAppRun($id = null) {
		$extraParams = Search::instance()
			->equals('full_video.ready', false)
			->equals('full_video.uploaded', true);

		if (!is_null($id)) {
			$extraParams->equals('_id', $id);
		}

		$list_scenes = $this->daoScenes
			->getList(50, null, $extraParams, Sort::instance()->descending('_id'))->get();

		foreach ($list_scenes as $scene) {
			$this->log($scene['_id'] . ":\t");

			if (!empty($scene['full_video']['job_id'])) {
				// Check if job is completed
				/** @var \Guzzle\Service\Resource\Model $result */
				$result = $this->clientTranscoding->readJob(
					array('Id' => (string)$scene['full_video']['job_id'])
				);

				if ($result['Job']['Status'] == 'Complete') {
					$res = $this->createMosaic($scene);
					if ($res === false) {
						$this->log('Unable to create mosaic', \Zend_Log::ERR);
						continue;
					}
					$res = $this->createPreview($scene);
					if ($res === false) {
						$this->log('Unable to create previews', \Zend_Log::ERR);
						continue;
					}

					$fsizes = array();
					foreach ($result['Job']['Outputs'] as $output) {
						$tmp = explode('/', $output['Key']);
						$encoding = $tmp[0];
						$resolution = (int)substr($tmp[1], strpos($tmp[1], '_') + 1);
						$fsizes[$encoding][$resolution] = $output['FileSize'];
					}

					$this->log("Complete\n");

					$this->daoScenes->updateEntry(
						$scene['_id'],
						array(
							'full_video.ready' => true,
							'full_video.encodings.vp8' => false,
							'full_video.encodings.h265' => true,
							'full_video.encodings.h264' => true,
							'full_video.fsizes' => $fsizes
						)
					);

					if ($this->full_video_app_complete instanceof \Closure) {
						$cl = $this->full_video_app_complete;
						$cl($scene);
					}
				} elseif (in_array($result['Job']['Status'], array('Canceled', 'Error'))) {
					$this->log(print_r($result->getAll(), true), \Zend_Log::WARN);
					$this->log("Error, restarting\n", \Zend_Log::WARN);
					$this->daoScenes->updateEntry($scene['_id'], array('full_video.job_id' => null));
				} else {
					$this->log("Progressing...\n");
				}

				continue;
			}

			$entry_file = $this->daoFiles->getFirstEntry(
				Search::instance()
					->equals('title', 'Full')
					->equals('hmd', \Dao\Mongodb\Listing\Slr\Scenes\Files::HMD_OCULUS)
					->equals('scene._id', $scene['_id'])
					->equals('upload_status', \Dao\Mongodb\Listing\Slr\Scenes\Files::UPLOAD_STATUS_UPLOADED)
			);
			if ($entry_file->exists() === false) {
				$this->log("No files found for this scene\n");
				continue;
			}

			$url_video_path = 'files_src/' . $scene['_id'] . '/' . $entry_file['filename'];
			$url_video_path_signed = $this->urlSign(
				'/files_src/' . $scene['_id'] . '/' . rawurlencode((string)$entry_file['filename'])
			);

			$video_info = $this->ffmpegObj->getVideoInfo($url_video_path_signed);
			if (empty($video_info['length'])) {
				$this->log("Unable to get video info\n", \Zend_Log::ERR);
				$this->log($url_video_path_signed . "\n", \Zend_Log::ERR);
				return false;
			}
			$this->log('Source video format: ' . $video_info['width'] . 'x' . $video_info['height'] . ', ' . $video_info['length'] . " sec\n");

			$jobTask = array(
				// PipelineId is required
				'PipelineId' => $this->pipeline_full, // Full Pipeline
				// Input is required
				'Input' => array(
					'Key' => $url_video_path
				),
				'OutputKeyPrefix' => 'full_videos_app',
				'Outputs' => array()
			);

			// H264 1080p @ 30 fps for low end phones
			$jobTask['Outputs'][] = array(
				'Key' => 'h264_30/' . $scene['_id'] . '_1080p.mp4',
				'PresetId' => self::PRESET_MP4_1080p_30
			);
			$this->storage->deleteItem('full_videos_app/h264_30/' . $scene['_id'] . '_1080p.mp4');

			$video_formats = $this->video_formats;
			foreach ($video_formats as &$format) {
				if ($format['height'] < self::MIN_RESOLUTION_H || $format['height'] > $video_info['height']) {
					$format['ok'] = false;
					// Skip transcoding if source video is smaller than 1080p or the target
					continue;
				}

				$format['ok'] = true;

				// H265
				if (!empty($format['PresetId_H265'])) {
					$key = 'h265/' . $scene['_id'] . '_' . $format['height'] . 'p.mp4';
					$jobTask['Outputs'][] = array(
						'Key' => $key,
						'PresetId' => $format['PresetId_H265']
					);
					// Remove object because transcoding task will fail if it is already exists
					$this->storage->deleteItem('full_videos_app/' . $key);
				}

				// H264
				if (!empty($format['PresetId_H264'])) {
					$key = 'h264/' . $scene['_id'] . '_' . $format['height'] . 'p.mp4';
					$jobTask['Outputs'][] = array(
						'Key' => $key,
						'PresetId' => $format['PresetId_H264']
					);
					// Remove object because transcoding task will fail if it is already exists
					$this->storage->deleteItem('full_videos_app/' . $key);
				}
			}
			unset($format);

			// Original resolution output if it make sense
			// Some videos has very low resolution, create original resolution version anyway in this case
			$original = true;
			foreach ($video_formats as $format) {
				if ($format['height'] > self::MIN_RESOLUTION_H && $format['height'] == $video_info['height']) {
					$original = false;
					break;
				}
			}
			if ($original == true) {
				$pixels = $video_info['height'] * $video_info['width'];
				foreach ($this->video_formats_original as $format) {
					if ($format['min'] < $pixels && $format['max'] >= $pixels) {
						// H265
						if (!empty($format['PresetId_H265'])) {
							$key = 'h265/' . $scene['_id'] . '_' . $video_info['height'] . 'p.mp4';
							$jobTask['Outputs'][] = array(
								'Key' => $key,
								'PresetId' => $format['PresetId_H265']
							);
							$this->storage->deleteItem('full_videos_app/' . $key);
						}

						// H264
						if (!empty($format['PresetId_H264'])) {
							$key = 'h264/' . $scene['_id'] . '_' . $video_info['height'] . 'p.mp4';
							$jobTask['Outputs'][] = array(
								'Key' => $key,
								'PresetId' => $format['PresetId_H264']
							);
							$this->storage->deleteItem('full_videos_app/' . $key);
						}

						break;
					}
				}
			}

			$stereo_format = $scene['video']['format'];
			if (!empty($scene['full_video']['format'])) {
				$stereo_format = $scene['full_video']['format'];
			} elseif (!empty($entry_file['format'])) {
				$stereo_format = $entry_file['format'];
			}

			$video_params = self::calculateCrop(
				(string)$stereo_format,
				$video_info['width'], $video_info['height'],
				self::PREVIEW_VIDEO_WIDTH, self::PREVIEW_VIDEO_HEIGHT
			);

			// Create preview jobs first, had to separate it from thumbnails because:
			// "Validation errors: [OutputKeyPrefix] length must be greater than or equal to 1'"
			$jobTaskPreview = array(
				// PipelineId is required
				'PipelineId' => $this->pipeline_full, // Full PipelineId -- we need Private ACL
				// Input is required
				'Input' => array(
					'Key' => $jobTask['Input']['Key']
				),
				'OutputKeyPrefix' => 'preview',
				'Outputs' => array(
					array(
						'Key' => 'full/' . $scene['_id'] . '_200p.mp4',
						'PresetId' => self::PRESET_MP4_24,
						'Video' => array(
							'Scale' => array(
								'Width' => $video_params['scale_width'],
								'Height' => $video_params['scale_height']
							),
							'Crop' => array(
								'Width' => self::PREVIEW_VIDEO_WIDTH,
								'Height' => self::PREVIEW_VIDEO_HEIGHT,
								'X' => $video_params['crop_x'],
								'Y' => $video_params['crop_y']
							)
						)
					)
				)
			);
			$this->storage->deleteItem('preview/full/' . $scene['_id'] . '_200p.mp4');
			$result = $this->clientTranscoding->createJob($jobTaskPreview);
			if (empty($result)) {
				$this->log("Unable to add transcoding job\n", \Zend_Log::ERR);
				$this->log(print_r($jobTaskPreview, true), \Zend_Log::ERR);
				continue;
			}

			// Create thumbnails and preview jobs first
			$jobTaskThumbs = array(
				// PipelineId is required
				'PipelineId' => $this->pipeline_trailers, // Trailers PipelineId -- we need Public ACL
				// Input is required
				'Input' => array(
					'Key' => $jobTask['Input']['Key']
				),
				'OutputKeyPrefix' => 'mosaic',
				'Outputs' => array(
					array(
						'Key' => 'not_defined',
						'ThumbnailPattern' => $scene['_id'] . '/thumbs_orig/{count}.png',
						'PresetId' => self::PRESET_THUMBS_PNG
					)
				)
			);

			$result = $this->clientTranscoding->createJob($jobTaskThumbs);
			if (empty($result)) {
				$this->log("Unable to add transcoding job\n", \Zend_Log::ERR);
				$this->log(print_r($jobTaskThumbs, true), \Zend_Log::ERR);
				continue;
			}

			if (empty($jobTask['Outputs'])) {
				$this->log("Video resolution is too low to transcode\n", \Zend_Log::WARN);
				continue;
			}

			$outputs_size = sizeof($jobTask['Outputs']);
			if ($outputs_size >= 30) {
				$jobTask_half = $jobTask;
				$jobTask_half['Outputs'] = array_slice($jobTask['Outputs'], 0, round($outputs_size/2));

				$result = $this->clientTranscoding->createJob($jobTask_half);
				if (empty($result)) {
					$this->log("Unable to add transcoding job\n", \Zend_Log::ERR);
					$this->log(print_r($jobTask, true), \Zend_Log::ERR);
					continue;
				}

				$jobTask['Outputs'] = array_slice($jobTask['Outputs'], round($outputs_size/2));
			}

			$result = $this->clientTranscoding->createJob($jobTask);
			if (empty($result)) {
				$this->log("Unable to add transcoding job\n", \Zend_Log::ERR);
				$this->log(print_r($jobTask, true), \Zend_Log::ERR);
				continue;
			}

			$video_obj = array(
				'ready' => false,
				'format' => $stereo_format,
				'length' => $video_info['length'],
				'job_id' => $result['Id'],
				'sizes' => array(),
				'orig_res' => array('w' => $video_info['width'], 'h' => $video_info['height'])
			);
			foreach ($video_formats as $f) {
				if (empty($f['ok'])) {
					continue;
				}
				$video_obj['sizes'][] = $f['height'];
			}
			if ($original == true) {
				$video_obj['sizes'][] = $video_info['height'];
			}

			$video_obj = array_merge($scene['full_video'], $video_obj);

			$this->daoScenes->updateEntry($scene['_id'], array('full_video' => $video_obj));
		}

		return true;
	}

	/**
	 * Set callback function for full video video complete event
	 * Closure will be called with a scene entry array as a first parameter
	 *
	 * @param \Closure $callback
	 */
	public function onFullVideoAppComplete(\Closure $callback) {
		$this->full_video_app_complete = $callback;
	}

	/**
	 * Create mosaic file from previously created frames
	 *
	 * @param array $entry_video
	 * @return bool
	 * @throws \Exception
	 */
	public function createMosaic(Array $entry_video) {
		$file_save_3073 = 'mosaic/' . $entry_video['_id'] . '/3073.jpg';

		$dst_w = (int)(self::MOSAIC_WIDTH / self::MOSAIC_COLUMNS) - self::MOSAIC_MARGIN;

		switch ($entry_video['full_video']['format']) {
			case 'sbs2l':
			case 'sbs2r':
				$src_w = (int)$entry_video['full_video']['orig_res']['w'] / 2;
				$src_h = $entry_video['full_video']['orig_res']['h'];
				$format_string = 'Side by side stereo';
				break;
			case 'ab2l':
			case 'ab2r':
				$src_w = $entry_video['full_video']['orig_res']['w'];
				$src_h = (int)$entry_video['full_video']['orig_res']['h'] / 2;
				$format_string = 'Above below stereo';
				break;
			case 'mono':
				$src_w = $entry_video['full_video']['orig_res']['w'];
				$src_h = $entry_video['full_video']['orig_res']['h'];
				$format_string = 'Mono';
				break;
			default:
				throw new \Exception('Unknown format - ' . $entry_video['full_video']['format']);
		}

		$dst_h = ($src_h / $src_w) * $dst_w - self::MOSAIC_MARGIN;

		$im = imagecreatetruecolor(self::MOSAIC_WIDTH + self::MOSAIC_MARGIN,
			$dst_h * self::MOSAIC_ROWS + self::MOSAIC_MARGIN * (self::MOSAIC_ROWS + 1) + self::MOSAIC_FILE_DESC_HEIGHT);

		// Logo
		$im_logo = imagecreatefrompng(self::MOSAIC_LOGO);
		imagecopy($im, $im_logo, 2400, 100, 0, 0, 625, 74);

		$color_desc = imagecolorallocate($im, 0xFF, 0xFF, 0xFF);

		/** @var \Guzzle\Service\Resource\Model $result */
		$entry_transcoding = $this->clientTranscoding->readJob(
			array('Id' => (string)$entry_video['full_video']['job_id'])
		)->get('Job');
		$video_props = $entry_transcoding['Inputs'][0]['DetectedProperties'];

		imagefttext(
			$im, 36, 0,
			15, 60,
			$color_desc, self::MOSAIC_FONT,
			'File: ' . substr(basename($entry_transcoding['Inputs'][0]['Key']), 6) . '; Produced by ' . $entry_video['link']['name']
		);
		imagefttext(
			$im, 36, 0,
			15, 120,
			$color_desc, self::MOSAIC_FONT,
			'Size: ' . number_format($video_props['FileSize']) . ' bytes (' . number_format($video_props['FileSize'] / 1073741824, 2) . ' GB); ' .
			'Duration: ' . sprintf('%d:%02d:%02d', floor($entry_video['full_video']['length'] / 3600), floor($entry_video['full_video']['length'] / 60 % 60), $entry_video['full_video']['length'] % 60) . '; ' .
			'Avg. bitrate: ' . number_format((($video_props['FileSize'] / $entry_video['full_video']['length']) * 8) / 1048576, 2) . ' Mbit/sec'
		);
		imagefttext(
			$im, 36, 0,
			15, 180,
			$color_desc, self::MOSAIC_FONT,
			'Audio: ' . $video_props['AudioCodec'] . '; ' . number_format($video_props['AudioBitrate'] / 1024) . ' kbit/sec'
		);
		imagefttext(
			$im, 36, 0,
			15, 240,
			$color_desc, self::MOSAIC_FONT,
			'Video: ' . $video_props['VideoCodec'] . '; ' . $video_props['Width'] . 'x' . $video_props['Height'] . '; ' . number_format($video_props['VideoBitrate'] / 1048576, 2) . ' Mbit/sec; ' . $video_props['FrameRate'] . ' fps; ' . $format_string . '; ' . $entry_video['view_angle'] . '°'
		);

		imagefttext(
			$im, 36, 0,
			15, 300,
			$color_desc, self::MOSAIC_FONT,
			'Hosted by SexLikeReal.com, HottiesVR.com, VRPornUpdates.com. Played with DeoVR.com video player'
		);

		$color_timestamp = imagecolorallocate($im, 0xFF, 0xFF, 0xFF);
		$shadow_timestamp = imagecolorallocate($im, 0x00, 0x00, 0x00);

		for ($i = 1; $i <= self::MOSAIC_COLUMNS * self::MOSAIC_ROWS; $i++) {
			$thumb = $this->storage->fetchItem('mosaic/' . $entry_video['_id'] . '/thumbs_orig/' . sprintf('%\'05d', $i) . '.png');
			if (empty($thumb)) {
				$this->log("\nUNABLE TO FETCH FILE - " . $i . "\n", \Zend_Log::ERR);
				return false;
			}
			$thumb_im = imagecreatefromstring($thumb);
			if ($thumb_im === false) {
				$this->log("\nUNABLE TO DECODE PNG - " . $i . "\n", \Zend_Log::ERR);
				return false;
			}

			$col = ($i - 1) % self::MOSAIC_COLUMNS;
			$row = floor(($i - 1) / self::MOSAIC_COLUMNS);

			$this->log($i . ' ~ ' . $col . '/' . $row . "\t");

			$dst_x = $col * $dst_w + ($col + 1) * self::MOSAIC_MARGIN;
			$dst_y = $row * $dst_h + ($row + 1) * self::MOSAIC_MARGIN + self::MOSAIC_FILE_DESC_HEIGHT;

			imagecopyresampled($im, $thumb_im,
				$dst_x, $dst_y,
				0, 0,
				$dst_w, $dst_h,
				$src_w, $src_h
			);

			$sec_current = round(($entry_video['full_video']['length'] / (self::MOSAIC_COLUMNS * self::MOSAIC_ROWS)) * ($i - 0.5));
			$timestamp = sprintf('%d:%02d:%02d', floor($sec_current / 3600), floor($sec_current / 60 % 60), $sec_current % 60);
			// Write frame time shadow
			imagefttext(
				$im, 24, 0,
				($dst_x + $dst_w - 119), ($dst_y + $dst_h - 11),
				$shadow_timestamp, self::MOSAIC_FONT,
				$timestamp
			);
			// Write frame time
			imagefttext(
				$im, 24, 0,
				($dst_x + $dst_w - 120), ($dst_y + $dst_h - 12),
				$color_timestamp, self::MOSAIC_FONT,
				$timestamp
			);
		}

		$im_watermark = imagecreatefrompng(self::MOSAIC_WATERMARK);
		imagecopy($im, $im_watermark, (int)(self::MOSAIC_WIDTH / 2 - 240), (int)(imagesy($im) * 0.3 - 235), 0, 0, 481, 470);
		imagecopy($im, $im_watermark, (int)(self::MOSAIC_WIDTH / 2 - 240), (int)(imagesy($im) * 0.66 - 235), 0, 0, 481, 470);

		$this->log("\n");

		ob_start();
		imagejpeg($im, null, 85);
		$thumb = ob_get_contents();
		ob_end_clean();

		$s3_options_public = \Dao\Files\AbstractClass::getS3Options();

		$this->storage->storeItem($file_save_3073, $thumb, $s3_options_public);

		return true;
	}

	/**
	 * - Create short video preview for list thumbnails 14 sections by 1 second
	 * - And 500 frames full video for seeking
	 *
	 * @param array $scene
	 * @return bool
	 */
	public function createPreview(Array $scene) {
		$this->log($scene['_id'] . ":\t");

		$file_save_500f = 'preview/500f/' . $scene['_id'] . '_200p.mp4';
		$file_save_14x1_mp4 = 'preview/14x1/' . $scene['_id'] . '_200p.mp4';
		$file_save_14x1_webm = 'preview/14x1/' . $scene['_id'] . '_200p.webm';

		$s3_rr_options = array(
			\Zend_Service_Amazon_S3::S3_ACL_HEADER => \Zend_Service_Amazon_S3::S3_ACL_PUBLIC_READ,
			\Zend_Service_Amazon_S3::S3_CONTENT_TYPE_HEADER => 'video/mp4'
		);
		$s3_rr_options_webm = array(
			\Zend_Service_Amazon_S3::S3_ACL_HEADER => \Zend_Service_Amazon_S3::S3_ACL_PUBLIC_READ,
			\Zend_Service_Amazon_S3::S3_CONTENT_TYPE_HEADER => 'video/webm'
		);

		$url_video = $this->bucket . '/preview/full/' . $scene['_id'] . '_200p.mp4';
		$filename = sys_get_temp_dir() . '/php_slr_unity_preview_' . $scene['_id'] . '.tmp';

		$res = $this->storage_s3->getObjectStream($url_video, $filename);
		if ($res === false) {
			$this->log("Unable to download file\n" . $url_video . "\n", \Zend_Log::ERR);
			unlink($filename);
			return false;
		}

		$filesize = filesize($filename);

		$this->log("File downloaded - " . $url_video . " / " . number_format($filesize) . "\n");

		$headers = $res->getHeaders();
		if ($filesize != $headers['Content-length']) {
			$this->log("Wrong downloaded file size, expected - " . number_format($headers['Content-length']) . "\n", \Zend_Log::ERR);
			return false;
		}

		$video_info = $this->ffmpegObj->getVideoInfo($filename);
		if (empty($video_info['length'])) {
			$this->log("Unable to get video info\n", \Zend_Log::ERR);
			unlink($filename);
			return false;
		}
		$this->log('Source video format: ' . $video_info['width'] . 'x' . $video_info['height'] . ', ' . $video_info['length'] . " sec\n");

		$save_to = sys_get_temp_dir() . '/php_slr_unity_preview_' . $scene['_id'] . '_out.mp4';

		// 500 Frames videos
		$frames = $video_info['fps'] * $video_info['length'];
		if ($frames <= 500) {
			copy($filename, $save_to);
		} else {
			$options = array(
				'vcodec' => \Shelby\Service\Ffmpeg::VCODEC_COPY,
				'noaudio' => true,
				'fps' => round(500/$video_info['length'], 2)
			);

			$res = $this->ffmpegObj->convert($filename, $save_to, $options);

			if ($res != 0) {
				$this->log($this->ffmpegObj->getLastCommandOutput(), \Zend_Log::ERR);
				return false;
			}
		}

		$this->log("Uploading 500 frames MP4 video...\n");
		$this->storage_s3->putFileStream(
			$save_to,
			$this->bucket . '/' . $file_save_500f,
			$s3_rr_options
		);

		unlink($save_to);

		// 14 pieces by 1 second MP4
		$options = array(
			'vcodec' => \Shelby\Service\Ffmpeg::VCODEC_COPY,
			'noaudio' => true,
			'time_duration' => 1
		);

		$save_to = sys_get_temp_dir() . '/php_slr_unity_preview_' . $scene['_id'] . '_out';
		$files = array();

		$skip = round($video_info['length']/15);
		for ($i=1; $i<=14; $i++) {
			$options['time_from'] = $i*$skip;
			$res = $this->ffmpegObj->convert($filename, $save_to . $i . '.mp4', $options);
			if ($res != 0) {
				$this->log($this->ffmpegObj->getLastCommandOutput(), \Zend_Log::ERR);
				return false;
			}

			$files[] = $save_to . $i . '.mp4';
		}

		$res = $this->ffmpegObj->concatenate($files, $save_to . '.mp4', 'mp4');

		if ($res != 0) {
			$this->log($this->ffmpegObj->getLastCommandOutput(), \Zend_Log::ERR);
			return false;
		}

		$this->log("Uploading 14x1 MP4 video...\n");
		$this->storage_s3->putFileStream(
			$save_to . '.mp4',
			$this->bucket . '/' . $file_save_14x1_mp4,
			$s3_rr_options
		);

		unlink($save_to . '.mp4');
		foreach ($files as $f) {
			unlink($f);
		}

		// 14 pieces by 1 second WebM
		$options = array(
			'vcodec' => \Shelby\Service\Ffmpeg::VCODEC_VP8,
			'noaudio' => true,
			'time_duration' => 1,
			'crf' => 30
		);

		$save_to = sys_get_temp_dir() . '/php_slr_unity_preview_' . $scene['_id'] . '_out';
		$files = array();

		$skip = round($video_info['length']/15);
		for ($i=1; $i<=14; $i++) {
			$options['time_from'] = $i*$skip;
			$res = $this->ffmpegObj->convert($filename, $save_to . $i . '.webm', $options);
			if ($res != 0) {
				$this->log($this->ffmpegObj->getLastCommandOutput(), \Zend_Log::ERR);
				return false;
			}

			$files[] = $save_to . $i . '.webm';
		}

		$res = $this->ffmpegObj->concatenate($files, $save_to . '.webm', 'webm');

		if ($res != 0) {
			$this->log($this->ffmpegObj->getLastCommandOutput(), \Zend_Log::ERR);
			return false;
		}

		$this->log("Uploading 14x1 WEBM video...\n");
		$this->storage_s3->putFileStream(
			$save_to . '.webm',
			$this->bucket . '/' . $file_save_14x1_webm,
			$s3_rr_options_webm
		);

		unlink($filename);
		unlink($save_to . '.webm');
		foreach ($files as $f) {
			unlink($f);
		}

		return true;
	}

	/**
	 * Check files structure
	 */
	public function filesCheck() {
		$this->log("Trailers...\n");
		$this->daoScenes->setBatchSize(10);
		$list_scenes = $this->daoScenes->getList(null, null,
			Search::instance()
				->equals('video.ready', true)
		);
		foreach ($list_scenes as $scene) {
			foreach ($scene['video']['sizes'] as $size) {
				$key = 'videos_app/h265/' . $scene['_id'] . '_' . $size . 'p.mp4';
				$info = $this->storage->fetchMetadata($key);
				if ($info === false) {
					$this->log($scene['_id'] . ":\t" . $key . "\t -- NOT FOUND\n", \Zend_Log::ERR);
				}

				$key = 'videos_app/h264/' . $scene['_id'] . '_' . $size . 'p.mp4';
				$info = $this->storage->fetchMetadata($key);
				if ($info === false) {
					$this->log($scene['_id'] . ":\t" . $key . "\t -- NOT FOUND\n", \Zend_Log::ERR);
				}
			}

			// 1080p @ 30FPS
			if ($scene['video']['orig_res']['h'] >= 1080) {
				$key = 'videos_app/h264_30/' . $scene['_id'] . '_1080p.mp4';
				$info = $this->storage->fetchMetadata($key);
				if ($info === false) {
					$this->log($scene['_id'] . ":\t" . $key . "\t -- NOT FOUND\n", \Zend_Log::ERR);
				}
			}

			// Previews
			$key = 'preview/500f_trailer/' . $scene['_id'] . '_200p.mp4';
			$info = $this->storage->fetchMetadata($key);
			if ($info === false) {
				$this->log($scene['_id'] . ":\t" . $key . "\t -- NOT FOUND\n", \Zend_Log::ERR);
			}
		}

		$this->log("Full videos...\n");
		$list_scenes = $this->daoScenes->getList(null, null,
			Search::instance()
				->equals('full_video.ready', true)
		);
		foreach ($list_scenes as $scene) {
			foreach ($scene['full_video']['sizes'] as $size) {
				$key = 'full_videos_app/h265/' . $scene['_id'] . '_' . $size . 'p.mp4';
				$info = $this->storage->fetchMetadata($key);
				if ($info === false) {
					$this->log($scene['_id'] . ":\t" . $key . "\t -- NOT FOUND\n", \Zend_Log::ERR);
				}

				$key = 'full_videos_app/h264/' . $scene['_id'] . '_' . $size . 'p.mp4';
				$info = $this->storage->fetchMetadata($key);
				if ($info === false) {
					$this->log($scene['_id'] . ":\t" . $key . "\t -- NOT FOUND\n", \Zend_Log::ERR);
				}
			}

			// 1080p @ 30FPS
			if ($scene['full_video']['orig_res']['h'] >= 1080) {
				$key = 'full_videos_app/h264_30/' . $scene['_id'] . '_1080p.mp4';
				$info = $this->storage->fetchMetadata($key);
				if ($info === false) {
					$this->log($scene['_id'] . ":\t" . $key . "\t -- NOT FOUND\n", \Zend_Log::ERR);
				}
			}

			// Previews
			$key = 'preview/500f/' . $scene['_id'] . '_200p.mp4';
			$info = $this->storage->fetchMetadata($key);
			if ($info === false) {
				$this->log($scene['_id'] . ":\t" . $key . "\t -- NOT FOUND\n", \Zend_Log::ERR);
			}
			$key = 'preview/14x1/' . $scene['_id'] . '_200p.mp4';
			$info = $this->storage->fetchMetadata($key);
			if ($info === false) {
				$this->log($scene['_id'] . ":\t" . $key . "\t -- NOT FOUND\n", \Zend_Log::ERR);
			}
			$key = 'preview/14x1/' . $scene['_id'] . '_200p.webm';
			$info = $this->storage->fetchMetadata($key);
			if ($info === false) {
				$this->log($scene['_id'] . ":\t" . $key . "\t -- NOT FOUND\n", \Zend_Log::ERR);
			}
			$key = 'mosaic/' . $scene['_id'] . '/3073.jpg';
			$info = $this->storage->fetchMetadata($key);
			if ($info === false) {
				$this->log($scene['_id'] . ":\t" . $key . "\t -- NOT FOUND\n", \Zend_Log::ERR);
			}
		}

		$this->log("Done\n");
	}

	/**
	 * Transcoding presets array for each resolution and encoder
	 *
	 * @return array
	 */
	public static function getTranscoderPresets() {
		return array(
			array(
				'code' => '480p',
				'width' => 640,
				'height' => 480,
				'vbitrate' => 750,
				'PresetId_VP8' => '576418e0267a0ad425466aa2',
				'PresetId_H265' => '580543065ff0d27a935d0ab7',
				'PresetId_H264' => '583f106a87b0fc084c2f616f'
			),
			array(
				'code' => '720p',
				'width' => 1280,
				'height' => 720,
				'vbitrate' => 2000,
				'PresetId_VP8' => '57641a08aaefd74241953723',
				'PresetId_H265' => '580624b0681d075fc534e758',
				'PresetId_H264' => '583f116166a71862231f5413'
			),
			array(
				'code' => '1080p',
				'width' => 1920,
				'height' => 1080,
				'vbitrate' => 5000,
				'PresetId_VP8' => '57641a54aaefd79855953721',
				'PresetId_H265' => '580624d4681d072d9627caa6',
				'PresetId_H264' => '583f119066a71860eb092882'
			),
			array(
				'code' => '1440p',
				'width' => 2560,
				'height' => 1440,
				'vbitrate' => 8000,
				'PresetId_VP8' => '57641adb6646f3850c1c0130',
				'PresetId_H265' => '580624f25ff0d25db36052e4',
				'PresetId_H264' => '583f11b012b70d08992ddd8e'
			),
			array(
				'code' => '1920p',
				'width' => 3410,
				'height' => 1920,
				'vbitrate' => 10000,
				'PresetId_VP8' => '5a66c475fe18e0277738220f',
				'PresetId_H265' => '5a66c3ddbbe8d61f8156a3cc',
				'PresetId_H264' => '5a66c0ebb12efe178520fc05'
			),
			array(
				'code' => '2160p',
				'width' => 3840,
				'height' => 2160,
				'vbitrate' => 12000,
				'PresetId_VP8' => '5767ed056646f3fe421c0130',
				'PresetId_H265' => '5806250f5ff0d257016178bd',
				'PresetId_H264' => '583f11d412b70d734913f470'
			),
			array(
				'code' => '2880p',
				'width' => 5120,
				'height' => 2880,
				'vbitrate' => 18000,
				'PresetId_VP8' => '58052cd25ff0d254005c8e1a',
				'PresetId_H265' => '5806252bf7a2f8016776d1a3',
				'PresetId_H264' => '583f122e65718304583b55bb'
			),
			array(
				'code' => '3360p',
				'width' => 5970,
				'height' => 3360,
				'vbitrate' => 20000,
				'PresetId_VP8' => '580cab6ef7a2f85fdb29b2a1',
				'PresetId_H265' => '580caae2681d07693f6cf111',
				'PresetId_H264' => '583f124d6571836207608371'
			),
			array(
				'code' => '3840p',
				'width' => 6820,
				'height' => 3840,
				'vbitrate' => 22000,
				'PresetId_VP8' => '598c296e6042cb1da2475e97',
				'PresetId_H265' => '598c2cf7b543e469ab5fb63d',
				'PresetId_H264' => '598c2b18b543e4676869d8ab'
			),
			array(
				'code' => '4320p',
				'width' => 7680,
				'height' => 4320,
				'vbitrate' => 24000,
				'PresetId_VP8' => null,
				'PresetId_H265' => '5b34bb3dfee23618d422bee4',
				'PresetId_H264' => null
			),
			array(
				'code' => '5760p',
				'width' => 10240,
				'height' => 5760,
				'vbitrate' => 26000,
				'PresetId_VP8' => null,
				'PresetId_H265' => '5b34bb7b5ff0d21bee7ecd02',
				'PresetId_H264' => null
			)
		);
	}

	/**
	 * Transcoding presets array for original video resolution depending on the number of pixels
	 *
	 * @return array
	 */
	public static function getTranscoderPresetsOriginal() {
		return array(
			array( // 0-2M
				'min' => 0,
				'max' => 2000000,
				'vbitrate' => 4000,
				'PresetId_VP8' => '5774f6734992e4c41be1717e',
				'PresetId_H265' => '580626f2681d0720e35cfb26',
				'PresetId_H264' => '583f12aa6571836ed82ef541'
			),
			array( // 2M-4M
				'min' => 2000000,
				'max' => 4000000,
				'vbitrate' => 8000,
				'PresetId_VP8' => '5774f6a14992e4bd1be17180',
				'PresetId_H265' => '580627a3c170c713d0555f3b',
				'PresetId_H264' => '583f12cc6571836df66c6b43'
			),
			array( // 4M-8M
				'min' => 4000000,
				'max' => 8000000,
				'vbitrate' => 16000,
				'PresetId_VP8' => '5774f6b0a612dcc82f83d1de',
				'PresetId_H265' => '580627b9c170c75dd6097969',
				'PresetId_H264' => '583f12e7657183526e15f34f'
			),
			array( // 8M-16M
				'min' => 8000000,
				'max' => 16000000,
				'vbitrate' => 22000,
				'PresetId_VP8' => '5774f6c1aaefd7734d3a443b',
				'PresetId_H265' => '580627d5fe18e07e1623466c',
				'PresetId_H264' => '583f130212b70d3a091ff57b'
			),
			array( // 16M-32M
				'min' => 16000000,
				'max' => PHP_INT_MAX, //32000000,
				'vbitrate' => 30000,
				'PresetId_VP8' => '598c2a4cb12efe620b02625d',
				'PresetId_H265' => '598c2d55b543e408ec6399e3',
				'PresetId_H264' => '598c2c42b543e408f52233b1'
			)
		);
	}

	/**
	 * Calculate cropping for static size view previews
	 *
	 * @param string $format
	 * @param int $width_in
	 * @param int $height_in
	 * @param int $width_out
	 * @param int $height_out
	 * @return array
	 * @throws \Exception
	 */
	public static function calculateCrop($format, $width_in, $height_in, $width_out, $height_out) {
		$crop_x = $crop_y = 0;

		$scale_width = null;
		$scale_height = null;

		switch ($format) {
			case 'sbs2l':
			case 'sbs2r':
				if (($width_in/2)/$height_in > $width_out/$height_out) {
					// Extremely wide videos like 4096x1024
					$scale_height = $height_out;
					$scale_width = ($height_out/$height_in) * $width_in;

					$crop_x = (($scale_width/2) - $width_out)/2;
				} else {
					$scale_height = ($width_out / ($width_in / 2)) * $height_in;
					$scale_width = $width_out * 2;

					$crop_y = ($scale_height - $height_out) / 2;
				}
				break;
			case 'ab2l':
			case 'ab2r':
				$scale_height = $height_out*2;
				$scale_width = ($height_out/($height_in/2)) * $width_in;

				$crop_x = ($scale_width - $width_out)/2;
				break;
			case 'mono':
				if ($width_in/$height_in > $width_out/$height_out) {
					$scale_height = $height_out;
					$scale_width = ($height_out / $height_in) * $width_in;

					$crop_x = ($scale_width - $width_out) / 2;
				} else {
					$scale_height = ($width_out / $width_in) * $height_in;
					$scale_width = $width_out;

					$crop_y = ($scale_height - $height_out) / 2;
				}
				break;
			default:
				throw new \Exception('Unknown format - ' . $format);
		}

		if ($scale_width%2 != 0) {
			$scale_width++;
		}

		return array(
			'crop_x' => (int)$crop_x,
			'crop_y' => (int)$crop_y,
			'scale_width' => (int)$scale_width,
			'scale_height' => (int)$scale_height
		);
	}

	/**
	 * Get array with available streaming files list
	 *
	 * @param int $id
	 * @param array $video
	 * @return array
	 */
	public static function getStreamingFiles(int $id, array $video) : array {
		if ($video['ready'] === false) {
			return array();
		}

		$result = array();
		$ratio = $video['orig_res']['w'] / $video['orig_res']['h'];
		foreach ($video['fsizes'] as $encoding => $fsizes) {
			$result[$encoding] = array();
			foreach ($fsizes as $h => $size) {
				$result[$encoding][$h] = array(
					'w' => intval($h * $ratio),
					'h' => $h,
					'size' => $size,
					'path' => $encoding . '/' . $id . '_' . $h . 'p' . '.' . ($encoding == 'vp8' ? 'webm':'mp4')
				);
			}
		}

		return $result;
	}

	public function setDebugOutput($debug) {
		$this->debug_echo = (bool)$debug;
	}

	/**
	 * Get signed URL for private files
	 *
	 * @param string $file
	 * @return string
	 */
	private function urlSign($file) {
		$expires = time() + 3600;
		$query = ['Expires' => $expires];

		$s3_access = \Dao\Files\AbstractClass::getCloudOptions();
		$s3_signature = \Dao\Service\S3::signURL($this->bucket, $file, $s3_access['SECRET_KEY'], $expires);

		$query['Signature'] = $s3_signature;
		$query['AWSAccessKeyId'] = $s3_access['ACCESS_KEY'];

		return 'http://' . $this->bucket . $file . '?' . http_build_query($query);
	}

	/**
	 * @param string $message
	 * @param int $level
	 */
	private function log($message, $level = \Zend_Log::DEBUG) {
		if ($this->debug_echo === true) {
			echo $message;
		}
		if ($level < \Zend_Log::NOTICE) {
			error_log(trim($message));
		}
	}

}