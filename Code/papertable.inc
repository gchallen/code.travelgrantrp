<?php
// papertable.inc -- HotCRP helper class for producing paper tables
// HotCRP is Copyright (c) 2006-2009 Eddie Kohler and Regents of the UC
// Distributed under an MIT-like license; see LICENSE

global $textAreaRows;
$textAreaRows = array("title" => 1, "abstract" => 12, "authorInformation" => 5,
		      "collaborators" => 6);

class PaperTable {

    const FINALCOPY = 4;
    const ENABLESUBMIT = 8;

    const STATUS_DATE = 16;
    const STATUS_CONFLICTINFO = 64;
    const STATUS_CONFLICTINFO_PC = 128;
    const STATUS_REVIEWERINFO = 256;

    const PAPERLINKS_TYPE = 2;

    var $prow;
    var $rrows;
    var $crows;
    var $mycrows;
    var $rrow;
    var $editrrow;
    var $mode;
    var $allreviewslink;

    var $editable;
    var $useRequest;
    var $allFolded;
    var $foldState;
    var $highlight;
    var $matchPreg;
    var $watchCheckbox;
    var $initial;
    var $entryMatches;
    var $videoLink;

    function PaperTable($prow) {
	global $Conf, $Me;

	$this->prow = $prow;
	$this->rrows = null;
	$this->crows = null;
	$this->rrow = null;
	$this->editrrow = null;

	if ($this->prow == null) {
	    $this->mode = "pe";
	    return;
	}

	$ms = array();
	if (isset($_REQUEST["reviewId"]))
	    $ms["re"] = true;
	if ($Me->canViewReview($prow, null))
	    $ms["r"] = true;
	if ($Me->canReview($prow, null))
	    $ms["re"] = true;
	if ($prow->conflictType >= CONFLICT_AUTHOR
	    && ($Conf->timeFinalizePaper($prow) || $prow->timeSubmitted <= 0))
	    $ms["pe"] = true;
	if ($Me->canViewPaper($prow))
	    $ms["p"] = true;
	if ($prow->conflictType >= CONFLICT_AUTHOR
	    || $Me->actChair($prow, true))
	    $ms["pe"] = true;
	if ($prow->myReviewType >= REVIEW_SECONDARY
	    || $Me->actChair($prow, true))
	    $ms["assign"] = true;
	if (isset($_REQUEST["mode"]) && isset($ms[$_REQUEST["mode"]]))
	    $this->mode = $_REQUEST["mode"];
	else if (isset($_REQUEST["m"]) && isset($ms[$_REQUEST["m"]]))
	    $this->mode = $_REQUEST["m"];
	else
	    $this->mode = key($ms);
	if ($this->mode == "p" && isset($ms["r"]))
	    $this->mode = "r";
    }

    function initialize($editable, $useRequest, $allFolded,
			$foldsession = null) {
	global $thisMatchPreg, $CurrentList;

	$this->editable = $editable;
	$this->useRequest = $useRequest;

	$this->foldState = 15;
	if ($foldsession && !defval($_SESSION, "fold${foldsession}a", 1))
	    $this->foldState &= ~1;
	if ($foldsession && !defval($_SESSION, "foldpaperp", 1))
	    $this->foldState &= ~2;
	if ($foldsession && !defval($_SESSION, "foldpaperb", 1))
	    $this->foldState &= ~4;
	if ($foldsession && !defval($_SESSION, "foldpapert", 1))
	    $this->foldState &= ~8;

	$this->allFolded = $allFolded;

	$this->matchPreg = array();
	$matcher = null;
	if (isset($thisMatchPreg) && $thisMatchPreg !== ""
	    && (!is_array($thisMatchPreg) || count($thisMatchPreg) > 0))
	    $matcher = $thisMatchPreg;
	else if (isset($_SESSION["l"]) && isset($CurrentList)
		 && $CurrentList > 0
		 && defval($_SESSION["l"][$CurrentList], "matchPreg", "") !== "")
	    $matcher = $_SESSION["l"][$CurrentList]["matchPreg"];
	if ($matcher !== null) {
	    if (!is_array($matcher))
		$matcher = array("abstract" => $matcher, "title" => $matcher,
				 "authorInformation" => $matcher,
				 "collaborators" => $matcher);
	    foreach ($matcher as $k => $v)
		if (is_string($v) && $v != "") {
		    if ($v[0] != "{")
			$v = "{(" . $v . ")}i";
		    $this->matchPreg[$k] = $v;
		}
	}
	if (count($this->matchPreg) == 0)
	    $this->matchPreg = null;

	$this->highlight = $this->matchPreg !== null;
	$this->watchCheckbox = WATCH_COMMENT;
	$this->initial = true;
    }

    function echoDivEnter($prow = null) {
	// if highlighting, automatically unfold abstract/authors
	if ($this->highlight && $prow && $this->allFolded
	    && ($this->foldState & 4)) {
	    $data = $this->entryData("abstract", $prow);
	    if ($this->entryMatches)
		$this->foldState &= ~4;
	}
	if ($this->highlight && $prow && ($this->foldState & 1)) {
	    cleanAuthor($prow);
	    $data = $this->entryData("authorInformation", $prow);
	    if ($this->entryMatches)
		$this->foldState &= ~(1 | 2);
	}

	echo "<div id='foldpaper' class='",
	    ($this->foldState & 1 ? "fold8c" : "fold8o"),
	    ($this->foldState & 2 ? " fold9c" : " fold9o"),
	    ($this->foldState & 4 ? " fold6c" : " fold6o"),
	    ($this->foldState & 8 ? " fold5c" : " fold5o"), "'>";
    }

    function echoDivExit() {
	echo "</div>";
    }

    function papt($what, $name, $extra = array()) {
	global $Error, $Conf, $ConfSiteSuffix;
	$type = defval($extra, "type", "pap");
	$c = "<div class='${type}t";
	if (isset($Error[$what]))
	    $c .= " error";
	if (isset($extra["extraclass"]))
	    $c .= " " . $extra["extraclass"];
	$c .= "'><span class='${type}fn'>";
	$editfolder = defval($extra, "editfolder", false);
	if (!($fold = defval($extra, "fold", false))) {
	    $n = (is_array($name) ? $name[0] : $name);
	    if ($editfolder)
		$c .= "<a class='q fn' title='Edit' "
		    . "href='javascript:fold(\"$editfolder\", 0)'>"
		    . $n . "</a><span class='fx'>" . $n . "</span>";
	    else
		$c .= $n;
	} else {
	    $foldnum = defval($extra, "foldnum", 0);
	    if (!is_array($name))
		$name = array($name, $name);
	    $c .= "<a class='q fn$foldnum' "
		. "href='javascript:fold(\"$fold\", 0, $foldnum)' "
		. "title=\"" . defval($extra, "showtitle", "Show")
		. "\">+&nbsp;" . $name[1] . "</a>"
		. "<a class='q fx$foldnum' "
		. "href='javascript:fold(\"$fold\", 1, $foldnum)' "
		. "title=\"" . defval($extra, "hidetitle", "Hide")
		. "\">&minus;&nbsp;" . $name[0] . "</a>";
	    if (($foldsession = defval($extra, "foldsession", false)))
		$c .= foldsessionpixel($fold . $foldnum, $foldsession);
	}
	$c .= "</span>";
	if ($editfolder) {
	    $x = defval($extra, "oneditunfold", "");
	    $a = "<a class='fn' href='javascript:" . $x . ($x ? ";" : "")
		   . "fold(\"$editfolder\", 0)' title='Edit'>";
	    $c .= "<span class='hint'>" . $a
		. $Conf->cacheableImage("edit.png", "[Edit]", null, "b")
		. "</a>&nbsp;" . $a . "Edit</a></span>";
	}
	$c .= "<div class='clear'></div></div>";
	return $c;
    }

    function entryData($fieldName, $prow, $authorTable = false) {
	global $textAreaRows, $Me;
	$this->entryMatches = 0;

	if ($this->useRequest)
	    $text = $_REQUEST[$fieldName];
	else if ($prow)
	    $text = $prow->$fieldName;
	else
	    $text = "".$Me->firstName." ".$Me->lastName." - ".$Me->affiliation;

	if ($this->highlight && isset($textAreaRows[$fieldName])
	    && !$this->editable && isset($this->matchPreg[$fieldName]))
	    $text = highlightMatch($this->matchPreg[$fieldName], htmlspecialchars($text), $this->entryMatches);
	else
	    $text = htmlspecialchars($text);

	if ($authorTable == "col" && !$this->editable)
	    $text = nl2br($text);

	if ($this->editable)
	    	$text = "<textarea class='textlite papertext' name='$fieldName' rows='" . $textAreaRows[$fieldName] . "' onchange='hiliter(this)'>" . $text . "</textarea>";
	return $text;
    }

    function echoTitle($prow) {
	if ($this->highlight && isset($this->matchPreg["title"]))
	    echo highlightMatch($this->matchPreg["title"], htmlspecialchars($prow->title));
	else
	    echo htmlspecialchars($prow->title);
    }

    function paptabTitle($prow, $index = false) {
		global $Me;
    if ($index === false) {
      echo $this->papt("title", "Title");
    } else {
      echo $this->papt("title", $index . "Title");
    }
	  echo "<div class='papv'>";
		
		$fieldName = "title";
		if ($this->useRequest)
	    	$text = $_REQUEST[$fieldName];
		else if ($prow)
		    $text = $prow->$fieldName;
		else
	    	$text = "".$Me->firstName." ".$Me->lastName." - ".$Me->affiliation;

		if ($this->highlight && isset($textAreaRows[$fieldName]) && !$this->editable && isset($this->matchPreg[$fieldName]))
		    $text = highlightMatch($this->matchPreg[$fieldName], htmlspecialchars($text), $this->entryMatches);
		else
		    $text = htmlspecialchars($text);

		echo "<input type='hidden' name='$fieldName' value='$text'>";
		echo $text;
    	
		echo "</div>\n\n";
    }

	function _downloadInfo($prow, $editable, $type) {
		global $Conf, $ConfSiteSuffix, $Me;
  
		// timestamp
		$t = "";
		$fieldName = "" . $type."SubmittedTime"; 
		if (isset($prow) && $prow->$fieldName > 0) {
			$tm = $prow->$fieldName;
			if ($type == "resume")
				$t = "<span class='nowrap' title='Time of most recent CV update'>" . $Conf->cacheableImage("timestamp12.png", "Updated", "Time of most recent CV update") . " " . $Conf->printableTime($tm) . "</span>";
			else if ($type == "studentLetter")
				$t = "<span class='nowrap' title='Time of most recent cover letter update'>" . $Conf->cacheableImage("timestamp12.png", "Updated", "Time of most recent cover letter update") . " " . $Conf->printableTime($tm) . "</span>";
			else if ($type == "referenceLetter")
				$t = "<span class='nowrap' title='Time of most recent supervisor letter update'>" . $Conf->cacheableImage("timestamp12.png", "Updated", "Time of most recent supervisor letter update") . " " . $Conf->printableTime($tm) . "</span>";
			else if ($type == "budget")
				$t = "<span class='nowrap' title='Time of most recent budget update'>" . $Conf->cacheableImage("timestamp12.png", "Updated", "Time of most recent budget update") . " " . $Conf->printableTime($tm) . "</span>";
		}
  
		if ($t)
			$t = "<span class='hint'>$t</span>";
	
		// download marker
		$d = "";
		if ($Me->canDownloadPaper($prow)) {
			$fieldId = "".$type."StorageId";
			if ($prow->$fieldId > 0) {
				$submissionMimetype = null;
				$d = "Current copy: &nbsp;" . paperDownload($prow, $type);			  
			} 			
		}		
	
		// return table
		if ($d !== "")
			return "<table><tr><td class='nowrap'>" . $d . "</td><td><span class='sep'></span></td><td>" . $t . "</td></tr></table>";
		else if ($t !== "")
			return "<table><tr><td>$t</td></tr></table>";
		else
			return "";
    }

    function paptabDownload($prow) {
		global $Conf, $Me, $ConfSiteSuffix, $Opt;
    $i = 1;
		$final = ($prow->outcome > 0 && $Conf->collectFinalPapers());
	
		// status
		echo "<div class='xconfirm'>" . $Me->paperStatus($prow->paperId, $prow) . "</div>\n\n";
		
    echo $this->papt("paper", $i++ . ") Title");
    echo "<div class='papv'><div class='g'></div>", $this->echoTitle($prow), "</div>\n\n";
    
    echo $this->papt("paper", $i++ . ") Contact information");
    echo "<div class='papv'><div class='g'></div>", contactHtml($Me), "</div>\n\n";
    
    if ($Opt['useApplicationTypes'] === true) {  
      echo $this->papt("paper", $i++ . ") Application Type");
      $type = $prow->applicationType;
      $typeText = $Opt['applicationTypes'][$type];
      echo "<div class='papv'><div class='g'></div>", $typeText, "</div>\n\n";
    }
    
    echo $this->papt("paper", $i++ .") US institution?");
    echo "<div class='papv'><div class='g'></div>";
    if ($prow->usInstitution == 0) {
      echo "No";
    } else {
      echo "Yes";
    }
    echo "</div>\n\n";
    
    echo $this->papt("paper", $i++ . ") Paper author?");
    echo "<div class='papv'><div class='g'></div>";
    if ($prow->paperAuthor == 0) {
      echo "No";
    } else {
      echo "Yes";
    }
    echo "</div>\n\n";

    echo $this->papt("paper", $i++ . ") Total Request");
    echo "<div class='papv'><div class='g'></div> \$", $prow->totalRequested, "</div>\n\n";
    

    echo $this->papt("paper", $i++ . ") Budget");
		echo "<div class='papv'><div class='g'></div>", $this->_downloadInfo($prow, false, "budget"), "</div>\n\n";		
		
		echo $this->papt("paper", $i++ . ") CV");
		echo "<div class='papv'><div class='g'></div>", $this->_downloadInfo($prow, false, "resume"), "</div>\n\n";
	  
    if ($prow->videoLink != "") {
      echo $this->papt("paper", $i++ . ") Video");
      echo "<center>";
      echo $this->makeVideoEmbed($prow->videoLink);
      echo "</center>";
    } else if ($this->_downloadInfo($prow, false, "studentLetter") != "") {
      echo $this->papt("paper", $i++ . ") Cover Letter");
      echo "<div class='papv'><div class='g'></div>", $this->_downloadInfo($prow, false, "studentLetter"), "</div>\n\n";
    } else {
      echo $this->papt("paper", $i++ . ") Video");
      echo "<div class='papv'><div class='g'></div></div>";
    }

		echo $this->papt("paper", $i++ . ") Supervisor Letter");
		echo "<div class='papv'><div class='g'></div>", $this->_downloadInfo($prow, false, "referenceLetter"), "</div>\n\n";		
    
		
		// conflicts
		$flags = self::STATUS_CONFLICTINFO;
		if (($flags & self::STATUS_CONFLICTINFO) && $prow->conflictType >= CONFLICT_AUTHOR)
			echo "<div class='g'></div>\nYou are the <span class='author'>applicant</span> of this application.";
		else if ((($flags & self::STATUS_CONFLICTINFO) && $prow->conflictType > 0)
			 || (($flags & self::STATUS_CONFLICTINFO_PC) && $Me->isPC && $prow->conflictType > 0))
			echo "<div class='g'></div>\nYou have a <span class='conflict'>conflict</span> with this application.";
		if ($prow->reviewType != null && ($flags & self::STATUS_REVIEWERINFO)) {
			if ($prow->reviewType == REVIEW_PRIMARY)
			echo "<div class='g'></div>\nYou are a primary reviewer for this application.";
			else if ($prow->reviewType == REVIEW_SECONDARY)
			echo "<div class='g'></div>\nYou are a secondary reviewer for this application.";
			else if ($prow->reviewType == REVIEW_EXTERNAL)
			echo "<div class='g'></div>\nYou were requested to review this application.";
			else
			echo "<div class='g'></div>\nYou began a review for this application.";
		}
		if ($Me->isPC && $prow->conflictType <= 0
			&& $Conf->timeUpdatePaper($prow) && $this->mode != "assign"
			&& $this->mode != "contact")
			echo "<div class='g'></div><div class='xwarning'>The applicant still has <a href='deadlines$ConfSiteSuffix'>time</a> to make changes.</div>";
    }
	
	function echoUpload($prow, $type, $index = false) { //type: resume, studentLetter, referenceLetter
		global $Conf, $ConfSiteSuffix, $Opt;

    if ($index === false) {
      $typeText = "";
    } else {
      $typeText = $index;
    }  
		if ($type == "resume")
			$typeText .= "CV";
		else if ($type == "studentLetter")
			$typeText .= "Cover letter";
		else if ($type == "referenceLetter")
			$typeText .= "Supervisor letter";
		else if ($type == "budget")
			$typeText .= "Budget";
	
		$maxsize = ini_get("upload_max_filesize");
		
		if (defval($Opt, "noPapers"))
		  $t = null;
		else if (defval($Opt, "disablePS"))
		  $t = $typeText . " <span class='papfnh'>(PDF file with max size " . $maxsize . "B) </span>";
		else if (defval($Opt, "disablePDF"))
		  $t = $typeText . " <span class='papfnh'>(PostScript file with max size " . $maxsize . "B) </span>";
		else
		  $t = $typeText . " <span class='papfnh'>(PDF/PostScript file with max size " . $maxsize . "B) </span>";
		  
		if ($t !== null)
		  echo $this->papt("paper", $t);
    if ($type == "budget") {
      echo "<div class='paphint'>Please provide a half-page budget justifying your request. Include estimates of the cost of transportation (airfare booked on US carriers for students at US institutions), lodging, meals, and miscellaneous expenses. Registration will be waived for travel grant recipients.<br></div>";
    } else if ($type == "resume") {
      echo "<div class='paphint'>Please provide a recent CV.<br></div>";
    } else if ($type == "referenceLetter") {
      echo "<div class='paphint'>Please provide a supporting letter from your Ph.D. adviser or academic supervisor. Paper authors applying for funding should make sure that their letter of support details the monetary need underlying the request.<br></div>";
    } else if ($type == "studentLetter") {
      echo "<div class='paphint'>Please provide two-page letter describing your current research, your interest in SenSys'10, and what you will contribute to the conference. <strong>Note that applicants submitting the video version of this information will receive preference.</strong> If you do choose to provide a letter, you may want to explain why you were unable to upload a video.<br></div>";
    }

		echo "<div class='papv'>";
		
		if (!defval($Opt, "noPapers")) {
		  echo "Upload copy:&nbsp; ", "<input ",
		  "class='textlite' type='file' name='". $type . "Upload' accept='",
		  (defval($Opt, "disablePDF") ? "" : "application/pdf "),
		  (defval($Opt, "disablePS") ? "" : "application/postscript "),
		  "' size='30' onchange='hiliter(this)";
		}
		
		echo "<input type='hidden' name='submit' value='1'>";
		  
		if (($t = $this->_downloadInfo($prow, true, $type)))
		echo "<div class='g'></div>", $t;
		
		echo "</div>\n\n";
	}

    function authorData($table, $type, $viewAs = null) {
	global $Conf;
	if ($this->highlight && isset($this->matchPreg["authorInformation"]))
	    $highpreg = $this->matchPreg["authorInformation"];
	else
	    $highpreg = false;
	$this->entryMatches = 0;

	$names = array();
	if ($type == "last") {
	    foreach ($table as $au) {
		$n = abbreviateName($au[0], $au[1]);
		if ($highpreg !== false) {
		    $n = highlightMatch($highpreg, htmlspecialchars($n), $nm);
		    $this->entryMatches += $nm;
		} else
		    $n = htmlspecialchars($n);
		$names[] = $n;
	    }
	    return join(", ", $names);

	} else {
	    foreach ($table as $au) {
		$n = trim("$au[0] $au[1]");
		if ($au[3] != "")
		    $n .= " ($au[3])";
		if ($au[2] != "")
		    $n .= " <$au[2]>";
		if ($highpreg !== false) {
		    $n = highlightMatch($highpreg, htmlspecialchars($n), $nm);
		    $this->entryMatches += $nm;
		} else
		    $n = htmlspecialchars($n);
		if ($au[2] != "" && preg_match("/ &lt;.*&gt;\\Z/", $n, $m))
		    $n = substr($n, 0, strlen($n) - strlen($m[0]) + 5)
			. "<a href=\"mailto:" . htmlspecialchars($au[2]) . "\">"
			. substr($m[0], 5, strlen($m[0]) - 9)
			. "</a>&gt;";
		if ($au[2] != "" && $viewAs !== null && $viewAs->email != $au[2]
		    && $viewAs->privChair)
		    $n .= " <a href=\"" . htmlspecialchars(selfHref(array("viewContact" => $au[2]))) . "\">" . $Conf->cacheableImage("viewas.png", "[Act as]", "Act as " . contactNameHtml($au)) . "</a>";
		$names[] = $n;
	    }
	    return join("<br />\n", $names);
	}
    }

	function echoContactInformation($index = false) {
	  global $Me, $Conf;
    if ($index === false) {
      echo $this->papt("contactAuthor", "Contact information");
    } else {
      echo $this->papt("contactAuthor", $index . "Contact information");
    }
		echo "<div class='paphint'>Your registered name and email will be used for future communication.</div>",
		  "<div class='papv'>";
	  echo contactHtml($Me);
	  echo "</div>\n\n";
	}

  function echoRequest($prow, $index = false) {
    global $Me, $Conf, $Opt;
    $fieldName = "totalRequested";
    if ($this->useRequest) {
      $totalRequested = $_REQUEST[$fieldName];
    } else if ($prow) {
      $totalRequested = "".$prow->$fieldName;
    } else {
      $totalRequested = 0;
    }
    if (($totalRequested < 0) ||
        (!(is_numeric($totalRequested)))) {
      $totalRequested = 0;
    }
    $maxRequest = $Opt['maxRequest'];
    if ($index === false) {
      $t = "";
    } else {
      $t = $index;
    }
    $t .= "Total Request" . " <span class='papfnh'>(In USD, maximum \$$maxRequest)</span>";
    echo $this->papt("totalRequested", $t);
    echo "<div class='paphint'>The budget you provide should support this number.<br></div>";
	  echo "<div class='g'></div>\n\n";
    echo "<div class='papv'>$<input type=\"text\" name=\"$fieldName\" value=\"$totalRequested\" size=10><br></div>\n\n";
  }

  function echoUSInstitution($prow, $index) {
    global $Me, $Conf, $Opt;
    $fieldName = "usInstitution";
    if ($this->useRequest) {
      $usInstitution = $_REQUEST[$fieldName];
    } else if ($prow) {
      $usInstitution = "".$prow->$fieldName;
    } else {
      $usInstitution = 1;
    }
    if ($index === false) {
      $t = "";
    } else {
      $t = $index;
    }
    $t .= "Are you studying at a US degree-granting institution?";
    echo $this->papt("usInstitution", $t);
    echo "<div class='paphint'>NSF funding is reserved for students at US institutions.></div>";
	  echo "<div class='g'></div>\n\n";
    echo "<input type=\"radio\" name=\"$fieldName\" value=\"1\" ";
    if ($usInstitution == 1) {
      echo "checked";
    }
    echo ">Yes</input>";
    echo "<input type=\"radio\" name=\"$fieldName\" value=\"0\" ";
    if ($usInstitution == 0) {
      echo "checked";
    }
    echo ">No</input>";
  }
  
  function echoPaperAuthor($prow, $index) {
    global $Me, $Conf, $Opt;
    $fieldName = "paperAuthor";
    if ($this->useRequest) {
      $paperAuthor = $_REQUEST[$fieldName];
    } else if ($prow) {
      $paperAuthor = "".$prow->$fieldName;
    } else {
      $paperAuthor = 0;
    }
    if ($index === false) {
      $t = "";
    } else {
      $t = $index;
    }
    $t .= "Are you the author of a full-length paper at SenSys'10?";
    echo $this->papt("paperAuthor", $t);
    echo "<div class='paphint'>Preference will be given to applicants who do <em>not</em> have full-length papers at the conference. Support for paper authors is expected to be provided by their home institution.<br></div>";
	  echo "<div class='g'></div>\n\n";
    echo "<input type=\"radio\" name=\"$fieldName\" value=\"1\" ";
    if ($paperAuthor == 1) {
      echo "checked";
    }
    echo ">Yes</input>";
    echo "<input type=\"radio\" name=\"$fieldName\" value=\"0\" ";
    if ($paperAuthor == 0) {
      echo "checked";
    }
    echo ">No</input>";
  }

  function echoVideo($prow, $index = false) {
    global $Me, $Conf, $Opt, $videoLink;
    $fieldName = "videoLink";
    if ($this->useRequest) {
      $videoLink = $_REQUEST[$fieldName];
    } else if ($prow) {
      $videoLink = "".$prow->$fieldName;
    } else {
      $videoLink = "";
    }
    if (strlen($videoLink) > 200) {
      $videoLink = "";
    }
    if ($index === false) {
      $t = "";
    } else {
      $t = $index;
    }
    $t .= "Video <span class=papfnh>(Fewer than 200 characters)</span>";
    echo $this->papt("videoLink", $t);
    if ($videoLink == "") {
      echo "<div class='paphint'>Please provide a link to a <strong>five minute maximum</strong> video describing your research, your interest in SenSys'10, and what you will contribute to the conference. Once provided, your video should appear embedded below. <strong>If you cannot see your video, neither can we!</strong><p>";
      echo "(YouTube links, as an example, are of the form <code>http://www.youtube.com/v/<strong>h5iQA1DalPs</strong>&hl=en</code>, where the key <strong>h5iQA1DalPs</strong> appears in the usual YouTube link <code>http://www.youtube.com/watch?v=<strong>h5iQA1DalPs</strong></code>. This is also what appears in the <code>src</code> field of the embedding code provided by video upload websites.)<p>";
      echo "If you cannot create a video, you may upload a cover letter below instead. However, <strong>applications including videos will be given preference during reviewing.</strong><p>";
      echo "</div>";
    } else {
      echo "<div class='paphint'>Clearing this field will unhide the cover letter upload field.";
      echo "</div>";
    }
    echo "<div class='papv'><input type=\"text\" name=\"$fieldName\" value=\"$videoLink\" size=\"100\" maxlength=200><br></div>\n\n";
    if ($videoLink != "") {
      echo "<center>";
      echo $this->makeVideoEmbed($videoLink);
      echo "</center>";
    }
  }

  function makeVideoEmbed($link) {
    return "<object type=\"application/x-shockwave-flash\" data=\"$link\" width=\"320\" height=\"267\"><param name=\"movie\" value=\"$link\" /><param name=\"FlashVars\" value=\"playerMode=embedded\" /><param name=\"wmode\" value=\"transparent\" /></object>";
  }


	function echoApplicationType($prow) {
	  global $Me, $Conf, $Opt;
	  
	  	$fieldName = "applicationType";
		if ($this->useRequest)
	    	$typeInt = $_REQUEST[$fieldName];
		else if ($prow)
		    $typeInt = "".$prow->$fieldName;
		else
	    	$typeInt = "1";

	  echo $this->papt("applicationType", "Application type"),
		  "<div class='paphint'>Please specify your application type.</div>",
		  "<div class='papv'>";
    foreach ($Opt['applicationTypes'] as $typeIndex => $typeName) {
      echo "<input type=\"radio\" name=\"$fieldName\" value=\"$typeIndex\" " . ($typeInt == "$typeIndex"? "checked" : "") . "> $typeName<br>";
    }
	  echo "</div>\n\n";
	}

    function _papstripLeadShepherd($prow, $type, $name, $showedit, $wholefold) {
	global $Conf, $ConfSiteSuffix, $Me, $Error, $linkExtra;
	$editable = $Me->actChair($prow);

	$field = $type . "ContactId";
	if ($prow->$field == 0 && !$editable)
	    return;
	$value = $prow->$field;
	$pc = pcMembers();

	if ($editable) {
	    echo "<form id='${type}form' class='fold7o' method='post' action='review$ConfSiteSuffix?p=", $prow->paperId, "&amp;post=1$linkExtra' enctype='multipart/form-data' accept-charset='UTF-8' onsubmit='return Miniajax.submit(\"${type}form\")'>",
		"<div id='fold$type' class='", ($showedit ? "foldo" : "foldc"), ($wholefold ? " fold2c" : " fold2o"), " inform'><div class='fx2'>",
		"<input type='hidden' name='set$type' value='1' />";
	    $Conf->footerStuff .= "<script type='text/javascript'>Miniajax.onload(\"${type}form\");</script>";
	}

	echo $this->papt($type, $name, array("type" => "ps", "editfolder" => ($editable && !$showedit ? $type : 0))),
	    "<div class='psv'>";

	if ($editable) {
	    $opt = array("0" => "None");
	    foreach ($pc as $row)
		$opt[htmlspecialchars($row->email)] = contactHtml($row->firstName, $row->lastName);

	    echo "<div class='fx'>",
		tagg_select($type, $opt,
			    ($value && isset($pc[$value]) ? htmlspecialchars($pc[$value]->email) : "0"),
			    array("onchange" => "Miniajax.submit(\"${type}form\")",
				  "id" => "fold${type}_d")),
		" <input class='b fx7' type='submit' value='Save' />",
		" <span id='${type}formresult'></span>",
		"</div><div class='fn'>";
	}

	if (!$value)
	    echo "None";
	else if (!isset($pc[$value]))
	    echo "Unknown!";
	else
	    echo contactHtml($pc[$value]->firstName, $pc[$value]->lastName);

	echo "</div>";
	if ($editable)
	    echo "</div></div></div></form>";
	echo "\n";
    }

    function papstripLead($prow, $showedit) {
		//$this->_papstripLeadShepherd($prow, "lead", "Discussion lead", $showedit, false);
    }

    function papstripShepherd($prow, $showedit, $fold) {
		//$this->_papstripLeadShepherd($prow, "shepherd", "Shepherd", $showedit, $fold);
    }

    function papstripTags($prow, $site = null) {
/*		global $Conf, $ConfSiteSuffix, $Me, $Error, $forceShow, $linkExtra;
		if ($site || defval($prow, "paperTags", "") !== "") {
			// Note that tags MUST NOT contain HTML special characters.
			require_once("tags.inc");
			$tx = tagsToText($prow, true, $Me);
			$editable = $site && $Me->canSetTags($prow, $forceShow);
	
			if ($editable)
			echo "<form method='post' action=\"$site\" enctype='multipart/form-data' accept-charset='UTF-8'><div id='foldtags' class='", (isset($Error["tags"]) ? "foldo" : "foldc"), " inform'><input type='hidden' name='settags' value='1' />";
	
			echo $this->papt("tags", "Tags", array("type" => "ps", "editfolder" => ($editable ? "tags" : 0), "oneditunfold" => "Miniajax.submit(\"tagreportform\")")),
			"<div class='psv'>";
			if ($editable) {
			// tag report form
			$Conf->footerStuff .= "<form id='tagreportform' class='fold7o' method='post' action='paper$ConfSiteSuffix?p=$prow->paperId$linkExtra' enctype='multipart/form-data' accept-charset='UTF-8' onsubmit='return Miniajax.submit(\"tagreportform\")'><div class='aahc'><input type='hidden' name='tagreport' value='1' /></div></form>";
	
			$t = tagsToText($prow, false, $Me, true);
			echo "<div class='fn'>", ($tx == "" ? "None" : $tx),
				"</div><div class='fx'><div id='tagreportformresult'></div>";
			if (isset($Error["tags"]))
				echo "<div class='xmerror'>", $Error["tags"], "</div>";
			echo "<textarea id='foldtags_d' cols='20' rows='2' name='tags' onkeypress='return crpSubmitKeyFilter(this, event)'>", $t, "</textarea><br />",
				"<input class='bsm' type='submit' value='Save' /> &nbsp; ",
				"<button type='button' class='bsm' onclick='javascript:fold(\"tags\", 1)'>Cancel</button><br />",
				"<span class='hint'><a href='help$ConfSiteSuffix?t=tags'>Learn more</a> &nbsp;<span class='barsep'>|</span>&nbsp; <strong>Tip:</strong> Twiddle tags like &ldquo;~tag&rdquo; are visible only to you.</span>",
				"</div>";
			} else
			echo ($tx == "" ? "None" : $tx);
			echo "</div>";
	
			if ($editable)
			echo "</div></form>";
			echo "\n";
		}
*/    }

    function papstripOutcomeSelector($prow, $showedit) {
		global $Conf, $ConfSiteSuffix, $rf, $Error, $linkExtra;
		if (!$rf)
			$rf = reviewForm();

		echo "<div id='folddecision' class='",
			($showedit ? "foldo" : "foldc"), "'>",
			$this->papt("decision", "Decision", array("type" => "ps", "editfolder" => ($showedit ? 0 : "decision"))),
			"<span class='hint'>Remember to finish the granted amount part as well <strong>EVEN IF</strong> rejecting. This is to mark that you already reviewed this application.</span>",
			"<div class='psv'><br /><form id='decisionform' class='fx fold7o' method='post' action='review$ConfSiteSuffix?p=", $prow->paperId, "&amp;post=1$linkExtra' enctype='multipart/form-data' accept-charset='UTF-8' onsubmit='return dosubmitdecision()'>",
			"<div class='inform'>",
			"<input type='hidden' name='setdecision' value='1' />";
		if (isset($_REQUEST["forceShow"]))
			echo "<input type='hidden' name='forceShow' value='", $_REQUEST['forceShow'], "' />";
		echo decisionSelector($prow->outcome, null, " onchange='dosubmitdecision()' id='folddecision_d'"),
			" <input class='b fx7' type='submit' value='Save' />",
			" <span id='decisionformresult'></span>",
			"</div></form><div class='fn'>",
			$rf->options["outcome"][$prow->outcome],
			"</div></div></div>\n";
		$Conf->footerStuff .= "<script type='text/javascript'>Miniajax.onload(\"decisionform\");</script>";
    }

    function papstripReviewPreference($prow) {
/*		global $Conf, $ConfSiteSuffix, $linkExtra;
		echo $this->papt("revpref", "Review preference", array("type" => "ps")),
			"<div class='psv'><form id='revprefform' class='fold7o' method='post' action='review$ConfSiteSuffix?p=", $prow->paperId, "&amp;post=1$linkExtra' enctype='multipart/form-data' accept-charset='UTF-8' onsubmit='return Miniajax.submit(\"revprefform\")'>",
			"<div class='inform'>",
			"<input type='hidden' name='setrevpref' value='1' />";
		if (isset($_REQUEST["forceShow"]))
			echo "<input type='hidden' name='forceShow' value='", $_REQUEST['forceShow'], "' />";
		$rp = (isset($prow->reviewerPreference) ? htmlspecialchars($prow->reviewerPreference) : "0");
		$rp = ($rp == "0" ? "" : $rp);
		echo "<input id='revprefform_d' class='textlite' type='text' size='4' name='revpref' value=\"$rp\" onchange='Miniajax.submit(\"revprefform\")' tabindex='1' />",
			" <input class='b fx7' type='submit' value='Save' />",
			" <span id='revprefformresult'></span>",
			"</div></form></div>\n";
		$Conf->footerStuff .= "<script type='text/javascript'>Miniajax.onload(\"revprefform\");</script>";
*/    }

    function papstripRank($prow) {
/*		global $Conf, $ConfSiteSuffix, $Me, $linkExtra;
		if (!($tag = $Conf->settingText("tag_rank")))
			return;
	
		// load rank
		$tagsearch = " " . $Me->contactId . "~" . $tag;
		if (($pos = strpos(" " . $prow->paperTags, $tagsearch)) !== false) {
			$pos += strlen($tagsearch) - 1;
			if ($pos == strlen($prow->paperTags)
			|| $prow->paperTags[$pos] != "#")
			$rp = "0";
			else if (($xpos = strpos($prow->paperTags, " ", $pos + 1)))
			$rp = substr($prow->paperTags, $pos + 1, $xpos - $pos - 1);
			else
			$rp = substr($prow->paperTags, $pos + 1);
		} else
			$rp = "";
	
		// rank context form
		$Conf->footerStuff .= "<form id='rankctxform' class='fold7o' method='post' action='paper$ConfSiteSuffix?p=$prow->paperId' enctype='multipart/form-data' accept-charset='UTF-8' onsubmit='return Miniajax.submit(\"rankctxform\")'><div class='aahc'><input type='hidden' name='rankctx' value='1' /></div></form>";
	
		echo "<div id='foldrank' class='foldc fold2c'>",
			$this->papt("rank", "Rank", array("type" => "ps", "editfolder" => "rank")),
			"<div class='psv'><form id='rankform' class='fx fold7o' method='post' action='review$ConfSiteSuffix?p=", $prow->paperId, "&amp;post=1$linkExtra' enctype='multipart/form-data' accept-charset='UTF-8' onsubmit='return Miniajax.submit(\"rankform\")'>",
			"<div class='inform'>",
			"<input type='hidden' name='setrank' value='1' />";
		if (isset($_REQUEST["forceShow"]))
			echo "<input type='hidden' name='forceShow' value='", $_REQUEST['forceShow'], "' />";
		echo "<input id='foldrank_d' class='textlite' type='text' size='4' name='rank' value=\"$rp\" onchange='Miniajax.submit(\"rankform\")' tabindex='1' />",
			" <input class='b fx7' type='submit' value='Save' />",
			" <span id='rankformresult'></span>",
			" <div class='hint'><strong>Tip:</strong> Use <a href='offline$ConfSiteSuffix'>offline reviewing</a> to rank many papers at once.</div>",
			"</div></form>",
			"<div class='fn'>",
			($rp === "" ? "None" : $rp);
		if ($rp != "")
			echo "<span class='fn2'> &nbsp; <a href='javascript:void 0' onclick='javascript:fold(\"rank\", 0, 2);void Miniajax.submit(\"rankctxform\")'>(context)</a>";
		echo "</div>",
			"<div id='rankctxformresult' class='fx2'>Loading...</div>",
			"</div></div>\n";
		$Conf->footerStuff .= "<script type='text/javascript'>Miniajax.onload(\"rankform\");</script>";
*/    }

    function papstripWatch($prow) {
	global $Conf, $ConfSiteSuffix, $Me, $linkExtra;
/*		if (!($this->watchCheckbox
			  && $Conf->setting("allowPaperOption") >= 6
			  && $prow->timeSubmitted > 0
			  && ($prow->conflictType >= CONFLICT_AUTHOR
			  || $prow->conflictType <= 0
			  || ($Me->privChair && isset($_REQUEST["forceShow"])))))
			return;
		// watch note
		$result = $Conf->q("select ContactInfo.contactId, reviewType, commentId, conflictType, watch from ContactInfo left join PaperReview on (PaperReview.paperId=$prow->paperId and PaperReview.contactId=ContactInfo.contactId) left join PaperComment on (PaperComment.paperId=$prow->paperId and PaperComment.contactId=ContactInfo.contactId) left join PaperConflict on (PaperConflict.paperId=$prow->paperId and PaperConflict.contactId=ContactInfo.contactId) left join PaperWatch on (PaperWatch.paperId=$prow->paperId and PaperWatch.contactId=ContactInfo.contactId) where ContactInfo.contactId=$Me->contactId");
		$row = edb_row($result);
	
		echo "<form id='watchform' class='fold7o' action=\"comment$ConfSiteSuffix?p=$prow->paperId&amp;post=1$linkExtra\" method='post' enctype='multipart/form-data' accept-charset='UTF-8' onsubmit='return Miniajax.submit(\"watchform\")'>",
			"<div class='inform'>",
			"<input type='hidden' name='setwatch' value='1' />";
	
		$checkbox = "<input type='checkbox' name='watch' value='" . $this->watchCheckbox . "'";
		if ($row[4] && ($row[4] & ($Me->defaultWatch >> 1)))
			$watchValue = $row[4];
		else if ($row[1] || $row[2] || $row[3] >= CONFLICT_AUTHOR)
			$watchValue = $Me->defaultWatch;
		else
			$watchValue = 0;
		if ($watchValue & $this->watchCheckbox)
			$checkbox .= " checked='checked'";
		$checkbox .= " onchange='Miniajax.submit(\"watchform\")' />";
	
		echo $this->papt("watch", $checkbox . "&nbsp;Comment notification",
				 array("type" => "ps")),
			"<div class='pshint'>If selected, the system will email you when updated comments are available. <span id='watchformresult'></span></div>",
			"<div class='papv'>",
			"<input class='b fx7' type='submit' value='Save' />",
			"</div></div></form>\n\n";
	
		$Conf->footerStuff .= "<script type='text/javascript'>Miniajax.onload(\"watchform\");</script>";
*/    }


    // Functions for editing

    function deadlineSettingIs($dname) {
		global $Conf;
		$deadline = $Conf->printableTimeSetting($dname);
		if ($deadline == "N/A")
			return "";
		else if (time() < $Conf->setting($dname))
			return "  The deadline is $deadline.";
		else
			return "  The deadline was $deadline.";
    }

    function editMessage() {
		global $Conf, $ConfSiteSuffix, $Me;
		$prow = $this->prow;
		$m = "";

		$override = ($Me->privChair ? "  As an administrator, you can override this deadline using the &ldquo;Override deadlines&rdquo; checkbox." : "");
	
		if (!$prow) {
	    	$timeStart = $Conf->timeStartPaper();
		    $startDeadline = $this->deadlineSettingIs("sub_reg");
		    if (!$timeStart) {
				if ($Conf->setting("sub_open") <= 0)
			   		$msg = "You can't register new applications because the conference site has not been opened for submissions.$override";
				else
				    $msg = "You can't register new applications since the <a href='deadlines$ConfSiteSuffix'>deadline</a> has passed.$startDeadline$override";
					
				if (!$Me->privChair)
		    		errorMsgExit($msg);
				$m .= "<div class='xinfo'>" . $msg . "</div>";
	    	} 
			else
				$m .= "<div class='xinfo'>" . "Fill in your application here.  You don't have to upload the required documents right away, but the application will not be considered until it is complete.$startDeadline" . "</div>";
		} 
		else if ($prow->conflictType >= CONFLICT_AUTHOR && ($Conf->timeUpdatePaper($prow) || $prow->timeSubmitted <= 0)) {
		    $timeUpdate = $Conf->timeUpdatePaper($prow);
		    $updateDeadline = $this->deadlineSettingIs("sub_update");
	    	$timeSubmit = $Conf->timeFinalizePaper($prow);
		    $submitDeadline = $this->deadlineSettingIs("sub_sub");
		
		    if ($timeUpdate && $prow->timeWithdrawn > 0)
				$m .= "<div class='xinfo'>" . "This application has been withdrawn, but you can still revive it.$updateDeadline" . "</div>";
		    else if ($timeUpdate)
			   		$m .=  "<div class='xconfirm'> " . $Me->paperStatus($prow->paperId, $prow) . "<br />You still have time to make changes if necessary.$updateDeadline</div>";		    	
			else if ($prow->conflictType >= CONFLICT_AUTHOR && $prow->outcome > 0 && $Conf->timeSubmitFinalPaper()) {
			    $updateDeadline = $this->deadlineSettingIs("final_done");
		    	$m .= "<div class='xinfo'> Congratulations!  This application was approved. </div>";
			} 
			else if ($prow->conflictType >= CONFLICT_AUTHOR) {
	    		$override2 = ($Me->privChair ? "  However, as an administrator, you can update the application anyway by selecting \"Override deadlines\"." : "");
	    		if ($this->mode == "pe")
					$m .= "<div class='xinfo'>" . "This application is under review and can no longer be changed, although you may still withdraw it.$override2" . "</div>";
			} 
			else
	    		$m .= "<div class='xinfo'>" . "You aren't the applicant of this application, but as an administrator you can still make changes." . "</div>";
		}
		return $m;
    }

    function echoActions() {
		global $Conf, $ConfSiteSuffix, $Me, $linkExtra;
		$prow = $this->prow;
	
		echo "<div class='aa'><table class='pt_buttons'>\n";
		$buttons = array();
		if (!$prow)
			$buttons[] = "<input class='bb' type='submit' name='update' value='Submit application' />";
		else if ($prow->timeWithdrawn > 0
			 && ($Conf->timeFinalizePaper($prow) || $Me->privChair))
			$buttons[] = "<input class='b' type='submit' name='revive' value='Revive application' />";
		else if ($prow->timeWithdrawn > 0)
			$buttons[] = "The application has been withdrawn, and the <a href='deadlines$ConfSiteSuffix'>deadline</a> for reviving it has passed.";
		else {
			//if ($prow->outcome > 0 && $Conf->collectFinalPapers() && ($Conf->timeSubmitFinalPaper() || $Me->privChair) && $this->mode == "pe")
				//$buttons[] = array("<input class='bb' type='submit' name='submitfinal' value='Submit final copy' />", "");
			if ($Conf->timeUpdatePaper($prow) && $this->mode == "pe")
				$buttons[] = array("<input class='bb' type='submit' name='update' value='Update application' />", "");
			else if ($Me->privChair && $this->mode == "pe") {
				$class = ($prow->outcome > 0 && $Conf->collectFinalPapers() ? "b" : "bb");
				$buttons[] = array("<input class='$class' type='submit' name='update' value='Update application' />", "(admin only)");
			}
			if ($prow->timeSubmitted <= 0)
			$buttons[] = "<input class='b' type='submit' name='withdraw' value='Withdraw application' />";
			else {
			$buttons[] = "<button type='button' class='b' onclick=\"popup(this, 'w', 0)\">Withdraw application</button>";
			$Conf->footerStuff .= "<div id='popup_w' class='popupc'><p>Are you sure you want to withdraw this application from consideration?";
			if ((!$Me->privChair || $prow->conflictType >= CONFLICT_AUTHOR)
				&& !$Conf->timeFinalizePaper($prow))
				$Conf->footerStuff .= "  Only administrators can undo this step.";
			$Conf->footerStuff .= "</p><form method='post' action=\"paper$ConfSiteSuffix?p=" . $prow->paperId . "&amp;post=1&amp;m=pe$linkExtra\" enctype='multipart/form-data' accept-charset='UTF-8'><div class='popup_actions'><input class='b' type='submit' name='withdraw' value='Withdraw application' /> &nbsp;<button type='button' class='b' onclick=\"popup(null, 'w', 1)\">Cancel</button></div></form></div>";
			}
			if (!$this->editable)
			$buttons[] = "<button type='button' class='b' onclick='document.location=\"contactauthors$ConfSiteSuffix?p=" . $prow->paperId . "$linkExtra\"'>Edit contact authors</button>";
		}
		if ($Me->privChair && $prow) {
			$buttons[] = array("<button type='button' class='b' onclick=\"popup(this, 'd', 0)\">Delete application</button>", "(admin only)");
			$Conf->footerStuff .= "<div id='popup_d' class='popupc'><p>Be careful: This will permanently delete all information about this application from the database and <strong>cannot be undone</strong>.</p><form method='post' action=\"paper$ConfSiteSuffix?p=" . $prow->paperId . "&amp;post=1&amp;m=pe$linkExtra\" enctype='multipart/form-data' accept-charset='UTF-8'><div class='popup_actions'><input class='b' type='submit' name='delete' value='Delete application' /> &nbsp;<button type='button' class='b' onclick=\"popup(null, 'd', 1)\">Cancel</button></div></form></div>";
		}
		echo "    <tr>\n";
		foreach ($buttons as $b) {
			$x = (is_array($b) ? $b[0] : $b);
			echo "      <td class='ptb_button'>", $x, "</td>\n";
		}
		echo "    </tr>\n    <tr>\n";
		foreach ($buttons as $b) {
			$x = (is_array($b) ? $b[1] : "");
			echo "      <td class='ptb_explain'>", $x, "</td>\n";
		}
		echo "    </tr>\n  </table></div>\n";
		if ($Me->privChair) {
			echo "  <div class='g'></div>\n  <table>\n",
			"    <tr><td><input type='hidden' name='doemail' value='0' /></td></tr>",
			"    <tr><td><input type='checkbox' name='override' value='1' />&nbsp;Override deadlines</td></tr>\n",
			"  </table>\n";
		}
    }


    // Functions for overall paper table viewing

    function _papstrip() {
	global $Conf, $ConfSiteSuffix, $Me, $forceShow, $linkExtra;
	$prow = $this->prow;
	if ($Me->canViewTags($prow, $forceShow))
	    $this->papstripTags($prow, "review$ConfSiteSuffix?p=$prow->paperId$linkExtra");
	if ($Me->canSetRank($prow))
	    $this->papstripRank($prow);
	$this->papstripWatch($prow);

	$foldDecisions = false;
	$foldShepherd = $Me->canSetOutcome($prow) && $prow->outcome <= 0
	    && $prow->shepherdContactId == 0 && $this->mode != "assign";
	if ($Me->canSetOutcome($prow))
	    $this->papstripOutcomeSelector($prow, $foldDecisions);
	if ($Me->actPC($prow))
	    $this->papstripLead($prow, $foldDecisions || $this->mode == "assign");
	if ($Me->actPC($prow) || $Me->canViewDecision($prow))
	    $this->papstripShepherd($prow, $foldDecisions || $this->mode == "assign", $foldShepherd);

	if ($Me->actPC($prow) && $Conf->timePCReviewPreferences())
	    $this->papstripReviewPreference($prow);
    }

    function paptabBegin() {
		global $Conf, $ConfSiteSuffix, $Me, $forceShow, $linkExtra, $Opt, $videoLink;
		$prow = $this->prow;
		$paperId = ($prow ? $prow->paperId : "new");
	
		// what actions are supported?
		$canEdit = ($prow && $Me->canEditPaper($prow));
		$canReview = ($prow && $Me->canReview($prow, null));
		$canAssign = ($prow && $Me->actChair($prow));
		$canHome = ($prow && ($canEdit || $canAssign || $this->mode == "contact"));
	
		// a little extra space after messages
		if ($prow && (self::PAPERLINKS_TYPE & 2)
			&& ($canEdit || $canReview || $canAssign || $canHome)
			&& $Conf->msgcount > 0)
			echo "<div class='msgpg'></div>\n";
	
		echo "<table class='pbox'>";
	
		// paper tabs
		if ($prow && (self::PAPERLINKS_TYPE & 2)
			&& ($canEdit || $canReview || $canAssign || $canHome)) {
			echo "<tr>\n    <td colspan='3' class='pboxt'>",
			"<div class='psmodec'><div class='psmode'>";
	
			// home link
			$a = "<a href='paper$ConfSiteSuffix?p=$prow->paperId";
			
			if ($this->mode == "pe" || $this->mode == "re" || $this->mode == "p")
				$a .= "&amp;m=p";
				
			$a .= "$linkExtra'>";
			$highlight = ($this->mode != "assign" && $this->mode != "pe" && $this->mode != "contact" && $this->mode != "re");		
			
			echo "<div class='", ($highlight ? "papmodex" : "papmode"), "'>", $a, $Conf->cacheableImage("view18.png", "[Main]", null, "b"), "</a>&nbsp;", $a, "Main</a></div>\n";
	
			if ($canEdit) {
			$a = "<a href='paper$ConfSiteSuffix?p=$prow->paperId&amp;m=pe$linkExtra'>";
			echo "<div class='", ($this->mode == "pe" ? "papmodex" : "papmode"),
				"'>", $a, $Conf->cacheableImage("edit18.png", "[Edit Application]", null, "b"),
				"</a>&nbsp;", $a, "Edit Application</a></div>\n";
			}
	
			if ($canReview) {
			$a = "<a href='review$ConfSiteSuffix?p=$prow->paperId&amp;m=re$linkExtra'>";
			echo "<div class='", ($this->mode == "re" && (!$this->editrrow || $this->editrrow->contactId == $Me->contactId) ? "papmodex" : "papmode"),
				"'>", $a, $Conf->cacheableImage("review18.png", "[Edit Granted Amount]", null, "b"),
				"</a>&nbsp;", $a, "Edit Granted Amount</a></div>\n";
			}
	
			/*if ($canAssign) {
			$a = "<a href='assign$ConfSiteSuffix?p=$prow->paperId$linkExtra'>";
			echo "<div class='", ($this->mode == "assign" ? "papmodex" : "papmode"),
				"'>", $a, $Conf->cacheableImage("assign18.png", "[Assign]", null, "b"),
				"</a>&nbsp;", $a, "Assign</a></div>\n";
			}*/
	
			echo "<div class='clear'></div></div></div></td>\n</tr>\n";
		}
	
		// paper number
		$pboxclass = ($prow ? "pbox" : "pboxn");
		echo "<tr>\n    <td class='${pboxclass}i'>";
		if ($prow) {
			$pa = "<a href='paper$ConfSiteSuffix?p=$paperId$linkExtra' class='q'>";
			echo "<div class='papnum'><h2>", $pa, "#", $this->prow->paperId, "</a></h2></div>";
	
			// paper strip
			echo "    <div class='papstripc'><div class='papstrip'>\n";
			$this->_papstrip();
			echo "</div></div>";
		}
		echo "</td>\n";
	
		// main paper table
		echo "    <td class='${pboxclass}t'><table class='papc'>
		<tr><td class='papculs'></td><td></td><td class='papcur'></td></tr>
		<tr><td></td><td>";
	
		if (self::PAPERLINKS_TYPE & 1) {
			$t = "";
			if ($prow && ($this->mode == "assign" || $this->mode == "pe"
				  || $this->mode == "contact")) {
			$a = "<a href='paper$ConfSiteSuffix?p=$prow->paperId";
			if ($this->mode == "pe")
				$a .= "&amp;m=p";
			$a .= "$linkExtra'>";
			if ($t != "")
				$t .= " &nbsp;<span class='barsep'>|</span>&nbsp; ";
			$t .= $a . $Conf->cacheableImage("view24.png", "[View]", null, "b elimg")
				. "</a>&nbsp;" . $a . "View</a>";
			}
			if ($prow && $this->mode != "assign" && $this->mode != "pe"
			&& $Me->canEditPaper($prow)) {
			$a = "<a href='paper$ConfSiteSuffix?p=$prow->paperId&amp;m=pe$linkExtra'>";
			if ($t != "")
				$t .= " &nbsp;<span class='barsep'>|</span>&nbsp; ";
			$t .= $a . $Conf->cacheableImage("edit24.png", "[Edit]", null, "b elimg")
				. "</a>&nbsp;" . $a . "Edit</a>";
			}
			if ($prow && $this->mode != "assign" && $this->mode != "contact"
			&& $Me->actChair($prow)) {
			$a = "<a href='assign$ConfSiteSuffix?p=$prow->paperId$linkExtra'>";
			if ($t != "")
				$t .= " &nbsp;<span class='barsep'>|</span>&nbsp; ";
			$t .= $a . $Conf->cacheableImage("assign24.png", "[Assign]", null, "b elimg")
				. "</a>&nbsp;" . $a . "Assign</a>";
			}
			if ($t != "")
			echo "<div class='floatright'>", $t, "</div>";
		}
	
		echo "<h2>";
		if ($prow) {
			echo $pa;
			$this->echoTitle($prow);
			echo "</a>";
		} else
			echo "New grant application";
		echo "</h2></td><td></td></tr>
		</table></td>
		<td class='${pboxclass}j'></td>
	</tr><tr>
		<td class='${pboxclass}l'></td>
		<td class='${pboxclass}r'><table class='papc'>
		<tr><td class='papcl'>",
			$Conf->cacheableImage("_.gif", "", null, "_"),
			"</td><td class='papct'><div class='inpapct'>";
	
		$form = "<form method='post' action=\"paper$ConfSiteSuffix?p="
			. ($prow ? $prow->paperId : "new")
			. "&amp;post=1&amp;m=pe$linkExtra\" enctype='multipart/form-data' accept-charset='UTF-8'>";
	
		$this->echoDivEnter($prow);
		if ($this->editable) {
			global $finalEditMode; // XXX
			$spacer = "<div class='g'></div>\n\n";
			echo $form, "<div class='aahc'>";
	
			if (($m = $this->editMessage()))
			echo $m, $spacer;
			
			// Title
			$this->paptabTitle($prow, "1) ");
			
			// Contact Info
			echo $spacer;
			$this->echoContactInformation("2) ");

      if ($Opt['useApplicationTypes'] === true) {
        // Application type
        $this->echoApplicationType($prow);
      } else {
        echo "<input type=\"hidden\" name=\"applicationType\" value=\"0\">";
      }
      
      echo $spacer;
      $this->echoUSInstitution($prow, "3) ");
      
      echo $spacer;
      $this->echoPaperAuthor($prow, "4) ");
      
      echo $spacer;
      $this->echoRequest($prow, "5) ");
      
      // Upload Budget
			echo $spacer;
			$this->echoUpload($prow, "budget", "6) ");
      
      echo $spacer;
      $this->echoVideo($prow, "7A) ");
      
      if ($videoLink == "") { 
        // Upload Student Letter
        echo $spacer;
        $this->echoUpload($prow, "studentLetter", "7B) ");
      }

      // Upload CV
      echo $spacer;
      $this->echoUpload($prow, "resume", "8) ");
			
			// Upload Supervisor Letter
			echo $spacer;
			$this->echoUpload($prow, "referenceLetter", "9) ");
	
			// Submit button
			echo $spacer;
			$this->echoActions();
	
			echo "</div></form>";
	
		} else {
			echo "<table class='paptab'><tr><td class='papbe' colspan='3'>";
			if ($this->mode == "pe" && ($m = $this->editMessage()))
			echo $m, "<div class='g'></div>\n";
			$this->paptabDownload($prow);
			echo "</td></tr>";
			echo "<tr><td class='paple'>";
			echo "</td><td class='papce'></td><td class='papre'>";
			echo "</td></tr>";
			echo "</table>";
		}
		$this->echoDivExit();
	
		echo "</div></td><td class='papcr'>", $Conf->cacheableImage("_.gif", "", null, "_"), "</td></tr>\n";

		if (!$this->editable && $this->mode == "pe") {
			$this->_paptabSepBegin();
			echo $form;
			$this->echoActions();
			echo "</form>";
			$this->_paptabSepEnd();
		}
    }

    function _paptabSepBegin() {
	echo "	<tr><td colspan='3' class='papsep'></td></tr>
	<tr><td></td><td class='papcc'>";
    }

    function _paptabSepEnd() {
	echo "</td><td></td></tr>\n";
    }

    function _paptabSepContaining($t) {
	if ($t !== "") {
	    $this->_paptabSepBegin();
	    echo $t;
	    $this->_paptabSepEnd();
	}
    }

    function _paptabReviewLinks($rtable, $editrrow, $ifempty) {
		require_once("reviewtable.inc");
		$t = "";
		if ($rtable)
			$t .= reviewTable($this->prow, $this->rrows, $this->mycrows, $editrrow, $this->mode);
		$t .= reviewLinks($this->prow, $this->rrows, $this->mycrows,
				  $editrrow, $this->mode, $this->allreviewslink);
		if (($empty = ($t == "")))
			$t = $ifempty;
		$this->_paptabSepContaining($t);
		echo tagg_cbox("pap", true), "</td></tr></table>\n";
		return $empty;
    }

    function paptabEndWithReviews() {
		global $Conf, $ConfSiteSuffix, $Me, $rf, $forceShow, $linkExtra;
		if (!$rf)
			$rf = reviewForm();
		$prow = $this->prow;
	
		if ($forceShow && !$Me->canViewReview($prow, null, $fakeWhyNotView, true))
			$this->_paptabSepContaining("<div class='inpapcc'>"
			. "You have used administrator privileges to view and edit "
			. "reviews for this application. (<a href=\"" . htmlspecialchars(selfHref(array("forceShow" => 0))) . "\">Unprivileged view</a>)"
			. "</div>");
	
		$empty = $this->_paptabReviewLinks(true, null, "<div class='hint'>There are no reviews or comments for you to view.</div>");
		//$Conf->infoMsg($empty);
		if ($empty)
			return;

	// text format link
	/*if ($Me->canViewReview($prow, null)
	    && count($this->rrows) + count($this->crows) > 0) {
	    $a = "<a href='review$ConfSiteSuffix?p=$prow->paperId&amp;m=r&amp;text=1$linkExtra'>";
	    echo "<div class='relative'>",
		"<table class='pbox'><tr><td class='pboxl'></td>",
		"<td class='pboxr'>", $a,
		$Conf->cacheableImage("txt24.png", "[Text]", null, "dlimg"),
		"</a>&nbsp;", $a, "Reviews and comments in plain text</a>",
		"</td></tr></table></div>\n";
	}*/

		$opt = array("edit" => false);
		foreach ($this->rrows as $rr)
			if ($rr->reviewSubmitted)
			$rf->show($prow, $this->rrows, $rr, $opt);
		foreach ($this->rrows as $rr)
			if (!$rr->reviewSubmitted && $rr->reviewModified > 0
			&& $Me->canViewReview($prow, $rr))
			$rf->show($prow, $this->rrows, $rr, $opt);
    }

    function paptabComments() {
		global $Conf, $ConfSiteSuffix, $Me, $forceShow, $linkExtra,
			$useRequest;
		$prow = $this->prow;
	
		// show comments as well
		if ((count($this->mycrows) || $Me->canComment($prow, null)
			 || $Conf->timeAuthorRespond()) && !$this->allreviewslink) {
			require_once("Code/commentview.inc");
			$cv = new CommentView;
			$editablecid = defval($_REQUEST, "commentId", "xxx");
			if (isset($_REQUEST["noedit"]))
			$editablecid = "xxx";
			$editableresponse = $Conf->timeAuthorRespond() && $prow->conflictType >= CONFLICT_AUTHOR;
	
			foreach ($this->mycrows as $cr) {
			$editMode = ($editablecid == $cr->commentId
					 || ($editableresponse && $cr->forAuthors > 1));
			$cv->show($prow, $cr, $editMode && $useRequest, $editMode);
			}
	
			// comment editing
			if ($Me->canComment($prow, null))
			$cv->show($prow, null, $editablecid == "new" && $useRequest,
				  true, $editablecid != "new");
			/*if (!$cv->nresponse && $Conf->timeAuthorRespond()
			&& ($prow->conflictType >= CONFLICT_AUTHOR
				|| ($Me->actChair($prow) && $editablecid == "response")))
			$cv->showResponse($prow, null, false, true);*/
	
			$cv->endTable();
		}
    }

    function paptabEndWithReviewMessage() {
		global $Conf, $ConfSiteSuffix, $Me, $linkExtra;
	
		if (!$Me->canViewReview($this->prow, null, $whyNot)
			&& $this->rrows)
			$this->_paptabSepContaining("You can't see the reviews for this application.  " . whyNotText($whyNot, "review"));
	
		if ($this->mode != "pe")
			$this->_paptabReviewLinks(false, null, "");
		else
			echo tagg_cbox("pap", true), "</td></tr></table>\n";
    }

    function paptabEndWithEditableReview() {
	global $Conf, $ConfSiteSuffix, $Me, $rf, $forceShow, $linkExtra;
	if (!$rf)
	    $rf = reviewForm();
	$prow = $this->prow;
	$actPC = $Me->actPC($prow);
	$actChair = $Me->actChair($prow);

	// review messages
	$viewall = $Me->canViewReview($prow, null, $whyNot, true);
	$msgs = array();
	if (!$this->rrow && $this->prow->reviewType <= 0)
	    $msgs[] = "You haven't been assigned to review this application, but you can review it anyway.";
	if ($forceShow && !$viewall) {
	    $msgs[] = "You have used administrator privileges to view and edit reviews for this application. (<a href=\"" . htmlspecialchars(selfHref(array("forceShow" => 0))) . "\">Unprivileged view</a>)";
	} else if (!$viewall && isset($whyNot["reviewNotComplete"])) {
	    $nother = 0;
	    foreach ($this->rrows as $rr)
		if (!$Me->ownReview($rr) && $rr->reviewSubmitted)
		    $nother++;
	    if ($nother > 0)
		$msgs[] = "You'll be able to see " . plural($nother, "other review") . " once you complete your own.";
	}
	if (count($msgs) > 0)
	    $this->_paptabSepContaining(join("<br />\n", $msgs));

	// links
	$this->_paptabReviewLinks(false, $this->editrrow, "");

	// review form, possibly with deadline warning
	$opt = array("edit" => $this->mode == "re");

	if ($this->editrrow && ($Me->ownReview($this->editrrow) || $actChair)
	    && !$Conf->timeReviewPaper($actPC, true, true)) {
	    if ($Me->actChair($prow))
		$override = "  As an administrator, you can override this deadline using the &ldquo;Override deadlines&rdquo; checkbox.";
	    else {
		$override = "";
		$opt["edit"] = false;
	    }
	    if (!$Conf->timeReviewPaper($actPC, true, true, true))
		$opt["editmessage"] = "The <a href='deadlines$ConfSiteSuffix'>deadline</a> for changing reviews has passed, so the review can no longer be changed.$override";
	    else
		$opt["editmessage"] = "The site is not open for reviewing, so the review cannot be changed.$override";
	} else if (!$Me->canReview($prow, $this->editrrow))
	    $opt["edit"] = false;

	$rf->show($prow, $this->rrows, $this->editrrow, $opt);
    }


    // Functions for loading papers

    function _maybeSearchPaperId() {
	global $Conf, $Me, $ConfSiteSuffix, $CurrentList;

	// if a number, don't search
	if (isset($_REQUEST["paperId"]) && $_REQUEST["paperId"] != "") {
	    if (ctype_digit($_REQUEST["paperId"])
		&& $_REQUEST["paperId"][0] != "0")
		return false;
	    if (preg_match('/^\s*#?([1-9]\d*)\s*$/s', $_REQUEST["paperId"], $m)) {
		$_REQUEST["paperId"] = $m[1];
		return false;
	    }
	}

	// if a complex request, or a form upload, don't search
	foreach ($_REQUEST as $k => $v)
	    if ($k != "p" && $k != "paperId" && $k != "m" && $k != "mode"
		&& $k != "forceShow" && $k != "go" && $k != "viewContact"
		&& $k != "chairMode" && $k != "ls" && $k != "t"
		&& !isset($_COOKIE[$k]))
		return false;

	// if no paper ID set, find one
	if (!isset($_REQUEST["paperId"])) {
	    $q = "select min(Paper.paperId) from Paper ";
	    if ($Me->isPC)
		$q .= "where timeSubmitted>0";
	    else if ($Me->isReviewer)
		$q .= "join PaperReview on (PaperReview.paperId=Paper.paperId and PaperReview.contactId=$Me->contactId)";
	    else
		$q .= "join ContactInfo on (ContactInfo.paperId=Paper.paperId and ContactInfo.contactId=$Me->contactId and ContactInfo.conflictType>=" . CONFLICT_AUTHOR . ")";
	    $result = $Conf->q($q);
	    if (($paperId = edb_row($result)))
		$_REQUEST["paperId"] = $paperId[0];
	    return false;
	}

	// if invalid contact, or it looks like a number, don't search
	if (!$Me->validated)
	    return false;

	// actually try to search
	require_once("search.inc");
	$search = new PaperSearch($Me, array("q" => $_REQUEST["paperId"], "t" => defval($_REQUEST, "t", 0)));
	$pl = $search->paperList();
	if (count($pl) == 1) {
	    global $thisMatchPreg;
	    if ($search->matchPreg)
		$thisMatchPreg = $search->matchPreg;
	    $_REQUEST["paperId"] = $pl[0];
	    $replaceList = oldestListNumber();
	    $_SESSION["l"][$replaceList] = $search->sessionList($search->limitName);
	    $_REQUEST["ls"] = $replaceList;
	    return true;
	} else {
	    $t = (defval($_REQUEST, "t", 0) ? "&t=" . urlencode($_REQUEST["t"]) : "");
	    $Conf->go("search$ConfSiteSuffix?q=" . urlencode($_REQUEST["paperId"]) . $t);
	    exit;
	}
    }

    function paperRow(&$whyNot) {
		global $Conf, $Me;
	
		if (!isset($_REQUEST["paperId"]) && isset($_REQUEST["p"]))
			$_REQUEST["paperId"] = $_REQUEST["p"];
		if (!isset($_REQUEST["reviewId"]) && isset($_REQUEST["r"]))
			$_REQUEST["reviewId"] = $_REQUEST["r"];
		if (!isset($_REQUEST["commentId"]) && isset($_REQUEST["c"]))
			$_REQUEST["commentId"] = $_REQUEST["c"];
	
		if (isset($_REQUEST["paperId"]) && $_REQUEST["paperId"] == "new")
			return null;
		if (!isset($_REQUEST["paperId"]) && isset($_REQUEST["reviewId"])
			&& preg_match('/^(\d+)[A-Z]+$/', $_REQUEST["reviewId"], $m))
			$_REQUEST["paperId"] = $m[1];
	
		$sel = array();
		if (isset($_REQUEST["paperId"])
			|| (!isset($_REQUEST["reviewId"]) && !isset($_REQUEST["commentId"]))) {
			self::_maybeSearchPaperId();
			$sel["paperId"] = $_REQUEST["paperId"];
		} else if (isset($_REQUEST["reviewId"]))
			$sel["reviewId"] = $_REQUEST["reviewId"];
		else if (isset($_REQUEST["commentId"]))
			$sel["commentId"] = $_REQUEST["commentId"];
	
		$sel["topics"] = $sel["options"] = true;
		if (($Me->isPC && $Conf->timePCReviewPreferences()) || $Me->privChair)
			$sel["reviewerPreference"] = true;
		if ($Me->isPC || $Conf->setting("tag_rank"))
			$sel["tags"] = true;
	
		if (!($prow = $Conf->paperRow($sel, $Me, $whyNot)))
			return null;
		if (!$Me->canViewPaper($prow, $whyNot)
			|| (!isset($_REQUEST["paperId"])
			&& !$Me->canViewReview($prow, null))) {
			// Don't allow querier to probe review/comment<->paper mapping
			if (!isset($_REQUEST["paperId"]))
			$whyNot = array("invalidId" => "paper");
			return null;
		}
		cleanAuthor($prow);
		return $prow;
    }

    function resolveReview() {
		global $Conf, $Me;
	
		$sel = array("paperId" => $this->prow->paperId, "array" => true);
		if ($Conf->setting("rev_ratings") != REV_RATINGS_NONE) {
			$sel["ratings"] = true;
			$sel["myRating"] = $Me->contactId;
		}
		$this->rrows = $Conf->reviewRow($sel, $whyNot);
	
		$rrid = strtoupper(defval($_REQUEST, "reviewId", ""));
		while ($rrid != "" && $rrid[0] == "0")
			$rrid = substr($rrid, 1);
	
		$this->rrow = $myrrow = null;
		foreach ($this->rrows as $rr) {
			if ($rrid != "") {
			if (strcmp($rr->reviewId, $rrid) == 0
				|| ($rr->reviewOrdinal && strcmp($rr->paperId . unparseReviewOrdinal($rr->reviewOrdinal), $rrid) == 0))
				$this->rrow = $rr;
			}
			if ($rr->contactId == $Me->contactId
			|| (!$myrrow && $Me->ownReview($rr)))
			$myrrow = $rr;
		}
	
		// naming a nonexistent review? silently view all reviews
		if ($this->mode == "re" && !$this->rrow && isset($_REQUEST["reviewId"]))
			$this->mode = "r";
	
		$this->editrrow = ($this->rrow ? $this->rrow : $myrrow);
    }

    function resolveComments() {
	global $Conf, $Me;
	if ($this->prow) {
	    $this->crows = $Conf->commentRows
		("select PaperComment.*, firstName, lastName, email
		from PaperComment join ContactInfo using (contactId)
		where paperId=" . $this->prow->paperId . " order by commentId");
	    $this->mycrows = array();
	    foreach ($this->crows as $crow)
		if ($Me->canViewComment($this->prow, $crow))
		    $this->mycrows[] = $crow;
	} else
	    $this->crows = $this->mycrows = array();
    }

    function fixReviewMode() {
	global $Conf, $Me;
	$prow = $this->prow;
	if ($this->mode == "re" && $this->rrow
	    && !$Me->canReview($prow, $this->rrow, $whyNot, false))
	    $this->mode = "r";
	if ($this->mode == "r" && $this->rrow
	    && !$Me->canViewReview($prow, $this->rrow, $whyNot))
	    $this->rrow = $this->editrrow = null;
	if ($this->mode == "r" && !$this->rrow && !$this->editrrow
	    && !$Me->canViewReview($prow, $this->rrow, $whyNot)
	    && $Me->canReview($prow, $this->rrow, $whyNot, false))  {
	    $this->mode = "re";
	    foreach ($this->rrows as $rr)
		if ($rr->contactId == $Me->contactId
		    || (!$this->editrrow && $Me->ownReview($rr)))
		    $this->editrrow = $rr;
	}
	if ($this->mode == "r" && $prow && !count($this->rrows)
	    && !count($this->mycrows)
	    && $prow->conflictType >= CONFLICT_AUTHOR
	    && ($Conf->timeFinalizePaper($prow) || $prow->timeSubmitted <= 0))
	    $this->mode = "pe";
    }

}
