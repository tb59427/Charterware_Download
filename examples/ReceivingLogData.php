<?php

$uname='lsv-grenzland';
$key='B5oruKheNKm4kdryqXpoKL72v7g6n2';
$url = 'http://application.charterware.net/index.php/dataexport';

// first get the session id 
$aData = array('cmd' => 'login',
               'u' => $uname,
               'p' => $key);


$ckfile = './cookiefile';           

/*
    Attention no error handling implemented
    only a demo 
*/

               
$res = get_request($url, $aData, $ckfile);

$aRes = json_decode($res);
    
var_dump ($aRes->WebServiceResponse);
print_r("\n");
 
if ($aRes->WebServiceResponse->sid != "") {
   
    $sid = $aRes->WebServiceResponse->sid;
    var_dump ($sid);
    // now ask for data
    $aData = array('cmd' => 'getFlightdata',
                   's' => $sid);

    $res = get_request($url, $aData, $ckfile);
    $aRes = json_decode($res);
    
    $aDatas = $aRes->WebServiceResponse->datas; 
    print_r($aDatas);
    # exit for stop execution 
    # exit();
    print_r($aRes->WebServiceResponse->msg."\n");
    if (is_array($aDatas)) {
        $sProcessedIds = '';
        foreach ($aDatas as $key => $Flights) {
            $uid = $Flights->uid;
            print_r("uid [$uid]\n");
            print_r($Flights);
            // data processing
            
            $sProcessedIds .= $uid.',';
        }

        $aData = array('cmd' => 'ackFlightdata',
                       'datas' => $sProcessedIds,
                       's' => $sid);

        print("sProcessedIds[$sProcessedIds]\n");
        #$res = get_request($url, $aData, $ckfile);
        #print("ackFlightdata result\n");
        #print("\n");        
        #$aRes = json_decode($res);
        #print_r($aRes);
        #print("\n");
    }
} else {
    print ("Login Faild\n");
}





function get_request ($url, $aData, $ckfile)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $ckfile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $ckfile); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);    
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type" => "application/octet-stream"));
    curl_setopt($ch, CURLOPT_USERAGENT, "curl/7.21.6 (x86_64-pc-linux-gnu) libcurl/7.21.6 O");
    #curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ;
    #curl_setopt($ch, CURLOPT_USERPWD, "100:100"); 
    curl_setopt($ch, CURLOPT_VERBOSE, 0);    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $aData);
    $response = curl_exec($ch);
    curl_close ($ch);
    return $response;

}
