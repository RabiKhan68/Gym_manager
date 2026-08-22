-- MySQL dump 10.13  Distrib 8.0.40, for Win64 (x86_64)
--
-- Host: localhost    Database: fitness_management
-- ------------------------------------------------------
-- Server version	8.0.40

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance` (
  `attendance_id` int NOT NULL AUTO_INCREMENT,
  `member_id` int NOT NULL,
  `attendance_date` date NOT NULL,
  `attendance_time` time NOT NULL,
  PRIMARY KEY (`attendance_id`),
  UNIQUE KEY `unique_member_attendance` (`member_id`,`attendance_date`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance`
--

LOCK TABLES `attendance` WRITE;
/*!40000 ALTER TABLE `attendance` DISABLE KEYS */;
INSERT INTO `attendance` VALUES (1,1,'2026-08-20','18:05:00'),(2,2,'2026-08-20','18:20:00'),(3,1,'2026-08-19','18:10:00'),(4,7,'2026-08-20','23:19:10'),(5,8,'2026-08-21','16:35:40');
/*!40000 ALTER TABLE `attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance_qr_tokens`
--

DROP TABLE IF EXISTS `attendance_qr_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_qr_tokens` (
  `token_id` int NOT NULL AUTO_INCREMENT,
  `gym_id` int NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`token_id`),
  UNIQUE KEY `token` (`token`),
  KEY `gym_id` (`gym_id`),
  CONSTRAINT `attendance_qr_tokens_ibfk_1` FOREIGN KEY (`gym_id`) REFERENCES `gyms` (`gym_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_qr_tokens`
--

LOCK TABLES `attendance_qr_tokens` WRITE;
/*!40000 ALTER TABLE `attendance_qr_tokens` DISABLE KEYS */;
INSERT INTO `attendance_qr_tokens` VALUES (15,2,'d54c8499a2eb67ffc0fb07b248717fd36f2d27963d562f569a38c1590c590afb','2026-08-21 00:01:08','2026-08-20 22:00:08'),(20,3,'8fbca0ef2ea28e6874c4101e145778790e45dd217fe91ad6da53647c289236f2','2026-08-21 21:49:15','2026-08-21 19:48:15');
/*!40000 ALTER TABLE `attendance_qr_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gym_owners`
--

DROP TABLE IF EXISTS `gym_owners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gym_owners` (
  `owner_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`owner_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gym_owners`
--

LOCK TABLES `gym_owners` WRITE;
/*!40000 ALTER TABLE `gym_owners` DISABLE KEYS */;
INSERT INTO `gym_owners` VALUES (1,'Ahmed Khan','ahmed@example.com','test123','03001234567','2026-08-20 17:29:48'),(2,'Rabi Khan','rabi@example.com','$2y$10$5Q72f4Af3IOg.4c/gbJDu.TXxqR8.xY3jpeQ7bjZRSJWrGVUGko9m','12344567890','2026-08-20 18:37:36'),(3,'Rabi Khan','rabi098@example.com','$2y$10$DpK1xSzAf0HiIRp.L9FVku9oQ4ng2TgYR7mMMTnEN718Wy9Dj/sgu','03108707908','2026-08-21 09:18:42'),(4,'Rabi Khan','rabi12@example.com','$2y$10$VoRMIFKQYAUL1ybzOxHE/.aVJeDodvK/8Ocowyvv/mSh2mj/FtEMa','03108707908','2026-08-21 09:21:05');
/*!40000 ALTER TABLE `gym_owners` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gym_settings`
--

DROP TABLE IF EXISTS `gym_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gym_settings` (
  `setting_id` int NOT NULL AUTO_INCREMENT,
  `gym_id` int NOT NULL,
  `wifi_name` varchar(100) DEFAULT NULL,
  `wifi_password` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `unique_gym_settings` (`gym_id`),
  CONSTRAINT `fk_settings_gym` FOREIGN KEY (`gym_id`) REFERENCES `gyms` (`gym_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gym_settings`
--

LOCK TABLES `gym_settings` WRITE;
/*!40000 ALTER TABLE `gym_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `gym_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gyms`
--

DROP TABLE IF EXISTS `gyms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gyms` (
  `gym_id` int NOT NULL AUTO_INCREMENT,
  `owner_id` int NOT NULL,
  `gym_name` varchar(150) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`gym_id`),
  KEY `owner_id` (`owner_id`),
  CONSTRAINT `gyms_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `gym_owners` (`owner_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gyms`
--

LOCK TABLES `gyms` WRITE;
/*!40000 ALTER TABLE `gyms` DISABLE KEYS */;
INSERT INTO `gyms` VALUES (1,1,'Power Fitness','Karachi, Pakistan','03001234567','2026-08-20 17:30:09'),(2,2,'Prop Gym','Karachi, DHA','123456789','2026-08-20 19:12:23'),(3,4,'Fitness Must','DHA Phase 2 ext, Karachi','03108707908','2026-08-21 09:27:54');
/*!40000 ALTER TABLE `gyms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `member_memberships`
--

DROP TABLE IF EXISTS `member_memberships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_memberships` (
  `membership_id` int NOT NULL AUTO_INCREMENT,
  `member_id` int NOT NULL,
  `plan_id` int NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','expired','cancelled') DEFAULT 'active',
  PRIMARY KEY (`membership_id`),
  KEY `member_id` (`member_id`),
  KEY `plan_id` (`plan_id`),
  CONSTRAINT `member_memberships_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`),
  CONSTRAINT `member_memberships_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `membership_plans` (`plan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `member_memberships`
--

LOCK TABLES `member_memberships` WRITE;
/*!40000 ALTER TABLE `member_memberships` DISABLE KEYS */;
INSERT INTO `member_memberships` VALUES (1,1,1,'2026-08-01','2026-08-31','active'),(2,2,2,'2026-08-05','2026-09-04','active'),(3,3,3,'2026-08-10','2026-09-09','active'),(4,5,4,'2026-08-20','2026-09-19','active'),(5,6,5,'2026-08-20','2026-09-19','active'),(6,7,4,'2026-08-20','2026-09-19','active'),(7,4,4,'2026-08-21','2026-09-20','active'),(8,8,6,'2026-08-21','2026-09-20','active'),(9,9,6,'2026-08-21','2026-09-20','active'),(10,10,9,'2026-08-21','2026-10-20','active'),(11,11,8,'2026-08-21','2026-09-20','active');
/*!40000 ALTER TABLE `member_memberships` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `members`
--

DROP TABLE IF EXISTS `members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `members` (
  `member_id` int NOT NULL AUTO_INCREMENT,
  `gym_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `joining_date` date NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`member_id`),
  KEY `fk_members_gym` (`gym_id`),
  CONSTRAINT `fk_members_gym` FOREIGN KEY (`gym_id`) REFERENCES `gyms` (`gym_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `members`
--

LOCK TABLES `members` WRITE;
/*!40000 ALTER TABLE `members` DISABLE KEYS */;
INSERT INTO `members` VALUES (1,1,'Ali Raza','03001111111','ali@example.com','2026-08-01','active'),(2,1,'Hamza Ahmed','03002222222','hamza@example.com','2026-08-05','active'),(3,1,'Bilal Khan','03003333333','bilal@example.com','2026-08-10','active'),(4,2,'Sikander','1234567901','sikander@example.com','2026-12-12','active'),(5,2,'Rahul','923913139231','rahul@example.com','2026-11-21','active'),(6,2,'Abdul Kalim','13238211412','kalim@example.com','2026-08-23','active'),(7,2,'Rabi','0987654321','rabi12@example.com','2026-02-08','active'),(8,3,'Shakeel','03124706251','shakeel@example.com','2026-02-08','active'),(9,3,'Shahzaib','03141252342','AteebShazaib@example.com','2026-08-21','active'),(10,3,'Syed Murtaza Shakeel','03173281922','murtaza@example.com','2026-08-21','active'),(11,3,'Hashim Ali','03326654211','hashim1@example.com','2026-08-21','active');
/*!40000 ALTER TABLE `members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `membership_plans`
--

DROP TABLE IF EXISTS `membership_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `membership_plans` (
  `plan_id` int NOT NULL AUTO_INCREMENT,
  `gym_id` int NOT NULL,
  `plan_name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration_months` int NOT NULL,
  `description` text,
  PRIMARY KEY (`plan_id`),
  KEY `fk_plans_gym` (`gym_id`),
  CONSTRAINT `fk_plans_gym` FOREIGN KEY (`gym_id`) REFERENCES `gyms` (`gym_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `membership_plans`
--

LOCK TABLES `membership_plans` WRITE;
/*!40000 ALTER TABLE `membership_plans` DISABLE KEYS */;
INSERT INTO `membership_plans` VALUES (1,1,'Basic',3000.00,1,'Basic gym membership'),(2,1,'Premium',5000.00,1,'Premium gym membership'),(3,1,'VIP',8000.00,1,'VIP gym membership'),(4,2,'Basic',1000.00,1,'Basic deal discount'),(5,2,'Pro',2000.00,1,'Pro features'),(6,3,'Basic',2000.00,1,'New package with trainer too'),(7,3,'Pro',6000.00,1,'Pro package'),(8,3,'Premium',15000.00,1,'Premium trainer'),(9,3,'Platinum',40000.00,2,'Platinum package with diet guide');
/*!40000 ALTER TABLE `membership_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `payment_id` int NOT NULL AUTO_INCREMENT,
  `member_id` int NOT NULL,
  `membership_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_for_month` date NOT NULL,
  `payment_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `payment_method` enum('cash','online') NOT NULL,
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `transaction_reference` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`payment_id`),
  UNIQUE KEY `unique_member_payment_month` (`member_id`,`payment_for_month`),
  KEY `membership_id` (`membership_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`),
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`membership_id`) REFERENCES `member_memberships` (`membership_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,1,1,3000.00,'2026-08-01','2026-08-20 22:32:00','cash','paid',NULL),(2,2,2,5000.00,'2026-08-01','2026-08-20 22:32:00','online','paid',NULL),(3,5,4,1000.00,'2026-08-01','2026-08-21 01:24:16','cash','paid',NULL),(4,6,5,2000.00,'2026-08-01','2026-08-21 03:07:37','online','paid',NULL),(5,7,6,1000.00,'2026-08-01','2026-08-21 14:05:27','online','paid',NULL),(6,4,7,1000.00,'2026-08-01','2026-08-21 14:06:13','cash','paid',NULL),(7,8,8,2000.00,'2026-08-01','2026-08-21 14:30:29','cash','paid',NULL),(8,10,10,40000.00,'2026-08-01','2026-08-21 19:51:50','cash','paid',NULL),(9,9,9,2000.00,'2026-08-01','2026-08-22 00:46:57','online','paid',NULL),(10,11,11,15000.00,'2026-08-01','2026-08-22 00:48:48','cash','paid',NULL);
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `weight_records`
--

DROP TABLE IF EXISTS `weight_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `weight_records` (
  `weight_id` int NOT NULL AUTO_INCREMENT,
  `member_id` int NOT NULL,
  `weight` decimal(5,2) NOT NULL,
  `recorded_date` date NOT NULL,
  PRIMARY KEY (`weight_id`),
  KEY `member_id` (`member_id`),
  CONSTRAINT `weight_records_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `weight_records`
--

LOCK TABLES `weight_records` WRITE;
/*!40000 ALTER TABLE `weight_records` DISABLE KEYS */;
INSERT INTO `weight_records` VALUES (1,1,82.50,'2026-08-01'),(2,1,81.70,'2026-08-15'),(3,2,90.00,'2026-08-05');
/*!40000 ALTER TABLE `weight_records` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-22 19:07:27
