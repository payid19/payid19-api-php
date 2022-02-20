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

    public function get_invoices($req){
        return $this->apiCall('get_invoices', $req);
    }

    private function isSetup()
    {
        if($this->public_key=='' or $this->private_key==''){
            return 'error';
        }
    }

    private function apiCall($cmd, $req = array())
    {
        if ($this->isSetup()=='error') {
            return json_encode(['status'=>'error','message' => ['You have not called the Setup function with your private and public keys!']]);  
        }
        return $this->guestApiCall($cmd, $req);
    }

    private function guestApiCall($cmd, $req = array())
    {
        $req['public_key']=$this->public_key;
        $req['private_key']=$this->private_key;

        $post=http_build_query($req);

        try {
            $apiUrl = $this->getApiUrl($cmd);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS,$post);
            $result = curl_exec($ch);
            curl_close($ch);

            return $result;

        } catch (\Exception $e) {
            return json_encode(['status'=>'error','message' => ['Could not send request to API : ' . $apiUrl]]);  

        }
    }
}
