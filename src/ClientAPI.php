<?php

namespace Payid19;

class ClientAPI
{
    protected $public_key ='';
    protected $private_key = '';

    public $apiEndPoint = 'https://payid19.com/api/v1';


    public function __construct($public_key,$private_key)
    {
        $this->public_key = $public_key;
        $this->private_key = $private_key;
    }

    protected function getApiUrl($commandUrl)
    {
        return trim($this->apiEndPoint, '/') . '/' . $commandUrl;
    }


    public function create_invoice($req)
    {
        return $this->apiCall('create_invoice', $req);
    }


    private function isSetup()
    {
        if($this->public_key=='' or $this->private_key==''){
            return false;
        }
    }


    private function apiCall($cmd, $req = array())
    {
        if ($this->isSetup()==false) {
            return array('error' => 'You have not called the Setup function with your private and public keys!');
        }
        return $this->guestApiCall($cmd, $req);
    }

    private function guestApiCall($cmd, $req = array())
    {
        try {
            $apiUrl = $this->getApiUrl($cmd);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($req));
            $result = curl_exec($ch);
            curl_close($ch);

            if($this->json_decode($result)->status=='error'){
                //error
                return $this->json_decode($result)->message[0];
            }else{
                //success
                return $this->json_decode($result)->message;
            }
        } catch (\Exception $e) {
            return array('status' => 'error', 'message' => 'Could not send request to API : ' . $apiUrl);
        }
    }

    private function jsonDecode($data)
    {
        if (PHP_INT_SIZE < 8 && version_compare(PHP_VERSION, '5.4.0') >= 0) {
            // We are on 32-bit PHP, so use the bigint as string option. If you are using any API calls with Satoshis it is highly NOT recommended to use 32-bit PHP
            $dec = json_decode($data, TRUE, 512, JSON_BIGINT_AS_STRING);
        } else {
            $dec = json_decode($data, TRUE);
        }
        return $dec;
    }

    public function verifyCallbackData($data, $secretKey)
    {

    }
}
