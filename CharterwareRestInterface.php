<?php
//
// Wrapper für REST Interface zu Charterware
// basierend auf Beispiel-Code von Charterware und Interface Dokumentation von März 2013
// (c) Torsten Beyer, 2016
//
    
class CharterwareRestInterface
{
	private $InterfaceUrl = 'http://application.charterware.net/index.php/dataexport';
	private $sid;
	private $HttpStatusCode = 0;
	private $aResponse = array();
    private $aData = array();
    
    private $ckfile = './cookiefile';
    
	
	//=============================================================================================
	// Anmelden
	//=============================================================================================
	public function SignIn($UserName, $key)
	{
        $aData = array (
            'cmd' => 'login',
            'u' => $UserName,
            'p' => $key
        );
        
        $result = $this->get_request ($aData);
        
        $this->aResponse = json_decode ($result);
        $this->sid = $this->aResponse->WebServiceResponse->sid;
        
        return ($this->aResponse->WebServiceResponse->sid != "");
	}

	//=============================================================================================
	// Abmelden
	//=============================================================================================
	public function SignOut()
	{
        return 1;
	}
	
	
	
	//=============================================================================================
	// Acknowledge Flight Data
	//=============================================================================================
	public function AckFlights($Flids)
	{
        $aData = array (
                        'cmd' => 'ackFlightdata',
                        'datas' => $Flids,
                        's' => $this->sid
                        );
        $result = $this->get_request ($aData);
        $response = json_decode ($result);
        
        $this->aResponse = $response->WebServiceResponse;
        
        // Returns amount of acked flights or -30 in case of failure
        return $response->WebServiceResponse->msg;
    }
	

    //=============================================================================================
	// GetFlights from Charterware. Charterware delivers max 50 flights in one go
	//=============================================================================================
	public function GetFlights()
	{
        $aData = array('cmd' => 'getFlightdata', 's' => $this->sid);
        
        $result = $this->get_request($aData);
        
        $response = json_decode($result);
        
        $this->aResponse = $response->WebServiceResponse;
        
        
        return 1;
        
	}
	
    
	//=============================================================================================
	// GetResponse
	//=============================================================================================
	public function GetResponse()
	{
		return $this->aResponse;
	}
	
	
    
    //=============================================================================================
    // Send Request to Charterware
    //=============================================================================================
    
    
    function get_request ($Data)
    {
        $ch = curl_init();
        $url = $this->InterfaceUrl.$resource;
        
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->ckfile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->ckfile);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type" => "application/octet-stream"));
        curl_setopt($ch, CURLOPT_USERAGENT, "curl/7.21.6 (x86_64-pc-linux-gnu) libcurl/7.21.6 O");
        #curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ;
        #curl_setopt($ch, CURLOPT_USERPWD, "100:100");
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $Data);
        $response = curl_exec($ch);
        curl_close ($ch);
        return $response;
        
    }
    
    //=============================================================================================
	// SetInterfaceUrl
	//=============================================================================================
	public function SetInterfaceUrl($InterfaceUrl)
	{
		return $this->InterfaceUrl = $InterfaceUrl;
	}

}
?>
