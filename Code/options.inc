<?php
// options.inc -- TGRP conference options
// Placed in the public domain

/*****************************************************************************
 * TGRP User Settings
 * Required for all installations.
 * Set up this file before running TGRP for the first time.
 *****************************************************************************/

// CONFERENCE NAMES
//
//   dbName          Database name.  Use the name you supplied to
//                   "Code/createdb.sh".  NO SPACES ALLOWED.
//   dbUser          Database user name.  Defaults to $Opt["dbName"].
//   dbPassword      Password for database user.  Defaults to $Opt["dbName"].
//   shortName       Short name of the conference.  Examples: "SIGCOMM 2007",
//                   "HotNets V".
//   longName        Longer name of the conference.  Example: "ACM SIGCOMM
//                   2007 Conference".
//   downloadPrefix  Prefix for downloaded files, such as papers.  Should
//                   end in a dash.  Example: "hotnets5-".  Defaults to
//                   $Opt["dbName"] plus a dash.
//   If your mysql database isn't running on localhost, see $Opt["dsn"] below.

$Opt["dbName"] = "sensys10";
$Opt["dbUser"] = "sensys10";
$Opt["dbPassword"] = "sensys10";
$Opt["shortName"] = "SenSys'10";
$Opt["longName"] = "The 8th ACM Conference on Embedded Networked Sensor Systems";
$Opt["downloadPrefix"] = "sensys10-";


// URLS
//
//   conferenceSite  Conference site URL (e.g., CFP, registration).
//   paperSite       URL for this TGRP installation.  Used in emails.

$Opt["conferenceSite"] = "http://sensys.acm.org/2010/";
$Opt["paperSite"] = "http://minitrue.eecs.harvard.edu/sensys10";


// EMAIL
//
//   contactName     Full name for site administrator (the person to contact
//                   about site problems).  Usually the PC chair(s).
//   contactEmail    Email address for site administrator.
//   sendEmail       Boolean.  If false, TGRP will send no email.  This
//                   should almost always be set to true.
//   emailFrom       "From:" address for email sent by TGRP.  Should be a
//                   plausible email address for mail originating at the
//                   conference server, so things like SPF don't mistake
//                   the mail for spam.
//   emailSender     Envelope sender address for email sent by TGRP.  Usually
//                   defaults to something like "www-data@yourservername".
//                   This email address should be connected to a valid mailbox
//                   or certain spam filters will reject email from TGRP.
//   emailCc         Default "Cc:" address for email sent by TGRP.  If not
//                   set, the default Cc is $Opt["contactEmail"].
//   emailReplyTo    If set, default "Reply-To:" field for email sent by TGRP.
//   sendmailParam   Extra parameters to be passed to PHP's mail() function.
//                   If your system's mailer is not sendmail, you may need to
//                   change the default value; see Code/mailtemplate.inc,
//                   sendPrepared().

$Opt["contactName"] = "Geoffrey Challen";
$Opt["contactEmail"] = "challen@buffalo.edu";
$Opt["sendEmail"] = true;
$Opt["emailFrom"] = "challen@eecs.harvard.edu";
$Opt["emailSender"] = "challen@eecs.harvard.edu";
$Opt["emailReplyTo"] = "challen@eecs.harvard.edu";


// SUBMISSIONS
//
//   emailSubmissions  If this is non-zero, then it should be an email address.
//                   All submissions are MIME-encoded and sent to this address
//                   as a backup.  Note that this will increase server load.

$Opt["emailSubmissions"] = 0;


// OPTIONAL SETTINGS (the defaults are reasonable)
//
//   dsn             Database configuration information in the format
//                   "mysql://DBUSER:DBPASSWORD@DBHOST/DBNAME".
//                   The default is derived from $Opt["dbName"], assuming
//                   that DBUSER, DBPASSWORD, and DBNAME are all the same
//                   and that DBHOST is "localhost".
//                   Example: "mysql://HotNetsV:HotNetsV@localhost/HotNetsV".
//   timezone        Server timezone.  See http://php.net/manual/en/timezones
//                   for a list.
//   time24hour      Set to true to use 24-hour timestamps, rather than the
//                   default am/pm.
//   disablePDF      Set to true to disable PDF format submissions.
//   disablePS       Set to true to disable PostScript format submissions.
//   extraFooter     Optional extra HTML text shown at the bottom of every
//                   page, before the HotCRP link.  If set, should generally
//                   end with " <span class='barsep'>|</span> ".

$Opt["disablePS"] = true;
$Opt["timezone"] = 'America/New_York';
$Opt['time24hour'] = true;

$Opt['useApplicationTypes'] = false;
$Opt['applicationTypes'] = array(1 => "Student", "Post-doc", "Geodiversity");
$Opt['maxRequest'] = 1250;
