<?php
// account.php -- HotCRP account management page
// HotCRP is Copyright (c) 2006-2009 Eddie Kohler and Regents of the UC
// Distributed under an MIT-like license; see LICENSE

require_once("Code/header.inc");
require_once("Code/countries.inc");
$Me = $_SESSION["Me"];
$Me->goIfInvalid();
$newProfile = false;
$Error = array();


if (!$Me->privChair)
    $Acct = $Me;		// always this contact
else if (isset($_REQUEST["new"])) {
    $Acct = new Contact();
    $Acct->invalidate();
    $newProfile = true;
} else if (isset($_REQUEST["contact"])) {
    $Acct = new Contact();
    if (($id = rcvtint($_REQUEST["contact"])) > 0)
	$Acct->lookupById($id, $Conf);
    else
	$Acct->lookupByEmail($_REQUEST["contact"], $Conf);
    if (!$Acct->valid($Conf)) {
	$Conf->errorMsg("Invalid contact.");
	$Acct = $Me;
    }
} else
    $Acct = $Me;

$Acct->lookupAddress($Conf);

if (isset($_REQUEST['register'])) {
	$needFields = array('uemail', 'firstName', 'lastName', 'affiliation');
	   
	foreach ($needFields as $field) {
		if ($field == "uemail")
			$fieldName = "Email address";
		else if ($field == "firstName")
			$fieldName = "First name";
		else if ($field == "lastName")
			$fieldName = "Last Name";
		else if ($field == "affiliation")
			$fieldName = "Affilication";
		
		if (!isset($_REQUEST[$field]) || $_REQUEST[$field] == "") {
			$UpdateError .= $fieldName . " is missing.\n";
			$Error[$field] = true;
			$OK = 0;
		}
	}
}

if (isset($_REQUEST['register']) && $OK) {
    $_REQUEST["uemail"] = trim($_REQUEST["uemail"]);
    if (!$newProfile && defval($_REQUEST, "upassword", "") == "") {
	$UpdateError = "Blank passwords are not allowed.";
	$Error['password'] = true;
    } else if (!$newProfile && $_REQUEST["upassword"] != defval($_REQUEST, "upassword2", "")) {
	$UpdateError = "The two passwords you entered did not match.";
	$Error['password'] = true;
    } else if (!$newProfile && trim($_REQUEST["upassword"]) != $_REQUEST["upassword"]) {
	$UpdateError = "Passwords cannot begin or end with spaces.";
	$Error['password'] = true;
    } else if ($_REQUEST["uemail"] != $Acct->email
	       && $Conf->getContactId($_REQUEST["uemail"])) {
	$UpdateError = "An account is already registered with email address &ldquo;" . htmlspecialchars($_REQUEST["uemail"]) . "&rdquo;.";
	if (!$newProfile)
	    $UpdateError .= "You may want to <a href='mergeaccounts$ConfSiteSuffix'>merge these accounts</a>.";
	$Error["uemail"] = true;
    } else if ($_REQUEST["uemail"] != $Acct->email
	       && !validateEmail($_REQUEST["uemail"])) {
	$UpdateError = "&ldquo;" . htmlspecialchars($_REQUEST["uemail"]) . "&rdquo; is not a valid email address.";
	$Error["uemail"] = true;
    } else {
	if ($newProfile) {
	    $result = $Acct->initialize($_REQUEST["uemail"], $Conf);
	    if ($OK) {
		$Acct->sendAccountInfo($Conf, true, false);
		$Conf->log("Account created by $Me->email", $Acct);
	    }
	}

	$updatepc = false;

	if ($Me->privChair) {
	    // initialize roles too
	    if (isset($_REQUEST["pctype"])) {
		if ($_REQUEST["pctype"] == "chair")
		    $_REQUEST["pc"] = $_REQUEST["chair"] = 1;
		else if ($_REQUEST["pctype"] == "pc") {
		    unset($_REQUEST["chair"]);
		    $_REQUEST["pc"] = 1;
		} else {
		    unset($_REQUEST["chair"]);
		    unset($_REQUEST["pc"]);
		}
	    } else if (isset($_REQUEST["chair"]))
		$_REQUEST["pc"] = 1;
	    $checkass = !isset($_REQUEST["ass"]) && $Me->contactId == $Acct->contactId && ($Acct->roles & Contact::ROLE_ADMIN) != 0;

	    $while = "while initializing roles";
	    foreach (array("pc" => "PCMember", "ass" => "ChairAssistant", "chair" => "Chair") as $k => $table) {
		$role = ($k == "pc" ? Contact::ROLE_PC : ($k == "ass" ? Contact::ROLE_ADMIN : Contact::ROLE_CHAIR));
		if (($Acct->roles & $role) != 0 && !isset($_REQUEST[$k])) {
		    $Conf->qe("delete from $table where contactId=$Acct->contactId", $while);
		    $Conf->log("Removed as $table by $Me->email", $Acct);
		    $Acct->roles &= ~$role;
		    $updatepc = true;
		} else if (($Acct->roles & $role) == 0 && isset($_REQUEST[$k])) {
		    $Conf->qe("insert into $table (contactId) values ($Acct->contactId)", $while);
		    $Conf->log("Added as $table by $Me->email", $Acct);
		    $Acct->roles |= $role;
		    $updatepc = true;
		}
	    }

	    // ensure there's at least one system administrator
	    if ($checkass) {
		$result = $Conf->qe("select contactId from ChairAssistant", $while);
		if (edb_nrows($result) == 0) {
		    $Conf->qe("insert into ChairAssistant (contactId) values ($Acct->contactId)", $while);
		    $Conf->warnMsg("Refusing to drop the only system administrator.");
		    $_REQUEST["ass"] = 1;
		    $Acct->roles |= Contact::ROLE_ADMIN;
		}
	    }
	}

	// ensure changes in PC member data are reflected immediately
	if (($Acct->roles & (Contact::ROLE_PC | Contact::ROLE_ADMIN | Contact::ROLE_CHAIR))
	    && !$updatepc
	    && ($Acct->firstName != $_REQUEST["firstName"]
		|| $Acct->lastName != $_REQUEST["lastName"]
		|| $Acct->email != $_REQUEST["uemail"]
		|| $Acct->affiliation != $_REQUEST["affiliation"]))
	    $updatepc = true;

	$Acct->firstName = $_REQUEST["firstName"];
	$Acct->lastName = $_REQUEST["lastName"];
	$Acct->email = $_REQUEST["uemail"];
	$Acct->affiliation = $_REQUEST["affiliation"];
	if (!$newProfile && !isset($Opt["ldapLogin"]))
	    $Acct->password = $_REQUEST["upassword"];
	foreach (array("voicePhoneNumber", "faxPhoneNumber", "collaborators",
		       "addressLine1", "addressLine2", "city", "state",
		       "zipCode", "country") as $v)
	    if (isset($_REQUEST[$v]))
		$Acct->$v = $_REQUEST[$v];
	$Acct->defaultWatch = 0;
	//if (isset($_REQUEST["watchcomment"]))
	//    $Acct->defaultWatch = WATCH_COMMENT;

	if ($OK)
	    $Acct->updateDB($Conf);

	if ($updatepc) {
	    $t = time();
	    $Conf->qe("insert into Settings (name, value) values ('pc', $t) on duplicate key update value=$t");
	    unset($_SESSION["pcmembers"]);
	    unset($_SESSION["pcmembersa"]);
	}

	// if PC member, update collaborators and areas of expertise
	if (($Acct->isPC || $newProfile) && $OK) {
	    // remove all current interests
	    $Conf->qe("delete from TopicInterest where contactId=$Acct->contactId", "while updating topic interests");

	    foreach ($_REQUEST as $key => $value)
		if ($OK && strlen($key) > 2 && $key[0] == 't' && $key[1] == 'i'
		    && ($id = (int) substr($key, 2)) > 0
		    && is_numeric($value)
		    && ($value = (int) $value) >= 0 && $value < 3)
		    $Conf->qe("insert into TopicInterest (contactId, topicId, interest) values ($Acct->contactId, $id, $value)", "while updating topic interests");
	}

	if ($OK) {
	    // Refresh the results
	    $Acct->lookupByEmail($_REQUEST["uemail"], $Conf);
	    $Acct->valid($Conf);
	    if ($newProfile)
		$Conf->confirmMsg("Successfully created <a href=\"account$ConfSiteSuffix?contact=" . urlencode($Acct->email) . "\">an account for " . htmlspecialchars($Acct->email) . "</a>.  A password has been emailed to that address.  You may now create another account if you'd like.");
	    else {
		$Conf->log("Account updated" . ($Me->contactId == $Acct->contactId ? "" : " by $Me->email"), $Acct);
		$Conf->confirmMsg("Account profile successfully updated.");
	    }
	    if (isset($_REQUEST["redirect"]))
		$Me->go("index$ConfSiteSuffix");
	    foreach (array("firstName", "lastName", "affiliation") as $k)
		$_REQUEST[$k] = $Acct->$k;
	}
    }
}


function crpformvalue($val, $field = null) {
    global $Acct;
    if (isset($_REQUEST[$val]))
	return htmlspecialchars($_REQUEST[$val]);
    else
	return htmlspecialchars($field ? $Acct->$field : $Acct->$val);
}

function fcclass($what) {
    global $Error;
    return (isset($Error[$what]) ? "f-c error" : "f-c");
}

function feclass($what) {
    global $Error;
    return (isset($Error[$what]) ? "f-e error" : "f-e");
}

function capclass($what) {
    global $Error;
    return (isset($Error[$what]) ? "caption error" : "caption");
}

if (!$newProfile) {
    $_REQUEST["pc"] = ($Acct->roles & Contact::ROLE_PC) != 0;
    $_REQUEST["ass"] = ($Acct->roles & Contact::ROLE_ADMIN) != 0;
    $_REQUEST["chair"] = ($Acct->roles & Contact::ROLE_CHAIR) != 0;
}


if ($newProfile)
    $Conf->header("Create Account", "account", actionBar());
else
    $Conf->header($Me->contactId == $Acct->contactId ? "Your Profile" : "Account Profile", "account", actionBar());


if (isset($UpdateError))
    $Conf->errorMsg($UpdateError);
else if ($_SESSION["AskedYouToUpdateContactInfo"] == 1
	 && ($Acct->roles & Contact::ROLE_PC)) {
    $_SESSION["AskedYouToUpdateContactInfo"] = 3;
    $msg = ($Acct->lastName ? "" : "Please take a moment to update your contact information.  ");
    $Conf->infoMsg($msg);
} else if ($_SESSION["AskedYouToUpdateContactInfo"] == 1) {
    $_SESSION["AskedYouToUpdateContactInfo"] = 2;
    $Conf->infoMsg("Please take a moment to update your contact information.");
}


echo "<form id='accountform' method='post' action='account$ConfSiteSuffix' accept-charset='UTF-8'><div class='aahc'>\n";
if ($newProfile)
    echo "<input type='hidden' name='new' value='1' />\n";
else if ($Me->contactId != $Acct->contactId)
    echo "<input type='hidden' name='contact' value='$Acct->contactId' />\n";
if (isset($_REQUEST["redirect"]))
    echo "<input type='hidden' name='redirect' value=\"", htmlspecialchars($_REQUEST["redirect"]), "\" />\n";


echo "<table id='foldpass' class='form foldc'>
<tr>
  <td class='caption initial'>Contact information</td>
  <td class='entry'><div class='f-contain'>

<div class='f-i'>
  <div class='", fcclass('uemail'), "'>Email <span class='hint'>(required)</span></div>
  <div class='", feclass('uemail'), "'><input class='textlite' type='text' name='uemail' size='52' value=\"", crpformvalue('uemail', 'email'), "\" onchange='hiliter(this)' /></div>
</div>\n\n";

echo "<div class='f-i'><div class='f-ix'>
  <div class='", fcclass('firstName'), "'>First&nbsp;name <span class='hint'>(required)</span></div>
  <div class='", feclass('firstName'), "'><input class='textlite' type='text' name='firstName' size='24' value=\"", crpformvalue('firstName'), "\" onchange='hiliter(this)' /></div>
</div><div class='f-ix'>
  <div class='", fcclass('lastName'), "'>Last&nbsp;name <span class='hint'>(required)</span></div>
  <div class='", feclass('lastName'), "'><input class='textlite' type='text' name='lastName' size='24' value=\"", crpformvalue('lastName'), "\" onchange='hiliter(this)' /></div>
</div><div class='clear'></div></div>\n\n";

if (!$newProfile) {
    echo "<div class='f-i'><div class='f-ix'>
  <div class='", fcclass('password'), "'>Password";
    echo "</div>
  <div class='", feclass('password'), "'><input class='textlite fn' type='password' name='upassword' size='24' value=\"", crpformvalue('upassword', 'password'), "\" onchange='hiliter(this);shiftPassword(1)' />";
    if ($Me->privChair)
	echo "<input class='textlite fx' type='text' name='upasswordt' size='24' value=\"", crpformvalue('upassword', 'password'), "\" onchange='hiliter(this);shiftPassword(0)' />";
    echo "</div>
</div><div class='fn f-ix'>
  <div class='", fcclass('password'), "'>Repeat password</div>
  <div class='", feclass('password'), "'><input class='textlite' type='password' name='upassword2' size='24' value=\"", crpformvalue('upassword', 'password'), "\" onchange='hiliter(this)' /></div>
</div>
  <div class='f-h'>The password is stored in our database in <strong>CLEARTEXT</strong> and will be mailed to you if you have forgotten it, so <strong>DO NOT</strong> use any high-security password.";
    if ($Me->privChair)
	echo "  <span class='sep'></span><span class='f-cx'><a class='fn' href='javascript:void fold(\"pass\")'>Show password</a><a class='fx' href='javascript:void fold(\"pass\")'>Hide password</a></span>";
    echo "</div>\n  <div class='clear'></div></div>\n\n";
}


echo "<div class='f-i'>
  <div class='", fcclass('affiliation'), "'>Affiliation <span class='hint'>(required)</span></div>
  <div class='", feclass('affiliation'), "'><input class='textlite' type='text' name='affiliation' size='52' value=\"", crpformvalue('affiliation'), "\" onchange='hiliter(this)' /></div>
</div>\n\n";


if ($Conf->setting("acct_addr")) {
    echo "<div class='g'></div>\n";
    if ($Conf->setting("allowPaperOption") >= 5) {
	echo "<div class='f-i'>
  <div class='f-c'>Address line 1</div>
  <div class='f-e'><input class='textlite' type='text' name='addressLine1' size='52' value=\"", crpformvalue('addressLine1'), "\" onchange='hiliter(this)' /></div>
</div>\n\n";
	echo "<div class='f-i'>
  <div class='f-c'>Address line 2</div>
  <div class='f-e'><input class='textlite' type='text' name='addressLine2' size='52' value=\"", crpformvalue('addressLine2'), "\" onchange='hiliter(this)' /></div>
</div>\n\n";
	echo "<div class='f-i'><div class='f-ix'>
  <div class='f-c'>City</div>
  <div class='f-e'><input class='textlite' type='text' name='city' size='32' value=\"", crpformvalue('city'), "\" onchange='hiliter(this)' /></div>
</div>";
	echo "<div class='f-ix'>
  <div class='f-c'>State/Province/Region</div>
  <div class='f-e'><input class='textlite' type='text' name='state' size='24' value=\"", crpformvalue('state'), "\" onchange='hiliter(this)' /></div>
</div>";
	echo "<div class='f-ix'>
  <div class='f-c'>ZIP/Postal code</div>
  <div class='f-e'><input class='textlite' type='text' name='zipCode' size='12' value=\"", crpformvalue('zipCode'), "\" onchange='hiliter(this)' /></div>
</div><div class='clear'></div></div>\n\n";
	echo "<div class='f-i'>
  <div class='f-c'>Country</div>
  <div class='f-e'>";
	countrySelector("country", (isset($_REQUEST["country"]) ? $_REQUEST["country"] : $Acct->country));
	echo "</div>\n</div>\n";
    }
    echo "<div class='f-i'><div class='f-ix'>
  <div class='f-c'>Phone <span class='f-cx'>(optional)</span></div>
  <div class='f-e'><input class='textlite' type='text' name='voicePhoneNumber' size='24' value=\"", crpformvalue('voicePhoneNumber'), "\" onchange='hiliter(this)' /></div>
</div><div class='f-ix'>
  <div class='f-c'>Fax <span class='f-cx'>(optional)</span></div>
  <div class='f-e'><input class='textlite' type='text' name='faxPhoneNumber' size='24' value=\"", crpformvalue('faxPhoneNumber'), "\" onchange='hiliter(this)' /></div>
</div><div class='clear'></div></div>\n";
}

echo "</div></td>\n</tr>\n\n";

if ($Acct->isPC || $newProfile)
    echo "<tr><td class='caption'></td><td class='entry'><div class='g'></div><strong>Account type</strong></td></tr>\n";


if ($newProfile || $Acct->contactId != $Me->contactId || $Me->privChair) {
    echo "<tr>
  <td class='caption'>Roles</td>
  <td class='entry'><table><tr><td class='nowrap'>\n";

    $pct = defval($_REQUEST, "pctype");
    if ($pct != "chair" && $pct != "pc" && $pct != "no") {
	if (defval($_REQUEST, "chair"))
	    $pct = "chair";
	else
	    $pct = defval($_REQUEST, "pc") ? "pc" : "no";
    }
    foreach (array("chair" => "Grant chair", "no" => "Applicant") as $k => $v) {
	echo "<input type='radio' name='pctype' value='$k'",
	    ($k == $pct ? " checked='checked'" : ""),
	    " onchange='hiliter(this)' />&nbsp;$v<br />\n";
    }

    echo "</td><td><span class='sep'></span></td><td class='nowrap'>";
    echo "<input type='checkbox' name='ass' value='1' ",
	(defval($_REQUEST, "ass") ? "checked='checked' " : ""),
	"onchange='hiliter(this)' />&nbsp;</td><td>System administrator<br />",
	"<div class='hint'>System administrators have full control over all site operations. There's always at least one system administrator.</div></td></tr></table>\n";
    echo "  </td>\n</tr>\n\n";
}

echo "<tr class='last'><td class='caption'></td>
  <td class='entry'><div class='aa'>
    <input class='bb' type='submit' value='",
    ($newProfile ? "Create account" : "Save changes"),
    "' name='register' />
    </div></td>
</tr>
</table></div></form>\n";


$Conf->footer();
