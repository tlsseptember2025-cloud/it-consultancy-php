-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: consultancy
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `agents`
--

DROP TABLE IF EXISTS `agents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `agents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `agents`
--

LOCK TABLES `agents` WRITE;
/*!40000 ALTER TABLE `agents` DISABLE KEYS */;
INSERT INTO `agents` VALUES (1,'Rami W','ramiwahdan1978@gmail.com',1,'2026-06-18 09:26:28'),(2,'Fatima H','fatima.habib1980@gmail.com',1,'2026-06-18 09:26:28');
/*!40000 ALTER TABLE `agents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `consultation_bookings`
--

DROP TABLE IF EXISTS `consultation_bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `consultation_bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `slot_id` int(11) NOT NULL,
  `agent_id` int(11) DEFAULT NULL,
  `booked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`),
  KEY `slot_id` (`slot_id`),
  CONSTRAINT `consultation_bookings_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`),
  CONSTRAINT `consultation_bookings_ibfk_2` FOREIGN KEY (`slot_id`) REFERENCES `consultation_slots` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consultation_bookings`
--

LOCK TABLES `consultation_bookings` WRITE;
/*!40000 ALTER TABLE `consultation_bookings` DISABLE KEYS */;
INSERT INTO `consultation_bookings` VALUES (19,34,6397,1,'2026-07-05 12:03:52');
/*!40000 ALTER TABLE `consultation_bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `consultation_slots`
--

DROP TABLE IF EXISTS `consultation_slots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `consultation_slots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slot_date` date NOT NULL,
  `slot_time` time NOT NULL,
  `agent_id` int(11) NOT NULL,
  `consultation_method` varchar(50) DEFAULT NULL,
  `meeting_link` varchar(255) DEFAULT NULL,
  `is_booked` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_consultation_slot` (`slot_date`,`slot_time`,`agent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6541 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consultation_slots`
--

LOCK TABLES `consultation_slots` WRITE;
/*!40000 ALTER TABLE `consultation_slots` DISABLE KEYS */;
INSERT INTO `consultation_slots` VALUES (6397,'2026-07-07','17:00:00',1,NULL,NULL,1,'2026-06-29 06:38:25'),(6409,'2026-07-08','17:00:00',1,NULL,NULL,0,'2026-06-29 06:38:25'),(6410,'2026-07-08','17:00:00',2,NULL,NULL,0,'2026-06-29 06:38:25'),(6411,'2026-07-08','17:30:00',1,NULL,NULL,0,'2026-06-29 06:38:25'),(6412,'2026-07-08','17:30:00',2,NULL,NULL,0,'2026-06-29 06:38:25'),(6413,'2026-07-08','18:00:00',1,NULL,NULL,0,'2026-06-29 06:38:25'),(6414,'2026-07-08','18:00:00',2,NULL,NULL,0,'2026-06-29 06:38:25'),(6415,'2026-07-08','18:30:00',1,NULL,NULL,0,'2026-06-29 06:38:25'),(6416,'2026-07-08','18:30:00',2,NULL,NULL,0,'2026-06-29 06:38:25'),(6417,'2026-07-08','19:00:00',1,NULL,NULL,0,'2026-06-29 06:38:25'),(6418,'2026-07-08','19:00:00',2,NULL,NULL,0,'2026-06-29 06:38:25'),(6419,'2026-07-08','19:30:00',1,NULL,NULL,0,'2026-06-29 06:38:25'),(6420,'2026-07-08','19:30:00',2,NULL,NULL,0,'2026-06-29 06:38:25'),(6421,'2026-07-09','17:00:00',1,NULL,NULL,0,'2026-06-29 06:38:25'),(6422,'2026-07-09','17:00:00',2,NULL,NULL,0,'2026-06-29 06:38:25'),(6423,'2026-07-09','17:30:00',1,NULL,NULL,0,'2026-06-29 06:38:25'),(6424,'2026-07-09','17:30:00',2,NULL,NULL,0,'2026-06-29 06:38:25'),(6425,'2026-07-09','18:00:00',1,NULL,NULL,0,'2026-06-29 06:38:25'),(6426,'2026-07-09','18:00:00',2,NULL,NULL,0,'2026-06-29 06:38:25'),(6427,'2026-07-09','18:30:00',1,NULL,NULL,0,'2026-06-29 06:38:25'),(6428,'2026-07-09','18:30:00',2,NULL,NULL,0,'2026-06-29 06:38:25'),(6429,'2026-07-09','19:00:00',1,NULL,NULL,0,'2026-06-29 06:38:25'),(6430,'2026-07-09','19:00:00',2,NULL,NULL,0,'2026-06-29 06:38:25'),(6431,'2026-07-09','19:30:00',1,NULL,NULL,0,'2026-06-29 06:38:25'),(6432,'2026-07-09','19:30:00',2,NULL,NULL,0,'2026-06-29 06:38:25'),(6433,'2026-07-12','17:00:00',1,NULL,NULL,0,'2026-06-29 06:38:25'),(6434,'2026-07-12','17:00:00',2,NULL,NULL,0,'2026-06-29 06:38:25'),(6435,'2026-07-12','17:30:00',1,NULL,NULL,0,'2026-06-29 06:38:25'),(6436,'2026-07-12','17:30:00',2,NULL,NULL,0,'2026-06-29 06:38:25'),(6437,'2026-07-12','18:00:00',1,NULL,NULL,0,'2026-06-29 06:38:25'),(6438,'2026-07-12','18:00:00',2,NULL,NULL,0,'2026-06-29 06:38:25'),(6439,'2026-07-12','18:30:00',1,NULL,NULL,0,'2026-06-29 06:38:25'),(6440,'2026-07-12','18:30:00',2,NULL,NULL,0,'2026-06-29 06:38:25'),(6441,'2026-07-12','19:00:00',1,NULL,NULL,0,'2026-06-29 06:38:25'),(6442,'2026-07-12','19:00:00',2,NULL,NULL,0,'2026-06-29 06:38:25'),(6443,'2026-07-12','19:30:00',1,NULL,NULL,0,'2026-06-29 06:38:25'),(6444,'2026-07-12','19:30:00',2,NULL,NULL,0,'2026-06-29 06:38:25'),(6445,'2026-07-13','17:00:00',1,NULL,NULL,0,'2026-06-29 06:38:25'),(6446,'2026-07-13','17:00:00',2,NULL,NULL,0,'2026-06-29 06:38:25'),(6447,'2026-07-13','17:30:00',1,NULL,NULL,0,'2026-06-29 06:38:25'),(6448,'2026-07-13','17:30:00',2,NULL,NULL,0,'2026-06-29 06:38:25'),(6449,'2026-07-13','18:00:00',1,NULL,NULL,0,'2026-06-29 06:38:25'),(6450,'2026-07-13','18:00:00',2,NULL,NULL,0,'2026-06-29 06:38:25'),(6451,'2026-07-13','18:30:00',1,NULL,NULL,0,'2026-06-29 06:38:25'),(6452,'2026-07-13','18:30:00',2,NULL,NULL,0,'2026-06-29 06:38:25'),(6453,'2026-07-13','19:00:00',1,NULL,NULL,0,'2026-06-29 06:38:25'),(6454,'2026-07-13','19:00:00',2,NULL,NULL,0,'2026-06-29 06:38:25'),(6455,'2026-07-13','19:30:00',1,NULL,NULL,0,'2026-06-29 06:38:25'),(6456,'2026-07-13','19:30:00',2,NULL,NULL,0,'2026-06-29 06:38:25'),(6457,'2026-07-14','17:00:00',1,NULL,NULL,0,'2026-06-30 04:14:12'),(6458,'2026-07-14','17:00:00',2,NULL,NULL,0,'2026-06-30 04:14:12'),(6459,'2026-07-14','17:30:00',1,NULL,NULL,0,'2026-06-30 04:14:12'),(6460,'2026-07-14','17:30:00',2,NULL,NULL,0,'2026-06-30 04:14:12'),(6461,'2026-07-14','18:00:00',1,NULL,NULL,0,'2026-06-30 04:14:12'),(6462,'2026-07-14','18:00:00',2,NULL,NULL,0,'2026-06-30 04:14:12'),(6463,'2026-07-14','18:30:00',1,NULL,NULL,0,'2026-06-30 04:14:12'),(6464,'2026-07-14','18:30:00',2,NULL,NULL,0,'2026-06-30 04:14:12'),(6465,'2026-07-14','19:00:00',1,NULL,NULL,0,'2026-06-30 04:14:12'),(6466,'2026-07-14','19:00:00',2,NULL,NULL,0,'2026-06-30 04:14:12'),(6467,'2026-07-14','19:30:00',1,NULL,NULL,0,'2026-06-30 04:14:12'),(6468,'2026-07-14','19:30:00',2,NULL,NULL,0,'2026-06-30 04:14:12'),(6469,'2026-07-15','17:00:00',1,NULL,NULL,0,'2026-07-01 04:45:50'),(6470,'2026-07-15','17:00:00',2,NULL,NULL,0,'2026-07-01 04:45:50'),(6471,'2026-07-15','17:30:00',1,NULL,NULL,0,'2026-07-01 04:45:50'),(6472,'2026-07-15','17:30:00',2,NULL,NULL,0,'2026-07-01 04:45:51'),(6473,'2026-07-15','18:00:00',1,NULL,NULL,0,'2026-07-01 04:45:51'),(6474,'2026-07-15','18:00:00',2,NULL,NULL,0,'2026-07-01 04:45:51'),(6475,'2026-07-15','18:30:00',1,NULL,NULL,0,'2026-07-01 04:45:51'),(6476,'2026-07-15','18:30:00',2,NULL,NULL,0,'2026-07-01 04:45:51'),(6477,'2026-07-15','19:00:00',1,NULL,NULL,0,'2026-07-01 04:45:51'),(6478,'2026-07-15','19:00:00',2,NULL,NULL,0,'2026-07-01 04:45:51'),(6479,'2026-07-15','19:30:00',1,NULL,NULL,0,'2026-07-01 04:45:51'),(6480,'2026-07-15','19:30:00',2,NULL,NULL,0,'2026-07-01 04:45:51'),(6481,'2026-07-16','17:00:00',1,NULL,NULL,0,'2026-07-02 06:39:12'),(6482,'2026-07-16','17:00:00',2,NULL,NULL,0,'2026-07-02 06:39:13'),(6483,'2026-07-16','17:30:00',1,NULL,NULL,0,'2026-07-02 06:39:13'),(6484,'2026-07-16','17:30:00',2,NULL,NULL,0,'2026-07-02 06:39:13'),(6485,'2026-07-16','18:00:00',1,NULL,NULL,0,'2026-07-02 06:39:13'),(6486,'2026-07-16','18:00:00',2,NULL,NULL,0,'2026-07-02 06:39:13'),(6487,'2026-07-16','18:30:00',1,NULL,NULL,0,'2026-07-02 06:39:13'),(6488,'2026-07-16','18:30:00',2,NULL,NULL,0,'2026-07-02 06:39:13'),(6489,'2026-07-16','19:00:00',1,NULL,NULL,0,'2026-07-02 06:39:13'),(6490,'2026-07-16','19:00:00',2,NULL,NULL,0,'2026-07-02 06:39:13'),(6491,'2026-07-16','19:30:00',1,NULL,NULL,0,'2026-07-02 06:39:13'),(6492,'2026-07-16','19:30:00',2,NULL,NULL,0,'2026-07-02 06:39:13'),(6493,'2026-07-19','17:00:00',1,NULL,NULL,0,'2026-07-05 11:20:44'),(6494,'2026-07-19','17:00:00',2,NULL,NULL,0,'2026-07-05 11:20:44'),(6495,'2026-07-19','17:30:00',1,NULL,NULL,0,'2026-07-05 11:20:44'),(6496,'2026-07-19','17:30:00',2,NULL,NULL,0,'2026-07-05 11:20:44'),(6497,'2026-07-19','18:00:00',1,NULL,NULL,0,'2026-07-05 11:20:44'),(6498,'2026-07-19','18:00:00',2,NULL,NULL,0,'2026-07-05 11:20:44'),(6499,'2026-07-19','18:30:00',1,NULL,NULL,0,'2026-07-05 11:20:44'),(6500,'2026-07-19','18:30:00',2,NULL,NULL,0,'2026-07-05 11:20:44'),(6501,'2026-07-19','19:00:00',1,NULL,NULL,0,'2026-07-05 11:20:44'),(6502,'2026-07-19','19:00:00',2,NULL,NULL,0,'2026-07-05 11:20:44'),(6503,'2026-07-19','19:30:00',1,NULL,NULL,0,'2026-07-05 11:20:44'),(6504,'2026-07-19','19:30:00',2,NULL,NULL,0,'2026-07-05 11:20:44'),(6505,'2026-07-20','17:00:00',1,NULL,NULL,0,'2026-07-06 07:20:47'),(6506,'2026-07-20','17:00:00',2,NULL,NULL,0,'2026-07-06 07:20:47'),(6507,'2026-07-20','17:30:00',1,NULL,NULL,0,'2026-07-06 07:20:47'),(6508,'2026-07-20','17:30:00',2,NULL,NULL,0,'2026-07-06 07:20:47'),(6509,'2026-07-20','18:00:00',1,NULL,NULL,0,'2026-07-06 07:20:47'),(6510,'2026-07-20','18:00:00',2,NULL,NULL,0,'2026-07-06 07:20:47'),(6511,'2026-07-20','18:30:00',1,NULL,NULL,0,'2026-07-06 07:20:47'),(6512,'2026-07-20','18:30:00',2,NULL,NULL,0,'2026-07-06 07:20:47'),(6513,'2026-07-20','19:00:00',1,NULL,NULL,0,'2026-07-06 07:20:47'),(6514,'2026-07-20','19:00:00',2,NULL,NULL,0,'2026-07-06 07:20:47'),(6515,'2026-07-20','19:30:00',1,NULL,NULL,0,'2026-07-06 07:20:47'),(6516,'2026-07-20','19:30:00',2,NULL,NULL,0,'2026-07-06 07:20:47'),(6517,'2026-07-21','17:00:00',1,NULL,NULL,0,'2026-07-07 12:38:46'),(6518,'2026-07-21','17:00:00',2,NULL,NULL,0,'2026-07-07 12:38:46'),(6519,'2026-07-21','17:30:00',1,NULL,NULL,0,'2026-07-07 12:38:47'),(6520,'2026-07-21','17:30:00',2,NULL,NULL,0,'2026-07-07 12:38:47'),(6521,'2026-07-21','18:00:00',1,NULL,NULL,0,'2026-07-07 12:38:47'),(6522,'2026-07-21','18:00:00',2,NULL,NULL,0,'2026-07-07 12:38:47'),(6523,'2026-07-21','18:30:00',1,NULL,NULL,0,'2026-07-07 12:38:47'),(6524,'2026-07-21','18:30:00',2,NULL,NULL,0,'2026-07-07 12:38:47'),(6525,'2026-07-21','19:00:00',1,NULL,NULL,0,'2026-07-07 12:38:47'),(6526,'2026-07-21','19:00:00',2,NULL,NULL,0,'2026-07-07 12:38:47'),(6527,'2026-07-21','19:30:00',1,NULL,NULL,0,'2026-07-07 12:38:47'),(6528,'2026-07-21','19:30:00',2,NULL,NULL,0,'2026-07-07 12:38:47'),(6529,'2026-07-22','17:00:00',1,NULL,NULL,0,'2026-07-08 09:17:03'),(6530,'2026-07-22','17:00:00',2,NULL,NULL,0,'2026-07-08 09:17:03'),(6531,'2026-07-22','17:30:00',1,NULL,NULL,0,'2026-07-08 09:17:03'),(6532,'2026-07-22','17:30:00',2,NULL,NULL,0,'2026-07-08 09:17:03'),(6533,'2026-07-22','18:00:00',1,NULL,NULL,0,'2026-07-08 09:17:03'),(6534,'2026-07-22','18:00:00',2,NULL,NULL,0,'2026-07-08 09:17:03'),(6535,'2026-07-22','18:30:00',1,NULL,NULL,0,'2026-07-08 09:17:03'),(6536,'2026-07-22','18:30:00',2,NULL,NULL,0,'2026-07-08 09:17:03'),(6537,'2026-07-22','19:00:00',1,NULL,NULL,0,'2026-07-08 09:17:03'),(6538,'2026-07-22','19:00:00',2,NULL,NULL,0,'2026-07-08 09:17:03'),(6539,'2026-07-22','19:30:00',1,NULL,NULL,0,'2026-07-08 09:17:03'),(6540,'2026-07-22','19:30:00',2,NULL,NULL,0,'2026-07-08 09:17:03');
/*!40000 ALTER TABLE `consultation_slots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contract_leads`
--

DROP TABLE IF EXISTS `contract_leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contract_leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) NOT NULL,
  `contact_person` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `employees` int(11) DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `status` enum('New','Contacted','Converted','Closed') DEFAULT 'New',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contract_leads`
--

LOCK TABLES `contract_leads` WRITE;
/*!40000 ALTER TABLE `contract_leads` DISABLE KEYS */;
/*!40000 ALTER TABLE `contract_leads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `password` varchar(255) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (4,'Fatima Habib','fatima.habib1980@gmail.com','0507626410','Loops','New','2026-06-08 05:05:36','$2y$10$zlnT7TWHBaO9yMFS.5F6e.fR.WI.jOj1QHJAMcHaCRqqHPtdt4nFa',NULL,NULL),(5,'Ahmad Rami','ramiwahdan1978@gmail.com','0501228293','ABCD','old','2026-06-08 05:16:32','$2y$10$UYuE591jq/PjY0eDpDbMve5TIlfIByUDJel65WgHXz55tz0E76v3a',NULL,NULL);
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `message_replies`
--

DROP TABLE IF EXISTS `message_replies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `message_replies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `sender` enum('visitor','admin') NOT NULL,
  `reply_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `message_id` (`message_id`),
  CONSTRAINT `message_replies_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `message_replies`
--

LOCK TABLES `message_replies` WRITE;
/*!40000 ALTER TABLE `message_replies` DISABLE KEYS */;
/*!40000 ALTER TABLE `message_replies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('unread','read','closed') NOT NULL DEFAULT 'unread',
  `service` varchar(255) NOT NULL,
  `preferred_contact` enum('Email','Phone') NOT NULL DEFAULT 'Email',
  `reply_token` varchar(64) NOT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_type` enum('admin','customer') NOT NULL,
  `recipient_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (21,'customer',4,'Consultation Confirmed','Your consultation has been confirmed.','?page=customer-requests',1,'2026-07-05 12:04:23'),(22,'customer',4,'Proposal Ready','Your proposal is ready for review.','?page=view-proposal&request_id=34',1,'2026-07-05 12:04:47'),(23,'customer',4,'Proposal Ready','Your proposal is ready for review.','?page=view-proposal&request_id=34',1,'2026-07-05 13:01:16'),(24,'customer',4,'Proposal Ready','Your proposal is ready for review.','?page=view-proposal&request_id=34',1,'2026-07-05 13:01:49');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_slips`
--

DROP TABLE IF EXISTS `payment_slips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_slips` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `uploaded_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `request_id` (`request_id`),
  CONSTRAINT `payment_slips_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `payment_slips_ibfk_2` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_slips`
--

LOCK TABLES `payment_slips` WRITE;
/*!40000 ALTER TABLE `payment_slips` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_slips` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('Pending','Paid','Refunded','Failed') NOT NULL DEFAULT 'Pending',
  `payment_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `refund_requests`
--

DROP TABLE IF EXISTS `refund_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `refund_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `reason_type` varchar(100) NOT NULL,
  `reason_details` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `refund_requests`
--

LOCK TABLES `refund_requests` WRITE;
/*!40000 ALTER TABLE `refund_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `refund_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `refunds`
--

DROP TABLE IF EXISTS `refunds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `refunds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `refund_date` datetime DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Processing','Completed') NOT NULL DEFAULT 'Processing',
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`),
  CONSTRAINT `refunds_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `refunds`
--

LOCK TABLES `refunds` WRITE;
/*!40000 ALTER TABLE `refunds` DISABLE KEYS */;
/*!40000 ALTER TABLE `refunds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `requests`
--

DROP TABLE IF EXISTS `requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `quoted_price` decimal(10,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Pending','Approved','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `workflow_stage` enum('Submitted','Consultation Approved','Consultation Scheduled','Consultation Confirmed','Consultation Completed','Proposal Sent','Proposal Accepted','Proposal Rejected','Awaiting Payment','Payment Submitted','Awaiting Service Scheduling','Service Scheduled','Service Active','Completed','Cancelled') NOT NULL DEFAULT 'Submitted',
  `proposal` text DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `completion_notes` text DEFAULT NULL,
  `consultation_reschedules` int(11) NOT NULL DEFAULT 0,
  `service_reschedules` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `requests_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `requests`
--

LOCK TABLES `requests` WRITE;
/*!40000 ALTER TABLE `requests` DISABLE KEYS */;
INSERT INTO `requests` VALUES (34,4,10,100.00,'test','Pending','2026-07-01 14:10:31','Proposal Sent','ok now?',NULL,NULL,0,0);
/*!40000 ALTER TABLE `requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_bookings`
--

DROP TABLE IF EXISTS `service_bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `slot_id` int(11) NOT NULL,
  `agent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`),
  KEY `slot_id` (`slot_id`),
  CONSTRAINT `service_bookings_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `service_bookings_ibfk_2` FOREIGN KEY (`slot_id`) REFERENCES `service_slots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_bookings`
--

LOCK TABLES `service_bookings` WRITE;
/*!40000 ALTER TABLE `service_bookings` DISABLE KEYS */;
/*!40000 ALTER TABLE `service_bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_slots`
--

DROP TABLE IF EXISTS `service_slots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_slots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_date` date NOT NULL,
  `service_time` time NOT NULL,
  `agent_id` int(11) NOT NULL,
  `is_booked` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_service_slot` (`service_date`,`service_time`,`agent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15234 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_slots`
--

LOCK TABLES `service_slots` WRITE;
/*!40000 ALTER TABLE `service_slots` DISABLE KEYS */;
INSERT INTO `service_slots` VALUES (14926,'2026-07-08','08:00:00',1,0),(14927,'2026-07-08','08:00:00',2,0),(14928,'2026-07-08','08:30:00',1,0),(14929,'2026-07-08','08:30:00',2,0),(14930,'2026-07-08','09:00:00',1,0),(14931,'2026-07-08','09:00:00',2,0),(14932,'2026-07-08','09:30:00',1,0),(14933,'2026-07-08','09:30:00',2,0),(14934,'2026-07-08','10:00:00',1,0),(14935,'2026-07-08','10:00:00',2,0),(14936,'2026-07-08','10:30:00',1,0),(14937,'2026-07-08','10:30:00',2,0),(14938,'2026-07-08','11:00:00',1,0),(14939,'2026-07-08','11:00:00',2,0),(14940,'2026-07-08','11:30:00',1,0),(14941,'2026-07-08','11:30:00',2,0),(14942,'2026-07-08','12:00:00',1,0),(14943,'2026-07-08','12:00:00',2,0),(14944,'2026-07-08','12:30:00',1,0),(14945,'2026-07-08','12:30:00',2,0),(14946,'2026-07-08','13:00:00',1,0),(14947,'2026-07-08','13:00:00',2,0),(14948,'2026-07-08','13:30:00',1,0),(14949,'2026-07-08','13:30:00',2,0),(14950,'2026-07-08','14:00:00',1,0),(14951,'2026-07-08','14:00:00',2,0),(14952,'2026-07-08','14:30:00',1,0),(14953,'2026-07-08','14:30:00',2,0),(14954,'2026-07-09','08:00:00',1,0),(14955,'2026-07-09','08:00:00',2,0),(14956,'2026-07-09','08:30:00',1,0),(14957,'2026-07-09','08:30:00',2,0),(14958,'2026-07-09','09:00:00',1,0),(14959,'2026-07-09','09:00:00',2,0),(14960,'2026-07-09','09:30:00',1,0),(14961,'2026-07-09','09:30:00',2,0),(14962,'2026-07-09','10:00:00',1,0),(14963,'2026-07-09','10:00:00',2,0),(14964,'2026-07-09','10:30:00',1,0),(14965,'2026-07-09','10:30:00',2,0),(14966,'2026-07-09','11:00:00',1,0),(14967,'2026-07-09','11:00:00',2,0),(14968,'2026-07-09','11:30:00',1,0),(14969,'2026-07-09','11:30:00',2,0),(14970,'2026-07-09','12:00:00',1,0),(14971,'2026-07-09','12:00:00',2,0),(14972,'2026-07-09','12:30:00',1,0),(14973,'2026-07-09','12:30:00',2,0),(14974,'2026-07-09','13:00:00',1,0),(14975,'2026-07-09','13:00:00',2,0),(14976,'2026-07-09','13:30:00',1,0),(14977,'2026-07-09','13:30:00',2,0),(14978,'2026-07-09','14:00:00',1,0),(14979,'2026-07-09','14:00:00',2,0),(14980,'2026-07-09','14:30:00',1,0),(14981,'2026-07-09','14:30:00',2,0),(14982,'2026-07-12','08:00:00',1,0),(14983,'2026-07-12','08:00:00',2,0),(14984,'2026-07-12','08:30:00',1,0),(14985,'2026-07-12','08:30:00',2,0),(14986,'2026-07-12','09:00:00',1,0),(14987,'2026-07-12','09:00:00',2,0),(14988,'2026-07-12','09:30:00',1,0),(14989,'2026-07-12','09:30:00',2,0),(14990,'2026-07-12','10:00:00',1,0),(14991,'2026-07-12','10:00:00',2,0),(14992,'2026-07-12','10:30:00',1,0),(14993,'2026-07-12','10:30:00',2,0),(14994,'2026-07-12','11:00:00',1,0),(14995,'2026-07-12','11:00:00',2,0),(14996,'2026-07-12','11:30:00',1,0),(14997,'2026-07-12','11:30:00',2,0),(14998,'2026-07-12','12:00:00',1,0),(14999,'2026-07-12','12:00:00',2,0),(15000,'2026-07-12','12:30:00',1,0),(15001,'2026-07-12','12:30:00',2,0),(15002,'2026-07-12','13:00:00',1,0),(15003,'2026-07-12','13:00:00',2,0),(15004,'2026-07-12','13:30:00',1,0),(15005,'2026-07-12','13:30:00',2,0),(15006,'2026-07-12','14:00:00',1,0),(15007,'2026-07-12','14:00:00',2,0),(15008,'2026-07-12','14:30:00',1,0),(15009,'2026-07-12','14:30:00',2,0),(15010,'2026-07-13','08:00:00',1,0),(15011,'2026-07-13','08:00:00',2,0),(15012,'2026-07-13','08:30:00',1,0),(15013,'2026-07-13','08:30:00',2,0),(15014,'2026-07-13','09:00:00',1,0),(15015,'2026-07-13','09:00:00',2,0),(15016,'2026-07-13','09:30:00',1,0),(15017,'2026-07-13','09:30:00',2,0),(15018,'2026-07-13','10:00:00',1,0),(15019,'2026-07-13','10:00:00',2,0),(15020,'2026-07-13','10:30:00',1,0),(15021,'2026-07-13','10:30:00',2,0),(15022,'2026-07-13','11:00:00',1,0),(15023,'2026-07-13','11:00:00',2,0),(15024,'2026-07-13','11:30:00',1,0),(15025,'2026-07-13','11:30:00',2,0),(15026,'2026-07-13','12:00:00',1,0),(15027,'2026-07-13','12:00:00',2,0),(15028,'2026-07-13','12:30:00',1,0),(15029,'2026-07-13','12:30:00',2,0),(15030,'2026-07-13','13:00:00',1,0),(15031,'2026-07-13','13:00:00',2,0),(15032,'2026-07-13','13:30:00',1,0),(15033,'2026-07-13','13:30:00',2,0),(15034,'2026-07-13','14:00:00',1,0),(15035,'2026-07-13','14:00:00',2,0),(15036,'2026-07-13','14:30:00',1,0),(15037,'2026-07-13','14:30:00',2,0),(15038,'2026-07-14','08:00:00',1,0),(15039,'2026-07-14','08:00:00',2,0),(15040,'2026-07-14','08:30:00',1,0),(15041,'2026-07-14','08:30:00',2,0),(15042,'2026-07-14','09:00:00',1,0),(15043,'2026-07-14','09:00:00',2,0),(15044,'2026-07-14','09:30:00',1,0),(15045,'2026-07-14','09:30:00',2,0),(15046,'2026-07-14','10:00:00',1,0),(15047,'2026-07-14','10:00:00',2,0),(15048,'2026-07-14','10:30:00',1,0),(15049,'2026-07-14','10:30:00',2,0),(15050,'2026-07-14','11:00:00',1,0),(15051,'2026-07-14','11:00:00',2,0),(15052,'2026-07-14','11:30:00',1,0),(15053,'2026-07-14','11:30:00',2,0),(15054,'2026-07-14','12:00:00',1,0),(15055,'2026-07-14','12:00:00',2,0),(15056,'2026-07-14','12:30:00',1,0),(15057,'2026-07-14','12:30:00',2,0),(15058,'2026-07-14','13:00:00',1,0),(15059,'2026-07-14','13:00:00',2,0),(15060,'2026-07-14','13:30:00',1,0),(15061,'2026-07-14','13:30:00',2,0),(15062,'2026-07-14','14:00:00',1,0),(15063,'2026-07-14','14:00:00',2,0),(15064,'2026-07-14','14:30:00',1,0),(15065,'2026-07-14','14:30:00',2,0),(15066,'2026-07-15','08:00:00',1,0),(15067,'2026-07-15','08:00:00',2,0),(15068,'2026-07-15','08:30:00',1,0),(15069,'2026-07-15','08:30:00',2,0),(15070,'2026-07-15','09:00:00',1,0),(15071,'2026-07-15','09:00:00',2,0),(15072,'2026-07-15','09:30:00',1,0),(15073,'2026-07-15','09:30:00',2,0),(15074,'2026-07-15','10:00:00',1,0),(15075,'2026-07-15','10:00:00',2,0),(15076,'2026-07-15','10:30:00',1,0),(15077,'2026-07-15','10:30:00',2,0),(15078,'2026-07-15','11:00:00',1,0),(15079,'2026-07-15','11:00:00',2,0),(15080,'2026-07-15','11:30:00',1,0),(15081,'2026-07-15','11:30:00',2,0),(15082,'2026-07-15','12:00:00',1,0),(15083,'2026-07-15','12:00:00',2,0),(15084,'2026-07-15','12:30:00',1,0),(15085,'2026-07-15','12:30:00',2,0),(15086,'2026-07-15','13:00:00',1,0),(15087,'2026-07-15','13:00:00',2,0),(15088,'2026-07-15','13:30:00',1,0),(15089,'2026-07-15','13:30:00',2,0),(15090,'2026-07-15','14:00:00',1,0),(15091,'2026-07-15','14:00:00',2,0),(15092,'2026-07-15','14:30:00',1,0),(15093,'2026-07-15','14:30:00',2,0),(15094,'2026-07-16','08:00:00',1,0),(15095,'2026-07-16','08:00:00',2,0),(15096,'2026-07-16','08:30:00',1,0),(15097,'2026-07-16','08:30:00',2,0),(15098,'2026-07-16','09:00:00',1,0),(15099,'2026-07-16','09:00:00',2,0),(15100,'2026-07-16','09:30:00',1,0),(15101,'2026-07-16','09:30:00',2,0),(15102,'2026-07-16','10:00:00',1,0),(15103,'2026-07-16','10:00:00',2,0),(15104,'2026-07-16','10:30:00',1,0),(15105,'2026-07-16','10:30:00',2,0),(15106,'2026-07-16','11:00:00',1,0),(15107,'2026-07-16','11:00:00',2,0),(15108,'2026-07-16','11:30:00',1,0),(15109,'2026-07-16','11:30:00',2,0),(15110,'2026-07-16','12:00:00',1,0),(15111,'2026-07-16','12:00:00',2,0),(15112,'2026-07-16','12:30:00',1,0),(15113,'2026-07-16','12:30:00',2,0),(15114,'2026-07-16','13:00:00',1,0),(15115,'2026-07-16','13:00:00',2,0),(15116,'2026-07-16','13:30:00',1,0),(15117,'2026-07-16','13:30:00',2,0),(15118,'2026-07-16','14:00:00',1,0),(15119,'2026-07-16','14:00:00',2,0),(15120,'2026-07-16','14:30:00',1,0),(15121,'2026-07-16','14:30:00',2,0),(15122,'2026-07-19','08:00:00',1,0),(15123,'2026-07-19','08:00:00',2,0),(15124,'2026-07-19','08:30:00',1,0),(15125,'2026-07-19','08:30:00',2,0),(15126,'2026-07-19','09:00:00',1,0),(15127,'2026-07-19','09:00:00',2,0),(15128,'2026-07-19','09:30:00',1,0),(15129,'2026-07-19','09:30:00',2,0),(15130,'2026-07-19','10:00:00',1,0),(15131,'2026-07-19','10:00:00',2,0),(15132,'2026-07-19','10:30:00',1,0),(15133,'2026-07-19','10:30:00',2,0),(15134,'2026-07-19','11:00:00',1,0),(15135,'2026-07-19','11:00:00',2,0),(15136,'2026-07-19','11:30:00',1,0),(15137,'2026-07-19','11:30:00',2,0),(15138,'2026-07-19','12:00:00',1,0),(15139,'2026-07-19','12:00:00',2,0),(15140,'2026-07-19','12:30:00',1,0),(15141,'2026-07-19','12:30:00',2,0),(15142,'2026-07-19','13:00:00',1,0),(15143,'2026-07-19','13:00:00',2,0),(15144,'2026-07-19','13:30:00',1,0),(15145,'2026-07-19','13:30:00',2,0),(15146,'2026-07-19','14:00:00',1,0),(15147,'2026-07-19','14:00:00',2,0),(15148,'2026-07-19','14:30:00',1,0),(15149,'2026-07-19','14:30:00',2,0),(15150,'2026-07-20','08:00:00',1,0),(15151,'2026-07-20','08:00:00',2,0),(15152,'2026-07-20','08:30:00',1,0),(15153,'2026-07-20','08:30:00',2,0),(15154,'2026-07-20','09:00:00',1,0),(15155,'2026-07-20','09:00:00',2,0),(15156,'2026-07-20','09:30:00',1,0),(15157,'2026-07-20','09:30:00',2,0),(15158,'2026-07-20','10:00:00',1,0),(15159,'2026-07-20','10:00:00',2,0),(15160,'2026-07-20','10:30:00',1,0),(15161,'2026-07-20','10:30:00',2,0),(15162,'2026-07-20','11:00:00',1,0),(15163,'2026-07-20','11:00:00',2,0),(15164,'2026-07-20','11:30:00',1,0),(15165,'2026-07-20','11:30:00',2,0),(15166,'2026-07-20','12:00:00',1,0),(15167,'2026-07-20','12:00:00',2,0),(15168,'2026-07-20','12:30:00',1,0),(15169,'2026-07-20','12:30:00',2,0),(15170,'2026-07-20','13:00:00',1,0),(15171,'2026-07-20','13:00:00',2,0),(15172,'2026-07-20','13:30:00',1,0),(15173,'2026-07-20','13:30:00',2,0),(15174,'2026-07-20','14:00:00',1,0),(15175,'2026-07-20','14:00:00',2,0),(15176,'2026-07-20','14:30:00',1,0),(15177,'2026-07-20','14:30:00',2,0),(15178,'2026-07-21','08:00:00',1,0),(15179,'2026-07-21','08:00:00',2,0),(15180,'2026-07-21','08:30:00',1,0),(15181,'2026-07-21','08:30:00',2,0),(15182,'2026-07-21','09:00:00',1,0),(15183,'2026-07-21','09:00:00',2,0),(15184,'2026-07-21','09:30:00',1,0),(15185,'2026-07-21','09:30:00',2,0),(15186,'2026-07-21','10:00:00',1,0),(15187,'2026-07-21','10:00:00',2,0),(15188,'2026-07-21','10:30:00',1,0),(15189,'2026-07-21','10:30:00',2,0),(15190,'2026-07-21','11:00:00',1,0),(15191,'2026-07-21','11:00:00',2,0),(15192,'2026-07-21','11:30:00',1,0),(15193,'2026-07-21','11:30:00',2,0),(15194,'2026-07-21','12:00:00',1,0),(15195,'2026-07-21','12:00:00',2,0),(15196,'2026-07-21','12:30:00',1,0),(15197,'2026-07-21','12:30:00',2,0),(15198,'2026-07-21','13:00:00',1,0),(15199,'2026-07-21','13:00:00',2,0),(15200,'2026-07-21','13:30:00',1,0),(15201,'2026-07-21','13:30:00',2,0),(15202,'2026-07-21','14:00:00',1,0),(15203,'2026-07-21','14:00:00',2,0),(15204,'2026-07-21','14:30:00',1,0),(15205,'2026-07-21','14:30:00',2,0),(15206,'2026-07-22','08:00:00',1,0),(15207,'2026-07-22','08:00:00',2,0),(15208,'2026-07-22','08:30:00',1,0),(15209,'2026-07-22','08:30:00',2,0),(15210,'2026-07-22','09:00:00',1,0),(15211,'2026-07-22','09:00:00',2,0),(15212,'2026-07-22','09:30:00',1,0),(15213,'2026-07-22','09:30:00',2,0),(15214,'2026-07-22','10:00:00',1,0),(15215,'2026-07-22','10:00:00',2,0),(15216,'2026-07-22','10:30:00',1,0),(15217,'2026-07-22','10:30:00',2,0),(15218,'2026-07-22','11:00:00',1,0),(15219,'2026-07-22','11:00:00',2,0),(15220,'2026-07-22','11:30:00',1,0),(15221,'2026-07-22','11:30:00',2,0),(15222,'2026-07-22','12:00:00',1,0),(15223,'2026-07-22','12:00:00',2,0),(15224,'2026-07-22','12:30:00',1,0),(15225,'2026-07-22','12:30:00',2,0),(15226,'2026-07-22','13:00:00',1,0),(15227,'2026-07-22','13:00:00',2,0),(15228,'2026-07-22','13:30:00',1,0),(15229,'2026-07-22','13:30:00',2,0),(15230,'2026-07-22','14:00:00',1,0),(15231,'2026-07-22','14:00:00',2,0),(15232,'2026-07-22','14:30:00',1,0),(15233,'2026-07-22','14:30:00',2,0);
/*!40000 ALTER TABLE `service_slots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` VALUES (10,'Remote IT Support','1> PC troubleshooting\r\n2> Printer issues\r\n3> Outlook issues\r\n4> Windows problems\r\n5> Software errors\r\n6> Slow computers\r\n7> Others','2026-06-10 11:47:59',0.00,'1782915195_1782301195_1782033972_support.jpg'),(11,'Software Installation & Setup','1> Microsoft Office\r\n2> Outlook\r\n3> Zoom\r\n4> Teams\r\n5> VPNs\r\n6> Adobe products\r\n7> QuickBooks\r\n8> Others','2026-06-14 12:52:04',0.00,'1782915189_1782301188_1782033966_software.jpg'),(12,'Website Builder - Small Business','Website Builder for Small Businesses\r\n\r\n1> Build professional websites\r\n2> Mobile-responsive design\r\n3> Contact forms and lead capture\r\n4> Basic SEO optimization\r\n5> Blog and content setup\r\n6> Basic e-commerce stores\r\n7> Ongoing website maintenance','2026-06-14 12:53:42',0.00,'1782915174_1782301178_1782033954_website.jpg');
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin@loopsautomation.com','$2y$10$lPc3Zja83nrSMDsJmj8Q9OkzA/lRJI1oEuvETV5sovGClE4YV1HeW');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-08 17:30:34
