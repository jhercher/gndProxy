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
 * @version 1.1
 */

//set_include_path(get_include_path() . PATH_SEPARATOR . 'easyrdf/lib/');
//require_once "easyrdf/lib/EasyRdf.php";
require 'vendor/autoload.php';

//$gnd = '118540238';
//$gnd = '2004374-0'; //BVG
//$gnd = '4394007-9'; // 68er

/*
check if the URL exists  
*/
function urlExists($url) {
 
   $handle = curl_init($url);
   curl_setopt($handle,  CURLOPT_RETURNTRANSFER, TRUE);
 
   $response = curl_exec($handle);
   $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
 
   if($httpCode >= 200 && $httpCode <= 400) {
       return true; } else { return false;
   }
 
   curl_close($handle);
}

// validate input
$gnd = filter_input(INPUT_GET, 'query', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);

// gnd set?
if (!isset($gnd)) {
    echo "gnd not set";
    exit;
} 
// gnd valid number?
// regexpattern https://www.wikidata.org/wiki/Property_talk:P227
if (!preg_match('/1[012]?\d{7}[0-9X]|[47]\d{6}-\d|[1-9]\d{0,7}-[0-9X]|3\d{7}[0-9X]/iD', $gnd))
{
    $error = array();
    $error['error'] = "GND-ID: not valid";
    echo json_encode($error);
    exit;
}
else
{

$uri = 'https://d-nb.info/gnd/' . $gnd .'/about/rdf' ;
$res = 'https://d-nb.info/gnd/' . $gnd;
//$uri = 'http://d-nb.info/gnd/118540238/about/rdf' ;
//$res = 'http://d-nb.info/gnd/118540238' ;//. $gnd;

//util to convert objectresource values into array 
function pArray($resource, $target = array(), $i=0){
foreach ($resource as $value) {
		   $target[$i] = strval($value);
		   $i++;
	  }
	  return $target;
}

// setup namespaces
// standard
EasyRdf_Namespace::set('owl', 'http://www.w3.org/2002/07/owl#');
EasyRdf_Namespace::set('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
EasyRdf_Namespace::set('rdfs', 'http://www.w3.org/2000/01/rdf-schema#');
EasyRdf_Namespace::set('foaf', 'http://xmlns.com/foaf/0.1/');

// dnb
//EasyRdf_Namespace::set('gnd', 'http://d-nb.info/standards/elementset/gnd#'); DNB changed namespace
EasyRdf_Namespace::set('gnd', 'https://d-nb.info/standards/elementset/gnd#');
EasyRdf_Namespace::set('bibo', 'http://purl.org/ontology/bibo/');
EasyRdf_Namespace::set('dct', 'http://purl.org/dc/terms/');
EasyRdf_Namespace::set('dc', 'http://purl.org/dc/elements/1.1/');
EasyRdf_Namespace::set('skos', 'http://www.w3.org/2004/02/skos/core#');
EasyRdf_Namespace::set('isbd', 'http://iflastandards.info/ns/isbd/elements/');
EasyRdf_Namespace::set('gndsc', 'https://d-nb.info/standards/vocab/gnd/gnd-sc#');

 
if (!urlExists($uri)){

$error = array();
     $error['error'] = "GND-ID: not exists";
     echo json_encode($error);
     exit;

}else{

$rdf = EasyRdf_Graph::newAndLoad($uri);


if (!$rdf) {
    $error = array();
    $error['error'] = "DNB: could not create rdf object";
    echo json_encode($error);
    exit;
}

$typeUri = $rdf->get($res, "rdf:type");
$typeArr = explode("#", $typeUri);
$type = $typeArr[1];

$name = $rdf->join($res, 'gnd:preferredNameForTheSubjectHeading|'
        . 'gnd:preferredNameForTheSubjectHeadingSensoStricto|'
        . 'gnd:preferredNameForTheCorporateBody|'
        . 'gnd:preferredNameForTheFamily|'
        . 'gnd:preferredNameForThePerson|'
        . 'gnd:preferredNameForThePlaceOrGeographicName|'
        . 'gnd:preferredNameForTheWork|'
        . 'gnd:preferredNameForTheConferenceOrEvent'
);

$altname = $rdf->join($res, 'gnd:variantNameForTheSubjectHeading|'
        . 'gnd:variantNameForTheSubjectHeadingSensoStricto|'
        . 'gnd:variantNameForTheCorporateBody|'
        . 'gnd:variantNameForTheFamily|'
        . 'gnd:variantNameForThePerson|'
        . 'gnd:variantNameForThePlaceOrGeographicName|'
        . 'gnd:variantNameForTheWork|'
        . 'gnd:variantNameForTheConferenceOrEvent'
        , ', ');

if (isset($name) && $name !==""){ // TODO: sometimes an old gndid is queried and dnb redirects to new, 
                                  //in this case $name is unset. gndProxy should not deliver results then. e.g. 4113236-1
$synSearch = '("' . $name . '") OR (' . $gnd . ') OR ("' . $rdf->join($res, 'gnd:variantNameForTheSubjectHeading|'
                . 'gnd:variantNameForTheSubjectHeadingSensoStricto|'
                . 'gnd:variantNameForTheCorporateBody|'
                . 'gnd:variantNameForTheFamily|'
                . 'gnd:variantNameForThePerson|'
                . 'gnd:variantNameForThePlaceOrGeographicName|'
                . 'gnd:variantNameForTheWork|'
                . 'gnd:variantNameForTheConferenceOrEvent'
                , '") OR ("') . '")';
}
$homepage = utf8_encode($rdf->get($res, "gnd:homepage"));
$definition = $rdf->join($res, "gnd:definition|gnd:biographicalOrHistoricalInformation");
$gndId = $rdf->join($res, "gnd:gndIdentifier");
$placeOfBirth =utf8_encode($rdf->get($res, "gnd:placeOfBirth"));
$since = $rdf->join($res, "gnd:dateOfBirth|gnd:dateOfEstablishment|gnd:dateOfConferenceOrEvent|gnd:udkCode", "literal");
$until = $rdf->join($res, "gnd:dateOfDeath|gnd:dateOfTermination");
$wikipage = utf8_encode($rdf->get($res, "foaf:page"));
$gndsc = pArray($rdf->allResources($res, "gnd:gndSubjectCategory"));
$occupation = pArray($rdf->allResources($res,"gnd:professionOrOccupation")); //Berufe
$broaderTermGeneric = pArray($rdf->allResources($res,"gnd:broaderTermGeneric")); // hat Oberbegriff
$broaderTermInstantial = pArray($rdf->allResources($res,"gnd:broaderTermInstantial")); //Beispiel für (übergeordnetes Konzept)
$broaderTermPartitive = pArray($rdf->allResources($res,"gnd:broaderTermPartitive")); // Teil von (übergeordnetes Konzept)
$relatedDdc = pArray($rdf->allResources($res,"gnd:relatedDdcWithDegreeOfDeterminacy4|gnd:relatedDdcWithDegreeOfDeterminacy3|gnd:relatedDdcWithDegreeOfDeterminacy2|gnd:relatedDdcWithDegreeOfDeterminacy1")); // the higher the determinacy the better! https://d-nb.info/standards/elementset/gnd#relatedDdcWithDegreeOfDeterminacy1
$relatedTerm = pArray($rdf->allResources($res,"gnd:relatedTerm"));
$placeOfActivity = pArray($rdf->allResources($res,"gnd:placeOfActivity"));

//{TODO  different indexes for swiRef = Topic in , autRef = autor of , betRef = contributor pubs, intRef = interpreted...  
//{TODO} $fieldOfStudy = $rdf->get($res, "gnd:fieldOfStudy/gnd:preferredNameForTheSubjectHeading");
$r = array();
if (isset($type)      			&& $type !=="" )         { $r['type'] = $type ;}
if (isset($name)      			&& $name !=="")          { $r['name'] = $name ;}
if (isset($altname)   			&& $altname !=="")       { $r['altname'] = $altname ;}
if (isset($homepage)  			&& $homepage !=="")      { $r['homepage'] = $homepage ;}
if (isset($definition)			&& $definition !== "")   { $r['definition'] = $definition ;}
if (isset($since)     			&& $since !== "")        { $r['since'] = $since ;}
if (isset($placeOfBirth)     	&& $placeOfBirth !== "") { $r['placeOfBirth'] = $placeOfBirth ;}
if (isset($until)     			&& $until !== "")        { $r['until'] = $until ;}
if (isset($wikipage)  			&& $wikipage !== "")     { $r['wikipage'] = $wikipage ;}
if (isset($gndId)     			&& $gndId !== "")        { $r['gndId'] = $gndId ;}
if (isset($synSearch) 			&& $synSearch !== "")    { $r['synSearch'] = $synSearch ;}
if (isset($relatedTerm) 		&& !empty($relatedTerm)) { $r['relatedTerm'] = $relatedTerm ;}
if (isset($gndsc) 	  			&& !empty($gndsc))    	 { $r['gndsc'] = $gndsc ;}
if (isset($occupation)			&& !empty($occupation))  { $r['occupation'] = $occupation ;}
if (isset($placeOfActivity)			&& !empty($placeOfActivity))  { $r['placeOfActivity'] = $placeOfActivity ;}
if (isset($broaderTermGeneric) 	&& !empty($broaderTermGeneric)) { $r['broaderTermGeneric'] = $broaderTermGeneric ;}
if (isset($broaderTermInstantial) && !empty($broaderTermInstantial)) { $r['broaderTermInstantial'] = $broaderTermInstantial ;}
if (isset($broaderTermPartitive) && !empty($broaderTermPartitive)) { $r['broaderTermPartitive'] = $broaderTermPartitive ;}
if (isset($relatedDdc) && !empty($relatedDdc)) { $r['relatedDdc'] = $relatedDdc ;}

//if (isset($gndSearchAllBooksUri)&& $gndSearchAllBooksUri !== ""){ $r['gndSearchAllBooksUri'] = $gndSearchAllBooksUri ;}
//if (isset($fieldOfStudy)        && $fieldOfStudy !== ""){ $r['fieldOfStudy'] = $fieldOfStudy ;}  
//if (isset($geo)                 && $geo !== ""){ $r['geo'] = $geo ;} 
} // end gnd exists
} // end gnd is valid

header('Content-Type: application/json');
echo json_encode($r);