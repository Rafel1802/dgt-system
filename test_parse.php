<?php
require 'vendor/autoload.php';

$csvData = '"Class","Doc Link","Public Link","Dated","Website link","","Class","Doc Link","Public Link","Dated","Website link","","Class","Doc Link","Public Link","Dated","Website link","","Class","Doc Link","Public Link","Dated","Website link","","Class","Doc Link","Public Link","Dated","Website link","","Class","Doc Link","Public Link","Dated","Website link","",""
"3","link ","https://usaforklift.org/how-to-reduce-forklift-downtime-during-busy-shifts/","27/06","usaforklift.org","","2","link ","https://americanforklift.org/how-warehouse-floor-conditions-affect-forklift-performance/","08/07","americanforklift.org","","1","link","https://newexcavatorsforsale.com/choosing-between-long-reach-and-standard-arm-excavators","","newexcavatorsforsale.com","","5","link","https://konstructz.com/blog/mini-excavator-arm-reach-for-different-excavation-tasks","08/22","konstructz.com","","6","link","https://usaforklifts.org/best-bucket-types-for-every-skid-steer-application/","08/22","usaforklifts.org","","7","link","https://stunningmachinery.com/blog/how-a-skid-steer-trencher-makes-trenching-easier","08/22","stunningmachinery.com","",""';

$lines = explode(PHP_EOL, $csvData);
$parsedRows = array_map('str_getcsv', $lines);
$headers = array_shift($parsedRows);

$blocks = [];
foreach ($headers as $idx => $headerName) {
    if (trim($headerName) === 'Class') {
        $blocks[] = [
            'classIdx' => $idx,
            'docLinkIdx' => $idx + 1,
            'publicLinkIdx' => $idx + 2,
            'datedIdx' => $idx + 3,
            'websiteLinkIdx' => $idx + 4,
        ];
    }
}
var_dump($blocks);
