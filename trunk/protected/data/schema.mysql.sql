# --------------------------------------------------------
# Host:                         127.0.0.1
# Server version:               5.5.20-log
# Server OS:                    Win32
# HeidiSQL version:             6.0.0.3603
# Date/time:                    2012-02-13 00:29:35
# --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

# Dumping database structure for futuremail
DROP DATABASE IF EXISTS `futuremail`;
CREATE DATABASE IF NOT EXISTS `futuremail` /*!40100 DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci */;
USE `futuremail`;


# Dumping structure for table futuremail.adress
DROP TABLE IF EXISTS `adress`;
CREATE TABLE IF NOT EXISTS `adress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `Address` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `Address2` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ZipCode` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `City` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `Country` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AdicionalInformation` varchar(1000) COLLATE utf8_unicode_ci NOT NULL,
  `LastUpdated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

# Dumping data for table futuremail.adress: ~0 rows (approximately)
DELETE FROM `adress`;
/*!40000 ALTER TABLE `adress` DISABLE KEYS */;
/*!40000 ALTER TABLE `adress` ENABLE KEYS */;


# Dumping structure for table futuremail.contacts
DROP TABLE IF EXISTS `contacts`;
CREATE TABLE IF NOT EXISTS `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `FirstName` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `LastName` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `Salutation` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `BirthDate` date DEFAULT NULL,
  `TelephoneNr` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `Email` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `Comments` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `Sex` char(1) COLLATE utf8_unicode_ci DEFAULT NULL,
  `LastUpdated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

# Dumping data for table futuremail.contacts: ~0 rows (approximately)
DELETE FROM `contacts`;
/*!40000 ALTER TABLE `contacts` DISABLE KEYS */;
/*!40000 ALTER TABLE `contacts` ENABLE KEYS */;


# Dumping structure for table futuremail.contacts_adress
DROP TABLE IF EXISTS `contacts_adress`;
CREATE TABLE IF NOT EXISTS `contacts_adress` (
  `contactid` int(10) NOT NULL,
  `addressid` int(10) NOT NULL,
  PRIMARY KEY (`contactid`,`addressid`),
  KEY `FK_contacts_address_address` (`addressid`),
  CONSTRAINT `FK_contacts_address_address` FOREIGN KEY (`addressid`) REFERENCES `adress` (`id`),
  CONSTRAINT `FK_contacts_address_contact` FOREIGN KEY (`contactid`) REFERENCES `contacts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

# Dumping data for table futuremail.contacts_adress: ~0 rows (approximately)
DELETE FROM `contacts_adress`;
/*!40000 ALTER TABLE `contacts_adress` DISABLE KEYS */;
/*!40000 ALTER TABLE `contacts_adress` ENABLE KEYS */;


# Dumping structure for table futuremail.contacts_destinationcontacts
DROP TABLE IF EXISTS `contacts_destinationcontacts`;
CREATE TABLE IF NOT EXISTS `contacts_destinationcontacts` (
  `Contact_id` int(10) NOT NULL,
  `DestinationContact_id` int(10) NOT NULL,
  PRIMARY KEY (`Contact_id`,`DestinationContact_id`),
  KEY `FK_contacts_destinationcontacts_destinationContact` (`DestinationContact_id`),
  CONSTRAINT `FK_contacts_destinationcontacts_contact` FOREIGN KEY (`Contact_id`) REFERENCES `contacts` (`id`),
  CONSTRAINT `FK_contacts_destinationcontacts_destinationContact` FOREIGN KEY (`DestinationContact_id`) REFERENCES `contacts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

# Dumping data for table futuremail.contacts_destinationcontacts: ~0 rows (approximately)
DELETE FROM `contacts_destinationcontacts`;
/*!40000 ALTER TABLE `contacts_destinationcontacts` DISABLE KEYS */;
/*!40000 ALTER TABLE `contacts_destinationcontacts` ENABLE KEYS */;


# Dumping structure for table futuremail.contacts_mail
DROP TABLE IF EXISTS `contacts_mail`;
CREATE TABLE IF NOT EXISTS `contacts_mail` (
  `Contact_id` int(10) NOT NULL,
  `Mail_id` int(10) NOT NULL,
  PRIMARY KEY (`Contact_id`,`Mail_id`),
  KEY `fk_contactsmail_mail` (`Mail_id`),
  CONSTRAINT `FK_contactsmail_contacts` FOREIGN KEY (`Contact_id`) REFERENCES `contacts` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_contactsmail_mail` FOREIGN KEY (`Mail_id`) REFERENCES `mail` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

# Dumping data for table futuremail.contacts_mail: ~0 rows (approximately)
DELETE FROM `contacts_mail`;
/*!40000 ALTER TABLE `contacts_mail` DISABLE KEYS */;
/*!40000 ALTER TABLE `contacts_mail` ENABLE KEYS */;


# Dumping structure for table futuremail.mail
DROP TABLE IF EXISTS `mail`;
CREATE TABLE IF NOT EXISTS `mail` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `DestinationContact_id` int(10) DEFAULT '0',
  `Subject` varchar(1024) COLLATE utf8_unicode_ci DEFAULT NULL,
  `MessageBody` mediumtext COLLATE utf8_unicode_ci,
  `DateOfDelivery` datetime DEFAULT NULL,
  `SentOn` datetime DEFAULT NULL,
  `EmailTo` varchar(1024) COLLATE utf8_unicode_ci DEFAULT NULL,
  `EmailCC` varchar(1024) COLLATE utf8_unicode_ci DEFAULT NULL,
  `EmailBCC` varchar(1024) COLLATE utf8_unicode_ci DEFAULT NULL,
  `Comments` varchar(1024) COLLATE utf8_unicode_ci NOT NULL,
  `LastUpdated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_mail_contacts_targetContact` (`DestinationContact_id`),
  KEY `DateOfDelivery` (`DateOfDelivery`),
  KEY `SentOn` (`SentOn`),
  CONSTRAINT `fk_mail_contacts_targetContact` FOREIGN KEY (`DestinationContact_id`) REFERENCES `contacts` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

# Dumping data for table futuremail.mail: ~0 rows (approximately)
DELETE FROM `mail`;
/*!40000 ALTER TABLE `mail` DISABLE KEYS */;
/*!40000 ALTER TABLE `mail` ENABLE KEYS */;


# Dumping structure for table futuremail.message
DROP TABLE IF EXISTS `message`;
CREATE TABLE IF NOT EXISTS `message` (
  `id` int(11) NOT NULL,
  `language` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  `translation` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`,`language`),
  KEY `FK_Message_SourceMessage` (`id`),
  CONSTRAINT `FK_Message_SourceMessage` FOREIGN KEY (`id`) REFERENCES `sourcemessage` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

# Dumping data for table futuremail.message: ~2 rows (approximately)
DELETE FROM `message`;
/*!40000 ALTER TABLE `message` DISABLE KEYS */;
INSERT INTO `message` (`id`, `language`, `translation`) VALUES
	(1, 'de', 'Jetzt kostenlos einen Account anlegen und die Zukunf verändern!'),
	(2, 'de', 'Kontakt');
/*!40000 ALTER TABLE `message` ENABLE KEYS */;


# Dumping structure for table futuremail.services
DROP TABLE IF EXISTS `services`;
CREATE TABLE IF NOT EXISTS `services` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(500) COLLATE utf8_unicode_ci NOT NULL,
  `price` float NOT NULL DEFAULT '0',
  `description` varchar(500) COLLATE utf8_unicode_ci DEFAULT NULL,
  `LastUpdated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

# Dumping data for table futuremail.services: ~0 rows (approximately)
DELETE FROM `services`;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
/*!40000 ALTER TABLE `services` ENABLE KEYS */;


# Dumping structure for table futuremail.sourcemessage
DROP TABLE IF EXISTS `sourcemessage`;
CREATE TABLE IF NOT EXISTS `sourcemessage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

# Dumping data for table futuremail.sourcemessage: ~15 rows (approximately)
DELETE FROM `sourcemessage`;
/*!40000 ALTER TABLE `sourcemessage` DISABLE KEYS */;
INSERT INTO `sourcemessage` (`id`, `category`, `message`) VALUES
	(1, 'app', 'Create now a free account'),
	(2, 'app', 'Contact'),
	(3, 'app', 'Home'),
	(4, 'app', 'About'),
	(5, 'app', 'slogan'),
	(6, 'app', 'Login'),
	(7, 'app', 'Logout'),
	(8, 'app', 'Message to an unborn person'),
	(9, 'app', 'Message to a person'),
	(10, 'app', 'Services'),
	(11, 'app', 'Procedure'),
	(12, 'app', 'Prices'),
	(13, 'app', 'Registration'),
	(14, 'app', 'User Reviews'),
	(15, 'app', 'How it works');
/*!40000 ALTER TABLE `sourcemessage` ENABLE KEYS */;


# Dumping structure for table futuremail.users
DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `Contacts_id` int(11) NOT NULL,
  `LoginName` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `Password` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `isUserEnabled` tinyint(1) DEFAULT '0',
  `LastUpdated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `LoginName_UNIQUE` (`LoginName`),
  KEY `fk_Users_Contacts` (`Contacts_id`),
  CONSTRAINT `fk_Users_Contacts` FOREIGN KEY (`Contacts_id`) REFERENCES `contacts` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

# Dumping data for table futuremail.users: ~0 rows (approximately)
DELETE FROM `users`;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
