<?php
/**
 * Define functions for writing XML
 *
 * PHP version 5
 *
 * @category R2R_Products
 * @package  R2R_Nav
 * @author   Aaron Sweeney <asweeney@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */

/**
 * Create R2R nav quality assessment report in XML format
 *
 * @param object $dataObject      Quality Assessment object
 * @param string $outfile         Output filename
 * @param string $navtemplatefile Output empty template filename
 * @param string $xmlt            Optional partly filled template filename 
 *                                 (default is null)
 */
function xml_write($dataObject, $outfile, $navtemplatefile, $xmlt = null) 
{
    if ($outfile != null) {
        $fmeta = fopen($outfile, "w");
        if ($fmeta == null) {
            echo "xml_write(): Could not open meta file for writing: "
                . $outfile . "\n";
            exit(1);
        }
    }
    
    if (!is_null($xmlt)) {
        
        $dom_prelim = new DOMDocument();
        $dom_prelim->load($xmlt);
        
    }
    
    // Hack!!!  Have not determined how to pull long name and institution.
    $person_longname = "Sweeney, Aaron";
    $person_id = "";
    $person_institution = "Scripps Institution of Oceanography";
    $person_institution_id = "edu.ucsd.sio";
    
    $dom = new DOMDocument();
    $dom->load($navtemplatefile);
    
    $mapping = array(
        "//r2r:identifier" => $dataObject->cruiseid . "_"
            . $dataObject->device->R2R_fileset_id . "_qa",
        "//r2r:cruise_id" => $dataObject->cruiseid,
        "//r2r:fileset_id" => $dataObject->device->R2R_fileset_id,
        "//r2r:create_time" => $dataObject->creation_date,
        "//r2r:create_person" => $person_longname,
        "//r2r:create_institution" => $person_institution,
        "//r2r:parameter[@name='data_starttime']" 
            => $dataObject->processing_parameters->datetime_start_UTC,
        "//r2r:parameter[@name='data_endtime']" 
            => $dataObject->processing_parameters->datetime_end_UTC,
        "//r2r:parameter[@name='speed_threshold']" => array(
            "value" => $dataObject->processing_parameters->speed_threshold->value,
            "uom" => $dataObject->processing_parameters->speed_threshold->uom
        ),
        "//r2r:parameter[@name='acceleration_threshold']" => array(
            "value" => $dataObject
                ->processing_parameters
                ->acceleration_threshold
                ->value,
            "uom" => $dataObject->processing_parameters->acceleration_threshold->uom
        ),
        "//r2r:parameter[@name='gap_threshold']" => array( 
            "value" => $dataObject->processing_parameters->gap_threshold->value,
            "uom" => $dataObject->processing_parameters->gap_threshold->uom
        ),
        "//r2r:info[@name='first_epoch']/r2r:value" 
            => $dataObject->duration_and_range_of_values->First_Epoch,
        "//r2r:info[@name='last_epoch']/r2r:value" 
            => $dataObject->duration_and_range_of_values->Last_Epoch,
        "//r2r:info[@name='epoch_interval']/r2r:value" => array( 
            "value" => $dataObject
                ->duration_and_range_of_values->Epoch_Interval->value,
            "uom" => $dataObject->duration_and_range_of_values->Epoch_Interval->uom
        ),
        "//r2r:info[@name='number_of_satellites']/r2r:bounds/r2r:bound[@name='maximum']"
            => $dataObject->duration_and_range_of_values
                ->Maximum_Number_of_Satellites,
        "//r2r:info[@name='number_of_satellites']/r2r:bounds/r2r:bound[@name='minimum']"
            => $dataObject->duration_and_range_of_values
                ->Minimum_Number_of_Satellites,
        "//r2r:info[@name='HDOP']/r2r:bounds/r2r:bound[@name='maximum']"
            => $dataObject->duration_and_range_of_values->Maximum_HDOP,
        "//r2r:info[@name='HDOP']/r2r:bounds/r2r:bound[@name='minimum']"
            => $dataObject->duration_and_range_of_values->Minimum_HDOP,
        "//r2r:info[@name='altitude']/r2r:bounds/r2r:bound[@name='maximum']" 
            => array( 
                "value" => $dataObject
                    ->duration_and_range_of_values->Maximum_Altitude->value,	
                "uom" => $dataObject
                    ->duration_and_range_of_values->Maximum_Altitude->uom
            ),
        "//r2r:info[@name='altitude']/r2r:bounds/r2r:bound[@name='minimum']"
            => array( 
                "value" => $dataObject
                    ->duration_and_range_of_values->Minimum_Altitude->value,
                "uom" => $dataObject
                    ->duration_and_range_of_values->Minimum_Altitude->uom
            ),
        "//r2r:info[@name='horizontal_speed']/r2r:bounds/r2r:bound[@name='maximum']"
            => array( 
                "value" => $dataObject
                    ->duration_and_range_of_values->Maximum_Horizontal_Speed->value,
                "uom" => $dataObject
                    ->duration_and_range_of_values->Maximum_Horizontal_Speed->uom
            ),
        "//r2r:info[@name='horizontal_speed']/r2r:bounds/r2r:bound[@name='minimum']"
            => array( 
                "value" => $dataObject
                    ->duration_and_range_of_values->Minimum_Horizontal_Speed->value,
                "uom" => $dataObject
                    ->duration_and_range_of_values->Minimum_Horizontal_Speed->uom
            ),
        "//r2r:info[@name='horizontal_acceleration']/r2r:bounds/r2r:bound[@name='maximum']"
            => array( 
                "value" => $dataObject
                    ->duration_and_range_of_values
                    ->Maximum_Horizontal_Acceleration->value,
                "uom" => $dataObject
                    ->duration_and_range_of_values
                    ->Maximum_Horizontal_Acceleration->uom
            ),
        "//r2r:info[@name='horizontal_acceleration']/r2r:bounds/r2r:bound[@name='minimum']"
            => array( 
                "value" => $dataObject
                    ->duration_and_range_of_values
                    ->Minimum_Horizontal_Acceleration->value,
                "uom" => $dataObject
                    ->duration_and_range_of_values
                    ->Minimum_Horizontal_Acceleration->uom
            ),
        "//r2r:info[@name='northBoundLatitude']/r2r:value"
            => $dataObject->duration_and_range_of_values->northBoundLatitude,
        "//r2r:info[@name='southBoundLatitude']/r2r:value"
            => $dataObject->duration_and_range_of_values->southBoundLatitude,
        "//r2r:info[@name='westBoundLongitude']/r2r:value"
            => $dataObject->duration_and_range_of_values->westBoundLongitude,
        "//r2r:info[@name='eastBoundLongitude']/r2r:value"
            => $dataObject->duration_and_range_of_values->eastBoundLongitude,
        "//r2r:info[@name='number_of_gaps_longer_than_threshold']/r2r:value"
            => $dataObject->quality_assessment->number_of_gaps_longer_than_threshold,
        "//r2r:test[@name='percent_completeness']/r2r:value"
            => $dataObject->quality_assessment->percent_completeness,
        "//r2r:test[@name='longest_epoch_gap']/r2r:value" => array( 
            "value" => $dataObject->quality_assessment->longest_epoch_gap->value,
            "uom" => $dataObject->quality_assessment->longest_epoch_gap->uom
        ),
        "//r2r:test[@name='percent_records_out_of_sequence']/r2r:value"
            => sprintf(
                "%0.2f", 100.0 * $dataObject
                    ->quality_assessment->number_of_epochs_out_of_sequence 
                / $dataObject->duration_and_range_of_values
                    ->Actual_Number_of_Epochs_with_Observations 
            ),
        "//r2r:test[@name='percent_records_with_bad_gps_quality_indicator']/r2r:value"
            => sprintf(
                "%0.2f", 100.0 * $dataObject
                    ->quality_assessment
                    ->number_of_epochs_with_bad_gps_quality_indicator
                / $dataObject->duration_and_range_of_values
                    ->Actual_Number_of_Epochs_with_Observations 
            ),
        "//r2r:test[@name='percent_unreasonable_speeds']/r2r:value"
            => sprintf(
                "%0.2f", 100.0 * $dataObject
                    ->quality_assessment
                    ->number_of_horizontal_speeds_exceeding_threshold 
                / ($dataObject->duration_and_range_of_values
                    ->Actual_Number_of_Epochs_with_Observations - 1)
            ),
        "//r2r:test[@name='percent_unreasonable_accelerations']/r2r:value"
            => sprintf(
                "%0.2f", 100.0 * $dataObject
                    ->quality_assessment
                    ->number_of_horizontal_accelerations_exceeding_threshold 
                / ($dataObject->duration_and_range_of_values
                    ->Actual_Number_of_Epochs_with_Observations - 2) 
            )
    );
    
    //  print_r($mapping);
    
    $xpath = new DOMXPath($dom);
    
    foreach ($mapping as $query => $value) {
        
        $elements = $xpath->query($query);
        if (is_array($value)) {
            $elements->item(0)->nodeValue = $value["value"];
            $elements->item(0)->setAttribute("uom", $value["uom"]);
        } else {
            $elements->item(0)->nodeValue = $value;
        }
    }
    
    $query = "//r2r:create_process";
    $elements = $xpath->query($query);
    $elements->item(0)->nodeValue = "navmanager.php";
    $elements->item(0)->setAttribute("version", "0.9");
    
    $query = "//r2r:create_time";
    $elements = $xpath->query($query);
    $elements->item(0)->nodeValue = gmdate("Y-m-d\TH:i:s\Z");
    $elements->item(0)->removeAttribute("xsi:nil");
    
    $query = "//r2r:create_person";
    $elements = $xpath->query($query);
    $elements->item(0)->setAttribute("id", $person_id);
    
    $query = "//r2r:create_institution";
    $elements = $xpath->query($query);
    $elements->item(0)->setAttribute("id", $person_institution_id);
    
    if (!is_null($xmlt)) {
        
        $elements = $xpath->query("//r2r:create_process");
        $node_old = $dom_prelim->getElementsByTagName("update_process")->item(0);
        $node_old->nodeValue = $elements->item(0)->nodeValue;
        $node_old->setAttribute(
            "version", $elements->item(0)->getAttribute("version")
        );
        
        $elements = $xpath->query("//r2r:create_time");
        $node_old = $dom_prelim->getElementsByTagName("update_time")->item(0);
        $node_old->nodeValue = $elements->item(0)->nodeValue;
        $node_old->removeAttribute("xsi:nil");
        
        $elements = $xpath->query("//r2r:certificate");
        $node_new = $dom_prelim->importNode($elements->item(0), true);
        $node_old = $dom_prelim->getElementsByTagName("certificate")->item(0);
        $node_old->parentNode->replaceChild($node_new, $node_old);
        
        $elements = $xpath->query("//r2r:configuration");
        $node_new = $dom_prelim->importNode($elements->item(0), true);
        $node_old = $dom_prelim->getElementsByTagName("configuration")->item(0);
        $node_old->parentNode->replaceChild($node_new, $node_old);
        
        $dom_prelim->save($outfile);
        
    } else {
        
        $dom->save($outfile);
        
    }
    
}  // end function xml_write( $outfile, $dataObject )


/**
 * Add ratings to navigation quality assessment report 
 *
 * @param string $outfile Filename of quality assessment report
 */
function xml_navratings($outfile) 
{
    $dom = new DOMDocument;
    $dom->load($outfile);
    
    $xpath = new DOMXPath($dom);
    
    $ratings = array(
        "overall" => array(
            "description" => "GREEN (G) if all tests GREEN, RED (R) if at least "
                . " one test RED, else YELLOW (Y).",
            "G/Y" => null,
            "Y/R" => null
        ),
        "percent_completeness" => array( 
            "description" => "GREEN (G) if greater than 95% complete, YELLOW (Y) "
            . "if less than or equal to 95% complete and greater than 75% complete,"
            . " RED (R) if less than or equal to 75% complete.", 
            "G/Y" => 95,
            "Y/R" => 75 ),
        "longest_epoch_gap" => array( 
            "description" => "GREEN (G) if no gaps greater than 15 minutes, RED (R)"
            . " if one or more gaps greater than or equal to 24 hours, else YELLOW"
            . " (Y).", 
            "G/Y" => 900,
            "Y/R" => 24*3600),
        "percent_records_out_of_sequence" => array( 
            "description" => "GREEN (G) if less than 5%, YELLOW (Y) if greater than "
            . "or equal to 5% and less than 10%, RED (R) if greater than or equal to"
            . " 10%.", 
            "G/Y" => 5,
            "Y/R" => 10 ),
        "percent_records_with_bad_gps_quality_indicator" => array( 
            "description" => "GREEN (G) if less than 5%, YELLOW (Y) if greater than "
            . "or equal to 5% and less than 10%, RED (R) if greater than or equal to"
            . " 10%.", 
            "G/Y" => 5,
            "Y/R" => 10 ),
        "percent_unreasonable_speeds" => array( 
            "description" => "GREEN (G) if less than 5%, YELLOW (Y) if greater than "
            . "or equal to 5% and less than 10%, RED (R) if greater than or equal to"
            . " 10%.", 
            "G/Y" => 5,
            "Y/R" => 10 ),
        "percent_unreasonable_accelerations" => array( 
            "description" => "GREEN (G) if less than 5%, YELLOW (Y) if greater than "
            . "or equal to 5% and less than 10%, RED (R) if greater than or equal to"
            . " 10%.", 
            "G/Y" => 5,
            "Y/R" => 10 )
    );
    
    // Initialize overall rating:
    $overall_rating = "G";
    
    //----- percent_completeness -----//
    
    $query = "//r2r:test[@name='percent_completeness']/r2r:value";
    $test_value = $xpath->query($query);
    
    $query = "//r2r:test[@name='percent_completeness']/r2r:rating";
    $test_rating = $xpath->query($query);
    
    $test_rating->item(0)->setAttribute(
        "description", $ratings["percent_completeness"]["description"]
    );
    
    if ($test_value->item(0)->nodeValue > $ratings["percent_completeness"]["G/Y"]) {
        $test_rating->item(0)->nodeValue = "G";
    } else {
        if ($test_value->item(0)->nodeValue <= $ratings["percent_completeness"]["Y/R"]) {
            $test_rating->item(0)->nodeValue = "R";
            $overall_rating = "R";
        } else {
            $test_rating->item(0)->nodeValue = "Y";
            if ($overall_rating != "R") {
                $overall_rating = "Y";
            }
        }
    }
    
    //----- longest_epoch_gap -----//
    
    $query = "//r2r:test[@name='longest_epoch_gap']/r2r:value";
    $test_value = $xpath->query($query);
    
    $query = "//r2r:test[@name='longest_epoch_gap']/r2r:rating";
    $test_rating = $xpath->query($query);
    
    $test_rating->item(0)->setAttribute("description", $ratings["longest_epoch_gap"]["description"]) ;
    
    if ($test_value->item(0)->nodeValue < $ratings["longest_epoch_gap"]["G/Y"]) {
        $test_rating->item(0)->nodeValue = "G";
    } else {
        if ($test_value->item(0)->nodeValue >= $ratings["longest_epoch_gap"]["Y/R"]) {
            $test_rating->item(0)->nodeValue = "R";
            $overall_rating = "R";
        } else {
            $test_rating->item(0)->nodeValue = "Y";
            if ($overall_rating != "R") {
                $overall_rating = "Y";
            }
        }
    }
    
    //----- percent_records_out_of_sequence -----//
    
    $query = "//r2r:test[@name='percent_records_out_of_sequence']/r2r:value";
    $test_value = $xpath->query($query);
    
    $query = "//r2r:test[@name='percent_records_out_of_sequence']/r2r:rating";
    $test_rating = $xpath->query($query);
    
    $test_rating->item(0)->setAttribute(
        "description", $ratings["percent_records_out_of_sequence"]["description"]
    );
    
    if ($test_value->item(0)->nodeValue < $ratings["percent_records_out_of_sequence"]["G/Y"]) {
        $test_rating->item(0)->nodeValue = "G";
    } else {
        if ($test_value->item(0)->nodeValue >= $ratings["percent_records_out_of_sequence"]["Y/R"]) {
            $test_rating->item(0)->nodeValue = "R";
            $overall_rating = "R";
        } else {
            $test_rating->item(0)->nodeValue = "Y";
            if ($overall_rating != "R") {
                $overall_rating = "Y";
            }
        }
    }
    
    //----- percent_records_with_bad_gps_quality_indicator -----//
    
    $query 
        = "//r2r:test[@name='percent_records_with_bad_gps_quality_indicator']/r2r:value";
    $test_value = $xpath->query($query);
    
    $query 
        = "//r2r:test[@name='percent_records_with_bad_gps_quality_indicator']/r2r:rating";
    $test_rating = $xpath->query($query);
    
    $test_rating->item(0)->setAttribute(
        "description", 
        $ratings["percent_records_with_bad_gps_quality_indicator"]["description"]
    );
    
    if ($test_value->item(0)->nodeValue < $ratings["percent_records_with_bad_gps_quality_indicator"]["G/Y"]) {
        $test_rating->item(0)->nodeValue = "G";
    } else {
        if ($test_value->item(0)->nodeValue >= $ratings["percent_records_with_bad_gps_quality_indicator"]["Y/R"]) {
            $test_rating->item(0)->nodeValue = "R";
            $overall_rating = "R";
        } else {
            $test_rating->item(0)->nodeValue = "Y";
            if ($overall_rating != "R") {
                $overall_rating = "Y";
            }
        }
    }
    
    //----- percent_unreasonable_speeds -----//
    
    $query = "//r2r:test[@name='percent_unreasonable_speeds']/r2r:value";
    $test_value = $xpath->query($query);
    
    $query = "//r2r:test[@name='percent_unreasonable_speeds']/r2r:rating";
    $test_rating = $xpath->query($query);
    
    $test_rating->item(0)->setAttribute(
        "description", $ratings["percent_unreasonable_speeds"]["description"]
    );
    
    if ($test_value->item(0)->nodeValue < $ratings["percent_unreasonable_speeds"]["G/Y"]) {
        $test_rating->item(0)->nodeValue = "G";
    } else {
        if ($test_value->item(0)->nodeValue >= $ratings["percent_unreasonable_speeds"]["Y/R"]) {
            $test_rating->item(0)->nodeValue = "R";
            $overall_rating = "R";
        } else {
            $test_rating->item(0)->nodeValue = "Y";
            if ($overall_rating != "R") {
                $overall_rating = "Y";
            }
        }
    }
    
    //----- percent_unreasonable_accelerations -----//
    
    $query = "//r2r:test[@name='percent_unreasonable_accelerations']/r2r:value";
    $test_value = $xpath->query($query);
    
    $query = "//r2r:test[@name='percent_unreasonable_accelerations']/r2r:rating";
    $test_rating = $xpath->query($query);
    
    $test_rating->item(0)->setAttribute(
        "description", $ratings["percent_unreasonable_accelerations"]["description"]
    );
    
    if ($test_value->item(0)->nodeValue < $ratings["percent_unreasonable_accelerations"]["G/Y"]) {
        $test_rating->item(0)->nodeValue = "G";
    } else {
        if ($test_value->item(0)->nodeValue >= $ratings["percent_unreasonable_accelerations"]["Y/R"]) {
            $test_rating->item(0)->nodeValue = "R";
            $overall_rating = "R";
        } else {
            $test_rating->item(0)->nodeValue = "Y";
            if ($overall_rating != "R") {
                $overall_rating = "Y";
            }
        }
    }
    
    //----- Overall Rating -----//
    
    $query = "//r2r:certificate/r2r:rating";
    $test_rating = $xpath->query($query);
    
    $test_rating->item(0)->setAttribute(
        "description", $ratings["overall"]["description"]
    );
    $test_rating->item(0)->nodeValue = $overall_rating;
    
    //----- Save results -----//
    
    $dom->save($outfile);
    
} // end function xml_navratings($outfile)


/**
 * Create R2R nav quality assessment report in XML format
 * for case when no data for cruise is present
 *
 * @param object $dataObject      Quality Assessment object
 * @param string $outfile         Output filename
 * @param string $navtemplatefile Output empty template filename
 * @param string $xmlt            Optional partly filled template filename 
 *                                 (default is null)
 */
function xml_write_special($dataObject, $outfile, $navtemplatefile, $xmlt = null) 
{  
    // In case of no data between cruise dates, do not include the blocks 
    // for "tests" nor for "infos".
    
    if ($outfile != null) {
        $fmeta = fopen($outfile, "w");
        if ($fmeta == null) {
            echo "xml_write(): Could not open meta file for writing: "
                . $outfile . "\n";
            exit(1);
        }              
    }                
    
    if (!is_null($xmlt)) {
        
        $dom_prelim = new DOMDocument();
        $dom_prelim->load($xmlt);
        
    }                
    // Hack!!!  Have not determined how to pull long name and institution.
    $person_longname = "Sweeney, Aaron";
    $person_id = "";
    $person_institution = "Scripps Institution of Oceanography";
    $person_institution_id = "edu.ucsd.sio";                                    
    $dom = new DOMDocument();
    $dom->load($navtemplatefile);
    
    $mapping = array(
        "//r2r:identifier" => $dataObject->cruiseid . "_"
            . $dataObject->device->R2R_fileset_id . "_qa",
        "//r2r:cruise_id" => $dataObject->cruiseid,
        "//r2r:fileset_id" => $dataObject->device->R2R_fileset_id,
        "//r2r:create_time" => $dataObject->creation_date,
        "//r2r:create_person" => $person_longname,
        "//r2r:create_institution" => $person_institution,
        "//r2r:parameter[@name='data_starttime']" 
            => $dataObject->processing_parameters->datetime_start_UTC,
        "//r2r:parameter[@name='data_endtime']"
            => $dataObject->processing_parameters->datetime_end_UTC,
        "//r2r:parameter[@name='speed_threshold']" => array(
            "value" => $dataObject->processing_parameters->speed_threshold->value,
            "uom" => $dataObject->processing_parameters->speed_threshold->uom
        ),
        "//r2r:parameter[@name='acceleration_threshold']" => array(
            "value" => $dataObject
                ->processing_parameters->acceleration_threshold->value,
            "uom" => $dataObject
                ->processing_parameters->acceleration_threshold->uom
        ),
        "//r2r:parameter[@name='gap_threshold']" => array(
            "value" => $dataObject->processing_parameters->gap_threshold->value,
            "uom" => $dataObject->processing_parameters->gap_threshold->uom
        )
    );
    
    $xpath = new DOMXPath($dom);
    
    foreach ($mapping as $query => $value) {
        
        $elements = $xpath->query($query);
        if (is_array($value)) {
            $elements->item(0)->nodeValue = $value["value"];
            $elements->item(0)->setAttribute("uom", $value["uom"]);
        } else {
            $elements->item(0)->nodeValue = $value;
        }
    }
    
    $query = "//r2r:create_process";
    $elements = $xpath->query($query);
    $elements->item(0)->nodeValue = "navmanager.php";
    $elements->item(0)->setAttribute("version", "0.9");
    
    $query = "//r2r:create_time";
    $elements = $xpath->query($query);
    $elements->item(0)->nodeValue = gmdate("Y-m-d\TH:i:s\Z");
    $elements->item(0)->removeAttribute("xsi:nil");
    
    $query = "//r2r:create_person";
    $elements = $xpath->query($query);
    $elements->item(0)->setAttribute("id", $person_id);
    
    $query = "//r2r:create_institution";
    $elements = $xpath->query($query);
    $elements->item(0)->setAttribute("id", $person_institution_id);
    
    if (!is_null($xmlt)) {
        
        $elements = $xpath->query("//r2r:create_process");
        $node_old = $dom_prelim->getElementsByTagName("update_process")->item(0);
        $node_old->nodeValue = $elements->item(0)->nodeValue;
        $node_old->setAttribute(
            "version", $elements->item(0)->getAttribute("version")
        );
        
        $elements = $xpath->query("//r2r:create_time");
        $node_old = $dom_prelim->getElementsByTagName("update_time")->item(0);
        $node_old->nodeValue = $elements->item(0)->nodeValue;
        $node_old->removeAttribute("xsi:nil");

        //----- Overall Rating -----//
        
        $query = "//r2r:certificate/r2r:rating";
        $test_rating = $xpath->query($query);
        
        $test_rating->item(0)->setAttribute(
            "description", "RED (R): No data for this cruise."
        );
        $test_rating->item(0)->nodeValue = "R";
        $node_old = $dom_prelim->getElementsByTagName("rating")->item(0);
        $node_old->setAttribute("description", "RED (R): No data for this cruise.");
        $node_old->nodeValue = $test_rating->item(0)->nodeValue;
        
        //$elements = $xpath->query("//r2r:certificate");
        //$node_new = $dom_prelim->importNode($elements->item(0), true);
        //$node_old = $dom_prelim->getElementsByTagName("certificate")->item(0);
        //$node_old->parentNode->replaceChild($node_new, $node_old);
        
        $elements = $xpath->query("//r2r:configuration");
        $node_new = $dom_prelim->importNode($elements->item(0), true);
        $node_old = $dom_prelim->getElementsByTagName("configuration")->item(0);
        $node_old->parentNode->replaceChild($node_new, $node_old);
        
        //----- Overall Rating -----//
        
        //$query = "//r2r:certificate/r2r:rating";
        //$test_rating = $xpath->query($query);
        
        //$test_rating->item(0)->setAttribute(
        //    "description", "RED (R) because no data betweeen cruise dates."
        //);
        //$test_rating->item(0)->nodeValue = "R";
        
        $dom_prelim->save($outfile);
        
    } else {
        
        $dom->save($outfile);
        
    } 
    
}  // end function xml_write_special($outfile, $dataObject)
?>
