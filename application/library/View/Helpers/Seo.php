<?php
namespace View\Helpers;

class Seo {

    const TYPE_MODEL = 1;
    const TYPE_SCENE = 2;

    /**
     * @param $url string
     * @return array|bool
     */
    public function getUrlParamsAsArray($url) {
        if (!empty($url)) {
            preg_match('{^/(?P<route>[a-z]+/*[a-z0-9]*)/*(?P<params>[A-Za-z/0-9_-]*)$}', $url, $matches);

            if (! empty($matches)) {
                if (!empty($matches['params'])) {
                    $parts = array_chunk(explode('/', $matches['params']), 2);

                    $keys = array_column($parts, 0);
                    $values = array_column($parts, 1);

                    if (count($values) == count($values)) {
                        $params = array_combine($keys, $values);
                    }
                }

                $params['route'] = $matches['route'];

                return $params;
            }
        }

        return false;
    }

    /**
     * @param string $url
     * @return null|string
     */
    public function getMetaData(string $url) {

        $params = $this->getUrlParamsAsArray($url);
        $metaData = $this->getSeoDataByUrl($params);

        return $metaData;
    }

    /**
     * @param $params
     * @return array|mixed
     */
    private function getSeoDataByUrl($params) {

        $route = $params['route'] ?? null;

        $metaDataDefault = [
            'title' => 'StasyQ VR: The Most Erotic VR Movies Ever, Striptease in VR',
            'description' => 'StasyQ VR website brings you hundreds of sexy babes to fulfill all of your XXX fantasies. Check out the biggest collection of VR Erotica! Download it or stream VR nude videos right on your device via our leading-edge VR app.',
        ];

        $metaDataList = [
            'virtualreality/scene' => [
                'title' => '{record_name}: 4K StasyQ VR XXX Video | StasyQ VR',
                'description' => '{record_name}. Download this vid and have some sexy VR fun tonight! And check out the other hot XXX vids from our StasyQ erotic VR collection!',
                'record_type' => self::TYPE_SCENE
            ],
            'virtualreality/list' => [
                'title' => 'VR StasyQ Videos: HQ Full-length 4K Striptease VR Videos | StasyQ VR',
                'description' => 'The biggest collection of 4K VR StasyQ Videos and free StasyQ previews. Make your XXX fantasies come true with the hottest babes in the world of virtual reality!'
            ],
            'pornstars/model' => [
                'title' => '{record_name}: All {record_name}\'s StasyQ VR Vids | StasyQ VR',
                'description' => 'Check out the biggest collection of StasyQ VR adult movies with {record_name}! Don\'t miss your chance to have some hot fun with {record_name} in the virtual reality - StasyQ VR.',
                'record_type' => self::TYPE_MODEL
            ],
            'pornstars/list' => [
                'title' => 'StasyQ Models: Full List of StasyQ VR Girls | StasyQ VR',
                'description' => 'Full List of StasyQ VR Babes. Check our our list of StasyQ girls and watch all their sexy VR movies! StasyQ models are really hot. Come and join them in XXX action!',
            ],
            'user/join' => [
                'title' => 'Sign Up - Full StasyQ VR Ultra HD Movies Collection | StasyQ VR',
                'description' => 'Sign up to StasyQVR to get 2 new VR StasyQ movies weekly, to get full access to the VR videos compatible with all devices, and to download the vids without limits.',
            ],
            'user/login' => [
                'title' => 'Log In - StasyQ Adult VR Movies for all VR Devices| StasyQ VR',
                'description' => 'Welcome to StasyQ VR. Log in to your favorite VR XXX website to enjoy all Stasy Q VR erotica. Weekly updates & access to the videos from any VR headset.',
            ],
            'pages/contact' => [
                'title' => 'Contact Us | StasyQ VR Support',
                'description' => 'StasyQ VR contact information. Need help or have questions? Message us and get help with your account, video issues, and more.',
            ],
            'pages/terms' => [
                'title' => 'Terms and Conditions of Use | StasyQ VR',
                'description' => 'Please read and agree our Terms and Conditions of Use before you enjoy our collection of StasyQ VR softcore videos.',
            ],
            'pages/privacy' => [
                'title' => 'Privacy Policy | StasyQ VR',
                'description' => 'Please read and agree our Privacy Policy before you download & watch our collection of StasyQ VR 4K adult movies.',
            ]
        ];

        $seoData = $metaDataList[$route] ?? $metaDataDefault;

        if (!empty($seoData['record_type'])) {
            $recordName = $this->getRecordName($seoData['record_type'], $params);
            $seoData['title'] = str_replace('{record_name}', $recordName, $seoData['title']);
            $seoData['description'] = str_replace('{record_name}', $recordName, $seoData['description']);
        }

        return $seoData;
    }

    /**
     * @param int $recordType
     * @param array $params
     * @return null|string
     */
    private function getRecordName(int $recordType, array $params) {
        $recordName = null;

        switch ($recordType) {
            case self::TYPE_MODEL:
                $recordName = $this->getModelName((int) $params['id']);
//                var_dump($recordName);
//                exit();
                break;
            case self::TYPE_SCENE:
                $recordName = $this->getSceneName((int) $params['id']);
                break;
        }

        return $recordName;
    }

    /**
     * @param int $id
     * @return string
     */
    private function getModelName(int $id) {
        $pornstarModel = new \PornstarsModel();
        $entry = $pornstarModel->getModel($id);

        return !empty($entry) ? $entry['name'] : 'Model';
    }

    /**
     * @param int $id
     * @return string
     */
    private function getSceneName(int $id) {
        $pornstarModel = new \VirtualrealityModel();
        $entry = $pornstarModel->getScene($id);

        return !empty($entry) ? $entry['title'] : 'Scene';
    }
}
