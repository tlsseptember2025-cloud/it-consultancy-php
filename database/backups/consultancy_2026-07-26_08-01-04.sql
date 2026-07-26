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
INSERT INTO `agents` VALUES (1,'Ahmad Rami','ramiwahdan1978@gmail.com','$2y$10$hzg9oZmuVZEUyNHpR5rcEeSxTjVaSNYM9npEPV9pbUiQUobrULq46','0503333333','IT Consultant','Active',1,'2026-07-18 16:37:28'),(2,'Shahed Rami','shahedramitaherwahdan2010@gmail.com','$2y$10$bPOanB0jTh5A29N14rf6lOwlBilUGpvFrVPR8e5FqQvA3s14NKIZS','0504444444','Senior Consultant','Active',1,'2026-07-18 16:37:28');
/*!40000 ALTER TABLE `agents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `consultation_agent_reassignments`
--

DROP TABLE IF EXISTS `consultation_agent_reassignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `consultation_agent_reassignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `old_agent_id` int(11) NOT NULL,
  `new_agent_id` int(11) NOT NULL,
  `reassigned_by` int(11) NOT NULL,
  `reason` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`),
  KEY `booking_id` (`booking_id`),
  KEY `old_agent_id` (`old_agent_id`),
  KEY `new_agent_id` (`new_agent_id`),
  KEY `reassigned_by` (`reassigned_by`),
  CONSTRAINT `consultation_agent_reassignments_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`),
  CONSTRAINT `consultation_agent_reassignments_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `consultation_bookings` (`id`),
  CONSTRAINT `consultation_agent_reassignments_ibfk_3` FOREIGN KEY (`old_agent_id`) REFERENCES `agents` (`id`),
  CONSTRAINT `consultation_agent_reassignments_ibfk_4` FOREIGN KEY (`new_agent_id`) REFERENCES `agents` (`id`),
  CONSTRAINT `consultation_agent_reassignments_ibfk_5` FOREIGN KEY (`reassigned_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consultation_agent_reassignments`
--

LOCK TABLES `consultation_agent_reassignments` WRITE;
/*!40000 ALTER TABLE `consultation_agent_reassignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `consultation_agent_reassignments` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consultation_bookings`
--

LOCK TABLES `consultation_bookings` WRITE;
/*!40000 ALTER TABLE `consultation_bookings` DISABLE KEYS */;
INSERT INTO `consultation_bookings` VALUES (27,29,61,1,'2026-07-22 12:24:42');
/*!40000 ALTER TABLE `consultation_bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `consultation_customer_contact_approvals`
--

DROP TABLE IF EXISTS `consultation_customer_contact_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `consultation_customer_contact_approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `approved_by` int(11) NOT NULL,
  `admin_instruction` text NOT NULL,
  `approved_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`),
  KEY `booking_id` (`booking_id`),
  KEY `agent_id` (`agent_id`),
  KEY `approved_by` (`approved_by`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consultation_customer_contact_approvals`
--

LOCK TABLES `consultation_customer_contact_approvals` WRITE;
/*!40000 ALTER TABLE `consultation_customer_contact_approvals` DISABLE KEYS */;
INSERT INTO `consultation_customer_contact_approvals` VALUES (1,29,27,1,1,'please do the needful!','2026-07-23 13:31:09'),(2,29,27,1,1,'You can call the customer','2026-07-23 13:36:26'),(3,29,27,1,1,'Now it is ok to call the customer...call between 10AM and 1PM','2026-07-23 13:37:18');
/*!40000 ALTER TABLE `consultation_customer_contact_approvals` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=193 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consultation_slots`
--

LOCK TABLES `consultation_slots` WRITE;
/*!40000 ALTER TABLE `consultation_slots` DISABLE KEYS */;
INSERT INTO `consultation_slots` VALUES (61,'2026-07-26','17:00:00',1,'Google Meet',NULL,1,'2026-07-18 14:33:34'),(62,'2026-07-26','17:00:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(63,'2026-07-26','17:30:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(64,'2026-07-26','17:30:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(65,'2026-07-26','18:00:00',1,NULL,NULL,0,'2026-07-18 14:33:34'),(66,'2026-07-26','18:00:00',2,NULL,NULL,0,'2026-07-18 14:33:34'),(67,'2026-07-26','18:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(68,'2026-07-26','18:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(69,'2026-07-26','19:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(70,'2026-07-26','19:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(71,'2026-07-26','19:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(72,'2026-07-26','19:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(73,'2026-07-27','17:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(74,'2026-07-27','17:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(75,'2026-07-27','17:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(76,'2026-07-27','17:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(77,'2026-07-27','18:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(78,'2026-07-27','18:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(79,'2026-07-27','18:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(80,'2026-07-27','18:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(81,'2026-07-27','19:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(82,'2026-07-27','19:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(83,'2026-07-27','19:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(84,'2026-07-27','19:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(85,'2026-07-28','17:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(86,'2026-07-28','17:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(87,'2026-07-28','17:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(88,'2026-07-28','17:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(89,'2026-07-28','18:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(90,'2026-07-28','18:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(91,'2026-07-28','18:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(92,'2026-07-28','18:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(93,'2026-07-28','19:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(94,'2026-07-28','19:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(95,'2026-07-28','19:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(96,'2026-07-28','19:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(97,'2026-07-29','17:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(98,'2026-07-29','17:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(99,'2026-07-29','17:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(100,'2026-07-29','17:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(101,'2026-07-29','18:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(102,'2026-07-29','18:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(103,'2026-07-29','18:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(104,'2026-07-29','18:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(105,'2026-07-29','19:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(106,'2026-07-29','19:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(107,'2026-07-29','19:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(108,'2026-07-29','19:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(109,'2026-07-30','17:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(110,'2026-07-30','17:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(111,'2026-07-30','17:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(112,'2026-07-30','17:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(113,'2026-07-30','18:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(114,'2026-07-30','18:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(115,'2026-07-30','18:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(116,'2026-07-30','18:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(117,'2026-07-30','19:00:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(118,'2026-07-30','19:00:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(119,'2026-07-30','19:30:00',1,NULL,NULL,0,'2026-07-18 14:33:35'),(120,'2026-07-30','19:30:00',2,NULL,NULL,0,'2026-07-18 14:33:35'),(121,'2026-08-02','17:00:00',1,NULL,NULL,0,'2026-07-19 04:24:10'),(122,'2026-08-02','17:00:00',2,NULL,NULL,0,'2026-07-19 04:24:10'),(123,'2026-08-02','17:30:00',1,NULL,NULL,0,'2026-07-19 04:24:10'),(124,'2026-08-02','17:30:00',2,NULL,NULL,0,'2026-07-19 04:24:10'),(125,'2026-08-02','18:00:00',1,NULL,NULL,0,'2026-07-19 04:24:10'),(126,'2026-08-02','18:00:00',2,NULL,NULL,0,'2026-07-19 04:24:10'),(127,'2026-08-02','18:30:00',1,NULL,NULL,0,'2026-07-19 04:24:10'),(128,'2026-08-02','18:30:00',2,NULL,NULL,0,'2026-07-19 04:24:10'),(129,'2026-08-02','19:00:00',1,NULL,NULL,0,'2026-07-19 04:24:10'),(130,'2026-08-02','19:00:00',2,NULL,NULL,0,'2026-07-19 04:24:10'),(131,'2026-08-02','19:30:00',1,NULL,NULL,0,'2026-07-19 04:24:10'),(132,'2026-08-02','19:30:00',2,NULL,NULL,0,'2026-07-19 04:24:10'),(133,'2026-08-03','17:00:00',1,NULL,NULL,0,'2026-07-20 05:33:37'),(134,'2026-08-03','17:00:00',2,NULL,NULL,0,'2026-07-20 05:33:37'),(135,'2026-08-03','17:30:00',1,NULL,NULL,0,'2026-07-20 05:33:37'),(136,'2026-08-03','17:30:00',2,NULL,NULL,0,'2026-07-20 05:33:37'),(137,'2026-08-03','18:00:00',1,NULL,NULL,0,'2026-07-20 05:33:37'),(138,'2026-08-03','18:00:00',2,NULL,NULL,0,'2026-07-20 05:33:37'),(139,'2026-08-03','18:30:00',1,NULL,NULL,0,'2026-07-20 05:33:37'),(140,'2026-08-03','18:30:00',2,NULL,NULL,0,'2026-07-20 05:33:37'),(141,'2026-08-03','19:00:00',1,NULL,NULL,0,'2026-07-20 05:33:37'),(142,'2026-08-03','19:00:00',2,NULL,NULL,0,'2026-07-20 05:33:37'),(143,'2026-08-03','19:30:00',1,NULL,NULL,0,'2026-07-20 05:33:37'),(144,'2026-08-03','19:30:00',2,NULL,NULL,0,'2026-07-20 05:33:37'),(145,'2026-08-04','17:00:00',1,NULL,NULL,0,'2026-07-21 06:12:39'),(146,'2026-08-04','17:00:00',2,NULL,NULL,0,'2026-07-21 06:12:39'),(147,'2026-08-04','17:30:00',1,NULL,NULL,0,'2026-07-21 06:12:39'),(148,'2026-08-04','17:30:00',2,NULL,NULL,0,'2026-07-21 06:12:39'),(149,'2026-08-04','18:00:00',1,NULL,NULL,0,'2026-07-21 06:12:39'),(150,'2026-08-04','18:00:00',2,NULL,NULL,0,'2026-07-21 06:12:39'),(151,'2026-08-04','18:30:00',1,NULL,NULL,0,'2026-07-21 06:12:39'),(152,'2026-08-04','18:30:00',2,NULL,NULL,0,'2026-07-21 06:12:39'),(153,'2026-08-04','19:00:00',1,NULL,NULL,0,'2026-07-21 06:12:39'),(154,'2026-08-04','19:00:00',2,NULL,NULL,0,'2026-07-21 06:12:40'),(155,'2026-08-04','19:30:00',1,NULL,NULL,0,'2026-07-21 06:12:40'),(156,'2026-08-04','19:30:00',2,NULL,NULL,0,'2026-07-21 06:12:40'),(157,'2026-08-05','17:00:00',1,NULL,NULL,0,'2026-07-22 08:11:49'),(158,'2026-08-05','17:00:00',2,NULL,NULL,0,'2026-07-22 08:11:49'),(159,'2026-08-05','17:30:00',1,NULL,NULL,0,'2026-07-22 08:11:49'),(160,'2026-08-05','17:30:00',2,NULL,NULL,0,'2026-07-22 08:11:49'),(161,'2026-08-05','18:00:00',1,NULL,NULL,0,'2026-07-22 08:11:49'),(162,'2026-08-05','18:00:00',2,NULL,NULL,0,'2026-07-22 08:11:49'),(163,'2026-08-05','18:30:00',1,NULL,NULL,0,'2026-07-22 08:11:49'),(164,'2026-08-05','18:30:00',2,NULL,NULL,0,'2026-07-22 08:11:49'),(165,'2026-08-05','19:00:00',1,NULL,NULL,0,'2026-07-22 08:11:49'),(166,'2026-08-05','19:00:00',2,NULL,NULL,0,'2026-07-22 08:11:49'),(167,'2026-08-05','19:30:00',1,NULL,NULL,0,'2026-07-22 08:11:49'),(168,'2026-08-05','19:30:00',2,NULL,NULL,0,'2026-07-22 08:11:49'),(169,'2026-08-06','17:00:00',1,NULL,NULL,0,'2026-07-23 06:32:33'),(170,'2026-08-06','17:00:00',2,NULL,NULL,0,'2026-07-23 06:32:33'),(171,'2026-08-06','17:30:00',1,NULL,NULL,0,'2026-07-23 06:32:33'),(172,'2026-08-06','17:30:00',2,NULL,NULL,0,'2026-07-23 06:32:33'),(173,'2026-08-06','18:00:00',1,NULL,NULL,0,'2026-07-23 06:32:33'),(174,'2026-08-06','18:00:00',2,NULL,NULL,0,'2026-07-23 06:32:33'),(175,'2026-08-06','18:30:00',1,NULL,NULL,0,'2026-07-23 06:32:33'),(176,'2026-08-06','18:30:00',2,NULL,NULL,0,'2026-07-23 06:32:33'),(177,'2026-08-06','19:00:00',1,NULL,NULL,0,'2026-07-23 06:32:33'),(178,'2026-08-06','19:00:00',2,NULL,NULL,0,'2026-07-23 06:32:33'),(179,'2026-08-06','19:30:00',1,NULL,NULL,0,'2026-07-23 06:32:33'),(180,'2026-08-06','19:30:00',2,NULL,NULL,0,'2026-07-23 06:32:33'),(181,'2026-08-09','17:00:00',1,NULL,NULL,0,'2026-07-26 05:42:27'),(182,'2026-08-09','17:00:00',2,NULL,NULL,0,'2026-07-26 05:42:27'),(183,'2026-08-09','17:30:00',1,NULL,NULL,0,'2026-07-26 05:42:27'),(184,'2026-08-09','17:30:00',2,NULL,NULL,0,'2026-07-26 05:42:27'),(185,'2026-08-09','18:00:00',1,NULL,NULL,0,'2026-07-26 05:42:27'),(186,'2026-08-09','18:00:00',2,NULL,NULL,0,'2026-07-26 05:42:27'),(187,'2026-08-09','18:30:00',1,NULL,NULL,0,'2026-07-26 05:42:27'),(188,'2026-08-09','18:30:00',2,NULL,NULL,0,'2026-07-26 05:42:27'),(189,'2026-08-09','19:00:00',1,NULL,NULL,0,'2026-07-26 05:42:27'),(190,'2026-08-09','19:00:00',2,NULL,NULL,0,'2026-07-26 05:42:28'),(191,'2026-08-09','19:30:00',1,NULL,NULL,0,'2026-07-26 05:42:28'),(192,'2026-08-09','19:30:00',2,NULL,NULL,0,'2026-07-26 05:42:28');
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
INSERT INTO `customers` VALUES (1,'Fatima Habib','fatima.habib1980@gmail.com','0507626410','ABC Trading','Valuable customer','2026-07-18 16:37:18','$2y$10$WQYDiXVmtpM2ncNtv0tvk.2zKzAUw1S3zisRN7JdRdRYYswb/LCjC',NULL,NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (48,'customer',1,'Consultation Confirmed','Your consultation has been confirmed.','?page=customer-requests',0,'2026-07-22 12:24:50'),(49,'customer',1,'📄 Proposal Ready','Your proposal is ready for review.','?page=view-proposal&id=29',0,'2026-07-22 12:25:13'),(50,'admin',NULL,'✅ Proposal Accepted','Fatima Habib has accepted the proposal.','?page=view-request&id=29',1,'2026-07-22 12:25:29'),(51,'customer',1,'Payment Approved','Your payment has been approved. You may now schedule your service.','?page=customer-requests',0,'2026-07-22 12:25:46');
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_slips`
--

LOCK TABLES `payment_slips` WRITE;
/*!40000 ALTER TABLE `payment_slips` DISABLE KEYS */;
INSERT INTO `payment_slips` VALUES (7,1,29,'1784723135_unnamed.png','Approved','2026-07-22 16:25:35');
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (5,29,500.00,'Paid','2026-07-22 16:25:43','Payment approved from deposit slip review','2026-07-22 12:25:43');
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
  `status` enum('Pending','Approved','In Progress','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `workflow_stage` varchar(50) DEFAULT NULL,
  `proposal` text DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `completion_notes` text DEFAULT NULL,
  `admin_instruction` text DEFAULT NULL,
  `contact_notes` text DEFAULT NULL,
  `incomplete_reason` text DEFAULT NULL,
  `consultation_reschedules` int(11) NOT NULL DEFAULT 0,
  `service_reschedules` int(11) NOT NULL DEFAULT 0,
  `service_rejection_reason` text DEFAULT NULL,
  `service_rejected_at` datetime DEFAULT NULL,
  `service_rejected_by` int(11) DEFAULT NULL,
  `job_status` enum('Pending','In Progress','Completed','Could Not Complete','Needs Admin Review','Customer Answered','No Answer','Wrong Number','Customer Requested Reschedule') NOT NULL DEFAULT 'Pending',
  `consultation_rejection_reason` text DEFAULT NULL,
  `consultation_rejected_at` datetime DEFAULT NULL,
  `consultation_rejected_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `requests_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `requests`
--

LOCK TABLES `requests` WRITE;
/*!40000 ALTER TABLE `requests` DISABLE KEYS */;
INSERT INTO `requests` VALUES (29,1,3,1,500.00,'ok','In Progress','2026-07-22 12:24:27','Customer Contact Approved','okokok','2026-07-22 18:22:32','no answer!','Now it is ok to call the customer...call between 10AM and 1PM','ok','Other',0,1,NULL,NULL,NULL,'No Answer',NULL,NULL,NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_bookings`
--

LOCK TABLES `service_bookings` WRITE;
/*!40000 ALTER TABLE `service_bookings` DISABLE KEYS */;
INSERT INTO `service_bookings` VALUES (4,29,197,NULL,'2026-07-22 12:30:30');
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
) ENGINE=InnoDB AUTO_INCREMENT=449 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_slots`
--

LOCK TABLES `service_slots` WRITE;
/*!40000 ALTER TABLE `service_slots` DISABLE KEYS */;
INSERT INTO `service_slots` VALUES (141,'2026-07-26','08:00:00',1,1),(142,'2026-07-26','08:00:00',2,0),(143,'2026-07-26','08:30:00',1,0),(144,'2026-07-26','08:30:00',2,0),(145,'2026-07-26','09:00:00',1,0),(146,'2026-07-26','09:00:00',2,0),(147,'2026-07-26','09:30:00',1,0),(148,'2026-07-26','09:30:00',2,0),(149,'2026-07-26','10:00:00',1,0),(150,'2026-07-26','10:00:00',2,0),(151,'2026-07-26','10:30:00',1,0),(152,'2026-07-26','10:30:00',2,0),(153,'2026-07-26','11:00:00',1,0),(154,'2026-07-26','11:00:00',2,0),(155,'2026-07-26','11:30:00',1,0),(156,'2026-07-26','11:30:00',2,0),(157,'2026-07-26','12:00:00',1,0),(158,'2026-07-26','12:00:00',2,0),(159,'2026-07-26','12:30:00',1,0),(160,'2026-07-26','12:30:00',2,0),(161,'2026-07-26','13:00:00',1,0),(162,'2026-07-26','13:00:00',2,0),(163,'2026-07-26','13:30:00',1,0),(164,'2026-07-26','13:30:00',2,0),(165,'2026-07-26','14:00:00',1,0),(166,'2026-07-26','14:00:00',2,0),(167,'2026-07-26','14:30:00',1,0),(168,'2026-07-26','14:30:00',2,0),(169,'2026-07-27','08:00:00',1,0),(170,'2026-07-27','08:00:00',2,0),(171,'2026-07-27','08:30:00',1,0),(172,'2026-07-27','08:30:00',2,0),(173,'2026-07-27','09:00:00',1,0),(174,'2026-07-27','09:00:00',2,0),(175,'2026-07-27','09:30:00',1,0),(176,'2026-07-27','09:30:00',2,0),(177,'2026-07-27','10:00:00',1,0),(178,'2026-07-27','10:00:00',2,0),(179,'2026-07-27','10:30:00',1,0),(180,'2026-07-27','10:30:00',2,0),(181,'2026-07-27','11:00:00',1,0),(182,'2026-07-27','11:00:00',2,0),(183,'2026-07-27','11:30:00',1,0),(184,'2026-07-27','11:30:00',2,0),(185,'2026-07-27','12:00:00',1,0),(186,'2026-07-27','12:00:00',2,0),(187,'2026-07-27','12:30:00',1,0),(188,'2026-07-27','12:30:00',2,0),(189,'2026-07-27','13:00:00',1,0),(190,'2026-07-27','13:00:00',2,0),(191,'2026-07-27','13:30:00',1,0),(192,'2026-07-27','13:30:00',2,0),(193,'2026-07-27','14:00:00',1,0),(194,'2026-07-27','14:00:00',2,0),(195,'2026-07-27','14:30:00',1,0),(196,'2026-07-27','14:30:00',2,0),(197,'2026-07-28','08:00:00',1,1),(198,'2026-07-28','08:00:00',2,0),(199,'2026-07-28','08:30:00',1,0),(200,'2026-07-28','08:30:00',2,0),(201,'2026-07-28','09:00:00',1,0),(202,'2026-07-28','09:00:00',2,0),(203,'2026-07-28','09:30:00',1,0),(204,'2026-07-28','09:30:00',2,0),(205,'2026-07-28','10:00:00',1,0),(206,'2026-07-28','10:00:00',2,0),(207,'2026-07-28','10:30:00',1,0),(208,'2026-07-28','10:30:00',2,0),(209,'2026-07-28','11:00:00',1,0),(210,'2026-07-28','11:00:00',2,0),(211,'2026-07-28','11:30:00',1,0),(212,'2026-07-28','11:30:00',2,0),(213,'2026-07-28','12:00:00',1,0),(214,'2026-07-28','12:00:00',2,0),(215,'2026-07-28','12:30:00',1,0),(216,'2026-07-28','12:30:00',2,0),(217,'2026-07-28','13:00:00',1,0),(218,'2026-07-28','13:00:00',2,0),(219,'2026-07-28','13:30:00',1,0),(220,'2026-07-28','13:30:00',2,0),(221,'2026-07-28','14:00:00',1,0),(222,'2026-07-28','14:00:00',2,0),(223,'2026-07-28','14:30:00',1,0),(224,'2026-07-28','14:30:00',2,0),(225,'2026-07-29','08:00:00',1,0),(226,'2026-07-29','08:00:00',2,0),(227,'2026-07-29','08:30:00',1,0),(228,'2026-07-29','08:30:00',2,0),(229,'2026-07-29','09:00:00',1,0),(230,'2026-07-29','09:00:00',2,0),(231,'2026-07-29','09:30:00',1,0),(232,'2026-07-29','09:30:00',2,0),(233,'2026-07-29','10:00:00',1,0),(234,'2026-07-29','10:00:00',2,0),(235,'2026-07-29','10:30:00',1,0),(236,'2026-07-29','10:30:00',2,0),(237,'2026-07-29','11:00:00',1,0),(238,'2026-07-29','11:00:00',2,0),(239,'2026-07-29','11:30:00',1,0),(240,'2026-07-29','11:30:00',2,0),(241,'2026-07-29','12:00:00',1,0),(242,'2026-07-29','12:00:00',2,0),(243,'2026-07-29','12:30:00',1,0),(244,'2026-07-29','12:30:00',2,0),(245,'2026-07-29','13:00:00',1,0),(246,'2026-07-29','13:00:00',2,0),(247,'2026-07-29','13:30:00',1,0),(248,'2026-07-29','13:30:00',2,0),(249,'2026-07-29','14:00:00',1,0),(250,'2026-07-29','14:00:00',2,0),(251,'2026-07-29','14:30:00',1,0),(252,'2026-07-29','14:30:00',2,0),(253,'2026-07-30','08:00:00',1,0),(254,'2026-07-30','08:00:00',2,0),(255,'2026-07-30','08:30:00',1,0),(256,'2026-07-30','08:30:00',2,0),(257,'2026-07-30','09:00:00',1,0),(258,'2026-07-30','09:00:00',2,0),(259,'2026-07-30','09:30:00',1,0),(260,'2026-07-30','09:30:00',2,0),(261,'2026-07-30','10:00:00',1,0),(262,'2026-07-30','10:00:00',2,0),(263,'2026-07-30','10:30:00',1,0),(264,'2026-07-30','10:30:00',2,0),(265,'2026-07-30','11:00:00',1,0),(266,'2026-07-30','11:00:00',2,0),(267,'2026-07-30','11:30:00',1,0),(268,'2026-07-30','11:30:00',2,0),(269,'2026-07-30','12:00:00',1,0),(270,'2026-07-30','12:00:00',2,0),(271,'2026-07-30','12:30:00',1,0),(272,'2026-07-30','12:30:00',2,0),(273,'2026-07-30','13:00:00',1,0),(274,'2026-07-30','13:00:00',2,0),(275,'2026-07-30','13:30:00',1,0),(276,'2026-07-30','13:30:00',2,0),(277,'2026-07-30','14:00:00',1,0),(278,'2026-07-30','14:00:00',2,0),(279,'2026-07-30','14:30:00',1,0),(280,'2026-07-30','14:30:00',2,0),(281,'2026-08-02','08:00:00',1,0),(282,'2026-08-02','08:00:00',2,0),(283,'2026-08-02','08:30:00',1,0),(284,'2026-08-02','08:30:00',2,0),(285,'2026-08-02','09:00:00',1,0),(286,'2026-08-02','09:00:00',2,0),(287,'2026-08-02','09:30:00',1,0),(288,'2026-08-02','09:30:00',2,0),(289,'2026-08-02','10:00:00',1,0),(290,'2026-08-02','10:00:00',2,0),(291,'2026-08-02','10:30:00',1,0),(292,'2026-08-02','10:30:00',2,0),(293,'2026-08-02','11:00:00',1,0),(294,'2026-08-02','11:00:00',2,0),(295,'2026-08-02','11:30:00',1,0),(296,'2026-08-02','11:30:00',2,0),(297,'2026-08-02','12:00:00',1,0),(298,'2026-08-02','12:00:00',2,0),(299,'2026-08-02','12:30:00',1,0),(300,'2026-08-02','12:30:00',2,0),(301,'2026-08-02','13:00:00',1,0),(302,'2026-08-02','13:00:00',2,0),(303,'2026-08-02','13:30:00',1,0),(304,'2026-08-02','13:30:00',2,0),(305,'2026-08-02','14:00:00',1,0),(306,'2026-08-02','14:00:00',2,0),(307,'2026-08-02','14:30:00',1,0),(308,'2026-08-02','14:30:00',2,0),(309,'2026-08-03','08:00:00',1,0),(310,'2026-08-03','08:00:00',2,0),(311,'2026-08-03','08:30:00',1,0),(312,'2026-08-03','08:30:00',2,0),(313,'2026-08-03','09:00:00',1,0),(314,'2026-08-03','09:00:00',2,0),(315,'2026-08-03','09:30:00',1,0),(316,'2026-08-03','09:30:00',2,0),(317,'2026-08-03','10:00:00',1,0),(318,'2026-08-03','10:00:00',2,0),(319,'2026-08-03','10:30:00',1,0),(320,'2026-08-03','10:30:00',2,0),(321,'2026-08-03','11:00:00',1,0),(322,'2026-08-03','11:00:00',2,0),(323,'2026-08-03','11:30:00',1,0),(324,'2026-08-03','11:30:00',2,0),(325,'2026-08-03','12:00:00',1,0),(326,'2026-08-03','12:00:00',2,0),(327,'2026-08-03','12:30:00',1,0),(328,'2026-08-03','12:30:00',2,0),(329,'2026-08-03','13:00:00',1,0),(330,'2026-08-03','13:00:00',2,0),(331,'2026-08-03','13:30:00',1,0),(332,'2026-08-03','13:30:00',2,0),(333,'2026-08-03','14:00:00',1,0),(334,'2026-08-03','14:00:00',2,0),(335,'2026-08-03','14:30:00',1,0),(336,'2026-08-03','14:30:00',2,0),(337,'2026-08-04','08:00:00',1,0),(338,'2026-08-04','08:00:00',2,0),(339,'2026-08-04','08:30:00',1,0),(340,'2026-08-04','08:30:00',2,0),(341,'2026-08-04','09:00:00',1,0),(342,'2026-08-04','09:00:00',2,0),(343,'2026-08-04','09:30:00',1,0),(344,'2026-08-04','09:30:00',2,0),(345,'2026-08-04','10:00:00',1,0),(346,'2026-08-04','10:00:00',2,0),(347,'2026-08-04','10:30:00',1,0),(348,'2026-08-04','10:30:00',2,0),(349,'2026-08-04','11:00:00',1,0),(350,'2026-08-04','11:00:00',2,0),(351,'2026-08-04','11:30:00',1,0),(352,'2026-08-04','11:30:00',2,0),(353,'2026-08-04','12:00:00',1,0),(354,'2026-08-04','12:00:00',2,0),(355,'2026-08-04','12:30:00',1,0),(356,'2026-08-04','12:30:00',2,0),(357,'2026-08-04','13:00:00',1,0),(358,'2026-08-04','13:00:00',2,0),(359,'2026-08-04','13:30:00',1,0),(360,'2026-08-04','13:30:00',2,0),(361,'2026-08-04','14:00:00',1,0),(362,'2026-08-04','14:00:00',2,0),(363,'2026-08-04','14:30:00',1,0),(364,'2026-08-04','14:30:00',2,0),(365,'2026-08-05','08:00:00',1,0),(366,'2026-08-05','08:00:00',2,0),(367,'2026-08-05','08:30:00',1,0),(368,'2026-08-05','08:30:00',2,0),(369,'2026-08-05','09:00:00',1,0),(370,'2026-08-05','09:00:00',2,0),(371,'2026-08-05','09:30:00',1,0),(372,'2026-08-05','09:30:00',2,0),(373,'2026-08-05','10:00:00',1,0),(374,'2026-08-05','10:00:00',2,0),(375,'2026-08-05','10:30:00',1,0),(376,'2026-08-05','10:30:00',2,0),(377,'2026-08-05','11:00:00',1,0),(378,'2026-08-05','11:00:00',2,0),(379,'2026-08-05','11:30:00',1,0),(380,'2026-08-05','11:30:00',2,0),(381,'2026-08-05','12:00:00',1,0),(382,'2026-08-05','12:00:00',2,0),(383,'2026-08-05','12:30:00',1,0),(384,'2026-08-05','12:30:00',2,0),(385,'2026-08-05','13:00:00',1,0),(386,'2026-08-05','13:00:00',2,0),(387,'2026-08-05','13:30:00',1,0),(388,'2026-08-05','13:30:00',2,0),(389,'2026-08-05','14:00:00',1,0),(390,'2026-08-05','14:00:00',2,0),(391,'2026-08-05','14:30:00',1,0),(392,'2026-08-05','14:30:00',2,0),(393,'2026-08-06','08:00:00',1,0),(394,'2026-08-06','08:00:00',2,0),(395,'2026-08-06','08:30:00',1,0),(396,'2026-08-06','08:30:00',2,0),(397,'2026-08-06','09:00:00',1,0),(398,'2026-08-06','09:00:00',2,0),(399,'2026-08-06','09:30:00',1,0),(400,'2026-08-06','09:30:00',2,0),(401,'2026-08-06','10:00:00',1,0),(402,'2026-08-06','10:00:00',2,0),(403,'2026-08-06','10:30:00',1,0),(404,'2026-08-06','10:30:00',2,0),(405,'2026-08-06','11:00:00',1,0),(406,'2026-08-06','11:00:00',2,0),(407,'2026-08-06','11:30:00',1,0),(408,'2026-08-06','11:30:00',2,0),(409,'2026-08-06','12:00:00',1,0),(410,'2026-08-06','12:00:00',2,0),(411,'2026-08-06','12:30:00',1,0),(412,'2026-08-06','12:30:00',2,0),(413,'2026-08-06','13:00:00',1,0),(414,'2026-08-06','13:00:00',2,0),(415,'2026-08-06','13:30:00',1,0),(416,'2026-08-06','13:30:00',2,0),(417,'2026-08-06','14:00:00',1,0),(418,'2026-08-06','14:00:00',2,0),(419,'2026-08-06','14:30:00',1,0),(420,'2026-08-06','14:30:00',2,0),(421,'2026-08-09','08:00:00',1,0),(422,'2026-08-09','08:00:00',2,0),(423,'2026-08-09','08:30:00',1,0),(424,'2026-08-09','08:30:00',2,0),(425,'2026-08-09','09:00:00',1,0),(426,'2026-08-09','09:00:00',2,0),(427,'2026-08-09','09:30:00',1,0),(428,'2026-08-09','09:30:00',2,0),(429,'2026-08-09','10:00:00',1,0),(430,'2026-08-09','10:00:00',2,0),(431,'2026-08-09','10:30:00',1,0),(432,'2026-08-09','10:30:00',2,0),(433,'2026-08-09','11:00:00',1,0),(434,'2026-08-09','11:00:00',2,0),(435,'2026-08-09','11:30:00',1,0),(436,'2026-08-09','11:30:00',2,0),(437,'2026-08-09','12:00:00',1,0),(438,'2026-08-09','12:00:00',2,0),(439,'2026-08-09','12:30:00',1,0),(440,'2026-08-09','12:30:00',2,0),(441,'2026-08-09','13:00:00',1,0),(442,'2026-08-09','13:00:00',2,0),(443,'2026-08-09','13:30:00',1,0),(444,'2026-08-09','13:30:00',2,0),(445,'2026-08-09','14:00:00',1,0),(446,'2026-08-09','14:00:00',2,0),(447,'2026-08-09','14:30:00',1,0),(448,'2026-08-09','14:30:00',2,0);
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
INSERT INTO `users` VALUES (1,'admin@loopsautomation.com','$2y$10$5ocZ9IkO8VIvLu34Me0nEOPZPhKDcz/EujsGwaW/hTKWB04GZBSfK');
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

-- Dump completed on 2026-07-26 10:01:05
