<?php

function xml_write2($dataObject, $outfile, $xmlt_file) {

    $test_counts = 0;
    $tests_green = 0;
    $tests_yellow = 0;
    $tests_red = 0;

    $doc = new DOMDocument("1.0", "utf-8");
    $doc->preserveWhiteSpace = FALSE;
    $doc->formatOutput = TRUE;
    $doc->load($xmlt_file);
    $xpath = new DOMXPath($doc);

    $certificate = $xpath->query("//r2r:certificate")->item(0);
    $tests = $doc->createElement("r2r:tests");

    // percent_completeness
    $val = $dataObject->quality_assessment->percent_completeness;
    $uom = 'Percent';

    switch(true) {
        case $val >= 95:
            $rating = 'G';
            $tests_green++;
            break;
        case $val >= 75:
            $rating = 'Y';
            $tests_yellow++;
            break;
        default:
            $rating = 'R';
            $tests_red++;
            break;
    }

    $test = $doc->createElement("r2r:test");
    $test->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode("PercentCompleteness"));
    $test->appendChild($doc->createElement("r2r:rating"))->appendChild($doc->createTextNode($rating));
    $test_result = $doc->createElement("r2r:test_result");
    $test_result->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode($uom));
    $test_result->appendChild($doc->createTextNode($val));
    $test->appendChild($test_result);
    $bounds = $doc->createElement("r2r:bounds");
    $bound1 = $doc->createElement("r2r:bound");
    $bound1->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('GMinimumThreshold'));
    $bound1->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode($uom));
    $bound1->appendChild($doc->createTextNode('95'));
    $bounds->appendChild($bound1);
    $bound2 = $doc->createElement("r2r:bound");
    $bound2->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('YMinimumThreshold'));
    $bound2->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode($uom));
    $bound2->appendChild($doc->createTextNode('75'));
    $bounds->appendChild($bound2);
    $test->appendChild($bounds);

    $tests->appendChild($test);

    // longest_epoch_gap
    $val = $dataObject->quality_assessment->longest_epoch_gap->value;
    $uom = $dataObject->quality_assessment->longest_epoch_gap->uom;

    switch(true) {
            case $val <= 900: 
                    $rating = 'G';
                    $tests_green++;
                    break;
            case $val <= 86400:
                    $rating = 'Y';
                    $tests_yellow++;
                    break;
            default:
                    $rating = 'R';
                    $tests_red++;
                    break;
    }

    $test = $doc->createElement("r2r:test");
    $test->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode("LongestGapEpoch"));
    $test->appendChild($doc->createElement("r2r:rating"))->appendChild($doc->createTextNode($rating));
    $test_result = $doc->createElement("r2r:test_result");
    $test_result->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode($uom));
    $test_result->appendChild($doc->createTextNode($val));
    $test->appendChild($test_result);
    $bounds = $doc->createElement("r2r:bounds");
    $bound1 = $doc->createElement("r2r:bound");
    $bound1->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('GMaximumThreshold'));
    $bound1->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode($uom));
    $bound1->appendChild($doc->createTextNode('900'));
    $bounds->appendChild($bound1);
    $bound2 = $doc->createElement("r2r:bound");
    $bound2->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('YMaximumThreshold'));
    $bound2->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode($uom));
    $bound2->appendChild($doc->createTextNode('86400'));
    $bounds->appendChild($bound2);
    $test->appendChild($bounds);

    $tests->appendChild($test);

    // percent_records_out_of_sequence
    // percent_completeness
    $val = sprintf(
                    "%0.2f", 100.0 * $dataObject
                        ->quality_assessment->number_of_epochs_out_of_sequence 
                    / $dataObject->duration_and_range_of_values
                        ->Actual_Number_of_Epochs_with_Observations 
                );
    $uom = 'Percent';

    switch(true) {
            case $val <= 5: 
                    $rating = 'G';
                    $tests_green++;
                    break;
            case $val <= 10: 
                    $rating = 'Y';
                    $tests_yellow++;
                    break;
            default:
                    $rating = 'R';
                    $tests_red++;
                    break;
    }

    $test = $doc->createElement("r2r:test");
    $test->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode("PercentRecordsOutOfSequence"));
    $test->appendChild($doc->createElement("r2r:rating"))->appendChild($doc->createTextNode($rating));
    $test_result = $doc->createElement("r2r:test_result");
    $test_result->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode($uom));
    $test_result->appendChild($doc->createTextNode($val));
    $test->appendChild($test_result);
    $bounds = $doc->createElement("r2r:bounds");
    $bound1 = $doc->createElement("r2r:bound");
    $bound1->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('GMaximumThreshold'));
    $bound1->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode($uom));
    $bound1->appendChild($doc->createTextNode('5'));
    $bounds->appendChild($bound1);
    $bound2 = $doc->createElement("r2r:bound");
    $bound2->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('YMaximumThreshold'));
    $bound2->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode($uom));
    $bound2->appendChild($doc->createTextNode('10'));
    $bounds->appendChild($bound2);
    $test->appendChild($bounds);

    $tests->appendChild($test);

    // percent_records_with_bad_quality_indicator
    $val = sprintf(
                    "%0.2f", 100.0 * $dataObject
                        ->quality_assessment
                        ->number_of_epochs_with_bad_gps_quality_indicator
                    / $dataObject->duration_and_range_of_values
                        ->Actual_Number_of_Epochs_with_Observations 
                );
    $uom = 'Percent';

    switch(true) {
            case $val <= 5:  
                    $rating = 'G';
                    $tests_green++;
                    break;
            case $val <= 10: 
                    $rating = 'Y';
                    $tests_yellow++;
                    break;
            default:
                    $rating = 'R';
                    $tests_red++;
                    break;
    }

    $test = $doc->createElement("r2r:test");
    $test->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode("PercentRecordsWithBadQualityIndicator"));
    $test->appendChild($doc->createElement("r2r:rating"))->appendChild($doc->createTextNode($rating));
    $test_result = $doc->createElement("r2r:test_result");
    $test_result->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode($uom));
    $test_result->appendChild($doc->createTextNode($val));
    $test->appendChild($test_result);
    $bounds = $doc->createElement("r2r:bounds");
    $bound1 = $doc->createElement("r2r:bound");
    $bound1->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('GMaximumThreshold'));
    $bound1->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode($uom));
    $bound1->appendChild($doc->createTextNode('5'));
    $bounds->appendChild($bound1);
    $bound2 = $doc->createElement("r2r:bound");
    $bound2->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('YMaximumThreshold'));
    $bound2->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode($uom));
    $bound2->appendChild($doc->createTextNode('10'));
    $bounds->appendChild($bound2);
    $test->appendChild($bounds);

    $tests->appendChild($test);

    // percent_unreasonable_speeds
    $val = sprintf(
                    "%0.2f", 100.0 * $dataObject
                        ->quality_assessment
                        ->number_of_horizontal_speeds_exceeding_threshold 
                    / ($dataObject->duration_and_range_of_values
                        ->Actual_Number_of_Epochs_with_Observations - 1)
                );
    $uom = 'Percent';

    switch(true) {
            case $val <= 5:  
                    $rating = 'G';
                    $tests_green++;
                    break;
            case $val <= 10: 
                    $rating = 'Y';
                    $tests_yellow++;
                    break;
            default:
                    $rating = 'R';
                    $tests_red++;
                    break;
    }

    $test = $doc->createElement("r2r:test");
    $test->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode("PercentUnreasonableSpeeds"));
    $test->appendChild($doc->createElement("r2r:rating"))->appendChild($doc->createTextNode($rating));
    $test_result = $doc->createElement("r2r:test_result");
    $test_result->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode($uom));
    $test_result->appendChild($doc->createTextNode($val));
    $test->appendChild($test_result);
    $bounds = $doc->createElement("r2r:bounds");
    $bound1 = $doc->createElement("r2r:bound");
    $bound1->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('GMaximumThreshold'));
    $bound1->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode($uom));
    $bound1->appendChild($doc->createTextNode('5'));
    $bounds->appendChild($bound1);
    $bound2 = $doc->createElement("r2r:bound");
    $bound2->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('YMaximumThreshold'));
    $bound2->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode($uom));
    $bound2->appendChild($doc->createTextNode('10'));
    $bounds->appendChild($bound2);
    $test->appendChild($bounds);

    $tests->appendChild($test);


    // percent_unreasonable_accelerations
    $val = sprintf(
                    "%0.2f", 100.0 * $dataObject
                        ->quality_assessment
                        ->number_of_horizontal_accelerations_exceeding_threshold
                    / ($dataObject->duration_and_range_of_values
                        ->Actual_Number_of_Epochs_with_Observations - 2)
                );
    $uom = 'Percent';

    switch(true) {
            case $val <= 5:  
                    $rating = 'G';
                    $tests_green++;
                    break;
            case $val <= 10: 
                    $rating = 'Y';
                    $tests_yellow++;
                    break;
            default:
                    $rating = 'R';
                    $tests_red++;
                    break;
    }

    $test = $doc->createElement("r2r:test");
    $test->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode("PercentUnreasonableAccelerations"));
    $test->appendChild($doc->createElement("r2r:rating"))->appendChild($doc->createTextNode($rating));
    $test_result = $doc->createElement("r2r:test_result");
    $test_result->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode($uom));
    $test_result->appendChild($doc->createTextNode($val));
    $test->appendChild($test_result);
    $bounds = $doc->createElement("r2r:bounds");
    $bound1 = $doc->createElement("r2r:bound");
    $bound1->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('GMaximumThreshold'));
    $bound1->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode($uom));
    $bound1->appendChild($doc->createTextNode('5'));
    $bounds->appendChild($bound1);
    $bound2 = $doc->createElement("r2r:bound");
    $bound2->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('YMaximumThreshold'));
    $bound2->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode($uom));
    $bound2->appendChild($doc->createTextNode('10'));
    $bounds->appendChild($bound2);
    $test->appendChild($bounds);

    $tests->appendChild($test);

    // Add up all the test scores and assign a grade
    $tests_total = $tests_green + $tests_yellow + $tests_red;
    if ($tests_total = $tests_green) {
        $rating_full = 'G';
    } elseif ($tests_red >= 1) {
        $rating_full = 'R';
    } else {
        $rating_full = 'Y';
    }

    $rating_element = $xpath->query("//r2r:certificate/r2r:rating")->item(0);
    $rating_element->setAttribute("description", "GREEN (G) if all tests GREEN, RED (R) if at least  one test RED, else YELLOW (Y)");
    $rating_element->nodeValue = $rating_full;
    $test_infos = $doc->createElement('r2r:infos');

    $test_info = $doc->createElement('r2r:info');
    $test_info->setAttribute('name', 'TotalTests');
    $test_info->setAttribute('uom', 'Count');
    $test_info->appendChild($doc->createTextNode($tests_total));
    $test_infos->appendChild($test_info);

    $test_info = $doc->createElement('r2r:info');
    $test_info->setAttribute('name', 'GTests');
    $test_info->setAttribute('uom', 'Count');
    $test_info->appendChild($doc->createTextNode($tests_green));
    $test_infos->appendChild($test_info);

    $test_info = $doc->createElement('r2r:info');
    $test_info->setAttribute('name', 'YTests');
    $test_info->setAttribute('uom', 'Count');
    $test_info->appendChild($doc->createTextNode($tests_yellow));
    $test_infos->appendChild($test_info);

    $test_info = $doc->createElement('r2r:info');
    $test_info->setAttribute('name', 'RTests');
    $test_info->setAttribute('uom', 'Count');
    $test_info->appendChild($doc->createTextNode($tests_red));
    $test_infos->appendChild($test_info);

    $certificate->appendChild($test_infos);
    

    // Now write the tests to the certificate
    $certificate->appendChild($tests);

    // Get the filesetinfo_supplementals block to add additional info
    $filesetinfo_supplementals = $xpath->query("//r2r:filesetinfo_supplementals")->item(0);

    // Time

    // NSV
    $info = $doc->createElement("r2r:filesetinfo_supplemental");
    $info->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('NSV'));
    $ranges = $doc->createElement("r2r:ranges");
    $min = $doc->createElement("r2r:range");
    $min->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('Minimum'));
    $min->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode('Count'));
    $min->appendChild($doc->createTextNode($dataObject->duration_and_range_of_values->Minimum_Number_of_Satellites));
    $ranges->appendChild($min);
    $max = $doc->createElement("r2r:range");
    $max->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('Maximum'));
    $max->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode('Count'));
    $max->appendChild($doc->createTextNode($dataObject->duration_and_range_of_values->Maximum_Number_of_Satellites));
    $ranges->appendChild($max);
    $info->appendChild($ranges);

    $filesetinfo_supplementals->appendChild($info);

    // HDOP
    $info = $doc->createElement("r2r:filesetinfo_supplemental");
    $info->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('HDOP'));
    $ranges = $doc->createElement("r2r:ranges");
    $min = $doc->createElement("r2r:range");
    $min->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('Minimum'));
    $min->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode('Dimensionless'));
    $min->appendChild($doc->createTextNode($dataObject->duration_and_range_of_values->Minimum_HDOP));
    $ranges->appendChild($min);
    $max = $doc->createElement("r2r:range");
    $max->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('Maximum'));
    $max->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode('Dimensionless'));
    $max->appendChild($doc->createTextNode($dataObject->duration_and_range_of_values->Maximum_HDOP));
    $ranges->appendChild($max);
    $info->appendChild($ranges);

    $filesetinfo_supplementals->appendChild($info);

    // Altitude
    $info = $doc->createElement("r2r:filesetinfo_supplemental");
    $info->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('Altitude'));
    $ranges = $doc->createElement("r2r:ranges");
    $min = $doc->createElement("r2r:range");
    $min->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('Minimum'));
    $min->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode($dataObject->duration_and_range_of_values->Minimum_Altitude->uom));
    $min->appendChild($doc->createTextNode($dataObject->duration_and_range_of_values->Minimum_Altitude->value));
    $ranges->appendChild($min);
    $max = $doc->createElement("r2r:range");
    $max->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('Maximum'));
    $max->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode($dataObject->duration_and_range_of_values->Maximum_Altitude->uom));
    $max->appendChild($doc->createTextNode($dataObject->duration_and_range_of_values->Maximum_Altitude->value));
    $ranges->appendChild($max);
    $info->appendChild($ranges);

    $filesetinfo_supplementals->appendChild($info);

    // HorizontalSpeed
    $info = $doc->createElement("r2r:filesetinfo_supplemental");
    $info->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('HorizontalSpeed'));
    $ranges = $doc->createElement("r2r:ranges");
    $min = $doc->createElement("r2r:range");
    $min->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('Minimum'));
    $min->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode($dataObject->duration_and_range_of_values->Minimum_Horizontal_Speed->uom));
    $min->appendChild($doc->createTextNode($dataObject->duration_and_range_of_values->Minimum_Horizontal_Speed->value));
    $ranges->appendChild($min);
    $max = $doc->createElement("r2r:range");
    $max->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('Maximum'));
    $max->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode($dataObject->duration_and_range_of_values->Maximum_Horizontal_Speed->uom));
    $max->appendChild($doc->createTextNode($dataObject->duration_and_range_of_values->Maximum_Horizontal_Speed->value));
    $ranges->appendChild($max);
    $info->appendChild($ranges);

    $filesetinfo_supplementals->appendChild($info);

    // HorizontalAcceleration
    $info = $doc->createElement("r2r:filesetinfo_supplemental");
    $info->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('HorizontalAcceleration'));
    $ranges = $doc->createElement("r2r:ranges");
    $min = $doc->createElement("r2r:range");
    $min->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('Minimum'));
    $min->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode($dataObject->duration_and_range_of_values->Minimum_Horizontal_Acceleration->uom));
    $min->appendChild($doc->createTextNode($dataObject->duration_and_range_of_values->Minimum_Horizontal_Acceleration->value));
    $ranges->appendChild($min);
    $max = $doc->createElement("r2r:range");
    $max->appendChild($doc->createAttribute("name"))->appendChild($doc->createTextNode('Maximum'));
    $max->appendChild($doc->createAttribute("uom"))->appendChild($doc->createTextNode($dataObject->duration_and_range_of_values->Maximum_Horizontal_Acceleration->uom));
    $max->appendChild($doc->createTextNode($dataObject->duration_and_range_of_values->Maximum_Horizontal_Acceleration->value));
    $ranges->appendChild($max);
    $info->appendChild($ranges);

    $filesetinfo_supplementals->appendChild($info);

    // Write the conents to the file
    $doc->save($outfile);
}


?>
