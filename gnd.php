<?php

/**
 * Send GND-ID requests to serveral services which return JSON objects for that id; 
 * Merge results into one JSON object; return error message if there is no valid merge.
 *
 * script makes use of:
 *  - easyrdf library by  Nicholas Humfrey  (http://www.easyrdf.org/)
 *  - curl multirequest from Stoyan Stefanov (http://www.phpied.com/simultaneuos-http-requests-in-php-with-curl/)
 * 
 * Example: http://localhost/gnd.php?query=118587943&services=cult,dnb,wiki&debug=Y
 * 
 * @author Johannes Hercher <hercher@ub.fu-berlin.de>
 * @author Christoph Krempe <krempe@ub.fu-berlin.de>
 * @version 0.1
 * 
 */

require_once "multirequest.inc";

/**
 * *********************************************************************************
 */
// url root to sub requests
$approot = '/gndProxy';
$appurl = 'http://localhost/' . $approot . '/';
$actualServices = array('dnb' => 'dnb', 'cult' => 'culturgraph', 'wiki' => 'wikidata');
$requestExample = 'http://localhost/' . $approot . '/gnd.php?query=118587943&services=' . $actualServices . '&debug=Y';
/**
 * *********************************************************************************
 */
// get parameters
$gnd = filter_input(INPUT_GET, 'query', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
$servicesIn = filter_input(INPUT_GET, 'services', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH); 
$debug = filter_input(INPUT_GET, 'debug', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
$lang = filter_input(INPUT_GET, 'lang', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH); 
$callback = filter_input(INPUT_GET, 'jsoncallback', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
//$gnd = $_REQUEST['query'];//$services = $_REQUEST['services'];//$debug = $_REQUEST['debug'];//$lang= $_REQUEST['lang'];
//TODO: capture request errors
if (!isset($debug)) {
    $debug = 'N';
}
if (!isset($gnd) || empty($gnd)) {
    gnderror("gnd not set", $requestExample);
}

if ($gnd == 'help' || $gnd == 'hilfe') {
    echo 'example: ' . $requestExample;
    exit();
}

if (!isset($servicesIn) || empty($servicesIn)) {
    gnderror("services not set", $requestExample);
}
$services = explode(',', $servicesIn);
$allowedservices = array('dnb', 'wiki', 'cult');
// create array of urls of the sub requests
$data = array();
foreach ($services as $service) {
    if (!in_array($service, $allowedservices)) {
        gnderror("service $service not allowed!", $requestExample);
    }
    if (isset($actualServices[$service])) {
        $data[$service] = $appurl . $actualServices[$service] . '.php?query=' . $gnd . '&lang=' . $lang;
    } else {
        gnderror("service $service not found", $requestExample);
    }
}

/**
 * $data = array(
 * 'http://d-nb.info/gnd/118540238/about/lds',
 * 'http://hub.culturegraph.org/entityfacts/118540238',
 * 'http://de.dbpedia.org/data/Johann_Wolfgang_von_Goethe.json'
 * );
 */
// start requests
$results = multiRequest($data);

$r = array();
//array  of returned fields
//eg. $r['preferredNameForThePerson'] = ''

$fields = array(
    'type',
    'wikipage',
    'img',
    'homepage',
    'definition',
    'gndId',
    'synSearch',
    'description', // descriptive text from Wikipedia
    'gndSearchAllBooksUri',
    'name',
    'altname',
    'academicDegree',
    'since',
    'until',
    'professions',
    'relations',
    'friends',
    'sameAs',
    'relatedDdc',
);

if ($debug == "Y") {
    echo 'REQUEST: ' . filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH) . '<br><br>';
}

foreach ($fields as $f) {
    $isSetVarName = $f . 'IsSet';
    //echo $isSetVarName . '<br>';
    $$isSetVarName = FALSE;
}

foreach ($results as $key => $content) {
    if ($debug == "Y") {
        // echo "\$r[$key] => $content.\n";
        $uri = $data[$key];
        echo "[$key] request URI: $uri\n\n";
        echo $content . "\n\n";
    }
    $obj = json_decode($content);
    foreach ($fields as $f) {
        //echo "Field: " . $f . '<br>';
        $isSetVarName = $f . 'IsSet';
        if (isset($obj->$f) && !$$isSetVarName) {
            $r[$f] = $obj->$f;
            $$isSetVarName = TRUE;
            if ($debug == 'Y') {
                echo "&nbsp;&nbsp;==&gt; take" . $f . " from [$key]<br><br>";
            }
        }
    }
}

if ($debug == "Y") {

    echo "RESULT: \n\n";
}

if ($resultIsEmpty) {
    $error = array();
    $error['error'] = "no result for gnd = " . $gnd;
    header('Content-Type: application/json');
    echo json_encode($error);
} else {
    if (isset($callback)) {
        header('Content-Type: application/json');
        print $callback . '(' . json_encode($r) . ')';
    } else {
        header('Content-Type: application/json');
        print json_encode($r);
    }
}

//**********************************************************************************
function gnderror($message, $usage) {
    $error = array();
    $error['error'] = $message;
    $error['usage'] = $usage;
    echo json_encode($error);
    exit();
}
