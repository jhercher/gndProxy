<?php

/**
 * GND request to DNB
 * @return json
 * 
 * @author Johannes Hercher <hercher@ub.fu-berlin.de>
 * @version 0.1
 */
$gnd = filter_input(INPUT_GET, 'query', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
$lang = filter_input(INPUT_GET, 'lang', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);

if (!isset($gnd)) {
    echo "gnd not set";
    exit;
}
/*
 * @return {string}
 * @definition: returns Wikidata Entity (Q...) by given gnd
 */

function getWikidataId($gnd) {
    $json = file_get_contents('http://wdq.wmflabs.org/api?q=STRING%5B227:%22' . $gnd . '%22%5D');
    $obj = json_decode($json);
    if ($obj !== NULL) {
        $ids = "Q" . $obj->items[0];
        return $ids;
    }
}

/*
 * Get URI to first img used in a Wikimedia Collection of a page with given GND
 * uses wdq.wmflabs.api {experimental?)
 * @return {string}
 * @param $gndId
 * TODO: additionally first img. from Wikipedia page if nothing in WikiCollection.
 */

function getWikidataImg($gnd, $width) {
    $json0 = file_get_contents('http://wdq.wmflabs.org/api?q=STRING%5B227:%22' . $gnd . '%22%5D&props=18');
    $obj0 = json_decode($json0);
    if ($obj0 !== NULL) {
        $fileName = str_replace(" ", "_", $obj0->props->{"18"}[0][2]);
        return $img = "https://commons.wikimedia.org/w/thumb.php?f=" . $fileName . "&w=" . $width;
    }
}

/*
 * Get Wikipages in all available languages from wikidata.
 * @return: {array}
 * @param: wikidata ID {string}, eg. Q14551995
 * @definition: get Wikipedia page title in defined language submitted
 */

function getWikiPage($ids) {
    $pageArr = array();
    $json3 = file_get_contents('https://www.wikidata.org/w/api.php?action=wbgetentities&ids=' . $ids . '&format=json');
    $obj3 = json_decode($json3);
    $i = 0;
    if ($obj3 !== NULL) {
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

/*
 * @return {string}
 * returns first x chars of of a wikipage. 
 * Tries to fetch preferred language, but iterates trough submitted array if not available.
 * @param: $pageArr (arr the wikipedia page label {string normalized}
 * @param: $lang: the preffered language of wikipedia page queried {string}
 * @param: $chars: number of chars to extract {int}
 */

function getDescription($pageArr, $lang, $chars) {
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
    $j = file_get_contents($q);
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

/*process*/
$wikidataId = getWikidataId($gnd);
$img = getWikidataImg($gnd, "100");
$wikiPageLabel = getWikiPage($wikidataId, $lang);
$wiki = getDescription($wikiPageLabel, $lang, 500);
$wikipage = $wiki["source"];
$description =  $wiki["text"];

$r = array();
if (isset($img))        { $r['img'] = $img ;}
if (isset($wikidataId)) { $r['wikidataId'] = $wikidataId ;}
if (isset($wikipage))   { $r['wikipage'] = $wikipage;}
if (isset($description)){ $r['description'] = $description ;}

header('Content-Type: application/json');
echo json_encode($r);
