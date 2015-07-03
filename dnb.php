<?php

/**
 * GND request to DNB (rdfxml files)
 * @return json
 * 
 * script makes use of:
 *  - easyrdf library by  Nicholas Humfrey  (http://www.easyrdf.org/)
 *  - curl multirequest from Stoyan Stefanov (http://www.phpied.com/simultaneuos-http-requests-in-php-with-curl/)
 * 
 * Example: http://localhost/dnb.php?query=118587943&services=cult,dnb,wiki&debug=Y
 * 
 * @author Johannes Hercher <hercher@ub.fu-berlin.de>
 * @author Christoph Krempe <krempe@ub.fu-berlin.de>
 * @version 0.1
 */

set_include_path(get_include_path() . PATH_SEPARATOR . 'easyrdf/lib/');
require_once "easyrdf/lib/EasyRdf.php";

//$gnd = '118540238';
//$gnd = '2004374-0'; //BVG

$gnd = filter_input(INPUT_GET, 'query', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);

if (!isset($gnd)) {
    echo "gnd not set";
    exit;
}

$uri = 'http://d-nb.info/gnd/' . $gnd;

// setup namespaces
// standard
EasyRdf_Namespace::set('owl', 'http://www.w3.org/2002/07/owl#');
EasyRdf_Namespace::set('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
EasyRdf_Namespace::set('rdfs', 'http://www.w3.org/2000/01/rdf-schema#');
// dnb
EasyRdf_Namespace::set('gnd', 'http://d-nb.info/standards/elementset/gnd#');
EasyRdf_Namespace::set('bibo', 'http://purl.org/ontology/bibo/');
EasyRdf_Namespace::set('dct', 'http://purl.org/dc/terms/');
EasyRdf_Namespace::set('dc', 'http://purl.org/dc/elements/1.1/');
EasyRdf_Namespace::set('skos', 'http://www.w3.org/2004/02/skos/core#');
EasyRdf_Namespace::set('isbd', 'http://iflastandards.info/ns/isbd/elements/');

$rdf = EasyRdf_Graph::newAndLoad($uri);

/** DNB returns error page if GND not found, no return code;
 * so we cannot catch an error
 * */
if (!$rdf) {
    $error = array();
    $error['error'] = "DNB: could not create rdf object";
    echo json_encode($error);
    exit;
}

$typeUri = $rdf->get($uri, "rdf:type");
$typeArr = split("#", $typeUri);
$type = $typeArr[1];

$name = $rdf->join($uri, 'gnd:preferredNameForTheSubjectHeading|'
        . 'gnd:preferredNameForTheSubjectHeadingSensoStricto|'
        . 'gnd:preferredNameForTheCorporateBody|'
        . 'gnd:preferredNameForTheFamily|'
        . 'gnd:preferredNameForThePerson|'
        . 'gnd:preferredNameForThePlaceOrGeographicName|'
        . 'gnd:preferredNameForTheWork|'
        . 'gnd:preferredNameForTheConferenceOrEvent'
);

$altname = $rdf->join($uri, 'gnd:variantNameForTheSubjectHeading|'
        . 'gnd:variantNameForTheSubjectHeadingSensoStricto|'
        . 'gnd:variantNameForTheCorporateBody|'
        . 'gnd:variantNameForTheFamily|'
        . 'gnd:variantNameForThePerson|'
        . 'gnd:variantNameForThePlaceOrGeographicName|'
        . 'gnd:variantNameForTheWork|'
        . 'gnd:variantNameForTheConferenceOrEvent'
        , ', ');

$synSearch = '("' . $name . '") OR (' . $gnd . ') OR ("' . $rdf->join($uri, 'gnd:variantNameForTheSubjectHeading|'
                . 'gnd:variantNameForTheSubjectHeadingSensoStricto|'
                . 'gnd:variantNameForTheCorporateBody|'
                . 'gnd:variantNameForTheFamily|'
                . 'gnd:variantNameForThePerson|'
                . 'gnd:variantNameForThePlaceOrGeographicName|'
                . 'gnd:variantNameForTheWork|'
                . 'gnd:variantNameForTheConferenceOrEvent'
                , '") OR ("') . '")';

$homepage = utf8_encode($rdf->get($uri, "gnd:homepage"));
$definition = $rdf->join($uri, "gnd:definition|gnd:biographicalOrHistoricalInformation");
$gndId = $rdf->join($uri, "gnd:gndIdentifier");
$since = $rdf->join("gnd:dateOfBirth|gnd:dateOfEstablishment|gnd:dateOfConferenceOrEvent|gnd:udkCode", "literal");
$until = $rdf->join("$uri", "gnd:dateOfDeath|gnd:dateOfTermination");
$wikipage = $rdf->join($uri, "foaf:page");
//{TODO} $broaderTermPartitive = utf8_encode($rdf->get("^gnd:broaderTermPartitive")); // Teil von
//{TODO} $broaderTermInstantial = $rdf->get($uri,"gnd:broaderTermInstantial","literal","de"); // Beispiel für       
//{TODO  different indexes for swiRef = Topic in , autRef = autor of , betRef = contributor pubs, intRef = interpreted...  
//{TODO} $fieldOfStudy = $rdf->get($uri, "gnd:fieldOfStudy/gnd:preferredNameForTheSubjectHeading");
//{TODO} DDC
//{TODO} $relatedDdc = $rdf->join("$uri", "gnd:relatedDdcWithDegreeOfDeterminacy1|gnd:relatedDdcWithDegreeOfDeterminacy2|gnd:relatedDdcWithDegreeOfDeterminacy3");  
$r = array();
if (isset($type)       && $type)                { $r['type'] = $type ;}
if (isset($name)       && $name)                { $r['name'] = $name ;}
if (isset($altname)    && $altname !=="")       { $r['altname'] = $altname ;}
if (isset($homepage)   && $homepage !=="")      { $r['homepage'] = $homepage ;}
if (isset($definition) && $definition !== "")   { $r['definition'] = $definition ;}
if (isset($since)      && $since !== "")        { $r['since'] = $since ;}
if (isset($until)      && $until !== "")        { $r['until'] = $until ;}
if (isset($wikipage)   && $wikipage !== "")     { $r['wikipage'] = $wikipage ;}
if (isset($gndId)      && $gndId !== "")        { $r['gndId'] = $gndId ;}
if (isset($synSearch)  && $synSearch !== "")    { $r['synSearch'] = $synSearch ;}
//if (isset($broaderTermInstantial) && $broaderTermInstantial !== ""){ $r['broaderTermInstantial'] = $broaderTermInstantial ;}
//if (isset($broaderTermPartitive)  && $broaderTermPartitive !== ""){ $r['broaderTermPartitive'] = $broaderTermPartitive ;}
//if (isset($relatedDdc)          && $relatedDdc !== ""){ $r['relatedDdc'] = $relatedDdc ;}
//if (isset($gndSearchAllBooksUri)&& $gndSearchAllBooksUri !== ""){ $r['gndSearchAllBooksUri'] = $gndSearchAllBooksUri ;}
//if (isset($fieldOfStudy)        && $fieldOfStudy !== ""){ $r['fieldOfStudy'] = $fieldOfStudy ;}  
//if (isset($geo)                 && $geo !== ""){ $r['geo'] = $geo ;}
   
header('Content-Type: application/json');
echo json_encode($r);