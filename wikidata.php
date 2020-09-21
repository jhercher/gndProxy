<?php

/**
 * GND request to DNB
 * description: build a json response with wikidata using a gnd identifier 
 * @param $gnd -> gnd id (https://de.wikipedia.org/wiki/Gemeinsame_Normdatei)
 * @param $lang -> language in iso 639-1 (e.g. de, en, fr)
 * @return json object with image, wikidata entity, wikipedia pagenames (de,en,fr), wikipage, first paragraph from wikipedia 
 * @author Johannes Hercher <hercher@ub.fu-berlin.de>
 * @version 1.1
 */

$gnd = filter_input(INPUT_GET, 'query', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
$lang = filter_input(INPUT_GET, 'lang', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
$ids = "";

if (!isset($gnd)) {
    echo "gnd not set";
    exit;
}

/*
 * getWikidataId
 * Get Wikidata Entity for GNDid
 * uses query.wikidata.org
 * @return {string}
 * @param $gnd -> GND ID (e.g. 130370533)
 * Example request: https://query.wikidata.org/bigdata/namespace/wdq/sparql?query=SELECT%20?item%20WHERE%20{%20?item%20wdt:P227%20%22130370533%22;%20}&format=json
*/
function getWikidataId($gnd) {
    $json = url_get_contents('https://query.wikidata.org/bigdata/namespace/wdq/sparql?query=SELECT%20?item%20WHERE%20{%20?item%20wdt:P227%20%22'.$gnd.'%22;%20}');
    $obj = json_decode($json);
	//check for Wikidata object 
     if (!empty($obj->results->bindings)) {
        $objprop = $obj->results->bindings[0]->item->value; // ["results"]["bindings"]["item"]["value"];
        $ids = strrchr(parse_url($objprop, PHP_URL_PATH), 'Q');
        return $ids;
    }
}

/*
 * getWikidataImg
 * Get URI to first img used in a Wikimedia Collection of a page with given GND
 * uses query.wikidata.org
 * @return {string}
 * @param $gnd -> GND ID 
 * @param $width -> width delivered by wikimedia
 */
function getWikidataImg($gnd, $width) {
    $json0 = url_get_contents('https://query.wikidata.org/bigdata/namespace/wdq/sparql?query=SELECT%20?pic%20WHERE%20{%20?item%20wdt:P227%20%22'. $gnd .'%22;%20wdt:P18%20?pic.%20SERVICE%20wikibase:label%20{%20bd:serviceParam%20wikibase:language%20%22[AUTO_LANGUAGE],en%22.%20}%20}');
    $obj0 = json_decode($json0);
    //check for image url 
    if (!empty($obj0->results->bindings)) {
        return 'https://commons.wikimedia.org/wiki/Special:FilePath' . strrchr(parse_url($obj0->results->bindings[0]->pic->value, PHP_URL_PATH), '/') . "?width=" . $width;
    }
}

/*
 * getWikiPage 
 * @definition: get Wikipedia page titles in defined language submitted
 * @param: $ids -> wikidata ID {string}, eg. Q14551995
 * @return: {array}
 */
function getWikiPage($ids) {
if(!empty($ids)){
    $pageArr = array();
    $json3 = url_get_contents('https://www.wikidata.org/w/api.php?action=wbgetentities&ids=' . $ids . '&format=json');
    $obj3 = json_decode($json3);
    $i = 0;
    if ($obj3 !== NULL || $obj3 !== "") {
        while ($label = current($obj3->{"entities"}->$ids->sitelinks)) {
            if ($label->site == "enwiki" || $label->site == "dewiki" || $label->site == "frwiki") {
                $pageArr[str_replace("wiki", "", $label->site)] = str_replace(" ", "_", $label->title);
            }
            $i++;
            next($obj3->{"entities"}->$ids->sitelinks);
        }
        return $pageArr;
    }
 }
}

/*
 * getDescription
 * @description: returns first $chars of a wikipage in specified $lang. 
 * Tries to fetch preferred language, but iterates trough submitted array if not available.
 * @param: $pageArr (arr the wikipedia page label {string normalized}
 * @param: $lang: the preffered language of wikipedia page queried {string}
 * @param: $chars: number of chars to extract {int}
 * @return {string}
 */
function getDescription($pageArr, $lang, $chars) {
if(!empty($pageArr)){ // case where no pages available 
    foreach ($pageArr as $language => $value) {
        if ($language == "$lang") { //prefLanguage available !
            $prefLanguage = TRUE;
        }
    }
    if ($prefLanguage) { // echo "ok, got pref language $language with page: $value exit!";
        $pageLabel = $pageArr[$lang];
        $l = $lang;
    } else { // echo "try to catch next possible wikipage from array of alternative Languages";
        $altLanguages = array("de", "en", "fr");
        switch (key($altLanguages)) {
            case "de" :
                if ($pageArr["de"] !== NULL) {
                    $pageLabel = $pageArr["de"];
                    $l = "de";
                }

                break;
            case "en" :
                if ($pageArr["en"] !== NULL) {
                    $pageLabel = $pageArr["en"];
                    $l = "en";
                }
                break;
            case "fr" :
                if ($pageArr["fr"] !== NULL) {
                    $pageLabel = $pageArr["fr"];
                    $l = "fr";
                }
                break;
        } 
    }
    $q = "https://$l.wikipedia.org/w/api.php?action=query&prop=extracts&exchars=$chars&titles=$pageLabel&explaintext=true&format=json";
    $j = url_get_contents($q);
    $result = json_decode($j);
    if ($result !== NULL) {
        $pid = key($result->query->pages);
        $d = $result->query->pages->{"$pid"}->extract;
        $description = array(
            'source' => "https://$l.wikipedia.org/wiki/$pageLabel",
            'text' => $d
        );
        return $description;
    }
  }
}

/*process*/
$wikidataId = getWikidataId($gnd);
$img = getWikidataImg($gnd, "100");
$wikiPageLabel = getWikiPage($wikidataId, $lang);
$wiki = getDescription($wikiPageLabel, $lang, 500);
$wikipage = $wiki["source"];
$description =  $wiki["text"];

/*build json with available data*/
$r = array();
if (isset($img))        { $r['img'] = $img ;}
if (isset($wikidataId)) { $r['wikidataId'] = $wikidataId ;}
if (isset($wikiPageLabel)) { $r['name'] = $wikiPageLabel ;}
if (isset($wikipage))   { $r['wikipage'] = $wikipage;}
if (isset($description)){ $r['description'] = $description ;}

header('Content-Type: application/json');
echo json_encode($r);
