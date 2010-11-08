<?php
// settings.php -- HotCRP chair-only conference settings management page
// HotCRP is Copyright (c) 2006-2009 Eddie Kohler and Regents of the UC
// Distributed under an MIT-like license; see LICENSE

require_once("Code/header.inc");
require_once("Code/tags.inc");
$Me = $_SESSION["Me"];
$Me->goIfInvalid();
$Me->goIfNotPrivChair();
$Highlight = array();
$Error = array();
$Values = array();
$rf = reviewForm();
$DateExplanation = "Date examples: &ldquo;now&rdquo;, &ldquo;10 Dec 2006 11:59:59pm PST&rdquo; <a href='http://www.gnu.org/software/tar/manual/html_section/Date-input-formats.html'>(more examples)</a>";

$SettingGroups = array("acc" => array(
			     "acct_addr" => "check",
			     "next" => "msg"),
		       "msg" => array(
			     "opt.shortName" => "simplestring",
			     "opt.longName" => "simplestring",
			     "homemsg" => "htmlstring",
			     "conflictdefmsg" => "htmlstring",
			     "next" => "sub"),
		       "sub" => array(
			     "sub_open" => "cdate",
			     "sub_blind" => 2,
			     "sub_reg" => "date",
			     "sub_sub" => "date",
			     "sub_grace" => "grace",
			     "sub_pcconf" => "check",
			     "sub_pcconfsel" => "check",
			     "sub_collab" => "check",
			     "banal" => "special",
			     "sub_freeze" => 1,
			     "pc_seeall" => "check",
			     "next" => "opt"),
		       "opt" => array(
			     "topics" => "special",
			     "options" => "special",
			     "next" => "rev"),
		       "rev" => array(
			     "rev_open" => "cdate",
			     "cmt_always" => "check",
			     "rev_blind" => 2,
			     "rev_notifychair" => "check",
			     "pcrev_any" => "check",
			     "pcrev_soft" => "date",
			     "pcrev_hard" => "date",
			     "x_rev_roundtag" => "special",
			     "pc_seeallrev" => 3,
			     "pc_seeblindrev" => 1,
			     "extrev_chairreq" => "check",
			     "x_tag_chair" => "special",
			     "x_tag_vote" => "special",
			     "x_tag_rank" => "special",
			     "tag_seeall" => "check",
			     "extrev_soft" => "date",
			     "extrev_hard" => "date",
			     "extrev_view" => 2,
			     "mailbody_requestreview" => "string",
			     "rev_ratings" => 2,
			     "next" => "rfo"),
		       "rfo" => array(
			     "reviewform" => "special",
			     "next" => "dec"),
		       "dec" => array(
			     "au_seerev" => 2,
			     "seedec" => 2,
			     "resp_open" => "check",
			     "resp_done" => "date",
			     "resp_grace" => "grace",
			     "decisions" => "special",
			     "final_open" => "check",
			     "final_done" => "date",
			     "final_grace" => "grace"));

$Group = defval($_REQUEST, "group");
if (!isset($SettingGroups[$Group])) {
    if ($Conf->timeAuthorViewReviews())
	$Group = "dec";
    else if ($Conf->settingsAfter("sub_sub") || $Conf->timeReviewOpen())
	$Group = "rev";
    else
	$Group = "sub";
}
if ($Group == "rfo")
    require_once("Code/reviewsetform.inc");
if ($Group == "acc")
    require_once("Code/contactlist.inc");


$SettingText = array(
	"sub_open" => "Submissions open setting",
	"sub_reg" => "Paper registration deadline",
	"sub_sub" => "Paper submission deadline",
	"rev_open" => "Reviews open setting",
	"cmt_always" => "Comments open setting",
	"pcrev_soft" => "PC soft review deadline",
	"pcrev_hard" => "PC hard review deadline",
	"extrev_soft" => "External reviewer soft review deadline",
	"extrev_hard" => "External reviewer hard review deadline",
	"sub_grace" => "Submissions grace period",
	"sub_blind" => "Blind submission setting",
	"rev_blind" => "Blind review setting",
	"sub_pcconf" => "Collect PC conflicts setting",
	"sub_pcconfsel" => "Collect conflict types setting",
	"sub_collab" => "Collect collaborators setting",
	"acct_addr" => "Collect addresses setting",
	"sub_freeze" => "Submitters can update until the deadline setting",
	"rev_notifychair" => "Notify chairs about reviews setting",
	"pc_seeall" => "PC can see all papers setting",
	"pcrev_any" => "PC can review any paper setting",
	"extrev_chairreq" => "PC chair must approve proposed external reviewers",
	"pc_seeallrev" => "PC can see all reviews setting",
	"pc_seeblindrev" => "PC can see blind reviewer identities setting",
	"extrev_view" => "External reviewers can view reviews setting",
	"tag_chair" => "Chair tags",
	"tag_vote" => "Voting tags",
	"tag_rank" => "Rank tag",
	"tag_seeall" => "PC can see tags for conflicted papers",
	"rev_ratings" => "Review ratings setting",
	"au_seerev" => "Authors can see reviews setting",
	"seedec" => "Decision visibility",
	"final_open" => "Collect final copies setting",
	"final_done" => "Final copy upload deadline",
	"homemsg" => "Home page message",
	"conflictdefmsg" => "Definition of conflict of interest",
	"mailbody_requestreview" => "Mail template for external review requests"
	);

function parseGrace($v) {
    $t = 0;
    $v = trim($v);
    if ($v == "" || strtoupper($v) == "N/A" || strtoupper($v) == "NONE" || $v == "0")
	return -1;
    if (ctype_digit($v))
	return $v * 60;
    if (preg_match('/^\s*([\d]+):([\d.]+)\s*$/', $v, $m))
	return $m[1] * 60 + $m[2];
    if (preg_match('/^\s*([\d.]+)\s*d(ays?)?(?![a-z])/i', $v, $m)) {
	$t += $m[1] * 3600 * 24;
	$v = substr($v, strlen($m[0]));
    }
    if (preg_match('/^\s*([\d.]+)\s*h(rs?|ours?)?(?![a-z])/i', $v, $m)) {
	$t += $m[1] * 3600;
	$v = substr($v, strlen($m[0]));
    }
    if (preg_match('/^\s*([\d.]+)\s*m(in(ute)?s?)?(?![a-z])/i', $v, $m)) {
	$t += $m[1] * 60;
	$v = substr($v, strlen($m[0]));
    }
    if (preg_match('/^\s*([\d.]+)\s*s(ec(ond)?s?)?(?![a-z])/i', $v, $m)) {
	$t += $m[1];
	$v = substr($v, strlen($m[0]));
    }
    if (trim($v) == "")
	return $t;
    else
	return null;
}

function unparseGrace($v) {
    if ($v === null || $v <= 0 || !is_numeric($v))
	return "none";
    if ($v % 3600 == 0)
	return ($v / 3600) . " hr";
    if ($v % 60 == 0)
	return ($v / 60) . " min";
    return sprintf("%d:%02d", intval($v / 60), $v % 60);
}

function expandMailTemplate($name, $default) {
    global $nullMailer;
    if (!isset($nullMailer)) {
	require_once("Code/mailtemplate.inc");
	$nullMailer = new Mailer(null, null);
	$nullMailer->width = 10000000;
    }
    return $nullMailer->expandTemplate($name, true, $default);
}

function _cleanXHTMLError(&$err, $etype) {
    $err = "Your XHTML code contains $etype.  Please fix this; the setting only accepts XHTML content tags, such as <tt>&lt;p&gt;</tt>, <tt>&lt;strong&gt;</tt>, and <tt>&lt;h1&gt;</tt>.";
    return false;
}

$goodtags = array_flip(array("a", "abbr", "acronym", "address", "area", "b", "bdo", "big", "blockquote", "br", "button", "caption", "center", "cite", "code", "col", "colgroup", "dd", "del", "dir", "div", "dfn", "dl", "dt", "em", "font", "h1", "h2", "h3", "h4", "h5", "h6", "hr", "i", "img", "ins", "kbd", "label", "legend", "li", "link", "map", "menu", "noscript", "ol", "optgroup", "option", "p", "pre", "q", "s", "samp", "select", "small", "span", "strike", "strong", "sub", "sup", "table", "tbody", "td", "textarea", "tfoot", "th", "thead", "title", "tr", "tt", "u", "ul", "var"));
$emptytags = array_flip(array("base", "meta", "link", "hr", "br", "param", "img", "area", "input", "col"));

function cleanXHTML($t, &$err) {
    global $goodtags, $emptytags;
    $tagstack = array();

    $x = "";
    while ($t != "") {
	if (($p = strpos($t, "<")) === false) {
	    $x .= $t;
	    break;
	}
	$x .= substr($t, 0, $p);
	$t = substr($t, $p);

	if (preg_match('/\A<!\[[ie]/', $t))
	    return _cleanXHTMLError($err, "an Internet Explorer conditional comment");
	else if (preg_match('/\A(<!\[CDATA\[.*?)(\]\]>|\z)(.*)\z/s', $t, $m)) {
	    $x .= $m[1] . "]]>";
	    $t = $m[3];
	} else if (preg_match('/\A<!--.*?(-->|\z)(.*)\z/s', $t, $m))
	    $t = $m[2];
	else if (preg_match('/\A<!(\S+)/s', $t, $m))
	    return _cleanXHTMLError($err, "<code>$m[1]</code> declarations");
	else if (preg_match('/\A<\s*([A-Za-z]+)\s*(.*)\z/s', $t, $m)) {
	    $tag = strtolower($m[1]);
	    $t = $m[2];
	    $x .= "<" . $tag;
	    if (!isset($goodtags[$tag]))
		return _cleanXHTMLError($err, "some <code>&lt;$tag&gt;</code> tag");
	    while ($t != "" && $t[0] != "/" && $t[0] != ">") {
		if (!preg_match(",\\A([^\\s/<>='\"]+)\\s*(.*)\\z,s", $t, $m))
		    return _cleanXHTMLError($err, "garbage within some <code>&lt;$tag&gt;</code> tag");
		$attr = strtolower($m[1]);
		if (strlen($attr) > 2 && $attr[0] == "o" && $attr[1] == "n")
		    return _cleanXHTMLError($err, "an event handler attribute in some <code>&lt;$tag&gt;</code> tag");
		else if ($attr == "style" || $attr == "script")
		    return _cleanXHTMLError($err, "a <code>$attr</code> attribute in some <code>&lt;$tag&gt;</code> tag");
		$x .= " " . $attr . "=";
		$t = $m[2];
		if (preg_match("/\\A=\\s*('.*?'|\".*?\")(.*)\\z", $t, $m)) {
		    $x .= $m[1];
		    $t = $m[2];
		} else
		    $x .= "\"$attr\" ";
	    }
	    if ($t == "")
		return _cleanXHTMLError($err, "an unclosed <code>&lt;$tag&gt;</code> tag");
	    else if ($t[0] == ">") {
		$t = substr($t, 1);
		if (isset($emptytags[$tag])
		    && !preg_match(',\A\s*<\s*/' . $tag . '\s*>,si', $t))
		    // automagically close empty tags
		    $x .= " />";
		else {
		    $x .= ">";
		    $tagstack[] = $tag;
		}
	    } else if (preg_match(',\A/\s*>(.*)\z,s', $t, $m)) {
		$x .= " />";
		$t = $m[1];
	    } else
		return _cleanXHTMLError($err, "garbage in some <code>&lt;$tag&gt;</code> tag");
	} else if (preg_match(',\A<\s*/\s*([A-Za-z]+)\s*>(.*)\z,s', $t, $m)) {
	    $tag = strtolower($m[1]);
	    if (!isset($goodtags[$tag]))
		return _cleanXHTMLError($err, "some <code>&lt;/$tag&gt;</code> tag");
	    else if (count($tagstack) == 0)
		return _cleanXHTMLError($err, "a extra close tag <code>&lt;/$tag&gt;</code>");
	    else if (($last = array_pop($tagstack)) != $tag)
		return _cleanXHTMLError($err, "a close tag <code>&lt;/$tag</code> that doesn't match the open tag <code>&lt;$last</code>");
	    $x .= "<$tag>";
	    $t = $m[2];
	} else {
	    $x .= "&lt;";
	    $t = substr($t, 1);
	}
    }

    if (count($tagstack) > 0)
	return _cleanXHTMLError($err, "unclosed tags, including <code>&lt;$tagstack[0]&gt;</code>");

    return $x;
}

function parseValue($name, $type) {
    global $SettingText, $Error, $Highlight;

    // PHP changes incoming variable names, substituting "." with "_".
    if (!isset($_REQUEST[$name]) && substr($name, 0, 4) === "opt."
	&& isset($_REQUEST["opt_" . substr($name, 4)]))
	$_REQUEST[$name] = $_REQUEST["opt_" . substr($name, 4)];

    if (!isset($_REQUEST[$name]))
	return null;
    $v = trim($_REQUEST[$name]);

    if ($type == "check")
	return $v != "";
    if ($type == "cdate" && $v == "1")
	return 1;
    if ($type == "date" || $type == "cdate") {
	if ($v == "" || strtoupper($v) == "N/A" || $v == "0")
	    return -1;
	else if (($v = strtotime($v)) !== false)
	    return $v;
	else
	    $err = $SettingText[$name] . ": not a valid date.";
    } else if ($type == "grace") {
	if (($v = parseGrace($v)) !== null)
	    return intval($v);
	else
	    $err = $SettingText[$name] . ": parse error.";
    } else if ($type == "string") {
	// Avoid storing the default message in the database
	if (substr($name, 0, 9) == "mailbody_") {
	    $t = expandMailTemplate(substr($name, 9), true);
	    $v = cleannl($v);
	    if ($t["body"] == $v)
		return 0;
	}
	return ($v == "" ? 0 : array(0, $v));
    } else if ($type == "simplestring") {
	$v = simplifyWhitespace($v);
	return ($v == "" ? 0 : array(0, $v));
    } else if ($type == "htmlstring") {
	//if (($v = cleanXHTML($v, $err)) === false)
	//    $err = $SettingText[$name] . ": $err";
	//else
	    return ($v == "" ? 0 : array(0, $v));
    } else if (is_int($type)) {
	if (ctype_digit($v) && $v >= 0 && $v <= $type)
	    return intval($v);
	else
	    $err = $SettingText[$name] . ": parse error on &ldquo;" . htmlspecialchars($v) . "&rdquo;.";
    } else
	return $v;

    $Highlight[$name] = true;
    $Error[] = $err;
    return null;
}

function doTags($set, $what) {
    global $Conf, $Values, $Error, $Highlight;
    require_once("Code/tags.inc");

    if (!$set && $what == "tag_chair" && isset($_REQUEST["tag_chair"])) {
	$vs = array();
	foreach (preg_split('/\s+/', $_REQUEST["tag_chair"]) as $t)
	    if ($t !== "" && checkTag($t, CHECKTAG_QUIET | CHECKTAG_NOPRIVATE | CHECKTAG_NOINDEX))
		$vs[] = $t;
	    else if ($t !== "") {
		$Error[] = "Chair-only tag &ldquo;" . htmlspecialchars($t) . "&rdquo; contains odd characters.";
		$Highlight["tag_chair"] = true;
	    }
	$v = array(count($vs), join(" ", $vs));
	if (!isset($Highlight["tag_chair"])
	    && ($Conf->setting("tag_chair") !== $v[0]
		|| $Conf->settingText("tag_chair") !== $v[1]))
	    $Values["tag_chair"] = $v;
    }

    if (!$set && $what == "tag_vote" && isset($_REQUEST["tag_vote"])) {
	$vs = array();
	foreach (preg_split('/\s+/', $_REQUEST["tag_vote"]) as $t)
	    if ($t !== "" && checkTag($t, CHECKTAG_QUIET | CHECKTAG_NOPRIVATE)) {
		if (preg_match('/\A([^#]+)(|#|#0+|#-\d*)\z/', $t, $m))
		    $t = $m[1] . "#1";
		$vs[] = $t;
	    } else if ($t !== "") {
		$Error[] = "Voting tag &ldquo;" . htmlspecialchars($t) . "&rdquo; contains odd characters.";
		$Highlight["tag_vote"] = true;
	    }
	$v = array(count($vs), join(" ", $vs));
	if (!isset($Highlight["tag_vote"])
	    && ($Conf->setting("tag_vote") != $v[0]
		|| $Conf->settingText("tag_vote") !== $v[1])) {
	    $Values["tag_vote"] = $v;
	    $Values["x_tag_vote"] = 1; /* want to get called at set time */
	}
    }

    if ($set && $what == "tag_vote" && isset($Values["tag_vote"])) {
	// check allotments
	$pcm = pcMembers();
	foreach (preg_split('/\s+/', $Values["tag_vote"][1]) as $t) {
	    if ($t === "")
		continue;
	    $base = substr($t, 0, strpos($t, "#"));
	    $allotment = substr($t, strlen($base) + 1);

	    $result = $Conf->q("select paperId, tag, tagIndex from PaperTag where tag like '%~" . sqlq_for_like($base) . "'");
	    $pvals = array();
	    $cvals = array();
	    $negative = false;
	    while (($row = edb_row($result))) {
		$who = substr($row[1], 0, strpos($row[1], "~"));
		if ($row[2] < 0) {
		    $Error[] = "Removed " . contactHtml($pcm[$who]) . "'s negative &ldquo;$base&rdquo; vote for paper #$row[0].";
		    $negative = true;
		} else {
		    $pvals[$row[0]] = defval($pvals, $row[0], 0) + $row[2];
		    $cvals[$who] = defval($cvals, $who, 0) + $row[2];
		}
	    }

	    foreach ($cvals as $who => $what)
		if ($what > $allotment) {
		    $Error[] = contactHtml($pcm[$who]) . " already has more than $allotment votes for tag &ldquo;$base&rdquo;.";
		    $Highlight["tag_vote"] = true;
		}

	    $q = ($negative ? " or (tag like '%~" . sqlq_for_like($base) . "' and tagIndex<0)" : "");
	    $Conf->qe("delete from PaperTag where tag='" . sqlq($base) . "'$q", "while counting votes");

	    $q = array();
	    foreach ($pvals as $pid => $what)
		$q[] = "($pid, '" . sqlq($base) . "', $what)";
	    $Conf->qe("insert into PaperTag values " . join(", ", $q), "while counting votes");
	}
    }

    if (!$set && $what == "tag_rank" && isset($_REQUEST["tag_rank"])) {
	$vs = array();
	foreach (preg_split('/\s+/', $_REQUEST["tag_rank"]) as $t)
	    if ($t !== "" && checkTag($t, CHECKTAG_QUIET | CHECKTAG_NOPRIVATE | CHECKTAG_NOINDEX))
		$vs[] = $t;
	    else if ($t !== "") {
		$Error[] = "Rank tag &ldquo;" . htmlspecialchars($t) . "&rdquo; contains odd characters.";
		$Highlight["tag_rank"] = true;
	    }
	if (count($vs) > 1) {
	    $Error[] = "At most one rank tag is currently supported.";
	    $Highlight["tag_rank"] = true;
	}
	$v = array(count($vs), join(" ", $vs));
	if (!isset($Highlight["tag_rank"])
	    && ($Conf->setting("tag_rank") !== $v[0]
		|| $Conf->settingText("tag_rank") !== $v[1]))
	    $Values["tag_rank"] = $v;
    }
}

function doTopics($set) {
    global $Conf, $Values, $rf;
    if (!$set) {
	$Values["topics"] = true;
	return;
    }
    $while = "while updating topics";

    $numnew = defval($_REQUEST, "newtopcount", 50);
    foreach ($_REQUEST as $k => $v) {
	if (!(strlen($k) > 3 && $k[0] == "t" && $k[1] == "o" && $k[2] == "p"))
	    continue;
	$v = simplifyWhitespace($v);
	if ($k[3] == "n" && $v != "" && cvtint(substr($k, 4), 100) <= $numnew)
	    $Conf->qe("insert into TopicArea (topicName) values ('" . sqlq($v) . "')", $while);
	else if (($k = cvtint(substr($k, 3), -1)) >= 0) {
	    if ($v == "") {
		$Conf->qe("delete from TopicArea where topicId=$k", $while);
		$Conf->qe("delete from PaperTopic where topicId=$k", $while);
	    } else if (isset($rf->topicName[$k]) && $v != $rf->topicName[$k])
		$Conf->qe("update TopicArea set topicName='" . sqlq($v) . "' where topicId=$k", $while);
	}
    }
}

function doCleanOptionValues($id) {
    global $Error;
    if (defval($_REQUEST, "optvt$id", 0) == 0
	|| rtrim(defval($_REQUEST, "optv$id", "")) == "")
	unset($_REQUEST["optv$id"]);
    else {
	$v = "";
	foreach (explode("\n", rtrim(cleannl($_REQUEST["optv$id"]))) as $t)
	    $v .= trim($t) . "\n";
	$_REQUEST["optv$id"] = substr($v, 0, strlen($v) - 1);
    }
    if (count($Error) > 0)
	$_REQUEST["optd$id"] = cleanXHTML($_REQUEST["optd$id"], $err);
}

function doOptions($set) {
    global $Conf, $Values, $Error;
    if (!$set) {
	foreach ($_REQUEST as $k => &$v)
	    if (substr($k, 0, 4) == "optd"
		&& (ctype_digit(substr($k, 4)) || $k == "optn")) {
		if (cleanXHTML($v, $err) === false) {
		    $Error[] = $err;
		    return;
		}
	    }
	if ($Conf->setting("allowPaperOption"))
	    $Values["options"] = true;
	return;
    }
    $while = "while updating options";

    $ochange = false;
    $anyo = false;
    foreach (paperOptions() as $id => $o) {
	doCleanOptionValues($id);
	if (isset($_REQUEST["optn$id"])
	    && ($_REQUEST["optn$id"] != $o->optionName
		|| defval($_REQUEST, "optd$id") != $o->description
		|| defval($_REQUEST, "optp$id", 0) != $o->pcView
		|| defval($_REQUEST, "optv$id", "") != defval($o, "optionValues", ""))) {
	    if ($_REQUEST["optn$id"] == "") {
		$Conf->qe("delete from OptionType where optionId=$id", $while);
		$Conf->qe("delete from PaperOption where optionId=$id", $while);
	    } else {
		$q = "update OptionType set optionName='" . sqlq($_REQUEST["optn$id"])
		    . "', description='" . sqlq(defval($_REQUEST, "optd$id", ""))
		    . "', pcView=" . (defval($_REQUEST, "optp$id") ? 1 : 0);
		if ($Conf->setting("allowPaperOption") >= 14)
		    $q .= ", optionValues='" . sqlq(defval($_REQUEST, "optv$id")) . "'";
		$Conf->qe($q . " where optionId=$id", $while);
		$anyo = true;
	    }
	    $ochange = true;
	} else
	    $anyo = true;
    }

    if (defval($_REQUEST, "optnn") && $_REQUEST["optnn"] != "New option"
	&& $_REQUEST["optnn"] != "(Enter new option here)") {
	doCleanOptionValues("n");
	$qa = "optionName, description, pcView";
	$qb = "'" . sqlq($_REQUEST["optnn"])
	    . "', '" . sqlq(defval($_REQUEST, "optdn", ""))
	    . "', " . (defval($_REQUEST, "optpn") ? 1 : 0);
	if ($Conf->setting("allowPaperOption") >= 14) {
	    $qa .= ", optionValues";
	    $qb .= ", '" . sqlq(defval($_REQUEST, "optvn", "")) . "'";
	}
	$Conf->qe("insert into OptionType ($qa) values ($qb)", $while);
	$ochange = $anyo = true;
    }

    if (!$anyo)
	$Conf->qe("delete from Settings where name='paperOption'", $while);
    else if ($ochange) {
	$t = time();
	$Conf->qe("insert into Settings (name, value) values ('paperOption', $t) on duplicate key update value=$t", $while);
    }
}

function doDecisions($set) {
    global $Conf, $Values, $rf;
    if (!$set) {
	$Values["decisions"] = true;
	return;
    }

    // mark all used decisions
    $while = "while updating decisions";
    $dec = $rf->options["outcome"];
    $update = false;
    foreach ($_REQUEST as $k => $v)
	if (strlen($k) > 3 && $k[0] == "d" && $k[1] == "e" && $k[2] == "c"
	    && ($k = cvtint(substr($k, 3), 0)) != 0) {
	    if ($v == "") {
		$Conf->qe("delete from ReviewFormOptions where fieldName='outcome' and level=$k", $while);
		$Conf->qe("update Paper set outcome=0 where outcome=$k", $while);
	    } else if ($v != $dec[$k])
		$Conf->qe("update ReviewFormOptions set description='" . sqlq($v) . "' where fieldName='outcome' and level=$k", $while);
	}

    if (defval($_REQUEST, "decn", "") != "") {
	$delta = (defval($_REQUEST, "dtypn", 1) > 0 ? 1 : -1);
	for ($k = $delta; true; $k += $delta)
	    if (!isset($dec[$k]))
		break;

	$Conf->qe("insert into ReviewFormOptions set fieldName='outcome', level=$k, description='" . sqlq($_REQUEST["decn"]) . "'");
    }
}

function doBanal($set) {
    global $Conf, $Values, $Highlight, $Error, $ConfSitePATH;
    if ($set)
	return true;
    if (!isset($_REQUEST["sub_banal"])) {
	if (($t = $Conf->settingText("sub_banal", "")) != "")
	    $Values["sub_banal"] = array(0, $t);
	else
	    $Values["sub_banal"] = null;
	return true;
    }

    // check banal subsettings
    require_once("Code/checkformat.inc");
    $old_error_count = count($Error);
    $bs = array_fill(0, 6, "");
    if (($s = trim(defval($_REQUEST, "sub_banal_papersize", ""))) != ""
	&& strcasecmp($s, "N/A") != 0) {
	if (!cvtdimen($s, 2)) {
	    $Highlight["sub_banal_papersize"] = true;
	    $Error[] = "Invalid paper size.";
	} else
	    $bs[0] = $s;
    }

    if (($s = trim(defval($_REQUEST, "sub_banal_pagelimit", ""))) != ""
	&& strcasecmp($s, "N/A") != 0) {
	if (($s = cvtint($s, -1)) <= 0) {
	    $Highlight["sub_banal_pagelimit"] = true;
	    $Error[] = "Page limit must be a whole number bigger than 0.";
	} else
	    $bs[1] = $s;
    }

    if (($s = trim(defval($_REQUEST, "sub_banal_textblock", ""))) != ""
	&& strcasecmp($s, "N/A") != 0) {
	// change margin specifications into text block measurements
	if (preg_match('/^(.*\S)\s+mar(gins?)?/i', $s, $m)) {
	    $s = $m[1];
	    if (!($ps = cvtdimen($bs[0]))) {
		$Highlight["sub_banal_pagesize"] = true;
		$Highlight["sub_banal_textblock"] = true;
		$Error[] = "You must specify a page size as well as margins.";
	    } else if (strpos($s, "x") !== false) {
		if (!($m = cvtdimen($s)) || !is_array($m) || count($m) > 4) {
		    $Highlight["sub_banal_textblock"] = true;
		    $Error[] = "Invalid margin definition.";
		    $s = "";
		} else if (count($m) == 2)
		    $s = array($ps[0] - 2 * $m[0], $ps[1] - 2 * $m[1]);
		else if (count($m) == 3)
		    $s = array($ps[0] - 2 * $m[0], $ps[1] - $m[1] - $m[2]);
		else
		    $s = array($ps[0] - $m[0] - $m[2], $ps[1] - $m[1] - $m[3]);
	    } else {
		$s = preg_replace('/\s+/', 'x', $s);
		if (!($m = cvtdimen($s)) || (is_array($m) && count($m) > 4)) {
		    $Highlight["sub_banal_textblock"] = true;
		    $Error[] = "Invalid margin definition.";
		} else if (!is_array($m))
		    $s = array($ps[0] - 2 * $m, $ps[1] - 2 * $m);
		else if (count($m) == 2)
		    $s = array($ps[0] - 2 * $m[1], $ps[1] - 2 * $m[0]);
		else if (count($m) == 3)
		    $s = array($ps[0] - 2 * $m[1], $ps[1] - $m[0] - $m[2]);
		else
		    $s = array($ps[0] - $m[1] - $m[3], $ps[1] - $m[0] - $m[2]);
	    }
	    $s = (is_array($s) ? unparsedimen($s) : "");
	}
	// check text block measurements
	if ($s && !cvtdimen($s, 2)) {
	    $Highlight["sub_banal_textblock"] = true;
	    $Error[] = "Invalid text block definition.";
	} else if ($s)
	    $bs[3] = $s;
    }

    if (($s = trim(defval($_REQUEST, "sub_banal_bodyfontsize", ""))) != ""
	&& strcasecmp($s, "N/A") != 0) {
	if (!is_numeric($s) || $s <= 0) {
	    $Highlight["sub_banal_bodyfontsize"] = true;
	    $Error[] = "Minimum body font size must be a number bigger than 0.";
	} else
	    $bs[4] = $s;
    }

    if (($s = trim(defval($_REQUEST, "sub_banal_bodyleading", ""))) != ""
	&& strcasecmp($s, "N/A") != 0) {
	if (!is_numeric($s) || $s <= 0) {
	    $Highlight["sub_banal_bodyleading"] = true;
	    $Error[] = "Minimum body leading must be a number bigger than 0.";
	} else
	    $bs[5] = $s;
    }

    while (count($bs) > 0 && $bs[count($bs) - 1] == "")
	array_pop($bs);

    // actually create setting
    if (count($Error) == $old_error_count) {
	$Values["sub_banal"] = array(1, join(";", $bs));
	$zoomarg = "";

	// Perhaps we have an old pdftohtml with a bad -zoom.
	for ($tries = 0; $tries < 2; ++$tries) {
	    $cf = new CheckFormat();
	    $s1 = $cf->analyzeFile("$ConfSitePATH/Code/sample.pdf", "letter;2;;6.5inx9in;12;14" . $zoomarg);
	    $e1 = $cf->errors;
	    if ($s1 == 1 && ($e1 & CheckFormat::ERR_PAPERSIZE) && $tries == 0)
		$zoomarg = ">-zoom=1";
	    else if ($s1 != 2 && $tries == 1)
		$zoomarg = "";
	}

	$Values["sub_banal"][1] .= $zoomarg;
	$e1 = $cf->errors;
	$s2 = $cf->analyzeFile("$ConfSitePATH/Code/sample.pdf", "a4;1;;3inx3in;13;15" . $zoomarg);
	$e2 = $cf->errors;
	$want_e2 = CheckFormat::ERR_PAPERSIZE | CheckFormat::ERR_PAGELIMIT
	    | CheckFormat::ERR_TEXTBLOCK | CheckFormat::ERR_BODYFONTSIZE
	    | CheckFormat::ERR_BODYLEADING;
	if ($s1 != 2 || $e1 != 0 || $s2 != 1 || ($e2 & $want_e2) != $want_e2)
	    $Conf->warnMsg("Running the automated paper checker on a sample PDF file produced unexpected results.  Check that your <code>pdftohtml</code> package is up to date.  You may want to disable the automated checker for now. (Internal error information: $s1 $e1 $s2 $e2)");
    }
}

function doSpecial($name, $set) {
    global $Values, $Error, $Highlight;
    if ($name == "x_tag_chair" || $name == "x_tag_vote"
	|| $name == "x_tag_rank")
	doTags($set, substr($name, 2));
    else if ($name == "topics")
	doTopics($set);
    else if ($name == "options")
	doOptions($set);
    else if ($name == "decisions")
	doDecisions($set);
    else if ($name == "reviewform") {
	if (!$set)
	    $Values[$name] = true;
	else {
	    rf_update(false);
	    $Values["revform_update"] = time();
	}
    } else if ($name == "banal")
	doBanal($set);
    else if ($name == "x_rev_roundtag") {
	if (!$set && !isset($_REQUEST["rev_roundtag"]))
	    $Values["rev_roundtag"] = null;
	else if (!$set) {
	    require_once("Code/tags.inc");
	    $t = trim($_REQUEST["rev_roundtag"]);
	    if ($t == "" || $t == "(None)")
		$Values["rev_roundtag"] = null;
	    else if (preg_match('/^[a-zA-Z0-9]+$/', $t))
		$Values["rev_roundtag"] = array(1, $t);
	    else {
		$Error[] = "The review round must contain only letters and numbers.";
		$Highlight["rev_roundtag"] = true;
	    }
	}
    }
}

function accountValue($name, $type) {
    global $Values;
    if ($type == "special")
	doSpecial($name, false);
    else if ($name != "next") {
	$v = parseValue($name, $type);
	if ($v === null) {
	    if ($type != "cdate" && $type != "check")
		return;
	    $v = 0;
	}
	if (!is_array($v) && $v <= 0 && !is_int($type))
	    $Values[$name] = null;
	else
	    $Values[$name] = $v;
    }
}

if (isset($_REQUEST["update"])) {
    // parse settings
    $settings = $SettingGroups[$Group];
    foreach ($settings as $name => $value)
	accountValue($name, $value);

    // check date relationships
    foreach (array("sub_reg" => "sub_sub", "pcrev_soft" => "pcrev_hard",
		   "extrev_soft" => "extrev_hard") as $first => $second)
	if (!isset($Values[$first]) && isset($Values[$second]))
	    $Values[$first] = $Values[$second];
	else if (isset($Values[$first]) && isset($Values[$second])) {
	    if ($Values[$second] && !$Values[$first])
		$Values[$first] = $Values[$second];
	    else if ($Values[$second] && $Values[$first] > $Values[$second]) {
		$Error[] = $SettingText[$first] . " must come before " . $SettingText[$second] . ".";
		$Highlight[$first] = true;
		$Highlight[$second] = true;
	    }
	}
    if (array_key_exists("sub_sub", $Values))
	$Values["sub_update"] = $Values["sub_sub"];
    // need to set 'resp_open' to a timestamp,
    // so we can join on later review changes
    if (array_key_exists("resp_open", $Values)
	&& $Values["resp_open"] > 0
	&& defval($Conf->settings, "resp_open") <= 0)
	$Values["resp_open"] = time();

    // update 'papersub'
    if (isset($settings["pc_seeall"])) {
	// see also conference.inc
	$result = $Conf->q("select ifnull(min(paperId),0) from Paper where " . (defval($Values, "pc_seeall", 0) <= 0 ? "timeSubmitted>0" : "timeWithdrawn<=0"));
	if (($row = edb_row($result)) && $row[0] != $Conf->setting("papersub"))
	    $Values["papersub"] = $row[0];
    }

    // warn on other relationships
    /*if (array_key_exists("resp_open", $Values)
		&& $Values["resp_open"] > 0
		&& (!array_key_exists("au_seerev", $Values)
	    || $Values["au_seerev"] <= 0)
		&& (!array_key_exists("resp_done", $Values)
	    || time() < $Values["resp_done"]))
		//$Conf->warnMsg("You have allowed authors to respond to the reviews, but authors can't see the reviews.  This seems odd.")
    if (array_key_exists("sub_freeze", $Values)
		&& $Values["sub_freeze"] == 0
		&& defval($Values, "sub_open", 0) > 0
		&& defval($Values, "sub_sub", 0) <= 0)
		//$Conf->warnMsg("You have not set a paper submission deadline, but authors can update their submissions until the deadline.  This seems odd.  You probably should (1) specify a paper submission deadline; (2) select &ldquo;Authors must freeze the final version of each submission&rdquo;; or (3) manually turn off &ldquo;Open site for submissions&rdquo; when submissions complete.");*/
   	 foreach (array("pcrev_soft", "pcrev_hard", "extrev_soft", "extrev_hard")
	     as $deadline)
	if (array_key_exists($deadline, $Values)
	    && $Values[$deadline] > time()
	    && $Values[$deadline] != $Conf->setting($deadline)
	    && (array_key_exists("rev_open", $Values)
		? $Values["rev_open"] <= 0
		: $Conf->setting("rev_open") <= 0)) {
	    $Conf->warnMsg("Review deadline set.  You may also want to open the site for reviewing.");
	    $Highlight["rev_open"] = true;
	    break;
	}

    // unset text messages that equal the default
    if (array_key_exists("conflictdefmsg", $Values)
	&& $Values["conflictdefmsg"]
	&& trim($Values["conflictdefmsg"][1]) == $Conf->conflictDefinitionText(true))
	$Values["conflictdefmsg"] = null;

    // make settings
    if (count($Error) == 0 && count($Values) > 0) {
	$while = "updating settings";
	$tables = "Settings write, TopicArea write, PaperTopic write";
	if ($Conf->setting("allowPaperOption"))
	    $tables .= ", OptionType write, PaperOption write";
	if (isset($Values['decisions']) || isset($Values['reviewform']))
	    $tables .= ", ReviewFormOptions write";
	else
	    $tables .= ", ReviewFormOptions read";
	if (isset($Values['decisions']) || isset($Values["tag_vote"]))
	    $tables .= ", Paper write";
	if (isset($Values["tag_vote"]))
	    $tables .= ", PaperTag write";
	if (isset($Values['reviewform']))
	    $tables .= ", ReviewFormField write, PaperReview write";
	else
	    $tables .= ", ReviewFormField read";
	$Conf->qe("lock tables $tables", $while);
	// alert others since we're changing settings
	$Values['revform_update'] = time();

	// apply settings
	$dq = $aq = "";
	foreach ($Values as $n => $v)
	    if (defval($settings, $n) == "special")
		doSpecial($n, true);
	    else {
		$dq .= " or name='$n'";
		if (is_array($v))
		    $aq .= ", ('$n', '" . sqlq($v[0]) . "', '" . sqlq($v[1]) . "')";
		else if ($v !== null)
		    $aq .= ", ('$n', '" . sqlq($v) . "', null)";
		if (substr($n, 0, 4) === "opt.")
		    $Opt[substr($n, 4)] = (is_array($v) ? $v[1] : $v);
	    }
	$Conf->qe("delete from Settings where " . substr($dq, 4), $while);
	if (strlen($aq))
	    $Conf->qe("insert into Settings (name, value, data) values " . substr($aq, 2), $while);

	$Conf->qe("unlock tables", $while);
	$Conf->log("Updated settings group '$Group'", $Me);
	$Conf->updateSettings();
	$rf->validate($Conf, true);
    }

    // report errors
    if (count($Error) > 0)
	$Conf->errorMsg(join("<br />\n", $Error));
} else if ($Group == "rfo")
    rf_update(false);


// header and script
$Conf->header("Conference Settings", "settings", actionBar());


function decorateSettingName($name, $text) {
    global $Highlight;
    if (isset($Highlight[$name]))
	return "<span class='error'>$text</span>";
    else
	return $text;
}

function setting($name, $defval = null) {
    global $Error, $Conf;
    if (count($Error) > 0)
	return defval($_REQUEST, $name, $defval);
    else
	return defval($Conf->settings, $name, $defval);
}

function settingText($name, $defval = null) {
    global $Error, $Conf;
    if (count($Error) > 0)
	return defval($_REQUEST, $name, $defval);
    else
	return defval($Conf->settingTexts, $name, $defval);
}

function doCheckbox($name, $text, $tr = false, $js = "hiliter(this)") {
    $x = setting($name);
    echo ($tr ? "<tr><td class='nowrap'>" : ""), "<input type='checkbox' name='$name' value='1'";
    if ($x !== null && $x > 0)
	echo " checked='checked'";
    echo " onchange='$js' />&nbsp;", ($tr ? "</td><td>" : ""), decorateSettingName($name, $text), ($tr ? "</td></tr>\n" : "<br />\n");
}

function doRadio($name, $varr) {
    $x = setting($name);
    if ($x === null || !isset($varr[$x]))
	$x = 0;
    echo "<table>\n";
    foreach ($varr as $k => $text) {
	echo "<tr><td class='nowrap'><input type='radio' name='$name' value='$k'";
	if ($k == $x)
	    echo " checked='checked'";
	echo " onchange='hiliter(this)' />&nbsp;</td><td>";
	if (is_array($text))
	    echo decorateSettingName($name, $text[0]), "<br /><small>", $text[1], "</small>";
	else
	    echo decorateSettingName($name, $text);
	echo "</td></tr>\n";
    }
    echo "</table>\n";
}

function doSelect($name, $nametext, $varr, $tr = false) {
    echo ($tr ? "<tr><td class='nowrap lcaption'>" : ""),
	decorateSettingName($name, $nametext),
	($tr ? "</td><td class='lentry'>" : ": &nbsp;"),
	tagg_select($name, $varr, setting($name),
		    array("onchange" => "hiliter(this)")),
	($tr ? "</td></tr>\n" : "<br />\n");
}

function doTextRow($name, $text, $v, $size = 30, $capclass = "lcaption",
		   $tempText = "") {
    $settingname = (is_array($text) ? $text[0] : $text);
    if ($tempText)
	$tempText = " onfocus=\"tempText(this, '$tempText', 1)\" onblur=\"tempText(this, '$tempText', 0)\"";
    echo "<tr><td class='$capclass nowrap'>", decorateSettingName($name, $settingname), "</td><td class='lentry'><input type='text' class='textlite' name='$name' value=\"", htmlspecialchars($v), "\" size='$size'$tempText onchange='hiliter(this)' />";
    if (is_array($text) && isset($text[2]))
	echo $text[2];
    if (is_array($text) && $text[1])
	echo "<br /><span class='hint'>", $text[1], "</span>";
    echo "</td></tr>\n";
}

function doDateRow($name, $text, $othername = null, $capclass = "lcaption") {
    global $Conf, $Error, $DateExplanation;
    $x = setting($name);
    if ($x === null || (count($Error) == 0 && $x <= 0)
	|| (count($Error) == 0 && $othername && setting($othername) == $x))
	$v = "N/A";
    else if (count($Error) == 0)
	$v = $Conf->parseableTime($x);
    else
	$v = $x;
    if ($DateExplanation) {
	if (is_array($text))
	    $text[1] = $DateExplanation . "<br />" . $text[1];
	else
	    $text = array($text, $DateExplanation);
	$DateExplanation = null;
    }
    doTextRow($name, $text, $v, 30, $capclass, "N/A");
}

function doGraceRow($name, $text, $capclass = "lcaption") {
    global $GraceExplanation;
    if (!isset($GraceExplanation)) {
	$text = array($text, "Example: &ldquo;15 min&rdquo;");
	$GraceExplanation = true;
    }
    doTextRow($name, $text, unparseGrace(setting($name)), 15, $capclass, "none");
}

function doActionArea() {
    echo "<div class='aa'>
  <input type='submit' class='bb' name='update' value='Save changes' />
  &nbsp;<input type='submit' class='b' name='cancel' value='Cancel' />
</div>";
}



// Accounts
function doAccGroup() {
    global $Conf, $ConfSiteSuffix, $Me, $belowHr;

    /*if ($Conf->setting("allowPaperOption") >= 5)
	doCheckbox("acct_addr", "Collect users' addresses and phone numbers");
    else
	doCheckbox("acct_addr", "Collect users' phone numbers");*/
	
	echo "<input type='hidden' name='acct_addr' value='0'>";

    echo "<hr class='hr' /><h3>Grant chairs &amp; system administrators</h3>";

    echo "<p><a href='account$ConfSiteSuffix?new=1' class='button'>Create account</a> &nbsp;|&nbsp; ",
	"Select a user's name to edit a profile or change grant chair/administrator status.</p>\n";
    $pl = new ContactList(false);
    echo $pl->text("pcadminx", $Me, "contacts$ConfSiteSuffix?t=pcadmin");
}

// Messages
function doMsgGroup() {
    global $Conf, $ConfSiteSuffix, $Opt;

    echo "<div class='f-c'>", decorateSettingName("opt.shortName", "Conference abbreviation"), "</div>
<input class='textlite' name='opt.shortName' size='20' onchange='hiliter(this)' value=\"", htmlspecialchars($Opt["shortName"]), "\" />
<div class='g'></div>\n";

    echo "<div class='f-c'>", decorateSettingName("opt.longName", "Full conference name"), "</div>
<input class='textlite' name='opt.longName' size='70' onchange='hiliter(this)' value=\"", htmlspecialchars($Opt["longName"]), "\" />
<div class='g'></div>\n";

    echo "<div class='f-c'>", decorateSettingName("homemsg", "Home page message"), " <span class='f-cx'>(XHTML allowed)</span></div>
<textarea class='textlite' name='homemsg' cols='60' rows='10' onchange='hiliter(this)'>", htmlspecialchars(settingText("homemsg", "")), "</textarea>
<div class='g'></div>\n";
}

// Submissions
function doSubGroup() {
    global $Conf;

    doCheckbox('sub_open', '<b>Open site for submissions</b>');

    /*echo "<div class='g'></div>\n";
    echo "<strong>Blind submission:</strong> Are author names visible to reviewers?<br />\n";
    doRadio("sub_blind", array(BLIND_NEVER => "Yes", BLIND_ALWAYS => "No&mdash;submissions are anonymous", BLIND_OPTIONAL => "Maybe (authors decide whether to expose their names)"));*/
	
	echo "<input type='hidden' name='sub_blind' value='". BLIND_NEVER. "'>";

    echo "<div class='g'></div>\n<table>\n";
    //doDateRow("sub_reg", "Application registration deadline", "sub_sub");
	
    doDateRow("sub_sub", "Application submission deadline");
    doGraceRow("sub_grace", 'Grace period');
    echo "</table>\n";

   /* echo "<div class='g'></div>\n<table id='foldpcconf' class='fold",
	($Conf->setting("sub_pcconf") ? "o" : "c"), "'>\n";
    doCheckbox("sub_pcconf", "Collect authors&rsquo; PC conflicts", true,
	       "hiliter(this);fold(\"pcconf\",!this.checked)");
    if ($Conf->setting("allowPaperOption") >= 22) {
	echo "<tr class='fx'><td></td><td>";
	doCheckbox("sub_pcconfsel", "Collect PC conflict types (&ldquo;Advisor/student,&rdquo; &ldquo;Recent collaborator,&rdquo; etc.)");
	echo "</td></tr>\n";
    }
    doCheckbox("sub_collab", "Collect authors&rsquo; other collaborators as text", true);
    echo "</table>\n";*/
	
	echo "<input type='hidden' name='sub_pcconf' value='0'>";
	echo "<input type='hidden' name='sub_pcconfsel' value='0'>";
	echo "<input type='hidden' name='sub_collab' value='0'>";

    echo "<hr class='hr' />\n";
    /*doRadio("sub_freeze", array(0 => "<strong>Authors can update submissions until the deadline</strong>", 1 => array("Authors must freeze the final version of each submission", "&ldquo;Authors can update submissions until the deadline&rdquo; is usually the best choice.  Freezing submissions is mostly useful when there is no submission deadline.")));*/
	echo "<input type='hidden' name='sub_freeze' value='0'>";

    /*echo "<div class='g'></div><table>\n";
    // compensate for pc_seeall magic
    if ($Conf->setting("pc_seeall") < 0)
	$Conf->settings["pc_seeall"] = 1;
    doCheckbox('pc_seeall', "PC can see <i>all registered papers</i> until submission deadline<br /><small>Check this box if you want to collect review preferences <em>before</em> most papers are submitted. After the submission deadline, PC members can only see submitted papers.</small>", true);
    echo "</table>";*/
	echo "<input type='hidden' name='pc_seeall' value='0'>";
}

/*// Submission options
function checkOptionNameUnique($oname) {
    if ($oname == "" || $oname == "none" || $oname == "any")
	return false;
    $m = 0;
    foreach (paperOptions() as $oid => $o)
	if (strstr(strtolower($o->optionName), $oname) !== false)
	    $m++;
    return $m == 1;
}

function doOptGroupOption($o) {
    global $Conf, $ConfSiteSuffix, $Error;
    $id = $o->optionId;
    if (count($Error) > 0 && isset($_REQUEST["optn$id"]))
	$o = (object) array("optionId" => $id,
		"optionName" => defval($_REQUEST, "optn$id", $o->optionName),
		"description" => defval($_REQUEST, "optd$id", $o->description),
		"optionValues" => defval($_REQUEST, "optv$id", $o->optionValues),
		"pcView" => defval($_REQUEST, "optp$id", $o->pcView));

    echo "<tr><td><div class='f-contain'>\n",
	"  <div class='f-i'>",
	"<div class='f-c'>Option name</div>",
	"<div class='f-e'><input type='text' class='textlite' name='optn$id' value=\"", htmlspecialchars($o->optionName), "\" size='50' onchange='hiliter(this)' ",
	($id == "n" ? "onfocus=\"tempText(this, '(Enter new option here)', 1)\" onblur=\"tempText(this, '(Enter new option here)', 0)\" " : ""),
	"/></div>\n",
	"  <div class='f-i'>",
	"<div class='f-c'>Description</div>",
	"<div class='f-e'><textarea class='textlite' name='optd$id' rows='2' cols='50' onchange='hiliter(this)'>", htmlspecialchars($o->description), "</textarea></div>",
	"</div></td></tr>\n",
	"  <tr><td><div class='f-i'>",
	"<div class='f-c'>Type</div>",
	"<div class='f-e'>";

    if ($Conf->setting("allowPaperOption") >= 14)
	echo tagg_select("optvt$id", array("Checkbox", "Selector"), defval($o, "optionValues") ? 1 : 0, array("onchange" => "hiliter(this);fold(\"optv$id\",this.value==0)")),
	    "<span class='sep'></span>";

    echo "<input type='checkbox' name='optp$o->optionId' value='1'", ($o->pcView ? " checked='checked'" : ""), " onchange='hiliter(this)' />&nbsp;Visible to reviewers";

    if ($Conf->setting("allowPaperOption") >= 14)
	echo "<div id='foldoptv$id' class='", (defval($o, "optionValues") ? "foldo" : "foldc"), "'><div class='fx'>",
	    "<div class='hint'>Enter the selector choices one per line.  The first choice will be the default.</div>",
	    "<textarea class='textlite' name='optv$id' rows='3' cols='50' onchange='hiliter(this)'>", htmlspecialchars(defval($o, "optionValues")), "</textarea>",
	    "</div></div>";

    echo "</div></td><td>";

    if ($id !== "n") {
	echo "<div class='f-i'>",
	    "<div class='f-c'>Example search</div>",
	    "<div class='f-e'>";
	$oabbrev = simplifyWhitespace($o->optionName);
	foreach (preg_split('/\s+/', preg_replace('/[^a-z\s]/', '', strtolower($o->optionName))) as $oword)
	    if (checkOptionNameUnique($oword)) {
		$oabbrev = $oword;
		break;
	    }
	if (($v = defval($o, "optionValues", "")) !== "") {
	    $a = explode("\n", $v);
	    if (count($a) > 1 && $a[1] !== "")
		$oabbrev .= "#" . strtolower(simplifyWhitespace($a[1]));
	}
	if (strstr($oabbrev, " ") !== false)
	    $oabbrev = "\"$oabbrev\"";
	echo "&ldquo;<a href=\"search$ConfSiteSuffix?q=opt:", urlencode($oabbrev), "\">",
	    "opt:", htmlspecialchars($oabbrev), "</a>&rdquo;",
	    "</div></div>";
    }

    echo "</td></tr>\n";
}

function doOptGroup() {
    global $Conf, $ConfSiteSuffix, $rf;

    if ($Conf->setting("allowPaperOption")) {
	echo "<h3>Submission options</h3>\n";
	echo "Options are selected by authors at submission time.  Examples have included &ldquo;PC-authored paper,&rdquo; &ldquo;Consider this paper for a Best Student Paper award,&rdquo; and &ldquo;Allow the shadow PC to see this paper.&rdquo;  The &ldquo;option name&rdquo; should be brief (&ldquo;PC paper,&rdquo; &ldquo;Best Student Paper,&rdquo; &ldquo;Shadow PC&rdquo;).  The description should be more descriptive and may use XHTML.  To delete an option, delete its name.  Add options one at a time.\n";
	echo "<div class='g'></div>\n";
	echo "<table>";
	$opt = paperOptions();
	$sep = "";
	foreach ($opt as $o) {
	    echo $sep;
	    doOptGroupOption($o);
	    $sep = "<tr><td colspan='2'><hr class='hr' /></td></tr>\n";
	}

	echo $sep;

	doOptGroupOption((object) array("optionId" => "n", "optionName" => "(Enter new option here)", "description" => "", "pcView" => 1, "optionValues" => ""));

	echo "</table>\n";
    }


    // Topics
    echo "<hr class='hr' /><h3>Topics</h3>\n";
    echo "Enter topics one per line.  Authors use checkboxes to identify the topics that apply to their papers; PC members use this information to find papers they'll want to review.  To delete a topic, delete its text.\n";
    echo "<div class='g'></div><table id='newtoptable'>";
    $td1 = "<td class='lcaption'>Current</td>";
    foreach ($rf->topicOrder as $tid => $crap) {
	echo "<tr>$td1<td class='lentry'><input type='text' class='textlite' name='top$tid' value=\"", htmlspecialchars($rf->topicName[$tid]), "\" size='50' onchange='hiliter(this)' /></td>";
	if ($td1 !== "<td></td>") {
	    // example search
	    echo "<td class='llentry' rowspan='40'><div class='f-i'>",
		"<div class='f-c'>Example search</div>";
	    $oabbrev = strtolower($rf->topicName[$tid]);
	    if (strstr($oabbrev, " ") !== false)
		$oabbrev = "\"$oabbrev\"";
	    echo "&ldquo;<a href=\"search$ConfSiteSuffix?q=topic:", urlencode($oabbrev), "\">",
		"topic:", htmlspecialchars($oabbrev), "</a>&rdquo;",
		"<div class='hint'>Topic abbreviations are also allowed.</div>",
		"</div></td>";
	}
	echo "</tr>\n";
	$td1 = "<td></td>";
    }
    $td1 = "<td class='lcaption' rowspan='40'>New<br /><small><a href='javascript:authorfold(\"newtop\",1,1)'>More</a> | <a href='javascript:authorfold(\"newtop\",1,-1)'>Fewer</a></small></td>";
    for ($i = 1; $i <= 40; $i++) {
	echo "<tr id='newtop$i' class='auedito'>$td1<td class='lentry'><input type='text' class='textlite' name='topn$i' value=\"\" size='50' onchange='hiliter(this)' /></td></tr>\n";
	$td1 = "";
    }
    echo "</table>",
	"<input id='newtopcount' type='hidden' name='newtopcount' value='40' />";
    $Conf->echoScript("authorfold(\"newtop\",0,3)");
}*/

// Reviews
function doRevGroup() {
    global $Conf, $Error, $ConfSiteSuffix, $DateExplanation;

    doCheckbox('rev_open', '<b>Open site for reviewing</b>');
    //doCheckbox('cmt_always', 'Allow comments even if reviewing is closed');
	echo "<input type='hidden' name='cmt_always' value='1'>";

    //echo "<div class='g'></div>\n";
    //echo "<strong>Anonymous review:</strong> Are reviewer names visible to authors?<br />\n";
    //doRadio("rev_blind", array(BLIND_NEVER => "Yes", BLIND_ALWAYS => "No&mdash;reviewers are anonymous", BLIND_OPTIONAL => "Maybe (reviewers decide whether to expose their names)"));
	echo "<input type='hidden' name='rev_blind' value='" . BLIND_ALWAYS . "'>";
	
    //echo "<div class='g'></div>\n";
    //doCheckbox('rev_notifychair', 'PC chairs are notified of new reviews by email');
	echo "<input type='hidden' name='rev_notifychair' value='0'>";
	
    echo "<hr class='hr' />";


    // PC reviews
    //echo "<h3>PC reviews</h3>\n";

    //echo "<table>\n";
    //$date_text = $DateExplanation;
    //$DateExplanation = null;
    //doDateRow("pcrev_soft", "Deadline", "pcrev_hard");
    //doDateRow("pcrev_hard", array("Hard deadline", "Reviews are due by the deadline and cannot be changed after the hard deadline.  You may set either or both.<br />$date_text"));
    //if (!($rev_roundtag = settingText("rev_roundtag")))
	//$rev_roundtag = "(None)";
    //doTextRow("rev_roundtag", array("Review round", "This will mark new PC review assignments by default.  Examples: &ldquo;R1&rdquo;, &ldquo;R2&rdquo; &nbsp;<span class='barsep'>|</span>&nbsp; <a href='help$ConfSiteSuffix?t=revround'>What is this?</a>"), $rev_roundtag, 15, "lcaption", "(None)");
    //echo "</table>\n";

    //echo "<div class='g'></div>\n";
    //doCheckbox('pcrev_any', 'PC members can review <strong>any</strong> submitted paper');
	echo "<input type='hidden' name='pcrev_any' value='1'>";

    //echo "<div class='g'></div>\n";
    //echo "Can PC members <strong>see all reviews</strong> except for conflicts?<br />\n";
    //doRadio("pc_seeallrev", array(0 => "No&mdash;a PC member can see a paper's reviews only after submitting their own review for that paper",
				  //3 => "Yes, unless they haven't completed an assigned review for the same paper",
				  //1 => "Yes"));
	echo "<input type='hidden' name='pc_seeallrev' value='1'>";

    //echo "<div class='g'></div>\n";
    //echo "Can PC members see who wrote blind reviews?<br />\n";
    //doRadio("pc_seeblindrev", array(0 => "Yes",
				    //1 => "Only after completing a review for the same paper"));
	echo "<input type='hidden' name='pc_seeblindrev' value='0'>";
	
	
    //echo "<hr class='hr' />";


    // External reviews
    //echo "<h3>External reviews</h3>\n";

    //if ($Conf->setting("allowPaperOption") > 1) {
	//doCheckbox('extrev_chairreq', "PC chair must approve proposed external reviewers");
	//echo "<div class='g'></div>";
    //}
	echo "<input type='hidden' name='extrev_chairreq' value='1'>";

    //echo "<table>\n";
    //doDateRow("extrev_soft", "Deadline", "extrev_hard");
    //doDateRow("extrev_hard", "Hard deadline");
    //echo "</table>\n";

    //echo "<div class='g'></div>";
    //echo "Can external reviewers see the other reviews for their assigned papers, once they've submitted their own?<br />\n";
    //doRadio("extrev_view", array(0 => "No", 2 => "Yes", 1 => "Yes, but they can't see who wrote blind reviews"));
	echo "<input type='hidden' name='extrev_view' value='0'>";
	
    /*echo "<div class='g'></div>\n";
    $t = expandMailTemplate("requestreview", false);
    echo "<div id='foldmailbody_requestreview' class='foldc'>", foldbutton("mailbody_requestreview", ""), "
  <a href=\"javascript:fold('mailbody_requestreview', 0)\" class='fn q'><strong>Mail template for external review requests</strong></a>\n";
    echo "  <span class='fx'><strong>Mail template for external review requests</strong> (<a href='mail$ConfSiteSuffix'>keywords</a> allowed)<br /></span>
<textarea class='tt fx' name='mailbody_requestreview' cols='80' rows='20' onchange='hiliter(this)'>", htmlspecialchars($t[1]), "</textarea></div>\n";

    echo "<hr class='hr' />";*/

    // Tags
    //echo "<h3>Tags</h3>\n";

    //echo "<table><tr><td class='lcaption'>", decorateSettingName("tag_chair", "Chair-only tags"), "</td>";
    //if (count($Error) > 0)
	//$v = defval($_REQUEST, "tag_chair", "");
    //else {
	//$t = array_keys(chairTags());
	//sort($t);
	//$v = join(" ", $t);
    //}
    //echo "<td><input type='text' class='textlite' name='tag_chair' value=\"", htmlspecialchars($v), "\" size='40' onchange='hiliter(this)' /><br /></td></tr>";

    //echo "<tr><td class='lcaption'>", decorateSettingName("tag_vote", "Voting tags"), "</td>";
    //if (count($Error) > 0)
	//$v = defval($_REQUEST, "tag_vote", "");
    //else {
	//$t = voteTags();
	//ksort($t);
	//$x = "";
	//foreach ($t as $n => $v)
	    //$x .= "$n#$v ";
	//$v = trim($x);
    //}
    //echo "<td><input type='text' class='textlite' name='tag_vote' value=\"", htmlspecialchars($v), "\" size='40' onchange='hiliter(this)' /><br /><div class='hint'>&ldquo;vote#10&rdquo; declares a voting tag named &ldquo;vote&rdquo; with an allotment of 10 votes per PC member. &nbsp;<span class='barsep'>|</span>&nbsp; <a href='help$ConfSiteSuffix?t=votetags'>What is this?</a></div></td></tr>";

    //echo "<tr><td class='lcaption'>", decorateSettingName("tag_rank", "Ranking tag"), "</td>";
    //if (count($Error) > 0)
	//$v = defval($_REQUEST, "tag_rank", "");
    //else
	//$v = $Conf->settingText("tag_rank", "");
    //echo "<td><input type='text' class='textlite' name='tag_rank' value=\"", htmlspecialchars($v), "\" size='40' onchange='hiliter(this)' /><br /><div class='hint'>If set, the <a href='offline$ConfSiteSuffix'>offline reviewing page</a> will expose support for uploading rankings by this tag.</div></td></tr>";
    //echo "</table>";

    //echo "<div class='g'></div>\n";
    //doCheckbox('tag_seeall', "PC can see tags for conflicted papers");
	echo "<input type='hidden' name='tag_seeall' value='1'>";

    echo "<hr class='hr' />";

    // Tags
    //echo "<h3>Review ratings</h3>\n";

    //echo "Should HotCRP collect ratings of reviews? &nbsp; <a class='hint' href='help$ConfSiteSuffix?t=revrate'>(Learn more)</a><br />\n";
    //doRadio("rev_ratings", array(REV_RATINGS_PC => "Yes, PC members can rate reviews", REV_RATINGS_PC_EXTERNAL => "Yes, PC members and external reviewers can rate reviews", REV_RATINGS_NONE => "No"));
	echo "<input type='hidden' name='rev_rating' value='". REV_RATING_NONE ."'>";
}

// Review form
function doRfoGroup() {
    require_once("Code/reviewsetform.inc");
    rf_show();
}

// Responses and decisions
function doDecGroup() {
    global $Conf, $rf;

    echo "Can <b>applicants</b> see the <b>granted amounts</b>?<br />";
    doRadio("au_seerev", array(AU_SEEREV_NO => "No", AU_SEEREV_ALWAYS => "Yes"));
	//echo "<input type='hidden' name='au_seerev' value='". AU_SEEREV_ALWAYS ."'>";

    /*echo "<div class='g'></div>\n<table>";
    doCheckbox('resp_open', "<b>Collect authors&rsquo; responses to the reviews:</b>", true);
    echo "<tr><td></td><td><table>";
    doDateRow('resp_done', 'Deadline', null, "lxcaption");
    doGraceRow('resp_grace', 'Grace period', "lxcaption");
    echo "</table></td></tr></table>";*/
	echo "<input type='hidden' name='resp_open' value='0'>";

    echo "<div class='g'></div>\n<hr class='hr' />\n", "Can <b>applicants</b> see the <b>decisions</b>?<br />\n";
    doRadio("seedec", array(SEEDEC_REV => "No", SEEDEC_ALL => "Yes"));

    echo "<div class='g'></div>\n";
    echo "<table>\n";
    $decs = $rf->options['outcome'];
    krsort($decs);
    $n = 0;
    foreach ($decs as $k => $v)
	if ($k)
	    $n++;
    $caption = "<td class='lcaption' rowspan='$n'>Current decision types</td>";
    foreach ($decs as $k => $v)
	if ($k) {
	    echo "<tr>$caption<td class='lentry nowrap'>";
	    echo "<input type='text' class='textlite' name='dec$k' value=\"", htmlspecialchars($v), "\" size='35' /> &nbsp; ", ($k > 0 ? "Accept class" : "Reject class"), "</td></tr>\n";
	    $caption = "";
	}
    echo "<tr><td class='lcaption'>New decision type<br /></td><td class='lentry nowrap'><input type='text' class='textlite' name='decn' value=\"\" size='35' /> &nbsp; ",
	tagg_select("dtypn", array(1 => "Accept class", -1 => "Reject class")),
	"<br /><small>Examples: &ldquo;Accepted as short paper&rdquo;, &ldquo;Early reject&rdquo;</small>",
	"</td></tr>\n</table>\n";

    // Final copies
    /*echo "<hr class='hr' />";
    echo "<h3>Final copies</h3>\n";
    echo "<table>";
    doCheckbox('final_open', '<b>Collect final copies of accepted papers:</b>', true);
    echo "<tr><td></td><td><table>";
    doDateRow("final_done", "Deadline", null, "lxcaption");
    doGraceRow("final_grace", "Grace period", "lxcaption");
    echo "</table></td></tr></table>\n\n";*/
	echo "<input type='hidden' name='final_open' value='0'>";
}

$belowHr = true;

echo "<form method='post' action='settings$ConfSiteSuffix?post=1' enctype='multipart/form-data' accept-charset='UTF-8'><div><input type='hidden' name='group' value='$Group' />\n";

echo "<table class='settings'><tr><td class='caption initial final'>";
echo "<table class='lhsel'>";
foreach (array("acc" => "Accounts",
	       "msg" => "Messages",
	       "sub" => "Submissions",
	       //"opt" => "Submission options",
	       "rev" => "Reviews",
	       "rfo" => "Review form",
	       "dec" => "Decisions") as $k => $v) {
    echo "<tr><td>";
    if ($Group == $k)
	echo "<div class='lhl1'><a class='q' href='settings$ConfSiteSuffix?group=$k'>$v</a></div>";
    else
	echo "<div class='lhl0'><a href='settings$ConfSiteSuffix?group=$k'>$v</a></div>";
    echo "</td></tr>";
}
echo "</table></td><td class='top'><div class='lht'>";

// Good to warn multiple times about GD
if (!function_exists("imagecreate"))
    $Conf->warnMsg("Your PHP installation appears to lack GD support, which is required for drawing graphs.  You may want to fix this problem and restart Apache.", true);

echo "<div class='aahc'>";
doActionArea();

if ($Group == "acc")
    doAccGroup();
else if ($Group == "msg")
    doMsgGroup();
else if ($Group == "sub")
    doSubGroup();
else if ($Group == "opt")
    doOptGroup();
else if ($Group == "rev")
    doRevGroup();
else if ($Group == "rfo")
    doRfoGroup();
else
    doDecGroup();
	
doActionArea();
echo "</div></div></td></tr>
</table></div></form>\n";

$Conf->footer();
