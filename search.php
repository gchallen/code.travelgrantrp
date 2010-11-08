<?php
// search.php -- HotCRP paper search page
// HotCRP is Copyright (c) 2006-2009 Eddie Kohler and Regents of the UC
// Distributed under an MIT-like license; see LICENSE

require_once("Code/header.inc");
require_once("Code/paperlist.inc");
require_once("Code/search.inc");
$Me = $_SESSION["Me"];
$Me->goIfInvalid();
$getaction = "";
if (isset($_REQUEST["get"]))
    $getaction = $_REQUEST["get"];
else if (isset($_REQUEST["getgo"]) && isset($_REQUEST["getaction"]))
    $getaction = $_REQUEST["getaction"];

// choose a sensible default action (if someone presses enter on a form element)
if (isset($_REQUEST["default"]) && defval($_REQUEST, "defaultact"))
    $_REQUEST[$_REQUEST["defaultact"]] = true;
else if (isset($_REQUEST["default"]))
    $_REQUEST["download"] = true;

// paper group
$tOpt = PaperSearch::searchTypes($Me);
if (count($tOpt) == 0) {
    $Conf->header("Search", 'search', actionBar());
    $Conf->errorMsg("You are not allowed to search for applications.");
    exit;
}
if (isset($_REQUEST["t"]) && !isset($tOpt[$_REQUEST["t"]])) {
    $Conf->errorMsg("You aren't allowed to search that application collection.");
    unset($_REQUEST["t"]);
}
if (!isset($_REQUEST["t"]))
    $_REQUEST["t"] = key($tOpt);
if (isset($_REQUEST["q"]) && trim($_REQUEST["q"]) == "(All)")
    $_REQUEST["q"] = "";


// paper selection
PaperSearch::parsePapersel();

function paperselPredicate($papersel, $prefix = "") {
    if (count($papersel) == 1)
	return "${prefix}paperId=$papersel[0]";
    else
	return "${prefix}paperId in (" . join(", ", $papersel) . ")";
}

function cleanAjaxResponse(&$response, $type) {
    global $papersel;
    foreach ($papersel as $pid)
	if (!isset($response[$type . $pid]))
	    $response[$type . $pid] = "";
}

function whyNotToText($e) {
    $e = preg_replace('|<a.*?</a>\s*\z|', "", $e);
    return preg_replace('|<.*?>|', "", $e);
}

// set fields to view
if (isset($_REQUEST["redisplay"])) {
    $_SESSION["pldisplay"] = "";
    foreach ($paperListFolds as $n => $v)
	if (defval($_REQUEST, "show$n", 0))
	    $_SESSION["pldisplay"] .= chr($v);
}
if (!isset($_SESSION["pldisplay"]))
    $_SESSION["pldisplay"] = $Conf->settingText("pldisplay_default", chr(PaperList::FIELD_SCORE));
if (defval($_REQUEST, "scoresort") == "M")
    $_REQUEST["scoresort"] = "C";
if (isset($_REQUEST["scoresort"]) && isset($scoreSorts[$_REQUEST["scoresort"]]))
    $_SESSION["scoresort"] = $_REQUEST["scoresort"];
if (!isset($_SESSION["scoresort"]))
    $_SESSION["scoresort"] = $Conf->settingText("scoresort_default", $defaultScoreSort);


// save display options
if (isset($_REQUEST["savedisplayoptions"]) && $Me->privChair) {
    $while = "while saving display options";
    if ($_SESSION["pldisplay"] != chr(PaperList::FIELD_SCORE)) {
	$pldisplay = str_split($_SESSION["pldisplay"]);
	sort($pldisplay);
	$_SESSION["pldisplay"] = join("", $pldisplay);
	$Conf->qe("insert into Settings (name, value, data) values ('pldisplay_default', 1, '" . sqlq($_SESSION["pldisplay"]) . "') on duplicate key update data=values(data)", $while);
    } else
	$Conf->qe("delete from Settings where name='pldisplay_default'", $while);
    if ($_SESSION["scoresort"] != "C")
	$Conf->qe("insert into Settings (name, value, data) values ('scoresort_default', 1, '" . sqlq($_SESSION["scoresort"]) . "') on duplicate key update data=values(data)", $while);
    else
	$Conf->qe("delete from Settings where name='scoresort_default'", $while);
    if ($OK && defval($_REQUEST, "ajax"))
	$Conf->ajaxExit(array("ok" => 1));
    else if ($OK)
	$Conf->confirmMsg("Display options saved.");
}


// exit early if Ajax
if (defval($_REQUEST, "ajax"))
    $Conf->ajaxExit(array("response" => ""));


// search
$Conf->header("Search", 'search', actionBar());
unset($_REQUEST["urlbase"]);
$Search = new PaperSearch($Me, $_REQUEST);
if (isset($_REQUEST["q"]) || isset($_REQUEST["qo"]) || isset($_REQUEST["qx"])) {
    $pl = new PaperList(true, true, $Search);
    $pl->showHeader = PaperList::HEADER_TITLES;
    $pl_text = $pl->text($Search->limitName, $Me);
} else
    $pl = null;


// set up the search form
if (isset($_REQUEST["redisplay"]))
    $activetab = 3;
else if (defval($_REQUEST, "qx", "") != "" || defval($_REQUEST, "qo", "") != ""
	 || defval($_REQUEST, "qt", "n") != "n" || defval($_REQUEST, "opt", 0) > 0)
    $activetab = 2;
else
    $activetab = 1;
$Conf->footerStuff .= "<script type='text/javascript'>crpfocus(\"searchform\", $activetab, 1);</script>";

$tselect = PaperSearch::searchTypeSelector($tOpt, $_REQUEST["t"], 1);


// SEARCH FORMS

// Prepare more display options
$ajaxDisplayChecked = false;
$pldisplay = $_SESSION["pldisplay"];

function ajaxDisplayer($type, $title, $disabled = false) {
    global $ajaxDisplayChecked, $paperListFolds, $pldisplay;
    $foldnum = defval($paperListFolds, $type, -1);
    $t = "<input type='checkbox' name='show$type' value='1'";
    if (defval($_REQUEST, "show$type")
	|| strpos($pldisplay, chr($foldnum)) !== false) {
	$t .= " checked='checked'";
	$ajaxDisplayChecked = true;
    }
    if ($disabled)
	$t .= " disabled='disabled'";
    return $t . " onclick='foldplinfo(this,$foldnum,\"$type\")' />&nbsp;$title"
	. "<br /><div id='${type}loadformresult'></div>\n";
}

if ($pl) {
    $moredisplay = "";
    $viewAllAuthors =
	($_REQUEST["t"] == "acc" && $Conf->timeReviewerViewAcceptedAuthors())
	|| $_REQUEST["t"] == "a";

    if ($Conf->blindSubmission() <= BLIND_OPTIONAL || $viewAllAuthors
	|| $Me->privChair)
	$moredisplay .= ajaxDisplayer("aufull", "Full author info");
    $ajaxDisplayChecked = false;
    if ($pl->headerInfo["collab"])
	$moredisplay .= ajaxDisplayer("collab", "Collaborators");
    if ($pl->headerInfo["topics"])
	$moredisplay .= ajaxDisplayer("topics", "Topics");
    if ($Me->privChair)
	$moredisplay .= ajaxDisplayer("reviewers", "Reviewers");
    if ($Me->privChair)
	$moredisplay .= ajaxDisplayer("pcconf", "PC conflicts");
    if ($Me->isPC && $pl->headerInfo["lead"])
	$moredisplay .= ajaxDisplayer("lead", "Discussion leads");
    if ($Me->isPC && $pl->headerInfo["shepherd"])
	$moredisplay .= ajaxDisplayer("shepherd", "Shepherds");
    if ($pl->anySelector) {
	$moredisplay .= "<input type='checkbox' name='showrownum' value='1'";
	if (strpos($pldisplay, "\6") !== false)
	    $moredisplay .= " checked='checked'";
	$moredisplay .= " onclick='fold(\"pl\",!this.checked,6)' />&nbsp;Row numbers<br />\n";
    }
}


echo "<table id='searchform' class='tablinks$activetab",
    ($ajaxDisplayChecked ? " fold4o" : " fold4c"), "'>
<tr><td><div class='tlx'><div class='tld1'>";

// Basic search
echo "<form method='get' action='search$ConfSiteSuffix' accept-charset='UTF-8'><div class='inform'>
  <input id='searchform1_d' class='textlite' type='text' size='40' name='q' value=\"", htmlspecialchars(defval($_REQUEST, "q", "")), "\" tabindex='1' /> &nbsp;in &nbsp;$tselect &nbsp;
  <input class='b' type='submit' value='Search' />
</div></form>";

echo "</div><div class='tld2'>";

// Advanced search
echo "<form method='get' action='search$ConfSiteSuffix' accept-charset='UTF-8'>
<table><tr>
  <td class='lxcaption'>Search these applications</td>
  <td class='lentry'>$tselect</td>
</tr>
<tr>
  <td class='lxcaption'>Using these fields</td>
  <td class='lentry'>";
$qtOpt = array("n" => "Title");
if (!isset($qtOpt[defval($_REQUEST, "qt", "")]))
    $_REQUEST["qt"] = "n";
echo tagg_select("qt", $qtOpt, $_REQUEST["qt"], array("tabindex" => 1)),
    "</td>
</tr>
<tr><td><div class='g'></div></td></tr>
<tr>
  <td class='lxcaption'>With <b>all</b> the words</td>
  <td class='lentry'><input id='searchform2_d' class='textlite' type='text' size='40' name='q' value=\"", htmlspecialchars(defval($_REQUEST, "q", "")), "\" tabindex='1' /><span class='sep'></span></td>
  <td rowspan='3'><input class='b' type='submit' value='Search' tabindex='2' /></td>
</tr><tr>
  <td class='lxcaption'>With <b>any</b> of the words</td>
  <td class='lentry'><input class='textlite' type='text' size='40' name='qo' value=\"", htmlspecialchars(defval($_REQUEST, "qo", "")), "\" tabindex='1' /></td>
</tr><tr>
  <td class='lxcaption'><b>Without</b> the words</td>
  <td class='lentry'><input class='textlite' type='text' size='40' name='qx' value=\"", htmlspecialchars(defval($_REQUEST, "qx", "")), "\" tabindex='1' /></td>
</tr>
</table></form>";

echo "</div>";

// Display options
if ($pl && $pl->count > 0) {
    echo "<div class='tld3'>";

    echo "<form id='foldredisplay' class='fold5c' method='get' action='search$ConfSiteSuffix' accept-charset='UTF-8'><div>\n";
    foreach (array("q", "qx", "qo", "qt", "t", "sort") as $x)
	if (isset($_REQUEST[$x]))
	    echo "<input type='hidden' name='$x' value=\"", htmlspecialchars($_REQUEST[$x]), "\" />\n";

    echo "<table><tr>
  <td class='pad nowrap'><strong>Show:</strong>",
	foldsessionpixel("pl", "pldisplay", null);
    if ($moredisplay !== "")
	echo "<span class='sep'></span>",
	    "<a class='fn4' href='javascript:void fold(e(\"searchform\"),0,4)'>More &#187;</a>",
	    "</td>\n  <td class='fx4'>",
	    //"<a class='fx4' href='javascript:void fold(e(\"searchform\"),1,4)'>&#171; Fewer</a>",
	    "</td>\n";
    else
	echo "</td>\n";
    if (isset($pl->scoreMax))
	echo "  <td class='padl'><strong>Scores:</strong></td>\n";
    echo "</tr><tr>
  <td class='pad'>";
    if ($Conf->blindSubmission() <= BLIND_OPTIONAL || $viewAllAuthors) {
	echo "<input id='showau' type='checkbox' name='showau' value='1'";
	if (strpos($pldisplay, "\1") !== false)
	    echo " checked='checked'";
	echo " onclick='fold(\"pl\",!this.checked,1)";
	if ($viewAllAuthors)
	    echo ";fold(\"pl\",!this.checked,2)";
	if ($Me->privChair)
	    echo ";foldplinfo_extra()";
	echo "' />&nbsp;Authors<br />\n";
    }
    if ($Conf->blindSubmission() >= BLIND_OPTIONAL && $Me->privChair && !$viewAllAuthors) {
	echo "<input ",
	    ($Conf->blindSubmission() == BLIND_OPTIONAL ? "" : "id='showau' "),
	    "type='checkbox' name='showanonau' value='1'";
	if (!$pl || !($pl->headerInfo["authors"] & 2))
	    echo " disabled='disabled'";
	if (strpos($pldisplay, "\2") !== false)
	    echo " checked='checked'";
	echo " onclick='fold(\"pl\",!this.checked,2)";
	if ($Me->privChair)
	    echo ";foldplinfo_extra()";
	echo "' />&nbsp;",
	    ($Conf->blindSubmission() == BLIND_OPTIONAL ? "Anonymous authors" : "Authors"),
	    "<br />\n";
    }

    if ($pl->headerInfo["abstract"])
	echo ajaxDisplayer("abstract", "Abstracts");
    if ($Me->isPC && $pl->headerInfo["tags"])
	echo ajaxDisplayer("tags", "Tags",
			   ($_REQUEST["t"] == "a" && !$Me->privChair));

    if ($moredisplay !== "") {
	echo //"<div class='ug'></div>",
	    //"<a class='fn4' href='javascript:void fold(e(\"searchform\"),0,4)'>More &#187;</a>",
	    "</td><td class='pad fx4'>", $moredisplay,
	    //"<div class='ug'></div>",
	    //"<a class='fx4' href='javascript:void fold(e(\"searchform\"),1,4)'>&#171; Fewer</a>",
	    "</td>\n";
    } else
	echo "</td>\n";

    if (isset($pl->scoreMax)) {
	echo "  <td class='padl'><table><tr><td>";
	$rf = reviewForm();
	if ($Me->amReviewer() && $_REQUEST["t"] != "a")
	    $revViewScore = $Me->viewReviewFieldsScore(null, true);
	else
	    $revViewScore = 0;
	foreach ($rf->fieldOrder as $field)
	    if ($rf->authorView[$field] > $revViewScore
		&& isset($rf->options[$field]))
		echo ajaxDisplayer($field, htmlspecialchars($rf->shortName[$field]));
	$onchange = "highlightUpdate(\"redisplay\")";
	if ($Me->privChair)
	    $onchange .= ";foldplinfo_extra()";
	echo "<div class='g'></div></td>
    <td><input id='redisplay' class='b' type='submit' name='redisplay' value='Redisplay' /></td>
  </tr><tr>
    <td colspan='2'>Sort method: &nbsp;",
	    tagg_select("scoresort", $scoreSorts, $_SESSION["scoresort"], array("onchange" => $onchange, "id" => "scoresort")),
	    " &nbsp; <a href='help$ConfSiteSuffix?t=scoresort' class='hint'>What is this?</a>";

	// "Save display options"
	if ($Me->privChair) {
	    echo "\n<div class='g'></div>
    <a class='fx5' href='javascript:void savedisplayoptions()'>",
		"Make these display options the default</a>",
		" <span id='savedisplayoptionsformcheck' class='fn5'></span>";
	    $Conf->footerStuff .= "<form id='savedisplayoptionsform' method='post' action='search$ConfSiteSuffix?savedisplayoptions=1' enctype='multipart/form-data' accept-charset='UTF-8'>"
. "<div><input id='scoresortsave' type='hidden' name='scoresort' value='"
. $_SESSION["scoresort"] . "' /></div></form>"
. "<script type='text/javascript'>function foldplinfo_extra() { fold('redisplay', 0, 5); }";
	    // strings might be in different orders, so sort before comparing
	    $pld = str_split($Conf->settingText("pldisplay_default", chr(PaperList::FIELD_SCORE)));
	    sort($pld);
	    if ($_SESSION["pldisplay"] != join("", $pld)
		|| $_SESSION["scoresort"] != $Conf->settingText("scoresort_default", $defaultScoreSort))
		$Conf->footerStuff .= " foldplinfo_extra();";
	    $Conf->footerStuff .= "</script>";
	}

	echo "</td>
  </tr></table></td>\n";
    } else
	echo "<td><input id='redisplay' class='b' type='submit' name='redisplay' value='Redisplay' /></td>\n";

    echo "</tr></table></div></form></div></div>";
}

// Tab selectors
echo "</td></tr>
<tr><td class='tllx'><table><tr>
  <td><div class='tll1'><a onclick='return crpfocus(\"searchform\", 1)' href=''>Basic search</a></div></td>
  <td><div class='tll2'><a onclick='return crpfocus(\"searchform\", 2)' href=''>Advanced search</a></div></td>\n";
echo "</tr></table></td></tr>
</table>\n\n";


if ($pl) {
    if ($Search->warnings) {
	echo "<div class='maintabsep'></div>\n";
	$Conf->warnMsg(join("<br />\n", $Search->warnings));
    }

    echo "<div class='maintabsep'></div>\n\n<div class='searchresult'>";

    if ($pl->anySelector)
	echo "<form method='post' action=\"", htmlspecialchars(selfHref(array("selector" => 1), "search$ConfSiteSuffix")), "\" enctype='multipart/formdata' accept-charset='UTF-8' id='sel' onsubmit='return paperselCheck();'><div class='inform'>\n",
	    "<input id='defaultact' type='hidden' name='defaultact' value='' />",
	    "<input class='hidden' type='submit' name='default' value='1' />";

    echo $pl_text;
    if ($pl->count == 0 && $_REQUEST["t"] != "s") {
	$a = array();
	foreach (array("q", "qo", "qx", "qt", "sort", "showtags") as $xa)
	    if (isset($_REQUEST[$xa]))
		$a[] = "$xa=" . urlencode($_REQUEST[$xa]);
	reset($tOpt);
	echo " in ", strtolower($tOpt[$_REQUEST["t"]]);
	if (key($tOpt) != $_REQUEST["t"])
	    echo " (<a href=\"search$ConfSiteSuffix?", join("&amp;", $a), "\">Repeat search in ", strtolower(current($tOpt)), "</a>)";
    }

    if ($pl->anySelector)
	echo "</div></form>";
    echo "</div>\n";
} else
    echo "<div class='g'></div>\n";

$Conf->footer();
