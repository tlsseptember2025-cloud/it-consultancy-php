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
  `password` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
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
INSERT INTO `agents` VALUES (1,'Ahmed Hassan','ahmed@wahbib.com','$2y$10$abcdefghijklmnopqrstuv','0503333333','IT Consultant','Active',1,'2026-07-18 16:37:28'),(2,'Mohammed Ali','mohammed@wahbib.com','$2y$10$abcdefghijklmnopqrstuv','0504444444','Senior Consultant','Active',1,'2026-07-18 16:37:28');
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consultation_bookings`
--

LOCK TABLES `consultation_bookings` WRITE;
/*!40000 ALTER TABLE `consultation_bookings` DISABLE KEYS */;
INSERT INTO `consultation_bookings` VALUES (1,1,51,2,'2026-07-18 16:38:12'),(2,2,2,2,'2026-07-18 16:38:12');
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
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consultation_slots`
--

LOCK TABLES `consultation_slots` WRITE;
/*!40000 ALTER TABLE `consultation_slots` DISABLE KEYS */;
INSERT INTO `consultation_slots` VALUES (1,'2026-07-19','17:00:00',1,'Google Meet','1111',0,'2026-07-18 14:33:34'),(2,'2026-07-19','17:00:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(3,'2026-07-19','17:30:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(4,'2026-07-19','17:30:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(5,'2026-07-19','18:00:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(6,'2026-07-19','18:00:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(7,'2026-07-19','18:30:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(8,'2026-07-19','18:30:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(9,'2026-07-19','19:00:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(10,'2026-07-19','19:00:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(11,'2026-07-19','19:30:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(12,'2026-07-19','19:30:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(13,'2026-07-20','17:00:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(14,'2026-07-20','17:00:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(15,'2026-07-20','17:30:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(16,'2026-07-20','17:30:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(17,'2026-07-20','18:00:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(18,'2026-07-20','18:00:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(19,'2026-07-20','18:30:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(20,'2026-07-20','18:30:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(21,'2026-07-20','19:00:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(22,'2026-07-20','19:00:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(23,'2026-07-20','19:30:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(24,'2026-07-20','19:30:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(25,'2026-07-21','17:00:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(26,'2026-07-21','17:00:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(27,'2026-07-21','17:30:00',1,'Microsoft Teams','4444',1,'2026-07-18 14:33:34'),(28,'2026-07-21','17:30:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(29,'2026-07-21','18:00:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(30,'2026-07-21','18:00:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(31,'2026-07-21','18:30:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(32,'2026-07-21','18:30:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(33,'2026-07-21','19:00:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(34,'2026-07-21','19:00:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(35,'2026-07-21','19:30:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(36,'2026-07-21','19:30:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(37,'2026-07-22','17:00:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(38,'2026-07-22','17:00:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(39,'2026-07-22','17:30:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(40,'2026-07-22','17:30:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(41,'2026-07-22','18:00:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(42,'2026-07-22','18:00:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(43,'2026-07-22','18:30:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(44,'2026-07-22','18:30:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(45,'2026-07-22','19:00:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(46,'2026-07-22','19:00:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(47,'2026-07-22','19:30:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(48,'2026-07-22','19:30:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(49,'2026-07-23','17:00:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(50,'2026-07-23','17:00:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(51,'2026-07-23','17:30:00',1,'Zoom','6666',1,'2026-07-18 14:33:34'),(52,'2026-07-23','17:30:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(53,'2026-07-23','18:00:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(54,'2026-07-23','18:00:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(55,'2026-07-23','18:30:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(56,'2026-07-23','18:30:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(57,'2026-07-23','19:00:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(58,'2026-07-23','19:00:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(59,'2026-07-23','19:30:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(60,'2026-07-23','19:30:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(61,'2026-07-26','17:00:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(62,'2026-07-26','17:00:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(63,'2026-07-26','17:30:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(64,'2026-07-26','17:30:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(65,'2026-07-26','18:00:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(66,'2026-07-26','18:00:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(67,'2026-07-26','18:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(68,'2026-07-26','18:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(69,'2026-07-26','19:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(70,'2026-07-26','19:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(71,'2026-07-26','19:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(72,'2026-07-26','19:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(73,'2026-07-27','17:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(74,'2026-07-27','17:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(75,'2026-07-27','17:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(76,'2026-07-27','17:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(77,'2026-07-27','18:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(78,'2026-07-27','18:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(79,'2026-07-27','18:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(80,'2026-07-27','18:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(81,'2026-07-27','19:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(82,'2026-07-27','19:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(83,'2026-07-27','19:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(84,'2026-07-27','19:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(85,'2026-07-28','17:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(86,'2026-07-28','17:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(87,'2026-07-28','17:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(88,'2026-07-28','17:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(89,'2026-07-28','18:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(90,'2026-07-28','18:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(91,'2026-07-28','18:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(92,'2026-07-28','18:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(93,'2026-07-28','19:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(94,'2026-07-28','19:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(95,'2026-07-28','19:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(96,'2026-07-28','19:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(97,'2026-07-29','17:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(98,'2026-07-29','17:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(99,'2026-07-29','17:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(100,'2026-07-29','17:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(101,'2026-07-29','18:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(102,'2026-07-29','18:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(103,'2026-07-29','18:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(104,'2026-07-29','18:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(105,'2026-07-29','19:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(106,'2026-07-29','19:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(107,'2026-07-29','19:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(108,'2026-07-29','19:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(109,'2026-07-30','17:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(110,'2026-07-30','17:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(111,'2026-07-30','17:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(112,'2026-07-30','17:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(113,'2026-07-30','18:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(114,'2026-07-30','18:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(115,'2026-07-30','18:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(116,'2026-07-30','18:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(117,'2026-07-30','19:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(118,'2026-07-30','19:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(119,'2026-07-30','19:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(120,'2026-07-30','19:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35');
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (1,'John Smith','john.smith@test.com','0501111111','ABC Trading','Test customer','2026-07-18 16:37:18','$2y$10$abcdefghijklmnopqrstuv',NULL,NULL),(2,'Sarah Ahmed','sarah@test.com','0502222222','Dubai Tech','VIP customer','2026-07-18 16:37:18','$2y$10$abcdefghijklmnopqrstuv',NULL,NULL);
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `price_list`
--

DROP TABLE IF EXISTS `price_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `price_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `starting_price` decimal(10,2) NOT NULL,
  `maximum_price` decimal(10,2) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_price_service` (`service_id`),
  CONSTRAINT `fk_price_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `price_list`
--

LOCK TABLES `price_list` WRITE;
/*!40000 ALTER TABLE `price_list` DISABLE KEYS */;
/*!40000 ALTER TABLE `price_list` ENABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  `agent_id` int(11) DEFAULT NULL,
  `quoted_price` decimal(10,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Pending','Approved','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `workflow_stage` enum('Submitted','Consultation Approved','Consultation Scheduled','Consultation Confirmed','Consultation Completed','Proposal Sent','Proposal Accepted','Proposal Rejected','Awaiting Payment','Payment Submitted','Awaiting Service Scheduling','Service Scheduled','Service Active','Completed','Cancelled') NOT NULL DEFAULT 'Submitted',
  `proposal` text DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `completion_notes` text DEFAULT NULL,
  `incomplete_reason` text DEFAULT NULL,
  `consultation_reschedules` int(11) NOT NULL DEFAULT 0,
  `service_reschedules` int(11) NOT NULL DEFAULT 0,
  `job_status` enum('Pending','In Progress','Completed','Could Not Complete','Needs Admin Review') NOT NULL DEFAULT 'Pending',
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `requests_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `requests`
--

LOCK TABLES `requests` WRITE;
/*!40000 ALTER TABLE `requests` DISABLE KEYS */;
INSERT INTO `requests` VALUES (1,1,2,1,500.00,'Need advice regarding upgrading office infrastructure.','Pending','2026-07-18 16:37:59','Consultation Scheduled',NULL,NULL,NULL,NULL,1,0,'Pending'),(2,2,1,2,3500.00,'Need a corporate website.','Pending','2026-07-18 16:37:59','Consultation Scheduled',NULL,NULL,NULL,NULL,0,0,'Pending');
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=281 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_slots`
--

LOCK TABLES `service_slots` WRITE;
/*!40000 ALTER TABLE `service_slots` DISABLE KEYS */;
INSERT INTO `service_slots` VALUES (1,'2026-07-19','08:00:00',1,0),(2,'2026-07-19','08:00:00',2,0),(3,'2026-07-19','08:30:00',1,0),(4,'2026-07-19','08:30:00',2,0),(5,'2026-07-19','09:00:00',1,0),(6,'2026-07-19','09:00:00',2,0),(7,'2026-07-19','09:30:00',1,0),(8,'2026-07-19','09:30:00',2,0),(9,'2026-07-19','10:00:00',1,0),(10,'2026-07-19','10:00:00',2,0),(11,'2026-07-19','10:30:00',1,0),(12,'2026-07-19','10:30:00',2,0),(13,'2026-07-19','11:00:00',1,0),(14,'2026-07-19','11:00:00',2,0),(15,'2026-07-19','11:30:00',1,0),(16,'2026-07-19','11:30:00',2,0),(17,'2026-07-19','12:00:00',1,0),(18,'2026-07-19','12:00:00',2,0),(19,'2026-07-19','12:30:00',1,0),(20,'2026-07-19','12:30:00',2,0),(21,'2026-07-19','13:00:00',1,0),(22,'2026-07-19','13:00:00',2,0),(23,'2026-07-19','13:30:00',1,0),(24,'2026-07-19','13:30:00',2,0),(25,'2026-07-19','14:00:00',1,0),(26,'2026-07-19','14:00:00',2,0),(27,'2026-07-19','14:30:00',1,0),(28,'2026-07-19','14:30:00',2,0),(29,'2026-07-20','08:00:00',1,0),(30,'2026-07-20','08:00:00',2,0),(31,'2026-07-20','08:30:00',1,0),(32,'2026-07-20','08:30:00',2,0),(33,'2026-07-20','09:00:00',1,0),(34,'2026-07-20','09:00:00',2,0),(35,'2026-07-20','09:30:00',1,0),(36,'2026-07-20','09:30:00',2,0),(37,'2026-07-20','10:00:00',1,0),(38,'2026-07-20','10:00:00',2,0),(39,'2026-07-20','10:30:00',1,0),(40,'2026-07-20','10:30:00',2,0),(41,'2026-07-20','11:00:00',1,0),(42,'2026-07-20','11:00:00',2,0),(43,'2026-07-20','11:30:00',1,0),(44,'2026-07-20','11:30:00',2,0),(45,'2026-07-20','12:00:00',1,0),(46,'2026-07-20','12:00:00',2,0),(47,'2026-07-20','12:30:00',1,0),(48,'2026-07-20','12:30:00',2,0),(49,'2026-07-20','13:00:00',1,0),(50,'2026-07-20','13:00:00',2,0),(51,'2026-07-20','13:30:00',1,0),(52,'2026-07-20','13:30:00',2,0),(53,'2026-07-20','14:00:00',1,0),(54,'2026-07-20','14:00:00',2,0),(55,'2026-07-20','14:30:00',1,0),(56,'2026-07-20','14:30:00',2,0),(57,'2026-07-21','08:00:00',1,0),(58,'2026-07-21','08:00:00',2,0),(59,'2026-07-21','08:30:00',1,0),(60,'2026-07-21','08:30:00',2,0),(61,'2026-07-21','09:00:00',1,0),(62,'2026-07-21','09:00:00',2,0),(63,'2026-07-21','09:30:00',1,0),(64,'2026-07-21','09:30:00',2,0),(65,'2026-07-21','10:00:00',1,0),(66,'2026-07-21','10:00:00',2,0),(67,'2026-07-21','10:30:00',1,0),(68,'2026-07-21','10:30:00',2,0),(69,'2026-07-21','11:00:00',1,0),(70,'2026-07-21','11:00:00',2,0),(71,'2026-07-21','11:30:00',1,0),(72,'2026-07-21','11:30:00',2,0),(73,'2026-07-21','12:00:00',1,0),(74,'2026-07-21','12:00:00',2,0),(75,'2026-07-21','12:30:00',1,0),(76,'2026-07-21','12:30:00',2,0),(77,'2026-07-21','13:00:00',1,0),(78,'2026-07-21','13:00:00',2,0),(79,'2026-07-21','13:30:00',1,0),(80,'2026-07-21','13:30:00',2,0),(81,'2026-07-21','14:00:00',1,0),(82,'2026-07-21','14:00:00',2,0),(83,'2026-07-21','14:30:00',1,0),(84,'2026-07-21','14:30:00',2,0),(85,'2026-07-22','08:00:00',1,0),(86,'2026-07-22','08:00:00',2,0),(87,'2026-07-22','08:30:00',1,0),(88,'2026-07-22','08:30:00',2,0),(89,'2026-07-22','09:00:00',1,0),(90,'2026-07-22','09:00:00',2,0),(91,'2026-07-22','09:30:00',1,0),(92,'2026-07-22','09:30:00',2,0),(93,'2026-07-22','10:00:00',1,0),(94,'2026-07-22','10:00:00',2,0),(95,'2026-07-22','10:30:00',1,0),(96,'2026-07-22','10:30:00',2,0),(97,'2026-07-22','11:00:00',1,0),(98,'2026-07-22','11:00:00',2,0),(99,'2026-07-22','11:30:00',1,0),(100,'2026-07-22','11:30:00',2,0),(101,'2026-07-22','12:00:00',1,0),(102,'2026-07-22','12:00:00',2,0),(103,'2026-07-22','12:30:00',1,0),(104,'2026-07-22','12:30:00',2,0),(105,'2026-07-22','13:00:00',1,0),(106,'2026-07-22','13:00:00',2,0),(107,'2026-07-22','13:30:00',1,0),(108,'2026-07-22','13:30:00',2,0),(109,'2026-07-22','14:00:00',1,0),(110,'2026-07-22','14:00:00',2,0),(111,'2026-07-22','14:30:00',1,0),(112,'2026-07-22','14:30:00',2,0),(113,'2026-07-23','08:00:00',1,0),(114,'2026-07-23','08:00:00',2,0),(115,'2026-07-23','08:30:00',1,0),(116,'2026-07-23','08:30:00',2,0),(117,'2026-07-23','09:00:00',1,0),(118,'2026-07-23','09:00:00',2,0),(119,'2026-07-23','09:30:00',1,0),(120,'2026-07-23','09:30:00',2,0),(121,'2026-07-23','10:00:00',1,0),(122,'2026-07-23','10:00:00',2,0),(123,'2026-07-23','10:30:00',1,0),(124,'2026-07-23','10:30:00',2,0),(125,'2026-07-23','11:00:00',1,0),(126,'2026-07-23','11:00:00',2,0),(127,'2026-07-23','11:30:00',1,0),(128,'2026-07-23','11:30:00',2,0),(129,'2026-07-23','12:00:00',1,0),(130,'2026-07-23','12:00:00',2,0),(131,'2026-07-23','12:30:00',1,0),(132,'2026-07-23','12:30:00',2,0),(133,'2026-07-23','13:00:00',1,0),(134,'2026-07-23','13:00:00',2,0),(135,'2026-07-23','13:30:00',1,0),(136,'2026-07-23','13:30:00',2,0),(137,'2026-07-23','14:00:00',1,0),(138,'2026-07-23','14:00:00',2,0),(139,'2026-07-23','14:30:00',1,0),(140,'2026-07-23','14:30:00',2,0),(141,'2026-07-26','08:00:00',1,0),(142,'2026-07-26','08:00:00',2,0),(143,'2026-07-26','08:30:00',1,0),(144,'2026-07-26','08:30:00',2,0),(145,'2026-07-26','09:00:00',1,0),(146,'2026-07-26','09:00:00',2,0),(147,'2026-07-26','09:30:00',1,0),(148,'2026-07-26','09:30:00',2,0),(149,'2026-07-26','10:00:00',1,0),(150,'2026-07-26','10:00:00',2,0),(151,'2026-07-26','10:30:00',1,0),(152,'2026-07-26','10:30:00',2,0),(153,'2026-07-26','11:00:00',1,0),(154,'2026-07-26','11:00:00',2,0),(155,'2026-07-26','11:30:00',1,0),(156,'2026-07-26','11:30:00',2,0),(157,'2026-07-26','12:00:00',1,0),(158,'2026-07-26','12:00:00',2,0),(159,'2026-07-26','12:30:00',1,0),(160,'2026-07-26','12:30:00',2,0),(161,'2026-07-26','13:00:00',1,0),(162,'2026-07-26','13:00:00',2,0),(163,'2026-07-26','13:30:00',1,0),(164,'2026-07-26','13:30:00',2,0),(165,'2026-07-26','14:00:00',1,0),(166,'2026-07-26','14:00:00',2,0),(167,'2026-07-26','14:30:00',1,0),(168,'2026-07-26','14:30:00',2,0),(169,'2026-07-27','08:00:00',1,0),(170,'2026-07-27','08:00:00',2,0),(171,'2026-07-27','08:30:00',1,0),(172,'2026-07-27','08:30:00',2,0),(173,'2026-07-27','09:00:00',1,0),(174,'2026-07-27','09:00:00',2,0),(175,'2026-07-27','09:30:00',1,0),(176,'2026-07-27','09:30:00',2,0),(177,'2026-07-27','10:00:00',1,0),(178,'2026-07-27','10:00:00',2,0),(179,'2026-07-27','10:30:00',1,0),(180,'2026-07-27','10:30:00',2,0),(181,'2026-07-27','11:00:00',1,0),(182,'2026-07-27','11:00:00',2,0),(183,'2026-07-27','11:30:00',1,0),(184,'2026-07-27','11:30:00',2,0),(185,'2026-07-27','12:00:00',1,0),(186,'2026-07-27','12:00:00',2,0),(187,'2026-07-27','12:30:00',1,0),(188,'2026-07-27','12:30:00',2,0),(189,'2026-07-27','13:00:00',1,0),(190,'2026-07-27','13:00:00',2,0),(191,'2026-07-27','13:30:00',1,0),(192,'2026-07-27','13:30:00',2,0),(193,'2026-07-27','14:00:00',1,0),(194,'2026-07-27','14:00:00',2,0),(195,'2026-07-27','14:30:00',1,0),(196,'2026-07-27','14:30:00',2,0),(197,'2026-07-28','08:00:00',1,0),(198,'2026-07-28','08:00:00',2,0),(199,'2026-07-28','08:30:00',1,0),(200,'2026-07-28','08:30:00',2,0),(201,'2026-07-28','09:00:00',1,0),(202,'2026-07-28','09:00:00',2,0),(203,'2026-07-28','09:30:00',1,0),(204,'2026-07-28','09:30:00',2,0),(205,'2026-07-28','10:00:00',1,0),(206,'2026-07-28','10:00:00',2,0),(207,'2026-07-28','10:30:00',1,0),(208,'2026-07-28','10:30:00',2,0),(209,'2026-07-28','11:00:00',1,0),(210,'2026-07-28','11:00:00',2,0),(211,'2026-07-28','11:30:00',1,0),(212,'2026-07-28','11:30:00',2,0),(213,'2026-07-28','12:00:00',1,0),(214,'2026-07-28','12:00:00',2,0),(215,'2026-07-28','12:30:00',1,0),(216,'2026-07-28','12:30:00',2,0),(217,'2026-07-28','13:00:00',1,0),(218,'2026-07-28','13:00:00',2,0),(219,'2026-07-28','13:30:00',1,0),(220,'2026-07-28','13:30:00',2,0),(221,'2026-07-28','14:00:00',1,0),(222,'2026-07-28','14:00:00',2,0),(223,'2026-07-28','14:30:00',1,0),(224,'2026-07-28','14:30:00',2,0),(225,'2026-07-29','08:00:00',1,0),(226,'2026-07-29','08:00:00',2,0),(227,'2026-07-29','08:30:00',1,0),(228,'2026-07-29','08:30:00',2,0),(229,'2026-07-29','09:00:00',1,0),(230,'2026-07-29','09:00:00',2,0),(231,'2026-07-29','09:30:00',1,0),(232,'2026-07-29','09:30:00',2,0),(233,'2026-07-29','10:00:00',1,0),(234,'2026-07-29','10:00:00',2,0),(235,'2026-07-29','10:30:00',1,0),(236,'2026-07-29','10:30:00',2,0),(237,'2026-07-29','11:00:00',1,0),(238,'2026-07-29','11:00:00',2,0),(239,'2026-07-29','11:30:00',1,0),(240,'2026-07-29','11:30:00',2,0),(241,'2026-07-29','12:00:00',1,0),(242,'2026-07-29','12:00:00',2,0),(243,'2026-07-29','12:30:00',1,0),(244,'2026-07-29','12:30:00',2,0),(245,'2026-07-29','13:00:00',1,0),(246,'2026-07-29','13:00:00',2,0),(247,'2026-07-29','13:30:00',1,0),(248,'2026-07-29','13:30:00',2,0),(249,'2026-07-29','14:00:00',1,0),(250,'2026-07-29','14:00:00',2,0),(251,'2026-07-29','14:30:00',1,0),(252,'2026-07-29','14:30:00',2,0),(253,'2026-07-30','08:00:00',1,0),(254,'2026-07-30','08:00:00',2,0),(255,'2026-07-30','08:30:00',1,0),(256,'2026-07-30','08:30:00',2,0),(257,'2026-07-30','09:00:00',1,0),(258,'2026-07-30','09:00:00',2,0),(259,'2026-07-30','09:30:00',1,0),(260,'2026-07-30','09:30:00',2,0),(261,'2026-07-30','10:00:00',1,0),(262,'2026-07-30','10:00:00',2,0),(263,'2026-07-30','10:30:00',1,0),(264,'2026-07-30','10:30:00',2,0),(265,'2026-07-30','11:00:00',1,0),(266,'2026-07-30','11:00:00',2,0),(267,'2026-07-30','11:30:00',1,0),(268,'2026-07-30','11:30:00',2,0),(269,'2026-07-30','12:00:00',1,0),(270,'2026-07-30','12:00:00',2,0),(271,'2026-07-30','12:30:00',1,0),(272,'2026-07-30','12:30:00',2,0),(273,'2026-07-30','13:00:00',1,0),(274,'2026-07-30','13:00:00',2,0),(275,'2026-07-30','13:30:00',1,0),(276,'2026-07-30','13:30:00',2,0),(277,'2026-07-30','14:00:00',1,0),(278,'2026-07-30','14:00:00',2,0),(279,'2026-07-30','14:30:00',1,0),(280,'2026-07-30','14:30:00',2,0);
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` VALUES (1,'Website Builder - Small Business','Website Builder for Small Businesses\r\n\r\n1> Build professional websites\r\n2> Mobile-responsive design\r\n3> Contact forms and lead capture\r\n4> Basic SEO optimization\r\n5> Blog and content setup\r\n6> Basic e-commerce stores\r\n7> Ongoing website maintenance','2026-07-18 14:35:51',0.00,'1784385351_1783426707_website.jpg'),(2,'Software Installation & Setup','1> Microsoft Office\r\n2> Outlook\r\n3> Zoom\r\n4> Teams\r\n5> VPNs\r\n6> Adobe products\r\n7> QuickBooks\r\n8> Others','2026-07-18 14:36:29',0.00,'1784385389_1783427535_software.jpg'),(3,'Remote IT Support','1> PC troubleshooting\r\n2> Printer issues\r\n3> Outlook issues\r\n4> Windows problems\r\n5> Software errors\r\n6> Slow computers\r\n7> Others','2026-07-18 14:36:53',0.00,'1784385413_1783427553_support.jpg');
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
INSERT INTO `users` VALUES (1,'admin@wahbibconsultancy.com','$2y$10$pH5iVj5LgQE3txIkOeJc5etgO4uttFFJwan6nR34Pwnhu0XkMDrYK');
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

-- Dump completed on 2026-07-18 21:56:36
