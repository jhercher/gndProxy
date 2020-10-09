<?php

/**
 * GND request to culturgraph.org
 * @return json
 * 
 * @author Christoph Krempe <krempe@ub.fu-berlin.de>
 * @author Johannes Hercher <hercher@ub.fu-berlin.de>
 * @version 0.1
 */
// $gnd = '118540238';

$gnd = filter_input(INPUT_GET, 'query', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);

if (!isset($gnd)) {
    echo "gnd not set";
    exit();
}

$uri = 'https://hub.culturegraph.org/entityfacts/' . $gnd;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $uri);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$ret = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($httpCode == 404) {
    /* Handle 404 here. */
    $error = array();
    $error['error'] = "CULTURGRAPH: " . $uri . " not found";
    echo json_encode($error);
    exit;
}

curl_close($ch);

if (!$ret) {
    $error = array();
    $error['error'] = "CULTURGRAPH: could not create object";
    echo json_encode($error);
    exit;
}

$obj = json_decode($ret);

if (isset($obj->Error)) {
    echo $ret;
    exit;
}

$name = $obj->preferredName;
$until = $obj->dateOfDeath;
$since = $obj->dateOfBirth;
$definition = $obj->biographicalOrHistoricalInformation;
$placeOfBirth = null ;
$placeOfDeath = null ;
$placeOfActivity = null ;
$relations = null ;
$friends = null ;
$sameAs = null ;
$professions = null ; 



if(!empty($obj->professionOrOccupation)){
foreach ($obj->professionOrOccupation as $prof) {
    $professions .= $prof->{'preferredName'} .' (('. $prof->{'@id'} . '));  ';
}
}
if(!empty($obj->placeOfBirth)){
foreach ($obj->placeOfBirth as $pob) {
    $placeOfBirth .= $pob->{'preferredName'} .' (('. $pob->{'@id'} . '));  ';
}
}
if(!empty($obj->placeOfDeath)){
foreach ($obj->placeOfDeath as $pod) {
    $placeOfDeath .= $pod->{'preferredName'} .' (('. $pod->{'@id'} . '));  ';
}
}
if(!empty($obj->placeOfActivity)){
foreach ($obj->placeOfActivity as $poa) {
    $placeOfActivity .= $poa->{'preferredName'} .' (('. $poa->{'@id'} . '));  ';
}
}
if(!empty($obj->familialRelationship)){
foreach ($obj->familialRelationship as $rel) {
    $relations .= $rel->{'relationship'} .': '. $rel->{'preferredName'} . ' (' . $rel->{'@id'} . '); ';
}
}
if(!empty($obj->relatedPerson)){
foreach ($obj->relatedPerson as $friend) {
    $friends .= $friend->{'preferredName'} . ' (' . $friend->{'@id'} . '); ';
}
}
if(!empty($obj->sameAs)){
foreach ($obj->sameAs as $same) {
    $sameAs .= $same->collection->abbr . ': ((' . $same->{'@id'} . ')); ';
}
}

$r = array();
        if (isset($name)){ $r['name'] = $name ;}
        if (isset($since)){ $r['since'] = $since ;}
        if (isset($until)){ $r['until'] = $until ;}
        if (isset($from)){ $r['form'] = $from ;}
        if (isset($placeOfBirth)){ $r['placeOfBirth'] = $placeOfBirth ;}
        if (isset($placeOfDeath)){ $r['placeOfDeath'] = $placeOfDeath ;}
        if (isset($placeOfActivity)){ $r['placeOfActivity'] = $placeOfActivity ;}
        if (isset($professions)){ $r['professions'] = $professions ;}
        if (isset($relations)){ $r['relations'] = $relations ;}
        if (isset($friends)){ $r['friends'] = $friends ;}
        if (isset($sameAs)){ $r['sameAs'] = $sameAs ;}
        if (isset($definition)){ $r['definition'] = $definition;}

       header('Content-Type: application/json');
        echo json_encode($r);
