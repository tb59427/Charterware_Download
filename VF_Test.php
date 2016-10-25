<?php 
    $CW_FLIGHT_TYPE = 13;   // Mit diesem Flugtyp tauchen CW Flüge in VF auf
    $CW_CHARGEMODE =2;      // mit diesem Abrechnungstypen tauchen CW Flüge in VF auf
    $CW_COMMENT = "Import von Charterware"; // Standardkommentar für importierte CW Flüge
    
    // TestTestTest
    
    
    require_once('VereinsfliegerRestInterface.php');
    
    $a = new VereinsfliegerRestInterface();
    
        
    $result = $a->SignIn("tb@pobox.com","Dergel99",0);

    if ($result) {
        print_r("SignIn hat geklappt\n");
        $return = $a->getFlight(2151525);
        if ($return) {
            print_r ("Flug lesen OK\n");
	    $aResponse = $a->GetResponse();
	    var_dump($aResponse);
        }
        else {
            print_r ("Flug schreiben NAK\n");
        }
    }
    else {
        print_r ("Login failed\n");
    }
?>
