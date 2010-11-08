--
-- Table structure for table `ActionLog`
--

DROP TABLE IF EXISTS `ActionLog`;
CREATE TABLE IF NOT EXISTS `ActionLog` (
  `logId` int(11) NOT NULL auto_increment,
  `contactId` int(11) NOT NULL,
  `paperId` int(11) default NULL,
  `time` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `ipaddr` varchar(16) default NULL,
  `action` text NOT NULL,
  PRIMARY KEY  (`logId`),
  UNIQUE KEY `logId` (`logId`),
  KEY `contactId` (`contactId`),
  KEY `paperId` (`paperId`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

--
-- Table structure for table `Chair`
--

DROP TABLE IF EXISTS `Chair`;
CREATE TABLE IF NOT EXISTS `Chair` (
  `contactId` int(11) NOT NULL,
  UNIQUE KEY `contactId` (`contactId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ChairAssistant`
--

DROP TABLE IF EXISTS `ChairAssistant`;
CREATE TABLE IF NOT EXISTS `ChairAssistant` (
  `contactId` int(11) NOT NULL,
  UNIQUE KEY `contactId` (`contactId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ContactAddress`
--

DROP TABLE IF EXISTS `ContactAddress`;
CREATE TABLE IF NOT EXISTS `ContactAddress` (
  `contactId` int(11) NOT NULL,
  `addressLine1` varchar(2048) NOT NULL,
  `addressLine2` varchar(2048) NOT NULL,
  `city` varchar(2048) NOT NULL,
  `state` varchar(2048) NOT NULL,
  `zipCode` varchar(2048) NOT NULL,
  `country` varchar(2048) NOT NULL,
  UNIQUE KEY `contactId` (`contactId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ContactInfo`
--

DROP TABLE IF EXISTS `ContactInfo`;
CREATE TABLE IF NOT EXISTS `ContactInfo` (
  `contactId` int(11) NOT NULL auto_increment,
  `visits` int(11) NOT NULL default '0',
  `firstName` varchar(60) NOT NULL default '',
  `lastName` varchar(60) NOT NULL default '',
  `email` varchar(120) NOT NULL,
  `affiliation` varchar(2048) NOT NULL default '',
  `voicePhoneNumber` varchar(2048) NOT NULL default '',
  `faxPhoneNumber` varchar(2048) NOT NULL default '',
  `password` varchar(2048) NOT NULL,
  `note` mediumtext,
  `collaborators` mediumtext,
  `creationTime` int(11) NOT NULL default '0',
  `lastLogin` int(11) NOT NULL default '0',
  `defaultWatch` tinyint(1) NOT NULL default '2',
  `roles` tinyint(1) NOT NULL default '0' COMMENT '0: Applicant, 2: Admin, 5: Grant Chair, 7: Both admin and Grant Chair',
  PRIMARY KEY  (`contactId`),
  UNIQUE KEY `contactId` (`contactId`),
  UNIQUE KEY `contactIdRoles` (`contactId`,`roles`),
  UNIQUE KEY `email` (`email`),
  KEY `fullName` (`lastName`,`firstName`,`email`),
  FULLTEXT KEY `name` (`lastName`,`firstName`,`email`),
  FULLTEXT KEY `affiliation` (`affiliation`),
  FULLTEXT KEY `email_3` (`email`),
  FULLTEXT KEY `firstName_2` (`firstName`),
  FULLTEXT KEY `lastName` (`lastName`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

--
-- Table structure for table `OptionType`
--

DROP TABLE IF EXISTS `OptionType`;
CREATE TABLE IF NOT EXISTS `OptionType` (
  `optionId` int(11) NOT NULL auto_increment,
  `optionName` varchar(200) NOT NULL,
  `description` text,
  `pcView` tinyint(1) NOT NULL default '1',
  `optionValues` text NOT NULL,
  PRIMARY KEY  (`optionId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `Paper`
--

DROP TABLE IF EXISTS `Paper`;
CREATE TABLE IF NOT EXISTS `Paper` (
  `paperId` int(11) NOT NULL auto_increment,
  `title` varchar(200) default NULL,
  `authorInformation` text,
  `abstract` text,
  `collaborators` text,
  `timeSubmitted` int(11) default '0',
  `resumeSubmittedTime` int(11) default '0',
  `studentLetterSubmittedTime` int(11) default '0',
  `budgetSubmittedTime` int(11) default '0',
  `referenceLetterSubmittedTime` int(11) default '0',
  `timeWithdrawn` int(11) NOT NULL default '0',
  `timeFinalSubmitted` int(11) NOT NULL default '0',
  `pcPaper` tinyint(1) NOT NULL default '0',
  `paperStorageId` int(11) NOT NULL default '0',
  `resumeStorageId` int(11) default '0',
  `studentLetterStorageId` int(11) default '0',
  `budgetStorageId` int(11) default '0',
  `referenceLetterStorageId` int(11) default '0',
  `sha1` varbinary(20) NOT NULL default '',
  `finalPaperStorageId` int(11) NOT NULL default '0',
  `blind` tinyint(1) NOT NULL default '1',
  `outcome` tinyint(1) NOT NULL default '0',
  `leadContactId` int(11) NOT NULL default '0',
  `shepherdContactId` int(11) NOT NULL default '0',
  `size` int(11) NOT NULL default '0',
  `resumeSize` int(11) NOT NULL default '0',
  `studentLetterSize` int(11) NOT NULL default '0',
  `budgetSize` int(11) default '0',
  `referenceLetterSize` int(11) NOT NULL default '0',
  `mimetype` varchar(40) NOT NULL default '',
  `timestamp` int(11) NOT NULL default '0',
  `numComments` int(11) NOT NULL default '0',
  `numAuthorComments` int(11) NOT NULL default '0',
  `applicationType` tinyint(5) unsigned default '0', 
  `usInstitution` tinyint(1) NOT NULL default '0',
  `paperAuthor` tinyint(1) NOT NULL default '0',
  `amountsRequested` varchar(200) NOT NULL default '',
  `totalRequested` int(11) NOT NULL default '0', 
  `totalGranted` int(11) NOT NULL default '0', 
  `videoLink` varchar(200) NOT NULL default '',
  PRIMARY KEY  (`paperId`),
  UNIQUE KEY `paperId` (`paperId`),
  KEY `title` (`title`),
  KEY `timeSubmitted` (`timeSubmitted`),
  KEY `leadContactId` (`leadContactId`),
  KEY `shepherdContactId` (`shepherdContactId`),
  FULLTEXT KEY `titleAbstractText` (`title`,`abstract`),
  FULLTEXT KEY `allText` (`title`,`abstract`,`authorInformation`,`collaborators`),
  FULLTEXT KEY `authorText` (`authorInformation`,`collaborators`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

--
-- Table structure for table `PaperComment`
--

DROP TABLE IF EXISTS `PaperComment`;
CREATE TABLE IF NOT EXISTS `PaperComment` (
  `commentId` int(11) NOT NULL auto_increment,
  `contactId` int(11) NOT NULL,
  `paperId` int(11) NOT NULL,
  `timeModified` int(11) NOT NULL,
  `timeNotified` int(11) NOT NULL default '0',
  `comment` mediumtext NOT NULL,
  `forReviewers` tinyint(1) NOT NULL default '0',
  `forAuthors` tinyint(1) NOT NULL default '0',
  `blind` tinyint(1) NOT NULL default '1',
  `replyTo` int(11) NOT NULL,
  PRIMARY KEY  (`commentId`),
  UNIQUE KEY `commentId` (`commentId`),
  KEY `contactId` (`contactId`),
  KEY `paperId` (`paperId`),
  KEY `contactPaper` (`contactId`,`paperId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `PaperConflict`
--

DROP TABLE IF EXISTS `PaperConflict`;
CREATE TABLE IF NOT EXISTS `PaperConflict` (
  `paperId` int(11) NOT NULL,
  `contactId` int(11) NOT NULL,
  `conflictType` tinyint(1) NOT NULL default '0',
  UNIQUE KEY `contactPaper` (`contactId`,`paperId`),
  UNIQUE KEY `contactPaperConflict` (`contactId`,`paperId`,`conflictType`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `PaperOption`
--

DROP TABLE IF EXISTS `PaperOption`;
CREATE TABLE IF NOT EXISTS `PaperOption` (
  `paperId` int(11) NOT NULL,
  `optionId` int(11) NOT NULL,
  `value` int(11) NOT NULL default '0',
  UNIQUE KEY `paperOption` (`paperId`,`optionId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `PaperReview`
--

DROP TABLE IF EXISTS `PaperReview`;
CREATE TABLE IF NOT EXISTS `PaperReview` (
  `reviewId` int(11) NOT NULL auto_increment,
  `paperId` int(11) NOT NULL,
  `contactId` int(11) NOT NULL,
  `reviewToken` int(11) NOT NULL default '0',
  `reviewType` tinyint(1) NOT NULL default '0',
  `reviewRound` tinyint(1) NOT NULL default '0',
  `requestedBy` int(11) NOT NULL default '0',
  `requestedOn` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `reviewBlind` tinyint(1) NOT NULL default '1',
  `reviewModified` int(1) default NULL,
  `reviewSubmitted` int(1) default NULL,
  `reviewOrdinal` int(1) default NULL,
  `reviewEditVersion` int(1) NOT NULL default '0',
  `reviewNeedsSubmit` tinyint(1) NOT NULL default '1',
  `overAllMerit` tinyint(1) NOT NULL default '0',
  `reviewerQualification` tinyint(1) NOT NULL default '0',
  `novelty` tinyint(1) NOT NULL default '0',
  `technicalMerit` tinyint(1) NOT NULL default '0',
  `interestToCommunity` tinyint(1) NOT NULL default '0',
  `longevity` tinyint(1) NOT NULL default '0',
  `grammar` tinyint(1) NOT NULL default '0',
  `likelyPresentation` tinyint(1) NOT NULL default '0',
  `suitableForShort` tinyint(1) NOT NULL default '0',
  `paperSummary` mediumtext NOT NULL,
  `commentsToAuthor` mediumtext NOT NULL,
  `commentsToPC` mediumtext NOT NULL,
  `commentsToAddress` mediumtext NOT NULL,
  `weaknessOfPaper` mediumtext NOT NULL,
  `strengthOfPaper` mediumtext NOT NULL,
  `potential` tinyint(4) NOT NULL default '0',
  `fixability` tinyint(4) NOT NULL default '0',
  `textField7` mediumtext NOT NULL,
  `textField8` mediumtext NOT NULL,
  PRIMARY KEY  (`reviewId`),
  UNIQUE KEY `reviewId` (`reviewId`),
  UNIQUE KEY `contactPaper` (`contactId`,`paperId`),
  KEY `paperId` (`paperId`,`reviewOrdinal`),
  KEY `reviewSubmitted` (`reviewSubmitted`),
  KEY `reviewNeedsSubmit` (`reviewNeedsSubmit`),
  KEY `reviewType` (`reviewType`),
  KEY `reviewRound` (`reviewRound`),
  KEY `requestedBy` (`requestedBy`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8; 

--
-- Table structure for table `PaperReviewArchive`
--

DROP TABLE IF EXISTS `PaperReviewArchive`;
CREATE TABLE IF NOT EXISTS `PaperReviewArchive` (
  `reviewArchiveId` int(11) NOT NULL auto_increment,
  `reviewId` int(11) NOT NULL,
  `paperId` int(11) NOT NULL,
  `contactId` int(11) NOT NULL,
  `reviewType` tinyint(1) NOT NULL default '0',
  `reviewRound` tinyint(1) NOT NULL default '0',
  `requestedBy` int(11) NOT NULL default '0',
  `requestedOn` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `reviewBlind` tinyint(1) NOT NULL default '1',
  `reviewModified` int(1) default NULL,
  `reviewSubmitted` int(1) default NULL,
  `reviewOrdinal` int(1) default NULL,
  `reviewNeedsSubmit` tinyint(1) NOT NULL default '1',
  `overAllMerit` tinyint(1) NOT NULL default '0',
  `reviewerQualification` tinyint(1) NOT NULL default '0',
  `novelty` tinyint(1) NOT NULL default '0',
  `technicalMerit` tinyint(1) NOT NULL default '0',
  `interestToCommunity` tinyint(1) NOT NULL default '0',
  `longevity` tinyint(1) NOT NULL default '0',
  `grammar` tinyint(1) NOT NULL default '0',
  `likelyPresentation` tinyint(1) NOT NULL default '0',
  `suitableForShort` tinyint(1) NOT NULL default '0',
  `paperSummary` mediumtext NOT NULL,
  `commentsToAuthor` mediumtext NOT NULL,
  `commentsToPC` mediumtext NOT NULL,
  `commentsToAddress` mediumtext NOT NULL,
  `weaknessOfPaper` mediumtext NOT NULL,
  `strengthOfPaper` mediumtext NOT NULL,
  `potential` tinyint(4) NOT NULL default '0',
  `fixability` tinyint(4) NOT NULL default '0',
  `textField7` mediumtext NOT NULL,
  `textField8` mediumtext NOT NULL,
  PRIMARY KEY  (`reviewArchiveId`),
  UNIQUE KEY `reviewArchiveId` (`reviewArchiveId`),
  KEY `reviewId` (`reviewId`),
  KEY `contactPaper` (`contactId`,`paperId`),
  KEY `paperId` (`paperId`),
  KEY `reviewSubmitted` (`reviewSubmitted`),
  KEY `reviewNeedsSubmit` (`reviewNeedsSubmit`),
  KEY `reviewType` (`reviewType`),
  KEY `requestedBy` (`requestedBy`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `PaperReviewPreference`
--

DROP TABLE IF EXISTS `PaperReviewPreference`;
CREATE TABLE IF NOT EXISTS `PaperReviewPreference` (
  `paperId` int(11) NOT NULL,
  `contactId` int(11) NOT NULL,
  `preference` int(4) NOT NULL default '0',
  UNIQUE KEY `contactPaper` (`contactId`,`paperId`),
  KEY `paperId` (`paperId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `PaperReviewRefused`
--

DROP TABLE IF EXISTS `PaperReviewRefused`;
CREATE TABLE IF NOT EXISTS `PaperReviewRefused` (
  `paperId` int(11) NOT NULL,
  `contactId` int(11) NOT NULL,
  `requestedBy` int(11) NOT NULL,
  `reason` text NOT NULL,
  KEY `paperId` (`paperId`),
  KEY `contactId` (`contactId`),
  KEY `requestedBy` (`requestedBy`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `PaperStorage`
--

DROP TABLE IF EXISTS `PaperStorage`;
CREATE TABLE IF NOT EXISTS `PaperStorage` (
  `paperStorageId` int(11) NOT NULL auto_increment,
  `paperId` int(11) NOT NULL,
  `timestamp` int(11) NOT NULL,
  `mimetype` varchar(40) NOT NULL default '',
  `paper` longblob,
  `compression` tinyint(1) NOT NULL default '0',
  PRIMARY KEY  (`paperStorageId`),
  UNIQUE KEY `paperStorageId` (`paperStorageId`),
  KEY `paperId` (`paperId`),
  KEY `mimetype` (`mimetype`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2;

--
-- Table structure for table `PaperTag`
--

DROP TABLE IF EXISTS `PaperTag`;
CREATE TABLE IF NOT EXISTS `PaperTag` (
  `paperId` int(11) NOT NULL,
  `tag` varchar(40) NOT NULL,
  `tagIndex` int(11) NOT NULL default '0',
  UNIQUE KEY `paperTag` (`paperId`,`tag`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `PaperTopic`
--

DROP TABLE IF EXISTS `PaperTopic`;
CREATE TABLE IF NOT EXISTS `PaperTopic` (
  `topicId` int(11) NOT NULL,
  `paperId` int(11) NOT NULL,
  UNIQUE KEY `paperTopic` (`paperId`,`topicId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `PaperWatch`
--

DROP TABLE IF EXISTS `PaperWatch`;
CREATE TABLE IF NOT EXISTS `PaperWatch` (
  `paperId` int(11) NOT NULL,
  `contactId` int(11) NOT NULL,
  `watch` tinyint(1) NOT NULL default '0',
  UNIQUE KEY `contactPaper` (`contactId`,`paperId`),
  UNIQUE KEY `contactPaperWatch` (`contactId`,`paperId`,`watch`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `PCMember`
--

DROP TABLE IF EXISTS `PCMember`;
CREATE TABLE IF NOT EXISTS `PCMember` (
  `contactId` int(11) NOT NULL,
  UNIQUE KEY `contactId` (`contactId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ReviewFormField`
--

DROP TABLE IF EXISTS `ReviewFormField`;
CREATE TABLE IF NOT EXISTS `ReviewFormField` (
  `fieldName` varchar(25) NOT NULL,
  `shortName` varchar(40) NOT NULL,
  `description` text,
  `sortOrder` tinyint(1) NOT NULL default '-1',
  `rows` tinyint(1) NOT NULL default '0',
  `authorView` tinyint(1) NOT NULL default '1',
  `levelChar` tinyint(1) NOT NULL default '0',
  PRIMARY KEY  (`fieldName`),
  UNIQUE KEY `fieldName` (`fieldName`),
  KEY `shortName` (`shortName`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ReviewFormOptions`
--

DROP TABLE IF EXISTS `ReviewFormOptions`;
CREATE TABLE IF NOT EXISTS `ReviewFormOptions` (
  `fieldName` varchar(25) NOT NULL,
  `level` tinyint(1) NOT NULL,
  `description` text,
  KEY `fieldName` (`fieldName`),
  KEY `level` (`level`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `ReviewRating`
--

DROP TABLE IF EXISTS `ReviewRating`;
CREATE TABLE IF NOT EXISTS `ReviewRating` (
  `reviewId` int(11) NOT NULL,
  `contactId` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL default '0',
  UNIQUE KEY `reviewContact` (`reviewId`,`contactId`),
  UNIQUE KEY `reviewContactRating` (`reviewId`,`contactId`,`rating`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `ReviewRequest`
--

DROP TABLE IF EXISTS `ReviewRequest`;
CREATE TABLE IF NOT EXISTS `ReviewRequest` (
  `paperId` int(11) NOT NULL,
  `name` varchar(120) default NULL,
  `email` varchar(120) default NULL,
  `reason` text,
  `requestedBy` int(11) NOT NULL,
  UNIQUE KEY `paperEmail` (`paperId`,`email`),
  KEY `paperId` (`paperId`),
  KEY `requestedBy` (`requestedBy`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `Settings`
--

DROP TABLE IF EXISTS `Settings`;
CREATE TABLE IF NOT EXISTS `Settings` (
  `name` char(40) NOT NULL,
  `value` int(11) NOT NULL,
  `data` text,
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `TopicArea`
--

DROP TABLE IF EXISTS `TopicArea`;
CREATE TABLE IF NOT EXISTS `TopicArea` (
  `topicId` int(11) NOT NULL auto_increment,
  `topicName` varchar(80) default NULL,
  PRIMARY KEY  (`topicId`),
  UNIQUE KEY `topicId` (`topicId`),
  KEY `topicName` (`topicName`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Table structure for table `TopicInterest`
--

DROP TABLE IF EXISTS `TopicInterest`;
CREATE TABLE IF NOT EXISTS `TopicInterest` (
  `contactId` int(11) NOT NULL,
  `topicId` int(11) NOT NULL,
  `interest` int(1) default NULL,
  UNIQUE KEY `contactTopic` (`contactId`,`topicId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


-- --------------------------------------------------------
--
-- Data for table `ContactInfo` (First admin account)
--
INSERT INTO `ContactInfo` (`email`, `password`, `roles`) VALUES
('admin@conf','admin',2);

--
-- Data for table `Settings`
--

INSERT INTO `Settings` (`name`, `value`, `data`) VALUES
('pc', 1240352404, NULL),
('allowPaperOption', 24, NULL),
('extrev_view', 0, NULL),
('au_seerev', 0, NULL),
('sub_sha1', 1, NULL),
('homemsg', 0, 'Hello and welcome to My Conference Travel Grants!'),
('tag_seeall', 1, NULL),
('revform_update', 1243200767, NULL),
('final_open', 1, NULL),
('papersub', 1, NULL),
('opt.longName', 0, 'My Conference Travel Grants'),
('sub_update', 1244271540, NULL),
('sub_reg', 1244271540, NULL),
('extrev_chairreq', 1, NULL),
('pc_seeblindrev', 0, NULL),
('tag_chair', 2, 'accept reject'),
('tag_vote', 0, ''),
('tag_rank', 0, ''),
('rev_ratings', 2, NULL),
('opt.shortName', 0, 'Conf Student Travel Grants'),
('pc_seeall', 1, NULL),
('paperlead', 1, NULL),
('paperacc', 2, NULL),
('sub_freeze', 0, NULL),
('sub_collab', 1, NULL),
('pc_seeallrev', 1, NULL),
('pcrev_any', 1, NULL),
('rev_notifychair', 1, NULL),
('rev_blind', 2, NULL),
('cmt_always', 1, NULL),
('rev_open', 1, NULL),
('sub_pcconfsel', 1, NULL),
('seedec', 1, NULL),
('resp_open', 1, NULL),
('sub_pcconf', 1, NULL),
('sub_grace', 86400, NULL),
('sub_sub', 1244271540, NULL),
('sub_blind', 0, NULL),
('sub_open', 1, NULL);

--
-- Data for table `ReviewFormField`
--

INSERT INTO `ReviewFormField` (`fieldName`, `shortName`, `description`, `sortOrder`, `rows`, `authorView`, `levelChar`) VALUES
('overAllMerit', 'Amount', '', -1, 0, 0, 1),
('reviewerQualification', 'Confidence level', 'How confident are you in your assessment of the paper?', -1, 0, 1, 1),
('novelty', 'Novelty', '', -1, 0, 1, 1),
('technicalMerit', 'Technical quality', '', -1, 0, 1, 1),
('interestToCommunity', 'Suitability', '', -1, 0, 1, 1),
('longevity', 'Longevity', 'How important will this work be over time?', -1, 0, 1, 1),
('grammar', 'Editorial quality', '', -1, 0, 1, 1),
('suitableForShort', 'Suitable for short paper', '', -1, 0, 1, 1),
('paperSummary', 'Comments to the public', '', -1, 5, 1, 0),
('commentsToAuthor', 'Amount', '- ENTER the granted amount (in $) or 0 if the application is rejected and CLICK save changes. <br />\r\n- Please DO NOT put any other comments here, use the Comments section instead. <br />\r\n- Once you entered the granted amount, you may want to SELECT the decision in the top left panel.', 0, 15, 1, 0),
('commentsToPC', 'Comments to PC', '', -1, 10, 0, 0),
('commentsToAddress', 'Overall', 'Discuss the overall quality of this application.', -1, 10, 0, 0),
('weaknessOfPaper', 'Paper weakness', 'What is the weakness of the paper? (1-3 sentences)', -1, 5, 1, 0),
('strengthOfPaper', 'Paper strengths', 'What is the strength of the paper? (1-3 sentences)', -1, 5, 1, 0),
('likelyPresentation', 'Additional score field', '', -1, 0, 1, 1),
('potential', 'Additional score field', '', -1, 0, 1, 1),
('fixability', 'Additional score field', '', -1, 0, 1, 1),
('textField7', 'Additional text field', '', -1, 0, 1, 0),
('textField8', 'Additional text field', '', -1, 0, 1, 0);

--
-- Data for table `ReviewFormOptions`
--

INSERT INTO `ReviewFormOptions` (`fieldName`, `level`, `description`) VALUES
('interestToCommunity', 3, 'Good match for this conference'),
('overAllMerit', 1, 'Place holder'),
('reviewerQualification', 2, 'Somewhat confident'),
('novelty', 5, 'This is very novel'),
('novelty', 4, 'This is a new contribution to an established area'),
('novelty', 3, 'Incremental improvement'),
('technicalMerit', 5, 'Top 5% of submitted papers!'),
('technicalMerit', 4, 'Top 10% but not top 5% of submitted papers'),
('interestToCommunity', 2, 'Somewhat suitable'),
('longevity', 5, 'Exciting'),
('longevity', 4, 'Important'),
('longevity', 3, 'Average importance'),
('longevity', 2, 'Low importance'),
('grammar', 4, 'Top 10% but not top 5% of submitted papers'),
('grammar', 5, 'Top 5% of submitted papers!'),
('suitableForShort', 3, 'Suitable'),
('suitableForShort', 2, 'Can''t tell'),
('outcome', 0, 'Unspecified'),
('outcome', -1, 'Rejected'),
('interestToCommunity', 1, 'Not suitable for this conference'),
('outcome', 2, 'Granted'),
('longevity', 1, 'Not important now or later'),
('suitableForShort', 1, 'Not suitable'),
('reviewerQualification', 3, 'Confident'),
('reviewerQualification', 1, 'Not at all confident'),
('novelty', 2, 'This has been done before'),
('novelty', 1, 'This has been done and published before'),
('technicalMerit', 3, 'Top 25% but not top 10% of submitted papers'),
('technicalMerit', 2, 'Top 50% but not top 25% of submitted papers'),
('grammar', 3, 'Top 25% but not top 10% of submitted papers'),
('grammar', 2, 'Top 50% but not top 25% of submitted papers'),
('reviewerQualification', 4, 'Extremely confident'),
('technicalMerit', 1, 'Bottom 50% of submitted papers'),
('grammar', 1, 'Bottom 50% of submitted papers');
