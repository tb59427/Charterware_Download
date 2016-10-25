<?php 
    // CW2VF - a php script to move flight information from Charterware's main flight log to Vereinsflieger
    // basiert auf Beispielcode von Charterware, der Dokumentation der Charterware Schnittstelle vom März 2013
    // und der Vereinsflieger REST Dokumentation vom 29.08.2016
    //
    // Konfigurieren muss man lediglich die unten stehenden Definitionen (anpassen für Eure Installation)
    // Wenn man dieses Programm dann z.B. alle 10min via cron laufen lässt, hat man einen automatischen Datentransfer von CW nach VF
    //
    // CW liefert bei einer Flugabfrage max 50 Flüge. Dieses Programm unterstellt, dass es häufig genug läuft, dass niemals mehrere aufeinanderfolgende
    // erforderlich werden (vermutlich sichergestellt, wenn es alle 10min läuft). Wenn es nur selten läuft, kann es sein, dass mehr als 50 Flüge in
    // CW aufgelaufen sind. In diesem Fall werden nur die 50 übertragen, die CW liefert. Will man die Situation abfangen, dass da mehr als 50 sind
    // muss man um die äußere foreach Schleife noch eine Schleife bauen, in der immer wieder $CWInterface->GetFlights() aufgerufen wird. War ich zu faul zu.
    
    // Ideen für Verbesserungen:
    // * Heuristiken für $CW_CHARGEMODE oder $CW_FLIGHT_TYPE - man könnte $CW_FLIGHT_TYPE auf "Schulungsflug" setzen, wenn der Co ein Lehrer ist
    // * mehrfache Durchläufe um sicher zu gehen, dass man nicht nur die ersten 50 Flüge aus Charterware geholt hat
    // * Trigger von Charterware nutzen - im Moment leider nicht praktikabel, das nur Email-basierte Trigger gehen
    // * Übergabe von Blockzeiten - ist schon implementiert (vielleicht falsch), geht aber noch nicht, weil Vereinsflieger das nicht kann
    
    
    // Versionshistorie:
    // V0.1 - 24.10.2016 initiale, funktionierende Version
    //
    // (c) Torsten Beyer, 24.10.2016
   
    // let's start with some definitions
    $CW_FLIGHT_TYPE = 13;                               // Mit diesem Flugtyp tauchen CW Flüge in VF auf - muss korrespondieren mit der VF Konfiguration (Flugarten)
                                                        // Konfiguration - d.h. in diesem Fall ist 13 der CW Flugtyp
    $CW_CHARGEMODE  = 2;                                // mit diesem Abrechnungstypen tauchen CW Flüge in VF auf (2 ist Pilot zahlt)
    $CW_COMMENT     = "Import von Charterware";         // Standardkommentar für in VF importierte CW Flüge
    
    $CWuname        = 'lsv-grenzland';                  // Charterware uname for login/ obtaining session ID
    $CWkey          = 'B5oruKheNKm4kdryqXpoKL72v7g6n2'; // Charterware key to talk to REST Interface - obtain yours from Charterware
    
    $VFusername     = 'tb@pobox.com';                   // Vereinsflieger username for SignIn to get session ID
    $VFpasswd       = 'Dergel99';                       // Corresponding Vereinsflieger password - THIS IS A SECURITY ISSUE and should be fixed (by vereinsflieger)
    
    
    // Get Interface Wrappers for CW and VF on board
    require_once('CharterwareRestInterface.php');
    require_once('VereinsfliegerRestInterface.php');

    // Initialise interface objects for CW and VF
    $CWInterface = new CharterwareRestInterface();
    $VFInterface = new VereinsfliegerRestInterface();
    
    // calm down mktime() by setting timezone
    date_default_timezone_set ( "UTC");
    
    // try logging in to CW
    $result = $CWInterface->SignIn($CWuname,$CWkey);
    if (!$result) {
        printf ("CW login failed. Bye!\n");
        exit (-1);
    }
    
    // Good CW talks to us, now lets try VF - this is a security issue, having uid and pw in code is not
    // a good idea. But so what - works for the moment. Untested for users with multiple club memberships (cid should not be 0 then, I suppose)
    $result = $VFInterface->SignIn ($VFusername,$VFpasswd,0);
    if (!$result) {
        printf ("CW login fine. Failed to login to VF. Bye!\n");
        $CWInterface->SignOut();
        exit (-2);
    }
    
    // Wonderful, both systems talk to us - we're in business
    // Let's see whether CW has flights for us. Beware: this programme only handles one chunk of flights (max 50) in one go
    $CWInterface->GetFlights();
    $CWresponse = $CWInterface->GetResponse();
    
    
    // if $CWresponse->datas is empty (i.e. not an array), CW had no Flights for us - else there are flights
    // and we can start to convert them into VF flights
    if (is_array($CWresponse->datas)) {
        
        // now lets start some work. First: remember which flights to delete from CW. Starting with nothing to remember :-)
        $sProcessedIds = '';
        
        // now plough through the list of flights from CW
        foreach ($CWresponse->datas as $key => $CWFlights) {

            // remember unique identifier for this flight - in case insertion into VF succeeds this flight can be ACKed from CW
            $uid = $CWFlights->uid;
            
            // Tell everyone where this flight comes from and how much g's there were
            $VFcommentfield = $CW_COMMENT . " " . $CWFlights->g_metering;
            
            // VF needs names in reverse order, i.e. lastname, firstname. We apply some assumptions here. E.g only ONE WORD for firstname
            // luckily, VF accepts anything - so in theory we can also have Alfred E. Neumann perform some flights
            //
            $temp_array = explode ($CWFlights-pilot1, " ");     // get name-words neatly ordered into an array - this assumes firstnames are always one-word only !!
            $first_name = array_shift ($temp_array);            // shift array left and remember first name
            $last_name = implode (" ", $temp_array);            // join whatever is left to last name - this way we can deal with multi-word lastnames
            $temp_array = array ($lastname,",",$firstname);     // now join put them in reverse order
            $VFPilot_name = implode ("",$temp_array);           // and create a string again
            
            //Re-order Co-pilot name - same thing, different guy
            //
            $temp_array = explode ($CWFlights-pilot1, " ");     // get name-words neatly ordered into an array - this assumes firstnames are always one-word only !!
            $first_name = array_shift ($temp_array);            // shift array left and remember first name
            $last_name = implode (" ", $temp_array);            // join whatever is left to last name - this way we can deal with multi-word lastnames
            $temp_array = array ($lastname,",",$firstname);     // now join put them in reverse order
            $VFCoPilot_name = implode ("",$temp_array);         // and create a string again
            
            // now check inside the CWFlight array how many flights have been recorded
            // this is a CW strangeness - they record multiple take-offs and landings under one flight (join by the same block-time)
            // hence we now need to check how many different take offs and landings there were
            //
            foreach ($CWFlights->flights as $flightkey => $CWIndFlight) {
           
                // Here the real work starts - take CW Flight data, massage it and assign it to VF flight data and insert flight in VF
                // change departure date to only contain minutes (not seconds also)
                // Caution: no check is being performed if flight is spanning 00 hrs - flight through midnite .. so don't fly over midnight
                // Also CW doesn't record actual flighttime (despite the field name) - it only records block time.
               
                //
                // Change starttime to hh:mm format from CW's hh:mm:ss format doing some roundings - if I were more experience in math and php I could have used floor()
                //
                $start_date_elements = explode (" ", $CWIndFlight->takeoff);
                $start_time_elements_UTC = explode ("+", $start_date_elements[1]);
                $start_time_elements = explode (":", $start_time_elements_UTC[0]);
                $seconds = intval($start_time_elements[2]);
                $minutes = intval($start_time_elements[1]);
                $hours = intval ($start_time_elements[0]);
                if ($seconds >30) {
                    $minutes +=1;
                }
                if ($minutes > 60) {
                    $hours += 1;
                }
                $beginnTime = mktime($hours,$minutes,0,0,0,0);
                
                $start_time = sprintf ("%s %d:%d", $start_date_elements[0], $hours, $minutes);
                
                //
                // Change arrivaltime to hh:mm format from CW's hh:mm:ss format doing some roundings - if I were more experience in math and php I could have used floor()
                //
                $arrival_date_elements = explode (" ", $CWIndFlight->landing);
                $arrival_time_elements_UTC = explode ("+", $arrival_date_elements[1]);
                $arrival_time_elements = explode (":", $arrival_time_elements_UTC[0]);
                $seconds = intval($arrival_time_elements[2]);
                $minutes = intval($arrival_time_elements[1]);
                $hours = intval ($arrival_time_elements[0]);
                if ($seconds >30) {
                    $minutes +=1;
                }
                if ($minutes > 60) {
                    $hours += 1;
                }
                $endTime = mktime ($hours,$minutes, 0,0,0,0);
                $arrival_time = sprintf ("%s %d:%d", $arrival_date_elements[0], $hours, $minutes);
                
                // calculate flighttime since CW only supplies us with block time
                $flighttime = ($endTime - $beginnTime)/60;
                
                // now build the VF Flight structure - should be encapsulated in the class really
                // maybe in the future, when I am less lazy
                // not 100% sure, that times here really should be take-off/landing times - maybe block times would make more sense
                //
                $VFFlightData = array (
                                       "callsign" => $CWFlights->planecode,
                                       "pilotname" => $VFPilot_name,
                                       
                                       // Zum Testen, diese Daten hier nehmen
                                       // "callsign" => "D-EZIC",
                                       // "pilotname" => "User, Joe",
                                       
                                       "attendantname" => $VFCoPilot_name,
                                       "starttype" => "E",
                                       "departuretime" => $start_time,
                                       "departurelocation" => $CWIndFlight->flightfrom,
                                       "arrivaltime" => $arrival_time,
                                       "arrivallocation" => $CWIndFlight->flightto,
                                       "landingcount" => $CWIndFlight->nooflandings,
                                       "offblock" => $CWFlights->offblock,                  // not working right now - VF promised to implement it, tb, 25.10.16
                                       "onblock" => $CWFlights->onblock,                    // not working right now - VF promised to implement it, tb, 25.10.16
                                       //"flighttime" => $flighttime,                       // VF calculated flighttime on it's own.
                                       "ftid" => $CW_FLIGHT_TYPE,
                                       "chargemode" => $CW_CHARGEMODE,
                                       "comment" => $VFcommentfield . " " . $CWFlights->g_metering, // add g_metering Info to comment in VF Flight
                                      );
            
                //
                // Now ask VF to insert this flight into it's database - if successful should show up in main flight log
                //
                
                $VF_insert_return = $VFInterface->InsertFlight ($VFFlightData);
           
                // did it work?
                if($VF_insert_return) {
                    // OK, that worked. Let's remember to delete this flight from CW
                    $sProcessedIds .= $uid.',';
                }
                else {
                    // Bullocks, didn't work. Continue, but do not delete from CW
                    printf ("Failed to insert flight to Vereinsflieger.\n");
                }
                
            }
            
        }
        
        printf ("Would ACK the following flights from CW\n");
        var_dump ($sProcessedIds);
        
        //
        // Now ACK these flights in CW (means delete them from their DB)
        // CW (and consequently AckFlights()) tells us, how many flights were acked (or negative number if no flights were acked)
        // Not sure what to do if CW acks less flights than we moved. In such cases - as always - ask a human for help and review!
        //
        //$result = $CWInterface->AckFlights($sProcessedIds);
        //if ($result < count($sProcessedIds)) {
        //    pritf ("Check your Charterware Logbook - not all flights were ACKed by Charterware\n");
        //}
    }
    else {
        printf ("CW hat keine Flugdaten. Message war: %s\n", $CWresponse->msg);
        $CWInterface->SignOut();
        $VFInterface->Signout();
        exit (-3);
    }
    
?>
