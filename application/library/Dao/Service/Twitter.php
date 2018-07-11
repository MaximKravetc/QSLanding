<?php

namespace Dao\Service;

class Twitter extends \Zend_Service_Twitter {
	
	/**
     * Show extended information on a users
     *
     * @param  array $ids Users IDs or names
     * @throws \Zend_Http_Client_Exception if HTTP request fails or times out
     * @return \Zend_Service_Twitter_Response
     */
    public function usersLookup(array $ids) {
        $this->init();
        $path = 'users/lookup';
        $response = $this->get($path, array('screen_name' => implode(',', $ids)));
        return new \Zend_Service_Twitter_Response($response);
    }

    /**
     * Upload image to the twitter image hosting
     * @doc https://dev.twitter.com/rest/public/uploading-media
     *
     * @param string $file_name -- name of the file or filename path
     * @param null|string $file_contents -- file contents or null
     * @return \Zend_Service_Twitter_Response
     * @throws \Zend_Http_Client_Exception
     * @throws \Zend_Service_Twitter_Exception
     */
    public function mediaUpload($file_name, $file_contents = null) {
        $this->init();

        $client = $this->getHttpClient();
        $client->setUri('https://upload.twitter.com/1.1/media/upload.json');
        $client->resetParameters();

        $client->setFileUpload($file_name, 'media', $file_contents);
        $response = $client->request(\Zend_Http_Client::POST);

        return new \Zend_Service_Twitter_Response($response);
    }

    /**
     * Update user's current status
     *
     * @param  string $status
     * @param  null|int $inReplyToStatusId
     * @param  array $media_ids
     * @return \Zend_Service_Twitter_Response if HTTP request fails or times out
     * @throws \Zend_Service_Twitter_Exception
     */
    public function statusesUpdate($status, $inReplyToStatusId = null, array $media_ids = array())
    {
        $this->init();
        $path = 'statuses/update';
        $len = iconv_strlen(htmlspecialchars($status, ENT_QUOTES, 'UTF-8'), 'UTF-8');
        if ($len > self::STATUS_MAX_CHARACTERS) {
            require_once 'Zend/Service/Twitter/Exception.php';
            throw new \Zend_Service_Twitter_Exception(
                'Status must be no more than '
                . self::STATUS_MAX_CHARACTERS
                . ' characters in length'
            );
        } elseif (0 == $len) {
            require_once 'Zend/Service/Twitter/Exception.php';
            throw new \Zend_Service_Twitter_Exception(
                'Status must contain at least one character'
            );
        }

        $params = array('status' => $status);
        $inReplyToStatusId = $this->validInteger($inReplyToStatusId);
        if ($inReplyToStatusId) {
            $params['in_reply_to_status_id'] = $inReplyToStatusId;
        }
        if (!empty($media_ids)) {
            $params['media_ids'] = implode(',', $media_ids);
        }
        $response = $this->post($path, $params);
        return new \Zend_Service_Twitter_Response($response);
    }
	
}