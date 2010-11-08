<?php
// paper.php -- HotCRP paper view and edit page
// HotCRP is Copyright (c) 2006-2009 Eddie Kohler and Regents of the UC
// Distributed under an MIT-like license; see LICENSE

require_once("Code/header.inc");
require_once("Code/papertable.inc");
$Me = $_SESSION["Me"];
$Me->goIfInvalid();
$useRequest = false;
$forceShow = (defval($_REQUEST, "forceShow") && $Me->privChair);
$linkExtra = ($forceShow ? "&amp;forceShow=1" : "");
$Error = array();
if (isset($_REQUEST["emailNote"])
    && $_REQUEST["emailNote"] == "Optional explanation")
    unset($_REQUEST["emailNote"]);
if (defval($_REQUEST, "mode") == "edit")
    $_REQUEST["mode"] = "pe";
else if (defval($_REQUEST, "mode") == "view")
    $_REQUEST["mode"] = "p";


// header
function confHeader() {
    global $paperId, $newPaper, $prow, $paperTable, $Conf, $linkExtra,
	$CurrentList;
    if ($paperTable)
	$mode = $paperTable->mode;
    else
	$mode = "p";
    if ($paperId <= 0)
	$title = ($newPaper ? "New Application" : "Application View");
    else
	$title = "Application #$paperId";

    $Conf->header($title, "paper_" . ($mode == "pe" ? "edit" : "view"), actionBar($prow, $newPaper, $mode), false);
    if (isset($CurrentList) && $CurrentList > 0
	&& strpos($linkExtra, "ls=") === false)
	$linkExtra .= "&amp;ls=" . $CurrentList;
}

function errorMsgExit($msg) {
    global $Conf;
    confHeader();
    $Conf->errorMsgExit($msg);
}


// collect paper ID: either a number or "new"
$newPaper = (defval($_REQUEST, "p") == "new"
	     || defval($_REQUEST, "paperId") == "new");
$paperId = -1;


// general error messages
if (isset($_REQUEST["post"]) && $_REQUEST["post"] && !count($_POST))
    $Conf->errorMsg("It looks like you tried to upload a gigantic file, larger than I can accept.  Any changes were lost.");


// grab paper row
function loadRows() {
    global $prow;
    if (!($prow = PaperTable::paperRow($whyNot)))
	errorMsgExit(whyNotText($whyNot, "view"));
}
$prow = null;
if (!$newPaper) {
    loadRows();
    $paperId = $prow->paperId;
}


// paper actions
if (isset($_REQUEST["setrevpref"]) && $prow) {
    require_once("Code/paperactions.inc");
    PaperActions::setReviewPreference($prow);
    loadRows();
}
if (isset($_REQUEST["setrank"]) && $prow) {
    require_once("Code/paperactions.inc");
    PaperActions::setRank($prow);
    loadRows();
}
if (isset($_REQUEST["rankctx"]) && $prow) {
    require_once("Code/paperactions.inc");
    PaperActions::rankContext($prow);
    loadRows();
}


// check paper action
if (isset($_REQUEST["checkformat"]) && $prow && $Conf->setting("sub_banal")) {
    $ajax = defval($_REQUEST, "ajax", 0);
    require_once("Code/checkformat.inc");
    $cf = new CheckFormat();
    $status = $cf->analyzePaper($prow->paperId, false, $Conf->settingText("sub_banal", ""));

    // chairs get a hint message about multiple checking
    if ($Me->privChair) {
	if (!isset($_SESSION["info"]))
	    $_SESSION["info"] = array();
	$_SESSION["info"]["nbanal"] = defval($_SESSION["info"], "nbanal", 0) + 1;
	if ($_SESSION["info"]["nbanal"] >= 3 && $_SESSION["info"]["nbanal"] <= 6)
	    $cf->msg("info", "To run the format checker for many papers, use Download &gt; Format check on the <a href='search$ConfSiteSuffix?q='>search page</a>.");
    }

    $cf->reportMessages();
    if ($ajax)
	$Conf->ajaxExit(array("status" => $status), true);
}


// withdraw and revive actions
if (isset($_REQUEST["withdraw"]) && !$newPaper) {
    if ($Me->canWithdrawPaper($prow, $whyNot)) {
	$Conf->qe("update Paper set timeWithdrawn=" . time() . ", timeSubmitted=if(timeSubmitted>0,-100,0) where paperId=$paperId", "while withdrawing application");
	$Conf->qe("update PaperReview set reviewNeedsSubmit=0 where paperId=$paperId", "while withdrawing application");
	$Conf->updatePapersubSetting(false);
	loadRows();

	// email contact authors themselves
	require_once("Code/mailtemplate.inc");
	if ($Me->privChair && defval($_REQUEST, "doemail") <= 0)
	    /* do nothing */;
	else if ($prow->conflictType >= CONFLICT_AUTHOR)
	    Mailer::sendContactAuthors("@authorwithdraw", $prow, null, array("infoNames" => 1));
	else
	    Mailer::sendContactAuthors("@adminwithdraw", $prow, null, array("reason" => defval($_REQUEST, "emailNote", ""), "infoNames" => 1));

	// email reviewers
	if ($prow->startedReviewCount > 0)
	    Mailer::sendReviewers("@withdrawreviewer", $prow, null, array("reason" => defval($_REQUEST, "emailNote", "")));

	// remove voting tags so people don't have phantom votes
	require_once("Code/tags.inc");
	$vt = voteTags();
	if (count($vt) > 0) {
	    $q = array();
	    foreach ($vt as $t => $v)
		$q[] = "tag='" . sqlq($t) . "' or tag like '%~" . sqlq_for_like($t) . "'";
	    $Conf->qe("delete from PaperTag where paperId=$prow->paperId and (" . join(" or ", $q) . ")", "while cleaning up voting tags");
	}

	$Conf->log("Withdrew", $Me, $paperId);
    } else
	$Conf->errorMsg(whyNotText($whyNot, "withdraw"));
}
if (isset($_REQUEST["revive"]) && !$newPaper) {
    if ($Me->canRevivePaper($prow, $whyNot)) {
	$Conf->qe("update Paper set timeWithdrawn=0, timeSubmitted=if(timeSubmitted=-100," . time() . ",0) where paperId=$paperId", "while reviving application");
	$Conf->qe("update PaperReview set reviewNeedsSubmit=1 where paperId=$paperId and reviewSubmitted is null", "while reviving application");
	$Conf->qe("update PaperReview join PaperReview as Req on (Req.paperId=$paperId and Req.requestedBy=PaperReview.contactId and Req.reviewType=" . REVIEW_EXTERNAL . ") set PaperReview.reviewNeedsSubmit=-1 where PaperReview.paperId=$paperId and PaperReview.reviewSubmitted is null and PaperReview.reviewType=" . REVIEW_SECONDARY, "while reviving application");
	$Conf->qe("update PaperReview join PaperReview as Req on (Req.paperId=$paperId and Req.requestedBy=PaperReview.contactId and Req.reviewType=" . REVIEW_EXTERNAL . " and Req.reviewSubmitted>0) set PaperReview.reviewNeedsSubmit=0 where PaperReview.paperId=$paperId and PaperReview.reviewSubmitted is null and PaperReview.reviewType=" . REVIEW_SECONDARY, "while reviving application");
	$Conf->updatePapersubSetting(true);
	loadRows();
	$Conf->log("Revived", $Me, $paperId);
    } else
	$Conf->errorMsg(whyNotText($whyNot, "revive"));
}


// set request authorTable from individual components
function setRequestAuthorTable() {
    if (!isset($_REQUEST["aueditcount"]))
	$_REQUEST["aueditcount"] = 50;
    if ($_REQUEST["aueditcount"] < 1)
	$_REQUEST["aueditcount"] = 1;
    $_REQUEST["authorTable"] = array();
    $anyAuthors = false;
    for ($i = 1; $i <= $_REQUEST["aueditcount"]; $i++) {
	if (isset($_REQUEST["auname$i"]) || isset($_REQUEST["auemail$i"]) || isset($_REQUEST["auaff$i"]))
	    $anyAuthors = true;
	$a = simplifyWhitespace(defval($_REQUEST, "auname$i", ""));
	$b = simplifyWhitespace(defval($_REQUEST, "auemail$i", ""));
	$c = simplifyWhitespace(defval($_REQUEST, "auaff$i", ""));
	if ($a != "" || $b != "" || $c != "") {
	    $a = splitName($a);
	    $a[2] = $b;
	    $a[3] = $c;
	    $_REQUEST["authorTable"][] = $a;
	}
    }
    if (!count($_REQUEST["authorTable"]) && !$anyAuthors)
	unset($_REQUEST["authorTable"]);
}
if (isset($_REQUEST["auname1"]) || isset($_REQUEST["auemail1"])
    || isset($_REQUEST["aueditcount"]))
    setRequestAuthorTable();


// update paper action
function setRequestFromPaper($prow) {
    foreach (array("title", "abstract", "authorTable", "collaborators") as $x)
	if (!isset($_REQUEST[$x]))
	    $_REQUEST[$x] = $prow->$x;
}

function requestSameAsPaper($prow) {
    global $Conf;
    foreach (array("title", "abstract", "authorTable", "collaborators") as $x)
	if ($_REQUEST[$x] != $prow->$x)
	    return false;
    if (fileUploaded($_FILES['paperUpload'], $Conf))
	return false;
    $result = $Conf->q("select TopicArea.topicId, PaperTopic.paperId from TopicArea left join PaperTopic on PaperTopic.paperId=$prow->paperId and PaperTopic.topicId=TopicArea.topicId");
    while (($row = edb_row($result))) {
	$got = rcvtint($_REQUEST["top$row[0]"]) > 0;
	if (($row[1] > 0) != $got)
	    return false;
    }
    if ($Conf->setting("paperOption")) {
	$result = $Conf->q("select OptionType.optionId, coalesce(PaperOption.value, 0) from OptionType left join PaperOption on PaperOption.paperId=$prow->paperId and PaperOption.optionId=OptionType.optionId");
	while (($row = edb_row($result))) {
	    $got = defval($_REQUEST, "opt$row[0]", 0);
	    if (cvtint($got, 0) != $row[1])
		return false;
	}
    }
    return true;
}

function uploadFile($type) {
    global $prow, $Conf, $Me;
	
    $result = $Conf->storePaper($type . 'Upload', $prow, $type, $Me->privChair && defval($_REQUEST, "override"));

    print "Here" . $type;  
	if ($type == "resume")
		$typeText = "resume";
	else if ($type == "studentLetter")
		$typeText = "cover letter";
	else if ($type == "referenceLetter")
		$typeText = "supervisor letter";
	else if ($type == "budget")
		$typeText = "budget";
		
    if ($result == 0 || PEAR::isError($result)) {
		$Conf->errorMsg("There was an error while trying to update your " . $typeText . ". Please try again.");
		return false;
    }
	
    return true;
}

function updatePaper($Me, $isSubmit, $isSubmitFinal=false) {
    global $ConfSiteSuffix, $paperId, $newPaper, $Error, $Conf, $Opt, $prow;
    $contactId = $Me->contactId;
    if ($isSubmitFinal)
	$isSubmit = false;

    // XXX lock tables 

    // check that all required information has been entered
    /*
	foreach (array("title","applicationType") as $x)
	if (!isset($_REQUEST[$x]))
	    $_REQUEST[$x] = "";

    $q = "";
	*/

    $q = "";
    foreach (array("title","applicationType","totalRequested","usInstitution","paperAuthor","videoLink") as $x) {
		/*if (trim($_REQUEST[$x]) == "") {
	    	if ($x != "collaborators" || ($Conf->setting("sub_collab") && $isSubmit))
				$Error[$x] = 1;
		}
		*/
      if ($x == "title") {
          $_REQUEST[$x] = simplifyWhitespace($_REQUEST[$x]);
        $q .= "$x='" . sqlqtrim($_REQUEST[$x]) . "', ";
      } else if ($x == "videoLink") {
        $q .= "$x='" . sqlqtrim($_REQUEST[$x]) . "', ";
      } else {
        $_REQUEST[$x] = (int) $_REQUEST[$x];
        $q .= "$x=" . $_REQUEST[$x] . ", ";	
      }
    }

    // any missing fields?
	/*
    if (count($Error) > 0) {
		$fields = array();
	
		if (isset($Error["title"]))
	    	$fields[0] = "Title";
		if (isset($Error["type"]))
	    	$fields[1] = "Type";
	
		$emsg = "Before " . ($isSubmit ? "submitting" : "registering") . " your application, you must enter ";
		if (count($fields) >= 1)
		    $emsg .= "a value for the " . commajoin($fields) . " field.";
	
		return false;
    }
	*/

    // defined contact ID
    if ($newPaper && (isset($_REQUEST["contact_email"]) || isset($_REQUEST["contact_name"])) && $Me->privChair)
		if (!($contactId = $Conf->getContactId($_REQUEST["contact_email"], "contact_"))) {
		    $Conf->errorMsg("You must supply a valid email address for the contact author.");
	    	$Error["contactAuthor"] = 1;
		    return false;
		}

    // update Paper table
    if ($newPaper)
		$q .= "paperStorageId=1";
    else
		$q = substr($q, 0, -2) . " where paperId=$paperId and timeWithdrawn<=0";

  	$result = $Conf->qe(($newPaper ? "insert into" : "update") . " Paper set $q", "while updating paper information");
    if (!$result)
		return false;

    // fetch paper ID
    if ($newPaper) {
		$result = $Conf->lastInsertId("while updating application information");
		if (!$result)
		    return false;
		$paperId = $_REQUEST["p"] = $_REQUEST["paperId"] = $result;

		$result = $Conf->qe("insert into PaperConflict (paperId, contactId, conflictType) values ($paperId, $contactId, " . CONFLICT_CONTACTAUTHOR . ")", "while updating application information");
		if (!$result)
	    	return false;
    }

    // upload resume and student letter if appropriate
    if (fileUploaded($_FILES['resumeUpload'], $Conf)) {
		if ($newPaper)
		    loadRows();
		if (!uploadFile("resume"))
		    return false;
    }
	if (fileUploaded($_FILES['studentLetterUpload'], $Conf)) {
		if ($newPaper)
		    loadRows();
		if (!uploadFile("studentLetter"))
		    return false;
    }
	if (fileUploaded($_FILES['referenceLetterUpload'], $Conf)) {
		if ($newPaper)
		    loadRows();
		if (!uploadFile("referenceLetter"))
		    return false;
    }
    
    if (fileUploaded($_FILES['budgetUpload'], $Conf)) {
		if ($newPaper)
		    loadRows();
		if (!uploadFile("budget"))
		    return false;
    }
		
    if ($isSubmit || $Conf->setting("pc_seeall"))
		$Conf->updatePapersubSetting(true);

    // confirmation message
    loadRows();
    if ($isSubmit) {
		$actiontext = "Submitted";
		$subject = "Submitted application #$paperId";
		$confirmtext = "submission of";
    } 
	else if ($newPaper) {
		$actiontext = "Registered new";
		$subject = "Registered application #$paperId";
		$confirmtext = "registration of";
    } 
	else {
		$actiontext = "Updated";
		$subject = "Updated application #$paperId";
		$confirmtext = "update of";
    }

    // additional information	
	$deadline = $Conf->printableTimeSetting("sub_update");
	if ($deadline != "N/A" && ($prow->timeSubmitted <= 0 || $Conf->setting("sub_freeze") <= 0))
	    $extratext = "  You have until $deadline to update the application further.";
		
    // HTML confirmation
	$Conf->confirmMsg($actiontext . " application #$paperId.  " . $extratext);

    // mail confirmation
    $m = "This mail confirms the $confirmtext application #$paperId at the %CONFNAME% submission site.\n\n
	
    Title: %TITLE%
    Application site: %URL%/paper$ConfSiteSuffix?p=%NUMBER%\n\n";
    if ($extratext !== "")
	$m .= preg_replace("|</?strong>|", "", $extratext) . "\n\n";
    if ($Me->privChair && isset($_REQUEST["emailNote"]))
	$m .= "A conference administrator provided the following reason for this update: %REASON%\n\n";
    else if ($Me->privChair && $prow->conflictType < CONFLICT_AUTHOR)
	$m .= "A conference administrator performed this update.\n\n";
    $m .= "Contact the site administrator, %ADMIN%, with any questions or concerns.

- %CONFSHORTNAME% Submissions\n";

    // send email to all contact authors
    if (!$Me->privChair || defval($_REQUEST, "doemail") > 0) {
	require_once("Code/mailtemplate.inc");
	Mailer::sendContactAuthors(array("subject" => "$subject %TITLEHINT%",
					 "body" => $m),
				   $prow, null, array("reason" => defval($_REQUEST, "emailNote", ""), "infoNames" => 1));
    }

    $Conf->log($actiontext, $Me, $paperId);
    return true;
}

if (isset($_REQUEST["update"]) || isset($_REQUEST["submitfinal"])) {
    // get missing parts of request
    if (!$newPaper)
	setRequestFromPaper($prow);

    // check deadlines
    if ($newPaper)
	// we know that canStartPaper implies canFinalizePaper
	$ok = $Me->canStartPaper($whyNot);
    else if (isset($_REQUEST["submitfinal"]))
	$ok = $Me->canSubmitFinalPaper($prow, $whyNot);
    else {
	$ok = $Me->canUpdatePaper($prow, $whyNot);
	if (!$ok && isset($_REQUEST["submit"]) && requestSameAsPaper($prow))
	    $ok = $Me->canFinalizePaper($prow, $whyNot);
    }

    // actually update
    if (!$ok) {
	if (isset($_REQUEST["submitfinal"]))
	    $action = "submit final copy for";
	else
	    $action = ($newPaper ? "register" : "update");
	$Conf->errorMsg(whyNotText($whyNot, $action));
    } else if (updatePaper($Me, isset($_REQUEST["submit"]), isset($_REQUEST["submitfinal"]))) {
	if ($newPaper)
	    $Conf->go("paper$ConfSiteSuffix?p=$paperId&mode=pe");
    }

    // use request?
    $useRequest = ($ok || $Me->privChair);
}


// delete action
if (isset($_REQUEST['delete'])) {
    if ($newPaper)
	$Conf->confirmMsg("Application deleted.");
    else if (!$Me->privChair)
	$Conf->errorMsg("Only the program chairs can permanently delete applications.  Authors can withdraw applications, which is effectively the same.");
    else {
	// mail first, before contact info goes away
	if (!$Me->privChair || defval($_REQUEST, "doemail") > 0) {
	    require_once("Code/mailtemplate.inc");
	    Mailer::sendContactAuthors("@deletepaper", $prow, null, array("reason" => defval($_REQUEST, "emailNote", ""), "infoNames" => 1));
	}
	// XXX email self?

	$error = false;
	$tables = array('Paper', 'PaperStorage', 'PaperComment', 'PaperConflict', 'PaperReview', 'PaperReviewArchive', 'PaperReviewPreference', 'PaperTopic', 'PaperTag');
	if ($Conf->setting("allowPaperOption"))
	    $tables[] = 'PaperOption';
	foreach ($tables as $table) {
	    $result = $Conf->qe("delete from $table where paperId=$paperId", "while deleting application");
	    $error |= ($result == false);
	}
	if (!$error) {
	    $Conf->confirmMsg("Application #$paperId deleted.");
	    $Conf->updatePapersubSetting(false);
	    if ($prow->outcome > 0)
		$Conf->updatePaperaccSetting(false);
	    $Conf->log("Deleted", $Me, $paperId);
	}

	$prow = null;
	errorMsgExit("");
    }
}


// paper actions
if (isset($_REQUEST["settags"])) {
    require_once("Code/paperactions.inc");
    PaperActions::setTags($prow);
    loadRows();
}
if (isset($_REQUEST["tagreport"])) {
    require_once("Code/paperactions.inc");
    PaperActions::tagReport($prow);
}


// correct modes
$paperTable = new PaperTable($prow);
$paperTable->resolveComments();
if ($paperTable->mode == "r" || $paperTable->mode == "re") {
    $paperTable->resolveReview();
    $paperTable->fixReviewMode();
}


// page header
confHeader();


// prepare paper table
$finalEditMode = false;
if ($paperTable->mode == "pe") {
    $editable = $newPaper
	|| ($prow->timeWithdrawn <= 0
	    && ($Conf->timeUpdatePaper($prow) || $Me->actChair($prow, true)));
    if ($prow && $prow->outcome > 0
	&& $Conf->collectFinalPapers()
	&& ($Conf->timeSubmitFinalPaper() || $Me->actChair($prow, true)))
	$editable = $finalEditMode = true;
} else
    $editable = false;

$paperTable->initialize($editable, $editable && $useRequest,
			$paperTable->mode != "pe" && (count($paperTable->rrows) || count($paperTable->mycrows)),
			"paper");

// produce paper table
$paperTable->paptabBegin();

if ($paperTable->mode == "r" && !$paperTable->rrow)
    $paperTable->paptabEndWithReviews();
else if ($paperTable->mode == "re" || $paperTable->mode == "r")
    $paperTable->paptabEndWithEditableReview();
else
    $paperTable->paptabEndWithReviewMessage();

if ($paperTable->mode != "pe")
    $paperTable->paptabComments();


echo foldsessionpixel("paper9", "foldpaperp"), foldsessionpixel("paper5", "foldpapert"), foldsessionpixel("paper6", "foldpaperb");
$Conf->footer();
