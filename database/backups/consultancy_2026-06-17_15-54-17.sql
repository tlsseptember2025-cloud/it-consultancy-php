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
-- Table structure for table `consultation_bookings`
--

DROP TABLE IF EXISTS `consultation_bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `consultation_bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `slot_id` int(11) NOT NULL,
  `booked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`),
  KEY `slot_id` (`slot_id`),
  CONSTRAINT `consultation_bookings_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`),
  CONSTRAINT `consultation_bookings_ibfk_2` FOREIGN KEY (`slot_id`) REFERENCES `consultation_slots` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consultation_bookings`
--

LOCK TABLES `consultation_bookings` WRITE;
/*!40000 ALTER TABLE `consultation_bookings` DISABLE KEYS */;
INSERT INTO `consultation_bookings` VALUES (6,14,7,'2026-06-14 11:46:11'),(7,16,8,'2026-06-14 13:57:49');
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
  `consultation_method` varchar(50) DEFAULT NULL,
  `meeting_link` varchar(255) DEFAULT NULL,
  `is_booked` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consultation_slots`
--

LOCK TABLES `consultation_slots` WRITE;
/*!40000 ALTER TABLE `consultation_slots` DISABLE KEYS */;
INSERT INTO `consultation_slots` VALUES (6,'2026-06-13','08:30:00','Zoom','https://us05web.zoom.us/j/87518436470?pwd=bLs95Xeh3RKl3fmphvywepV5x7Vbsb.1',0,'2026-06-10 11:19:05'),(7,'2026-06-13','21:45:00','Google Meet','https://meet.google.com/cfk-siet-kqb?hs=122&authuser=0',1,'2026-06-10 11:23:41'),(8,'2026-06-14','20:50:00','Google Meet','https://meet.google.com/cfk-siet-kqb?hs=122&authuser=0',1,'2026-06-10 11:46:02');
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contract_leads`
--

LOCK TABLES `contract_leads` WRITE;
/*!40000 ALTER TABLE `contract_leads` DISABLE KEYS */;
INSERT INTO `contract_leads` VALUES (1,'Loops Automation LLC','Rami','rami.wahdan@loopsautomation.com','0501228293',40,'Techical','Closed','2026-06-16 06:46:42'),(5,'loops','rami','rami.wahdan@loopsautomation.com','0501228293',45,'test!','New','2026-06-16 07:55:49'),(6,'loops','rami','test@gmail.com','0501228293',45,'test!','New','2026-06-16 08:03:23'),(7,'loops','rami','test@gmail.com','0501228293',45,'test!','New','2026-06-16 08:07:25'),(8,'loops','rami','test@gmail.com','0501228293',45,'test!','New','2026-06-16 08:07:40'),(9,'loops','rami','test@gmail.com','0501228293',45,'test!','New','2026-06-16 08:11:13');
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
INSERT INTO `customers` VALUES (4,'Fatima Habib','fatima.habib1980@gmail.com','0507626410','Loops LLC','New','2026-06-08 05:05:36','$2y$10$zlnT7TWHBaO9yMFS.5F6e.fR.WI.jOj1QHJAMcHaCRqqHPtdt4nFa',NULL,NULL),(5,'Ahmad Rami','ramiwahdan1978@gmail.com','0501228293','ABC','','2026-06-08 05:16:32','$2y$10$UYuE591jq/PjY0eDpDbMve5TIlfIByUDJel65WgHXz55tz0E76v3a',NULL,NULL);
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
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
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'unread',
  `service` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
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
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `uploaded_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `request_id` (`request_id`),
  CONSTRAINT `payment_slips_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `payment_slips_ibfk_2` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_slips`
--

LOCK TABLES `payment_slips` WRITE;
/*!40000 ALTER TABLE `payment_slips` DISABLE KEYS */;
INSERT INTO `payment_slips` VALUES (15,5,14,'1781437650_Screenshot 2026-04-20 161626.png','Approved','2026-06-14 15:47:30'),(16,4,16,'1781445533_Screenshot 2026-05-19 130549.png','Approved','2026-06-14 17:58:53');
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
  `status` enum('Unpaid','Partially Paid','Paid','Refund Pending','Refunded') DEFAULT 'Unpaid',
  `payment_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (14,14,100.00,'Paid','2026-06-14 15:47:40','Payment approved from deposit slip review','2026-06-14 11:47:40'),(15,16,250.00,'Paid','2026-06-14 17:59:01','Payment approved from deposit slip review','2026-06-14 13:59:01');
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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `refund_requests`
--

LOCK TABLES `refund_requests` WRITE;
/*!40000 ALTER TABLE `refund_requests` DISABLE KEYS */;
INSERT INTO `refund_requests` VALUES (10,16,'Cancellation','ok','Approved','2026-06-17 13:23:44');
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
  `status` enum('Processed','Completed') NOT NULL DEFAULT 'Processed',
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`),
  CONSTRAINT `refunds_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `refunds`
--

LOCK TABLES `refunds` WRITE;
/*!40000 ALTER TABLE `refunds` DISABLE KEYS */;
INSERT INTO `refunds` VALUES (19,16,250.00,'2026-06-17 17:23:54','Cancellation','2026-06-17 13:23:54','Completed');
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
  `status` enum('Pending','Approved','In Progress','Completed','Cancelled') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `workflow_stage` varchar(50) DEFAULT 'Submitted',
  `proposal` text DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `completion_notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `requests_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `requests`
--

LOCK TABLES `requests` WRITE;
/*!40000 ALTER TABLE `requests` DISABLE KEYS */;
INSERT INTO `requests` VALUES (14,5,10,100.00,'need help','Completed','2026-06-14 11:45:24','Service Completed','ok','2026-06-14 16:30:40',NULL),(16,4,10,250.00,'i need help with 5 printers','','2026-06-14 13:57:21','Service Scheduled','each printer will be 50AED',NULL,NULL);
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
INSERT INTO `service_bookings` VALUES (3,14,3,'2026-06-14 11:47:55'),(4,16,2,'2026-06-14 13:59:08');
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
  `is_booked` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_slots`
--

LOCK TABLES `service_slots` WRITE;
/*!40000 ALTER TABLE `service_slots` DISABLE KEYS */;
INSERT INTO `service_slots` VALUES (1,'2026-06-13','18:30:00',0),(2,'2026-06-20','19:45:00',1),(3,'2026-06-13','20:30:00',1);
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
INSERT INTO `services` VALUES (10,'Remote IT Support','1> PC troubleshooting\r\n2> Printer issues\r\n3> Outlook issues\r\n4> Windows problems\r\n5> Software errors\r\n6> Slow computers\r\n7> Others','2026-06-10 11:47:59',0.00,'1781441241_1780828344_support.jpg'),(11,'Software Installation & Setup','1> Microsoft Office\r\n2> Outlook\r\n3> Zoom\r\n4> Teams\r\n5> VPNs\r\n6> Adobe products\r\n7> QuickBooks\r\n8> Others','2026-06-14 12:52:04',0.00,'1781441524_1780828344_support.jpg'),(12,'Website Builder - Small Business','Website Builder for Small Businesses\r\n\r\n1> Build professional websites\r\n2> Mobile-responsive design\r\n3> Contact forms and lead capture\r\n4> Basic SEO optimization\r\n5> Blog and content setup\r\n6> Basic e-commerce stores\r\n7> Ongoing website maintenance','2026-06-14 12:53:42',0.00,'1781441622_1780828344_support.jpg');
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

-- Dump completed on 2026-06-17 17:54:17
