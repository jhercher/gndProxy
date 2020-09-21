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

$name = $obj->person->preferredName;
$until = $obj->person->dateOfDeath;
$since = $obj->person->dateOfBirth;
$definition = $obj->person->biographicalOrHistoricalInformation;

foreach ($obj->person->professionOrOccupation as $prof) {
    $professions .= $prof->{'@value'} . ', ';
}

foreach ($obj->person->familialRelationship as $rel) {
    $relations .= $rel->preferredName . ' (' . $rel->relationship . '); ';
}
foreach ($obj->person->relatedPerson as $friend) {
    $friends .= $friend->preferredName . ' (' . $friend->relationship . '); ';
}

foreach ($obj->sameAs as $same) {
    $sameAs .= $same->publisher->name . ': ((' . $same->{'@id'} . ')); ';
}

$r = array();
        if (isset($since)){ $r['until'] = $until ;}
        if (isset($from)){ $r['form'] = $from ;}
        if (isset($name)){ $r['name'] = $name ;}
        if (isset($professions)){ $r['professions'] = $professions ;}
        if (isset($relations)){ $r['relations'] = $relations ;}
        if (isset($friends)){ $r['friends'] = $friends ;}
        if (isset($sameAs)){ $r['sameAs'] = $sameAs ;}
        if (isset($definition)){ $r['definition'] = $definition;}

       header('Content-Type: application/json');
        echo json_encode($r);
