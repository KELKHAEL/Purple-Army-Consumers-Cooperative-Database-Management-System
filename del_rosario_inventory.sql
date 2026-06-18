-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 18, 2026 at 10:31 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `del_rosario_inventory`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `module` varchar(50) NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` varchar(100) DEFAULT NULL,
  `entity_name` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'SUCCESS',
  `actor_name` varchar(100) NOT NULL DEFAULT 'SYSTEM',
  `actor_role` varchar(50) NOT NULL DEFAULT 'SYSTEM'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`log_id`, `created_at`, `module`, `action`, `entity_type`, `entity_id`, `entity_name`, `details`, `status`, `actor_name`, `actor_role`) VALUES
(1, '2026-06-02 07:32:07', 'INVENTORY', 'DELETE PRODUCT', 'PRODUCT', '25', 'VANILLA', 'Removed product from master inventory. Category: Bar Soaps', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(2, '2026-06-02 07:36:26', 'INVENTORY', 'UPDATE PRODUCT', 'PRODUCT', '26', 'PAPAYA', 'Updated product details. Category: Bar Soaps, Unit: Pieces, Price: 80.00', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(3, '2026-06-02 07:38:44', 'INVENTORY', 'ADJUST STOCK', 'PRODUCT', '26', 'PAPAYA', 'Adjusted stock by 1 from 5 to 6', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(4, '2026-06-02 07:38:56', 'INVENTORY', 'ADJUST STOCK', 'PRODUCT', '26', 'PAPAYA', 'Adjusted stock by -1 from 6 to 5', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(5, '2026-06-02 07:39:17', 'INVENTORY', 'ADJUST STOCK', 'PRODUCT', '4', 'BUCO PANDAN', 'Adjusted stock by -9 from 10 to 1', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(6, '2026-06-03 02:38:50', 'SALES', 'ADD MEMBER SHARE', 'TRANSACTION', '17', 'LIM, AMRANA BERNARDITA DULPINA', 'Payment Type: Membership Fee, Amount: 1.00, Date: 2026-06-03', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(7, '2026-06-10 02:51:48', 'SALES', 'ADD MEMBER SHARE', 'TRANSACTION', '18', 'AMADA, DEXTER ESTOLE', 'Payment Type: Share Capital, Amount: 4,000.00, Date: 2026-06-10', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(8, '2026-06-10 02:52:09', 'SALES', 'ADD MEMBER SHARE', 'TRANSACTION', '19', 'AMADA, DEXTER ESTOLE', 'Payment Type: Membership Fee, Amount: 200.00, Date: 2026-06-10', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(9, '2026-06-13 02:07:49', 'MEMBERS', 'ADD MEMBER', 'MEMBER', '726', 'GARCIA, MIKHAEL', 'New member created with 1 beneficiary record(s).', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(10, '2026-06-13 02:09:56', 'ADMIN', 'IMPORT SHARES', 'TRANSACTIONS', NULL, 'Bulk Share / Fee Import', 'Imported 1 share/fee transaction row(s) from Excel.', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(11, '2026-06-13 03:58:24', 'ADMIN', 'IMPORT SHARES', 'TRANSACTIONS', NULL, 'Bulk Share / Fee Import', 'Imported 240 share/fee transaction row(s) from Excel.', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(12, '2026-06-13 04:00:14', 'ADMIN', 'IMPORT SHARES', 'TRANSACTIONS', NULL, 'Bulk Share / Fee Import', 'Imported 0 share/fee transaction row(s) from Excel.', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(13, '2026-06-13 04:06:16', 'ADMIN', 'IMPORT SHARES', 'TRANSACTIONS', NULL, 'Bulk Share / Fee Import', 'Imported 1 share/fee transaction row(s) from Excel.', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(14, '2026-06-13 04:08:46', 'ADMIN', 'IMPORT SHARES', 'TRANSACTIONS', NULL, 'Bulk Share / Fee Import', 'Imported 1 share/fee transaction row(s) from Excel.', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(15, '2026-06-13 04:09:50', 'ADMIN', 'IMPORT SHARES', 'TRANSACTIONS', NULL, 'Bulk Share / Fee Import', 'Imported 1 share/fee transaction row(s) from Excel.', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(16, '2026-06-13 05:39:28', 'ADMIN', 'IMPORT SHARES', 'TRANSACTIONS', NULL, 'Bulk Share / Fee Import', 'Processed 247 row(s); imported 2; unmatched 171; ambiguous 0; missing required 0; duplicates 74.', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(17, '2026-06-13 05:55:23', 'ADMIN', 'IMPORT TRANSACTIONS', 'TRANSACTIONS', NULL, 'Bulk Transaction Import', 'Inserted 6 transaction row(s) and updated 1 existing row(s) from Excel.', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(18, '2026-06-13 05:56:25', 'ADMIN', 'IMPORT SHARES', 'TRANSACTIONS', NULL, 'Bulk Share / Fee Import', 'Processed 247 row(s); imported 61; unmatched 171; ambiguous 0; missing required 0; duplicates 15.', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(19, '2026-06-13 07:54:34', 'SALES', 'ADD MEMBER SHARE', 'TRANSACTION', '333', 'GARCIA, MIKHAEL VILLAFUERTE', 'Payment Type: Membership Fee, Amount: 5,000.00, Date: 2026-06-13', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(20, '2026-06-13 08:35:06', 'INVENTORY', 'ADJUST STOCK', 'PRODUCT', '18', 'CANOLA OIL', 'Adjusted stock by 10 from 4 to 14', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(21, '2026-06-13 08:36:45', 'SALES', 'OUTSOURCE CHECKOUT', 'TRANSACTION', '334', 'Indang', 'Payment Method: Others, Items: 1, Total: 320.00, Status: PENDING', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(22, '2026-06-13 08:37:23', 'INVENTORY', 'RECONCILE OUTSOURCE', 'OUTSOURCING RECORD', '12', 'Indang', 'Product ID 27, Sold: 2, Returned: 2, Method: Others', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(23, '2026-06-13 08:38:26', 'SALES', 'SALE CHECKOUT', 'TRANSACTION', '335', 'GARCIA, MIKHAEL', 'Payment Method: Pay Later, Items: 1, Total: 160.00, Status: PENDING', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(24, '2026-06-13 08:39:32', 'SALES', 'RECORD PAY LATER DOWNPAYMENT', 'TRANSACTION', '335', 'GARCIA, MIKHAEL', 'Payment received: 60.00, Remaining balance: 100.00', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(25, '2026-06-13 08:40:17', 'SALES', 'SALE CHECKOUT', 'TRANSACTION', '336', 'GARCIA, MIKHAEL', 'Payment Method: Pay Later, Items: 1, Total: 2,600.00, Status: PENDING', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(26, '2026-06-13 08:40:38', 'SALES', 'RECORD PAY LATER DOWNPAYMENT', 'TRANSACTION', '336', 'GARCIA, MIKHAEL', 'Payment received: 1,600.00, Remaining balance: 1,000.00', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(27, '2026-06-15 06:53:23', 'INVENTORY', 'ADD PRODUCT', 'PRODUCT', '29', 'Datu puti', 'Added new product. Category: Condiments, Unit: Bottle, Starting Qty: 3, Price: 53.00', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(28, '2026-06-15 06:54:01', 'INVENTORY', 'ADD PRODUCT', 'PRODUCT', '30', 'Canola oil', 'Added new product. Category: Condiments, Unit: Bottle, Starting Qty: 2, Price: 132.00', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(29, '2026-06-15 06:54:51', 'INVENTORY', 'ADD PRODUCT', 'PRODUCT', '31', 'Ligo sardines', 'Added new product. Category: Groceries, Unit: Can, Starting Qty: 18, Price: 25.00', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(30, '2026-06-15 06:55:48', 'INVENTORY', 'ADD PRODUCT', 'PRODUCT', '32', 'Sugar', 'Added new product. Category: Condiments, Unit: Kilo, Starting Qty: 10, Price: 80.00', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(31, '2026-06-15 06:57:18', 'INVENTORY', 'ADD PRODUCT', 'PRODUCT', '33', 'Vanilla', 'Added new product. Category: Bar Soaps, Unit: Pieces, Starting Qty: 5, Price: 80.00', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(32, '2026-06-15 06:58:13', 'INVENTORY', 'ADD PRODUCT', 'PRODUCT', '34', 'Papaya', 'Added new product. Category: Bar Soaps, Unit: Pieces, Starting Qty: 5, Price: 80.00', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(33, '2026-06-15 06:58:33', 'INVENTORY', 'ADD PRODUCT', 'PRODUCT', '35', 'Charcoal', 'Added new product. Category: Bar Soaps, Unit: Pieces, Starting Qty: 5, Price: 80.00', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(34, '2026-06-15 06:59:00', 'INVENTORY', 'ADD PRODUCT', 'PRODUCT', '36', 'Tumbler', 'Added new product. Category: Others, Unit: Pieces, Starting Qty: 4, Price: 390.00', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(35, '2026-06-18 03:23:59', 'ADMIN', 'IMPORT TRANSACTIONS', 'TRANSACTIONS', NULL, 'Bulk Transaction Import', 'Inserted 53 transaction row(s) and updated 40 existing row(s) from Excel.', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(36, '2026-06-18 04:02:22', 'ADMIN', 'IMPORT TRANSACTIONS', 'TRANSACTIONS', NULL, 'Bulk Transaction Import', 'Inserted 45 transaction row(s), overwritten 48 row(s), and skipped 0 unreadable row(s) from Excel.', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(37, '2026-06-18 07:00:49', 'SETTINGS', 'DELETE TRANSACTION TYPE', 'CONFIG', '6', 'Purchase', 'Deactivated a transaction type.', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(38, '2026-06-18 07:01:04', 'SETTINGS', 'ADD TRANSACTION TYPE', 'CONFIG', '6', 'Purchase', 'Added or reactivated a transaction type.', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(39, '2026-06-18 07:01:13', 'SETTINGS', 'DELETE TRANSACTION TYPE', 'CONFIG', '5', 'Miscellaneous', 'Deactivated a transaction type.', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(40, '2026-06-18 07:01:18', 'SETTINGS', 'DELETE TRANSACTION TYPE', 'CONFIG', '3', 'Share Capital', 'Deactivated a transaction type.', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(41, '2026-06-18 07:01:29', 'SETTINGS', 'DELETE TRANSACTION TYPE', 'CONFIG', '4', 'Membership Fee', 'Deactivated a transaction type.', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(42, '2026-06-18 07:24:21', 'SALES', 'SALE CHECKOUT', 'TRANSACTION', '435', 'AMADA, DEXTER', 'Payment Method: Cash, Items: 1, Total: 80.00, Status: COMPLETED\nReverted on June 18, 2026 03:33 PM from activity log #42.', 'REVERTED', 'SYSTEM', 'SYSTEM'),
(43, '2026-06-18 07:33:49', 'SALES', 'REVERT ACTION', 'TRANSACTION', '435', 'AMADA, DEXTER', 'Reverted activity log #42 (SALE CHECKOUT).', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(44, '2026-06-18 07:34:49', 'SALES', 'SALE CHECKOUT', 'TRANSACTION', '436', 'AMADA, DEXTER', 'Payment Method: Cash, Items: 1, Total: 80.00, Status: COMPLETED\nReverted on June 18, 2026 03:35 PM from activity log #44.', 'REVERTED', 'SYSTEM', 'SYSTEM'),
(45, '2026-06-18 07:35:14', 'SALES', 'REVERT ACTION', 'TRANSACTION', '436', 'AMADA, DEXTER', 'Reverted activity log #44 (SALE CHECKOUT).', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(46, '2026-06-18 07:36:17', 'SETTINGS', 'ADD UNIT', 'CONFIG', '8', 'Pair', 'Added new unit type setting.\nReverted on June 18, 2026 03:36 PM from activity log #46.', 'REVERTED', 'SYSTEM', 'SYSTEM'),
(47, '2026-06-18 07:36:28', 'SETTINGS', 'REVERT ACTION', 'CONFIG', '8', 'Pair', 'Reverted activity log #46 (ADD UNIT).', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(48, '2026-06-18 07:52:47', 'SETTINGS', 'ADD UNIT', 'CONFIG', '9', 'Bag', 'Added new unit type setting.\nReverted on June 18, 2026 03:53 PM from activity log #48.', 'REVERTED', 'SYSTEM', 'SYSTEM'),
(49, '2026-06-18 07:53:38', 'SETTINGS', 'REVERT ACTION', 'CONFIG', '9', 'Bag', 'Reverted activity log #48 (ADD UNIT).', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(50, '2026-06-18 07:53:47', 'SETTINGS', 'ADD UNIT', 'CONFIG', '10', 'Bag', 'Added new unit type setting.', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(51, '2026-06-18 07:54:01', 'SALES', 'SALE CHECKOUT', 'TRANSACTION', '437', 'AMADA, DEXTER', 'Payment Method: Cash, Items: 1, Total: 80.00, Status: COMPLETED\nReverted on June 18, 2026 03:54 PM from activity log #51.', 'REVERTED', 'SYSTEM', 'SYSTEM'),
(52, '2026-06-18 07:54:15', 'SALES', 'REVERT ACTION', 'TRANSACTION', '437', 'AMADA, DEXTER', 'Reverted activity log #51 (SALE CHECKOUT).', 'SUCCESS', 'SYSTEM', 'SYSTEM'),
(53, '2026-06-18 07:55:05', 'INVENTORY', 'ADJUST STOCK', 'PRODUCT', '33', 'Vanilla', 'JSON:{\"table\":\"inventory\",\"operation\":\"adjust\",\"before\":{\"current_quantity\":3},\"after\":{\"current_quantity\":5},\"delta\":2}', 'SUCCESS', 'SYSTEM', 'SYSTEM');

-- --------------------------------------------------------

--
-- Table structure for table `beneficiaries`
--

CREATE TABLE `beneficiaries` (
  `beneficiary_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `relationship` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `beneficiaries`
--

INSERT INTO `beneficiaries` (`beneficiary_id`, `member_id`, `first_name`, `middle_name`, `last_name`, `date_of_birth`, `relationship`) VALUES
(1199, 647, 'NOEL', 'P.', 'BATURIANO', '1974-03-04', 'HUSBAND'),
(1200, 647, 'MARK, CYRONE', '', 'BATURIANO', '2007-06-01', 'CHILD'),
(1201, 647, 'MARCO NOEL', '', 'BATURIANO', '2012-03-12', 'CHILD'),
(1202, 648, 'IAN CRISLER', '', 'ANGKICO', '2004-04-18', 'CHILD'),
(1203, 649, 'TOCHIE', 'E.', 'ARMIJO', '1964-11-12', 'FATHER'),
(1204, 649, 'ELLEN', 'E.', 'ARMIJO', '1966-12-01', 'MOTHER'),
(1205, 650, 'JEMMALYN', '', 'ARAñAS', '1986-01-01', 'CHILD'),
(1206, 650, 'JERLYN', '', 'ARAñAS', '1990-05-21', 'CHILD'),
(1207, 651, 'AEZEN, ARADO', '', 'ARIGO', '2005-07-10', 'CHILD'),
(1208, 651, 'ARIAN, ARADO', '', 'ARIGO', '2008-07-29', 'CHILD'),
(1209, 652, 'ANIA, AMADA', '', 'ESTOLE', '1958-12-30', 'MOTHER'),
(1210, 653, 'ANGELINE', 'F.', 'ARIGO', '1978-10-27', 'SISTER'),
(1211, 654, 'REYNANTE, PANGALINAN', '', 'BUENAFLOR', '1978-07-06', 'HUSBAND'),
(1212, 654, 'AERIZ ANGEL MAE,', 'ARIGO', 'BUENAFLOR', '2009-04-17', 'CHILD'),
(1213, 654, 'AIZA REYN,', 'ARIGO', 'BUENAFLOR', '2012-09-05', 'CHILD'),
(1214, 655, 'MANUELITO', 'M.', 'BARRIENTOS', '1955-03-19', 'HUSBAND'),
(1215, 656, 'JOHA ANTHONY,', 'ARAñAS', 'BOBADILLA', '1991-09-28', 'CHILD'),
(1216, 656, 'JOHANN NICOLAS,', 'ARAñAS', 'BOBADILLA', '1998-08-21', 'CHILD'),
(1217, 656, 'JAN HOWARD,', 'PRUDENTE', 'BOBADILLA', '2001-05-15', 'CHILD'),
(1218, 656, 'PRINCE CEDRICK,', 'PRUDENTE', 'BOBADILLA', '2002-07-06', 'CHILD'),
(1219, 656, 'PRINCESS CRYSTAL,', 'PRUDENTE', 'BOBADILLA', '2004-08-11', 'CHILD'),
(1220, 657, 'VERGENIO', 'JR.', 'CLAVEL', '1972-10-27', 'HUSBAND'),
(1221, 657, 'QUEENIE JANE', 'A.', 'CLAVEL', '2002-12-14', 'CHILD'),
(1222, 657, 'MARK JEANVEL', 'A.', 'CLAVEL', '2007-09-18', 'CHILD'),
(1223, 658, 'RAFAEL', '', 'LIM', '1982-10-18', 'CHILD'),
(1224, 659, 'SEAN ZAMIEL,', 'ERCILLA', 'MALASAN', '2011-11-16', 'CHILD'),
(1225, 655, 'JUSTINE, DALE', '', 'MERCADO', '2004-03-01', 'CHILD'),
(1226, 655, 'JASMYNE CLAIRE', '', 'MERCADO', '2008-10-10', 'CHILD'),
(1227, 660, 'JAMES BRYAN', 'E.', 'MENDOZA', '1992-03-06', 'HUSBAND'),
(1228, 660, 'LIAM CAIDEN', 'S.', 'MENDOZA', '2015-07-31', 'CHILD'),
(1229, 660, 'DEAN JAMESON', 'S.', 'MENDOZA', '2018-01-14', 'CHILD'),
(1230, 661, 'ABELARDO', 'V.', 'MENDOZA', '1968-06-12', 'HUSBAND'),
(1231, 661, 'JAMES BRYAN', 'E.', 'MENDOZA', '1992-05-06', 'CHILD'),
(1232, 661, 'ARIANE JOY', 'E.', 'MENDOZA', '1994-07-30', 'CHILD'),
(1233, 662, 'JANE ANN', 'L.', 'MERCADO', '1984-03-25', 'CHILD'),
(1234, 662, 'CHARISSIET', 'O.', 'MERCADO', '2026-05-21', 'CHILD'),
(1235, 662, 'CHARLES', 'O.', 'MERCADO', '2026-12-16', 'CHILD'),
(1236, 662, 'KCEL', 'L.', 'MERCADO', '2026-11-02', 'CHILD'),
(1237, 663, 'JOSEPH CHRISTIAN', '', 'LAURENTE', '1987-11-06', 'CHILD'),
(1238, 663, 'JANICE MAGLIAN', '', 'LAURENTE', '1991-01-16', 'CHILD'),
(1239, 663, 'SUSSANA', '', 'SAGUBAN', '1990-11-02', 'IN-LAW'),
(1240, 664, 'JHADE ANNE CHARISSE,', 'LANDICHO', 'MORALINA', '2012-02-29', 'CHILD'),
(1241, 664, 'AICHELLE JOYCE,', 'LANDICHO', 'MORALINA', '2010-02-15', 'CHILD'),
(1242, 664, 'PRINCESS JHAY', 'ROSE', 'MORALINA', '2014-09-14', 'CHILD'),
(1243, 665, 'KATHRINE MAE', '', 'DUMO', '2002-08-29', 'SISTER'),
(1244, 665, 'ANGEL FRANCINE ,', 'DUMO', 'BARRO', '2008-09-11', 'COUSIN'),
(1245, 665, 'ANGELA, DIVINO', '', 'DUMO', '1978-10-12', 'AUNT'),
(1246, 665, 'ELAINE JOY,', 'DUMO', 'RIVAS', '1993-03-31', 'SISTER'),
(1247, 666, 'DOYENN, GITO', '', 'NUESTRO', '1980-07-12', 'WIFE'),
(1248, 666, 'EURICA, GITO', '', 'NUESTRO', '2006-08-22', 'CHILD'),
(1249, 666, 'ASIA, GITO', '', 'NUESTRO', '2009-09-29', 'CHILD'),
(1250, 666, 'VENIZ, GITO', '', 'NUESTRO', '2018-12-20', 'CHILD'),
(1251, 667, 'ROGELLO', 'M.', 'OLALIA', '1969-08-10', 'HUSBAND'),
(1252, 667, 'JAMES', 'C.', 'OLALIA', '2003-02-02', 'CHILD'),
(1253, 667, 'VANESSA JOY', 'C.', 'OLALIA', '1996-04-26', 'CHILD'),
(1254, 667, 'RONEL', 'C.', 'OLALIA', '1994-04-28', 'CHILD'),
(1255, 668, 'MENOR BREGIDE', 'L.', 'OMAY', '1987-03-12', 'HUSBAND'),
(1256, 668, 'XYRISEE JHEDRY', 'S.', 'OMAY', '2017-07-01', 'CHILD'),
(1257, 668, 'XAVION JHED', '', 'OMAY', '2020-09-14', 'CHILD'),
(1258, 669, 'ALEX', 'B.', 'VILLANUEVA', '1977-01-03', 'HUSBAND'),
(1259, 669, 'ALLEN', 'B.', 'VILLANUEVA', '2001-10-15', 'CHILD'),
(1260, 669, 'JOHN AIRON', 'B.', 'VILLANUEVA', '2003-02-01', 'CHILD'),
(1261, 669, 'ALEXIS JOY', 'B.', 'VILLANUEVA', '2011-09-04', 'CHILD'),
(1262, 670, 'CYRUS VIEN', 'GONZALES', 'GENPACIO', '2013-07-13', 'CHILD'),
(1263, 671, 'JHON REY', '', 'GUARIN', '1998-08-30', 'CHILD'),
(1264, 671, 'REYVEN GUARIN', '', 'PEPITO', '2008-06-04', 'CHILD'),
(1265, 671, 'RONMATHEW GUARIN', '', 'PEPITO', '2011-01-23', 'CHILD'),
(1266, 671, 'JANAIRAH BANTOTO', '', 'LUTERIA', '1997-01-13', 'CHILD'),
(1267, 672, 'ANTHONY', '', 'GALAROSA', '1983-12-27', 'SPOUSE'),
(1268, 672, 'ANN BERNICE', '', 'GALAROSA', '2015-11-08', 'CHILD'),
(1269, 673, 'ELMA', 'G.', 'GIMAO', '1988-02-26', 'CHILD'),
(1270, 673, 'CEDRIC', 'G.', 'GIMAO', '1998-10-24', 'CHILD'),
(1271, 673, 'MARK ALVIN', 'G.', 'GIMAO', '1989-07-22', 'CHILD'),
(1272, 673, 'JERICK', 'G.', 'GIMAO', '2003-02-28', 'CHILD'),
(1273, 674, 'FERDINAND', 'M.', 'GARCIA', '1976-02-08', 'HUSBAND'),
(1274, 674, 'FRANCIS JHUDIEL', 'F.', 'GARCIA', '2004-11-15', 'CHILD'),
(1275, 674, 'FLORIAN JOHN', 'F.', 'GARCIA', '2011-04-06', 'CHILD'),
(1276, 675, 'ALLYZA LLYNETTE', '', 'HORARIO', '2004-02-10', 'CHILD'),
(1277, 675, 'KURT RUSSELLEA', '', 'HORARIO', '2007-12-04', 'CHILD'),
(1278, 676, 'ISIDRO', 'J.', 'JARQUIO', '1964-05-22', 'HUSBAND'),
(1279, 676, 'JESSAMAE', 'N.', 'MENDOZA', '2004-08-24', 'CHILD'),
(1280, 676, 'RONA ANGEL', 'N.', 'OGALINOLA', '2007-08-04', 'CHILD'),
(1281, 677, 'ANGELA BONIFACIO', '', 'LOURDEE', '1997-02-11', 'CHILD'),
(1282, 677, 'LOUISE ANGELYN', '', 'LITA', '2007-04-23', 'CHILD'),
(1283, 677, 'LUIS ALFONSO', '', 'LITA', '1999-02-12', 'CHILD'),
(1284, 677, 'LORENZ ANGELO', '', 'LITA', '1995-09-18', 'CHILD'),
(1285, 678, 'RODNEY', '', 'LLAMASARES', '1971-05-22', 'HUSBAND'),
(1286, 678, 'MAUREEN JOY', '', 'LLAMASARES', '1997-07-13', 'CHILD'),
(1287, 678, 'DEBIE BEATRIZ', '', 'LLAMASARES', '2007-05-27', 'CHILD'),
(1288, 678, 'MARY GRACE', '', 'LLAMASARES', '1995-11-13', 'CHILD'),
(1289, 679, 'DENNIS LEDESMA', '', 'LUSABIA', '1975-02-23', 'HUSBAND'),
(1290, 679, 'MARCUS KENZO', 'C.', 'LUSABIA', '2011-11-05', 'CHILD'),
(1291, 679, 'DENISE GABRIELLE', 'C.', 'LUSABIA', '2016-04-09', 'CHILD'),
(1292, 679, 'CALISTA DANIELLE', 'C.', 'LUSABIA', '2018-08-03', 'CHILD'),
(1293, 680, 'ERIC', 'A.', 'LADISLA', '1972-05-13', 'HUSBAND'),
(1294, 680, 'ROY JOHN', 'T.', 'BARREDO', '2000-01-21', 'CHILD'),
(1295, 680, 'ANDRE KIM ALFONSO', 'T.', 'CORTES', '2005-08-26', 'CHILD'),
(1296, 680, 'EDUARDO LUIS', 'T.', 'CORTES', '2007-08-09', 'CHILD'),
(1297, 681, 'JOHN BENEDICT', 'O.', 'PRIJOLES', '2006-08-22', 'CHILD'),
(1298, 681, 'BRENAN JAMES', 'O.', 'PRIJOLES', '2010-04-08', 'CHILD'),
(1299, 681, 'BIANCA JHOI', 'O.', 'PRIJOLES', '2016-01-25', 'CHILD'),
(1300, 681, 'BRIANA JANE', 'O.', 'PRIJOLES', '2016-01-25', 'CHILD'),
(1301, 682, 'JOHNNA MAE', '', 'NAVALES', '2017-08-05', 'CHILD'),
(1302, 682, 'RODEL', '', 'NAVALES', '1985-08-20', 'PARTNER'),
(1303, 682, 'JOHN WENDEL', '', 'NAVALES', '2019-04-25', 'CHILD'),
(1304, 682, 'CHRISTINE MAE', '', 'NAVALES', '2021-11-13', 'CHILD'),
(1305, 683, 'CHARLES JONNEL', 'S.', 'PEREZ', '2003-11-27', 'CHILD'),
(1306, 683, 'JOHN CEDRICK', 'S.', 'PEREZ', '2007-06-08', 'CHILD'),
(1307, 683, 'CYRA JASLYN', '', 'S.', '2014-03-01', 'CHILD'),
(1308, 684, 'VICTORINO', '', 'PAPIO', '1962-03-06', 'HUSBAND'),
(1309, 684, 'KIMHARD', '', 'PAPIO', '1994-12-24', 'CHILD'),
(1310, 684, 'KHEYSIE', '', 'PAPIO', '1996-08-29', 'CHILD'),
(1311, 685, 'MARY JEANE', 'T.', 'PADUA', '1973-09-28', 'MOTHER'),
(1312, 685, 'KATE JEZRELL', 'T.', 'PADUA', '2003-07-06', 'SISTER'),
(1313, 685, 'MARLON', 'E.', 'PADUA', '1974-08-31', 'FATHER'),
(1314, 686, 'DAVE JHON', 'POLLAROSTE', 'QUINTANA', '1999-07-04', 'CHILD'),
(1315, 687, 'ROSARIO', 'R.', 'QUISTO', '1962-09-19', 'WIFE'),
(1316, 687, 'JESTER MICHAEL', '', 'QUISTO', '1991-09-12', 'CHILD'),
(1317, 687, 'BRIGETTE RIZA', '', 'QUISTO', '1992-10-11', 'CHILD'),
(1318, 687, 'HONEY ROSE', '', 'QUISTO', '1996-02-27', 'CHILD'),
(1319, 688, 'SHARON MAE', '', 'ROJO', '1982-01-30', 'WIFE'),
(1320, 688, 'DANYL SEBASTIAN', '', 'ROJO', '2007-02-01', 'CHILD'),
(1321, 688, 'DWAYNE SIMON', '', 'ROJO', '2017-01-21', 'CHILD'),
(1322, 689, 'DANY CENA', '', 'RONQUILLO', '1970-03-30', 'HUSBAND'),
(1323, 689, 'DANNIE MAY', 'ANDAL', 'RONQUILLO', '1993-04-04', 'CHILD'),
(1324, 689, 'DANNA ROSE', 'ANDAL', 'RONQUILLO', '1999-01-22', 'CHILD'),
(1325, 689, 'DIANE FAYE', 'ANDAL', 'RONQUILLO', '2006-10-08', 'CHILD'),
(1326, 690, 'SHIELA STO.', 'DOMINGO', 'ROBLES', '1977-07-15', 'WIFE'),
(1327, 690, 'SHEINELLE STO.', 'DOMINGO', 'ROBLES', '2010-08-12', 'CHILD'),
(1328, 691, 'VIOLETA BERMUNDO', '', 'ROMBAOA', '1946-07-07', 'CHILD'),
(1329, 693, 'LUISITO', 'A.', 'SERRANO', '1963-06-21', 'HUSBAND'),
(1330, 693, 'YVI RICA', 'L.', 'SERRANO', '2002-04-20', 'CHILD'),
(1331, 693, 'FREDERICK', 'L.', 'SERRANO', '2000-11-09', 'CHILD'),
(1332, 694, 'CHLOE ARABELLA', 'MARQUEZ', 'SOLIMAN', '2011-02-22', 'CHILD'),
(1333, 694, 'MARIS ISABELLA', 'MARQUEZ', 'SOLIMAN', '2013-09-18', 'CHILD'),
(1334, 694, 'ZURIEL ONYX', 'MARQUEZ', 'SOLIMAN', '2024-09-28', 'CHILD'),
(1335, 695, 'VIOLETA EUSEBIO', '', 'SARDILLA', '1962-01-26', 'MOTHER'),
(1336, 696, 'SAMUEL', '', 'SOLAYAO', '1998-08-09', 'CHILD'),
(1337, 696, 'SARAH', '', 'SOLAYAO', '2024-10-02', 'CHILD'),
(1338, 697, 'ARNEL', 'B.', 'SOLIMAN', '1965-09-21', 'FATHER'),
(1339, 697, 'GINA', 'B.', 'SOLIMAN', '1964-10-28', 'MOTHER'),
(1340, 698, 'ARNEL', 'B.', 'SOLIMAN', '1965-09-21', 'FATHER'),
(1341, 698, 'GINA', 'B.', 'SOLIMAN', '1964-10-28', 'MOTHER'),
(1342, 699, 'GAREM', 'R.', 'SALVADOR', '1994-05-13', 'CHILD'),
(1343, 699, 'BABY LUTH', 'R', 'SALVADOR', '1991-12-26', 'CHILD'),
(1344, 699, 'GAREN', 'R.', 'SALVADOR', '1999-01-19', 'CHILD'),
(1345, 700, 'CHRISTINE JOY', 'C.', 'SALVADOR', '2003-08-03', 'CHILD'),
(1346, 700, 'EMMANUEL TOLEDO', '', 'SALVADOR', '1976-05-25', 'HUSBAND'),
(1347, 701, 'EMERITO', 'B.', 'TECSON', '1978-09-12', 'HUSBAND'),
(1348, 701, 'DESIREE KATE', 'H.', 'TECSON', '2001-05-22', 'CHILD'),
(1349, 701, 'ZEUS EZEKIEL', 'H.', 'TECSON', '2009-07-15', 'CHILD'),
(1350, 702, 'JOHN CRIS', 'M.', 'VILLALON', '2005-05-31', 'CHILD'),
(1351, 702, 'MIGUEL ANGHEL', 'M.', 'VILLALON', '2011-02-10', 'CHILD'),
(1352, 702, 'SHANHAEL YARA', 'M.', 'VILLALON', '2004-07-23', 'CHILD'),
(1353, 703, 'MICHAEL BRYAN', '', 'VALIENTE', '1995-01-01', 'CHILD'),
(1354, 703, 'MICHAEL JONH', '', 'VALIENTE', '1996-01-11', 'CHILD'),
(1355, 703, 'MICHAEL JAMES', '', 'VALIENTE', '1996-01-11', 'CHILD'),
(1356, 703, 'JULIO', '', 'GARCIA', NULL, 'HUSBAND'),
(1357, 704, 'DENNIS', '', 'VASQUEZ', '1973-01-28', 'SPOUSE'),
(1358, 705, 'CHEYSERR', 'A.', 'SANGO', '1990-08-13', 'CHILD'),
(1359, 706, 'RIENVENIDO', '', 'MERCADO', '1957-07-08', 'HUSBAND'),
(1360, 706, 'JENETH', 'E.', 'MERCADO', '1983-06-29', 'CHILD'),
(1361, 706, 'CHARITO', 'E.', 'MERCADO', '1979-11-08', 'CHILD'),
(1362, 706, 'KEVIN', 'E.', 'MERCADO', '1990-01-07', 'CHILD'),
(1363, 707, 'REY', 'S.', 'CRUZAT', NULL, 'HUSBAND'),
(1367, 710, 'FERDINAND', 'S.', 'CRUZ', '1982-03-07', 'HUSBAND'),
(1368, 710, 'XYRUS', 'D.', 'CRUZ', '2004-06-08', 'CHILD'),
(1369, 710, 'XIAN', 'D.', 'CRUZ', '2018-07-16', 'CHILD'),
(1373, 712, 'RAFAELZON', '', 'APOLINARIO', '2001-08-13', 'CHILD'),
(1374, 712, 'MARIZON', '', 'APOLINARIO', '2001-08-13', 'CHILD'),
(1375, 712, 'MARK GERZON', '', 'APOLINARIO', '2003-01-01', 'CHILD'),
(1376, 713, 'RHONIE', 'P.', 'COSTA', '1979-03-21', 'HUSBAND'),
(1377, 713, 'RAMON PAOLO', 'H.', 'COSTA', '2002-01-23', 'CHILD'),
(1378, 713, 'PAULINE SOPHIA', 'H.', 'COSTA', '2004-11-11', 'CHILD'),
(1379, 713, 'RONNEL MIGUEL', 'H.', 'COSTA', '2010-10-02', 'CHILD'),
(1380, 714, 'MANUEL', 'B.', 'DORMIDO', '2007-09-09', 'CHILD'),
(1381, 714, 'MARIANE', 'B.', 'MORALES', '2013-01-14', 'CHILD'),
(1382, 715, 'RICKY', 'L.', 'MALNEGRO', '1977-10-21', 'HUSBAND'),
(1383, 715, 'RAEANNE JADE', 'D.', 'MALNEGRO', '2005-12-10', 'CHILD'),
(1384, 715, 'JOHN RENZO', 'D.', 'MALNEGRO', '2009-11-03', 'CHILD'),
(1385, 715, 'RICKY JR.', 'D.', 'MALNEGRO', '2011-06-01', 'CHILD'),
(1386, 716, 'MARK LESTER', '', 'DIAZ', '2010-08-11', 'CHILD'),
(1387, 716, 'JOHN EDUARD', '', 'DIAZ', '2012-11-15', 'CHILD'),
(1388, 716, 'PRINCE CARL', '', 'DIAZ', '2015-09-27', 'CHILD'),
(1389, 717, 'JANETH', 'A.', 'PACULANANG', '1983-08-30', 'LIVE IN PARTNER'),
(1390, 717, 'SOFIA', 'P.', 'DELA CUEVA', '2013-08-24', 'CHILD'),
(1391, 717, 'ZEIHLOAN', 'P.', 'DELA CUEVA', '2014-12-29', 'CHILD'),
(1392, 717, 'ATHEENA', 'P.', 'DELA CUEVA', '2017-10-09', 'CHILD'),
(1393, 718, 'REYNANTE', '', 'DALANGIN', '1980-01-07', 'HUSBAND'),
(1394, 718, 'QUINN MARIE', '', 'DALANGIN', '2019-08-24', 'CHILD'),
(1395, 718, 'EDMUNDO', '', 'SALVADOR', '1959-10-15', 'CHILD'),
(1396, 718, 'MARY JANE', '', 'SALVADOR', '1996-10-27', 'CHILD'),
(1397, 719, 'ROLDAN', 'T.', 'CRESCENCIO', '1981-09-08', 'LIVE IN PARTNER'),
(1398, 719, 'JULIANA', 'A.', 'DELA PEñA', '1966-01-09', 'MOTHER'),
(1399, 719, 'JOHN ZEUS', 'D.', 'QUINDARA', '2004-07-21', 'NEPHEW'),
(1400, 720, 'JOEL', 'A.', 'DILIDILI', '1972-03-03', 'HUSBAND'),
(1401, 720, 'MA. JOMELLE', '', 'DILIDILI', '2003-05-12', 'CHILD'),
(1402, 721, 'JOHN LAURENZ', '', 'DALAY', NULL, 'CHILD'),
(1403, 722, 'MARVIN', '', 'DE GUZMAN', NULL, 'HUSBAND'),
(1404, 723, 'REBECCA', 'C.', 'FERRER', '1966-11-11', 'WIFE'),
(1405, 723, 'JAKE', '', 'FERRER', '2014-10-09', 'CHILD'),
(1406, 723, 'JEROME', '', 'FERRER', '2002-09-22', 'CHILD'),
(1407, 723, 'MARJORIE', '', 'FERRER', '1993-03-15', 'CHILD'),
(1408, 724, 'ALMA', 'A.', 'CENTINO', '1979-10-18', 'PARENT'),
(1409, 724, 'ROEL', 'E.', 'CENTINO', '1974-04-09', 'PARENT'),
(1410, 724, 'RAYMOND', 'A.', 'CENTINO', '2008-01-02', 'BROTHER'),
(1411, 725, 'PHILIP MARCO', '', 'CABUHAT', '1995-06-02', 'HUSBAND'),
(1412, 725, 'KYLEEN MARIE', '', 'CABUHAT', '2016-01-16', 'CHILD'),
(1413, 725, 'KEIRAH MAUREEN', '', 'CABUHAT', '2018-01-23', 'CHILD');

-- --------------------------------------------------------

--
-- Table structure for table `config_civil_status`
--

CREATE TABLE `config_civil_status` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `config_civil_status`
--

INSERT INTO `config_civil_status` (`id`, `name`) VALUES
(1, 'Single'),
(2, 'Married'),
(3, 'Widowed'),
(4, 'Separated'),
(5, 'Annuled');

-- --------------------------------------------------------

--
-- Table structure for table `config_excel_headers`
--

CREATE TABLE `config_excel_headers` (
  `id` int(11) NOT NULL,
  `system_field` varchar(100) NOT NULL,
  `excel_header_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `config_excel_headers`
--

INSERT INTO `config_excel_headers` (`id`, `system_field`, `excel_header_name`, `description`) VALUES
(1, 'form_id', 'form id', 'Column for Form ID'),
(2, 'member_name', 'member name', 'Column for Member Name'),
(3, 'dob', 'date of birth', 'Column for Date of Birth'),
(4, 'birth_place', 'birth place', 'Column for Birth Place'),
(5, 'civil_status', 'civil status', 'Column for Civil Status'),
(6, 'religion', 'religion', 'Column for Religion'),
(7, 'sex', 'sex', 'Column for Sex (Male/Female)'),
(8, 'tribe', 'tribe', 'Column for Tribe'),
(9, 'sss_no', 'sss/gsis no.', 'Column for SSS/GSIS Number'),
(10, 'tin_no', 'tin no.', 'Column for TIN Number'),
(11, 'postal_code', 'postal code', 'Column for Postal Code'),
(12, 'address', 'address', 'Column for Address'),
(13, 'business_address', 'business - office address', 'Column for Business Address'),
(14, 'education', 'educational attainment', 'Column for Educational Attainment'),
(15, 'employment', 'present employment/business activities', 'Column for Employment'),
(16, 'occupation', 'occupation', 'Column for Occupation'),
(17, 'income', 'monthly income', 'Column for Monthly Income'),
(18, 'ben_name', 'beneficiaries names', 'Column for Beneficiary Names'),
(19, 'ben_dob', 'beneficiaries date of birth', 'Column for Beneficiary DOB'),
(20, 'ben_rel', 'relationship to the member', 'Column for Beneficiary Relationship');

-- --------------------------------------------------------

--
-- Table structure for table `config_inventory_settings`
--

CREATE TABLE `config_inventory_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `config_inventory_settings`
--

INSERT INTO `config_inventory_settings` (`setting_key`, `setting_value`, `description`) VALUES
('allow_negative_stock', '0', 'Allow items to be outsourced even if current stock is 0.');

-- --------------------------------------------------------

--
-- Table structure for table `config_monthly_income`
--

CREATE TABLE `config_monthly_income` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `config_monthly_income`
--

INSERT INTO `config_monthly_income` (`id`, `name`) VALUES
(1, 'Below 5,000'),
(2, '5,000 - 9,999'),
(3, '10,000 - 19,999'),
(4, '20,000 - 39,999'),
(5, '40,000 and above');

-- --------------------------------------------------------

--
-- Table structure for table `config_occupations`
--

CREATE TABLE `config_occupations` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `config_occupations`
--

INSERT INTO `config_occupations` (`id`, `name`) VALUES
(1, 'Private Employee'),
(2, 'Gov\'t Employee'),
(3, 'Self-Employed'),
(4, 'Farmer'),
(5, 'Pensioner'),
(6, 'Student'),
(7, 'House Keeper'),
(8, 'Fisher folk'),
(9, 'Entrepreneur/Vendor'),
(10, 'Others'),
(11, 'IT Personnel');

-- --------------------------------------------------------

--
-- Table structure for table `config_product_categories`
--

CREATE TABLE `config_product_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `config_product_categories`
--

INSERT INTO `config_product_categories` (`id`, `name`) VALUES
(1, 'Rice'),
(2, 'Groceries'),
(3, 'Poultry'),
(4, 'Agricultural'),
(5, 'Condiments'),
(6, 'Dried Fish'),
(7, 'Detergents'),
(8, 'Others'),
(9, 'Bar Soaps'),
(10, 'Dishwashing');

-- --------------------------------------------------------

--
-- Table structure for table `config_share_payment_types`
--

CREATE TABLE `config_share_payment_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `config_share_payment_types`
--

INSERT INTO `config_share_payment_types` (`id`, `name`, `is_active`, `created_at`) VALUES
(1, 'Membership Fee', 1, '2026-06-03 02:20:45'),
(2, 'Share Capital', 1, '2026-06-03 02:20:45');

-- --------------------------------------------------------

--
-- Table structure for table `config_transaction_types`
--

CREATE TABLE `config_transaction_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `config_transaction_types`
--

INSERT INTO `config_transaction_types` (`id`, `name`, `is_active`, `created_at`) VALUES
(1, 'Sales', 1, '2026-06-13 07:04:01'),
(2, 'Outsourced', 1, '2026-06-13 07:04:01'),
(3, 'Share Capital', 0, '2026-06-13 07:04:01'),
(4, 'Membership Fee', 0, '2026-06-13 07:04:01'),
(5, 'Miscellaneous', 0, '2026-06-13 07:04:01'),
(6, 'Purchase', 1, '2026-06-13 07:04:01');

-- --------------------------------------------------------

--
-- Table structure for table `config_unit_types`
--

CREATE TABLE `config_unit_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `config_unit_types`
--

INSERT INTO `config_unit_types` (`id`, `name`) VALUES
(1, 'Sack'),
(2, 'Kilo'),
(3, 'Pieces'),
(4, 'Pack'),
(5, 'Tray'),
(6, 'Can'),
(7, 'Bottle'),
(8, 'Pair'),
(10, 'Bag');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_type` varchar(100) NOT NULL,
  `quantity_type` varchar(100) NOT NULL,
  `current_quantity` int(11) DEFAULT 0,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`product_id`, `product_name`, `product_type`, `quantity_type`, `current_quantity`, `price`, `created_at`) VALUES
(29, 'Datu puti', 'Condiments', 'Bottle', 3, 53.00, '2026-06-15 06:53:23'),
(30, 'Canola oil', 'Condiments', 'Bottle', 2, 132.00, '2026-06-15 06:54:01'),
(31, 'Ligo sardines', 'Groceries', 'Can', 18, 25.00, '2026-06-15 06:54:51'),
(32, 'Sugar', 'Condiments', 'Kilo', 10, 80.00, '2026-06-15 06:55:48'),
(33, 'Vanilla', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-06-15 06:57:18'),
(34, 'Papaya', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-06-15 06:58:13'),
(35, 'Charcoal', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-06-15 06:58:33'),
(36, 'Tumbler', 'Others', 'Pieces', 4, 390.00, '2026-06-15 06:59:00');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_daily_snapshot`
--

CREATE TABLE `inventory_daily_snapshot` (
  `snapshot_date` date NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_type` varchar(100) NOT NULL,
  `quantity_type` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_daily_snapshot`
--

INSERT INTO `inventory_daily_snapshot` (`snapshot_date`, `product_id`, `product_name`, `product_type`, `quantity_type`, `quantity`, `price`, `created_at`) VALUES
('2026-05-27', 4, 'BUCO PANDAN', 'Rice', 'Sack', 10, 1400.00, '2026-05-27 08:46:54'),
('2026-05-27', 5, 'LA ROSA', 'Rice', 'Sack', 0, 1300.00, '2026-05-27 08:46:54'),
('2026-05-27', 6, 'SINANDOMENG', 'Rice', 'Sack', 6, 1300.00, '2026-05-27 08:46:54'),
('2026-05-27', 7, 'EGG - MEDIUM', 'Poultry', 'Tray', 0, 0.00, '2026-05-27 08:46:54'),
('2026-05-27', 8, 'EGG - LARGE', 'Poultry', 'Tray', 2, 230.00, '2026-05-27 08:46:54'),
('2026-05-27', 9, 'EGG - EXTRA LARGE', 'Poultry', 'Tray', 0, 0.00, '2026-05-27 08:46:54'),
('2026-05-27', 10, 'SUGAR - 1/4', 'Condiments', 'Pack', 10, 22.00, '2026-05-27 08:46:54'),
('2026-05-27', 11, 'SUGAR - 1/2', 'Condiments', 'Pack', 3, 40.00, '2026-05-27 08:46:54'),
('2026-05-27', 12, 'SUGAR - 1KG', 'Condiments', 'Kilo', 11, 80.00, '2026-05-27 08:46:54'),
('2026-05-27', 13, 'JUFRAN KETCHUP - 560G', 'Condiments', 'Pack', 0, 42.00, '2026-05-27 08:46:54'),
('2026-05-27', 14, 'THAI FISH SAUCE', 'Condiments', 'Bottle', 19, 50.00, '2026-05-27 08:46:54'),
('2026-05-27', 15, 'DATU PUTI - 1L', 'Condiments', 'Bottle', 0, 53.00, '2026-05-27 08:46:54'),
('2026-05-27', 16, 'TIMPLA SUKA', 'Condiments', 'Bottle', 16, 100.00, '2026-05-27 08:46:54'),
('2026-05-27', 17, 'VALUE PACK - 1L', 'Condiments', 'Bottle', 0, 90.00, '2026-05-27 08:46:54'),
('2026-05-27', 18, 'CANOLA OIL', 'Condiments', 'Bottle', 4, 132.00, '2026-05-27 08:46:54'),
('2026-05-27', 19, 'LIGO SARDINES - 155G', 'Groceries', 'Can', 0, 25.00, '2026-05-27 08:46:54'),
('2026-05-27', 20, 'IGAT', 'Dried Fish', 'Pack', 24, 100.00, '2026-05-27 08:46:54'),
('2026-05-27', 21, 'MUANG', 'Dried Fish', 'Pack', 1, 80.00, '2026-05-27 08:46:54'),
('2026-05-27', 22, 'FABRIC CONDITIONER', 'Detergents', 'Bottle', 34, 45.00, '2026-05-27 08:46:54'),
('2026-05-27', 23, 'DETERGENT POWDER', 'Detergents', 'Bottle', 35, 45.00, '2026-05-27 08:46:54'),
('2026-05-27', 24, 'DISHWASHING LIQUID', 'Dishwashing', 'Bottle', 34, 40.00, '2026-05-27 08:46:54'),
('2026-05-27', 25, 'VANILLA', 'Bar Soaps', 'Pieces', 2, 80.00, '2026-05-27 08:46:54'),
('2026-05-27', 26, 'PAPAYa', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-05-27 08:46:54'),
('2026-05-27', 27, 'CHARCOAL', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-05-27 08:46:54'),
('2026-05-27', 28, 'TUMBLER', 'Others', 'Pieces', 4, 390.00, '2026-05-27 08:46:54'),
('2026-05-28', 4, 'BUCO PANDAN', 'Rice', 'Sack', 10, 1400.00, '2026-05-28 03:46:20'),
('2026-05-28', 5, 'LA ROSA', 'Rice', 'Sack', 0, 1300.00, '2026-05-28 03:46:20'),
('2026-05-28', 6, 'SINANDOMENG', 'Rice', 'Sack', 6, 1300.00, '2026-05-28 03:46:20'),
('2026-05-28', 7, 'EGG - MEDIUM', 'Poultry', 'Tray', 0, 0.00, '2026-05-28 03:46:20'),
('2026-05-28', 8, 'EGG - LARGE', 'Poultry', 'Tray', 2, 230.00, '2026-05-28 03:46:20'),
('2026-05-28', 9, 'EGG - EXTRA LARGE', 'Poultry', 'Tray', 0, 0.00, '2026-05-28 03:46:20'),
('2026-05-28', 10, 'SUGAR - 1/4', 'Condiments', 'Pack', 10, 22.00, '2026-05-28 03:46:20'),
('2026-05-28', 11, 'SUGAR - 1/2', 'Condiments', 'Pack', 3, 40.00, '2026-05-28 03:46:20'),
('2026-05-28', 12, 'SUGAR - 1KG', 'Condiments', 'Kilo', 11, 80.00, '2026-05-28 03:46:20'),
('2026-05-28', 13, 'JUFRAN KETCHUP - 560G', 'Condiments', 'Pack', 0, 42.00, '2026-05-28 03:46:20'),
('2026-05-28', 14, 'THAI FISH SAUCE', 'Condiments', 'Bottle', 19, 50.00, '2026-05-28 03:46:20'),
('2026-05-28', 15, 'DATU PUTI - 1L', 'Condiments', 'Bottle', 0, 53.00, '2026-05-28 03:46:20'),
('2026-05-28', 16, 'TIMPLA SUKA', 'Condiments', 'Bottle', 16, 100.00, '2026-05-28 03:46:20'),
('2026-05-28', 17, 'VALUE PACK - 1L', 'Condiments', 'Bottle', 0, 90.00, '2026-05-28 03:46:20'),
('2026-05-28', 18, 'CANOLA OIL', 'Condiments', 'Bottle', 4, 132.00, '2026-05-28 03:46:20'),
('2026-05-28', 19, 'LIGO SARDINES - 155G', 'Groceries', 'Can', 0, 25.00, '2026-05-28 03:46:20'),
('2026-05-28', 20, 'IGAT', 'Dried Fish', 'Pack', 24, 100.00, '2026-05-28 03:46:20'),
('2026-05-28', 21, 'MUANG', 'Dried Fish', 'Pack', 1, 80.00, '2026-05-28 03:46:20'),
('2026-05-28', 22, 'FABRIC CONDITIONER', 'Detergents', 'Bottle', 34, 45.00, '2026-05-28 03:46:20'),
('2026-05-28', 23, 'DETERGENT POWDER', 'Detergents', 'Bottle', 35, 45.00, '2026-05-28 03:46:20'),
('2026-05-28', 24, 'DISHWASHING LIQUID', 'Dishwashing', 'Bottle', 34, 40.00, '2026-05-28 03:46:20'),
('2026-05-28', 25, 'VANILLA', 'Bar Soaps', 'Pieces', 2, 80.00, '2026-05-28 03:46:20'),
('2026-05-28', 26, 'PAPAYa', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-05-28 03:46:20'),
('2026-05-28', 27, 'CHARCOAL', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-05-28 03:46:20'),
('2026-05-28', 28, 'TUMBLER', 'Others', 'Pieces', 4, 390.00, '2026-05-28 03:46:20'),
('2026-06-02', 4, 'BUCO PANDAN', 'Rice', 'Sack', 1, 1400.00, '2026-06-02 03:11:42'),
('2026-06-02', 5, 'LA ROSA', 'Rice', 'Sack', 0, 1300.00, '2026-06-02 03:11:42'),
('2026-06-02', 6, 'SINANDOMENG', 'Rice', 'Sack', 6, 1300.00, '2026-06-02 03:11:42'),
('2026-06-02', 7, 'EGG - MEDIUM', 'Poultry', 'Tray', 0, 0.00, '2026-06-02 03:11:42'),
('2026-06-02', 8, 'EGG - LARGE', 'Poultry', 'Tray', 2, 230.00, '2026-06-02 03:11:42'),
('2026-06-02', 9, 'EGG - EXTRA LARGE', 'Poultry', 'Tray', 0, 0.00, '2026-06-02 03:11:42'),
('2026-06-02', 10, 'SUGAR - 1/4', 'Condiments', 'Pack', 10, 22.00, '2026-06-02 03:11:42'),
('2026-06-02', 11, 'SUGAR - 1/2', 'Condiments', 'Pack', 3, 40.00, '2026-06-02 03:11:42'),
('2026-06-02', 12, 'SUGAR - 1KG', 'Condiments', 'Kilo', 11, 80.00, '2026-06-02 03:11:42'),
('2026-06-02', 13, 'JUFRAN KETCHUP - 560G', 'Condiments', 'Pack', 0, 42.00, '2026-06-02 03:11:42'),
('2026-06-02', 14, 'THAI FISH SAUCE', 'Condiments', 'Bottle', 19, 50.00, '2026-06-02 03:11:42'),
('2026-06-02', 15, 'DATU PUTI - 1L', 'Condiments', 'Bottle', 0, 53.00, '2026-06-02 03:11:42'),
('2026-06-02', 16, 'TIMPLA SUKA', 'Condiments', 'Bottle', 16, 100.00, '2026-06-02 03:11:42'),
('2026-06-02', 17, 'VALUE PACK - 1L', 'Condiments', 'Bottle', 0, 90.00, '2026-06-02 03:11:42'),
('2026-06-02', 18, 'CANOLA OIL', 'Condiments', 'Bottle', 4, 132.00, '2026-06-02 03:11:42'),
('2026-06-02', 19, 'LIGO SARDINES - 155G', 'Groceries', 'Can', 0, 25.00, '2026-06-02 03:11:42'),
('2026-06-02', 20, 'IGAT', 'Dried Fish', 'Pack', 24, 100.00, '2026-06-02 03:11:42'),
('2026-06-02', 21, 'MUANG', 'Dried Fish', 'Pack', 1, 80.00, '2026-06-02 03:11:42'),
('2026-06-02', 22, 'FABRIC CONDITIONER', 'Detergents', 'Bottle', 34, 45.00, '2026-06-02 03:11:42'),
('2026-06-02', 23, 'DETERGENT POWDER', 'Detergents', 'Bottle', 35, 45.00, '2026-06-02 03:11:42'),
('2026-06-02', 24, 'DISHWASHING LIQUID', 'Dishwashing', 'Bottle', 34, 40.00, '2026-06-02 03:11:42'),
('2026-06-02', 25, 'VANILLA', 'Bar Soaps', 'Pieces', 2, 80.00, '2026-06-02 03:11:42'),
('2026-06-02', 26, 'PAPAYA', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-06-02 03:11:42'),
('2026-06-02', 27, 'CHARCOAL', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-06-02 03:11:42'),
('2026-06-02', 28, 'TUMBLER', 'Others', 'Pieces', 4, 390.00, '2026-06-02 03:11:42'),
('2026-06-03', 4, 'BUCO PANDAN', 'Rice', 'Sack', 1, 1400.00, '2026-06-03 01:56:40'),
('2026-06-03', 5, 'LA ROSA', 'Rice', 'Sack', 0, 1300.00, '2026-06-03 01:56:40'),
('2026-06-03', 6, 'SINANDOMENG', 'Rice', 'Sack', 6, 1300.00, '2026-06-03 01:56:40'),
('2026-06-03', 7, 'EGG - MEDIUM', 'Poultry', 'Tray', 0, 0.00, '2026-06-03 01:56:40'),
('2026-06-03', 8, 'EGG - LARGE', 'Poultry', 'Tray', 2, 230.00, '2026-06-03 01:56:40'),
('2026-06-03', 9, 'EGG - EXTRA LARGE', 'Poultry', 'Tray', 0, 0.00, '2026-06-03 01:56:40'),
('2026-06-03', 10, 'SUGAR - 1/4', 'Condiments', 'Pack', 10, 22.00, '2026-06-03 01:56:40'),
('2026-06-03', 11, 'SUGAR - 1/2', 'Condiments', 'Pack', 3, 40.00, '2026-06-03 01:56:40'),
('2026-06-03', 12, 'SUGAR - 1KG', 'Condiments', 'Kilo', 11, 80.00, '2026-06-03 01:56:40'),
('2026-06-03', 13, 'JUFRAN KETCHUP - 560G', 'Condiments', 'Pack', 0, 42.00, '2026-06-03 01:56:40'),
('2026-06-03', 14, 'THAI FISH SAUCE', 'Condiments', 'Bottle', 19, 50.00, '2026-06-03 01:56:40'),
('2026-06-03', 15, 'DATU PUTI - 1L', 'Condiments', 'Bottle', 0, 53.00, '2026-06-03 01:56:40'),
('2026-06-03', 16, 'TIMPLA SUKA', 'Condiments', 'Bottle', 16, 100.00, '2026-06-03 01:56:40'),
('2026-06-03', 17, 'VALUE PACK - 1L', 'Condiments', 'Bottle', 0, 90.00, '2026-06-03 01:56:40'),
('2026-06-03', 18, 'CANOLA OIL', 'Condiments', 'Bottle', 4, 132.00, '2026-06-03 01:56:40'),
('2026-06-03', 19, 'LIGO SARDINES - 155G', 'Groceries', 'Can', 0, 25.00, '2026-06-03 01:56:40'),
('2026-06-03', 20, 'IGAT', 'Dried Fish', 'Pack', 24, 100.00, '2026-06-03 01:56:40'),
('2026-06-03', 21, 'MUANG', 'Dried Fish', 'Pack', 1, 80.00, '2026-06-03 01:56:40'),
('2026-06-03', 22, 'FABRIC CONDITIONER', 'Detergents', 'Bottle', 34, 45.00, '2026-06-03 01:56:40'),
('2026-06-03', 23, 'DETERGENT POWDER', 'Detergents', 'Bottle', 35, 45.00, '2026-06-03 01:56:40'),
('2026-06-03', 24, 'DISHWASHING LIQUID', 'Dishwashing', 'Bottle', 34, 40.00, '2026-06-03 01:56:40'),
('2026-06-03', 26, 'PAPAYA', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-06-03 01:56:40'),
('2026-06-03', 27, 'CHARCOAL', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-06-03 01:56:40'),
('2026-06-03', 28, 'TUMBLER', 'Others', 'Pieces', 4, 390.00, '2026-06-03 01:56:40'),
('2026-06-04', 4, 'BUCO PANDAN', 'Rice', 'Sack', 1, 1400.00, '2026-06-04 07:47:47'),
('2026-06-04', 5, 'LA ROSA', 'Rice', 'Sack', 0, 1300.00, '2026-06-04 07:47:47'),
('2026-06-04', 6, 'SINANDOMENG', 'Rice', 'Sack', 6, 1300.00, '2026-06-04 07:47:47'),
('2026-06-04', 7, 'EGG - MEDIUM', 'Poultry', 'Tray', 0, 0.00, '2026-06-04 07:47:47'),
('2026-06-04', 8, 'EGG - LARGE', 'Poultry', 'Tray', 2, 230.00, '2026-06-04 07:47:47'),
('2026-06-04', 9, 'EGG - EXTRA LARGE', 'Poultry', 'Tray', 0, 0.00, '2026-06-04 07:47:47'),
('2026-06-04', 10, 'SUGAR - 1/4', 'Condiments', 'Pack', 10, 22.00, '2026-06-04 07:47:47'),
('2026-06-04', 11, 'SUGAR - 1/2', 'Condiments', 'Pack', 3, 40.00, '2026-06-04 07:47:47'),
('2026-06-04', 12, 'SUGAR - 1KG', 'Condiments', 'Kilo', 11, 80.00, '2026-06-04 07:47:47'),
('2026-06-04', 13, 'JUFRAN KETCHUP - 560G', 'Condiments', 'Pack', 0, 42.00, '2026-06-04 07:47:47'),
('2026-06-04', 14, 'THAI FISH SAUCE', 'Condiments', 'Bottle', 19, 50.00, '2026-06-04 07:47:47'),
('2026-06-04', 15, 'DATU PUTI - 1L', 'Condiments', 'Bottle', 0, 53.00, '2026-06-04 07:47:47'),
('2026-06-04', 16, 'TIMPLA SUKA', 'Condiments', 'Bottle', 16, 100.00, '2026-06-04 07:47:47'),
('2026-06-04', 17, 'VALUE PACK - 1L', 'Condiments', 'Bottle', 0, 90.00, '2026-06-04 07:47:47'),
('2026-06-04', 18, 'CANOLA OIL', 'Condiments', 'Bottle', 4, 132.00, '2026-06-04 07:47:47'),
('2026-06-04', 19, 'LIGO SARDINES - 155G', 'Groceries', 'Can', 0, 25.00, '2026-06-04 07:47:47'),
('2026-06-04', 20, 'IGAT', 'Dried Fish', 'Pack', 24, 100.00, '2026-06-04 07:47:47'),
('2026-06-04', 21, 'MUANG', 'Dried Fish', 'Pack', 1, 80.00, '2026-06-04 07:47:47'),
('2026-06-04', 22, 'FABRIC CONDITIONER', 'Detergents', 'Bottle', 34, 45.00, '2026-06-04 07:47:47'),
('2026-06-04', 23, 'DETERGENT POWDER', 'Detergents', 'Bottle', 35, 45.00, '2026-06-04 07:47:47'),
('2026-06-04', 24, 'DISHWASHING LIQUID', 'Dishwashing', 'Bottle', 34, 40.00, '2026-06-04 07:47:47'),
('2026-06-04', 26, 'PAPAYA', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-06-04 07:47:47'),
('2026-06-04', 27, 'CHARCOAL', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-06-04 07:47:47'),
('2026-06-04', 28, 'TUMBLER', 'Others', 'Pieces', 4, 390.00, '2026-06-04 07:47:47'),
('2026-06-05', 4, 'BUCO PANDAN', 'Rice', 'Sack', 1, 1400.00, '2026-06-05 01:46:53'),
('2026-06-05', 5, 'LA ROSA', 'Rice', 'Sack', 0, 1300.00, '2026-06-05 01:46:53'),
('2026-06-05', 6, 'SINANDOMENG', 'Rice', 'Sack', 6, 1300.00, '2026-06-05 01:46:53'),
('2026-06-05', 7, 'EGG - MEDIUM', 'Poultry', 'Tray', 0, 0.00, '2026-06-05 01:46:53'),
('2026-06-05', 8, 'EGG - LARGE', 'Poultry', 'Tray', 2, 230.00, '2026-06-05 01:46:53'),
('2026-06-05', 9, 'EGG - EXTRA LARGE', 'Poultry', 'Tray', 0, 0.00, '2026-06-05 01:46:53'),
('2026-06-05', 10, 'SUGAR - 1/4', 'Condiments', 'Pack', 10, 22.00, '2026-06-05 01:46:53'),
('2026-06-05', 11, 'SUGAR - 1/2', 'Condiments', 'Pack', 3, 40.00, '2026-06-05 01:46:53'),
('2026-06-05', 12, 'SUGAR - 1KG', 'Condiments', 'Kilo', 11, 80.00, '2026-06-05 01:46:53'),
('2026-06-05', 13, 'JUFRAN KETCHUP - 560G', 'Condiments', 'Pack', 0, 42.00, '2026-06-05 01:46:53'),
('2026-06-05', 14, 'THAI FISH SAUCE', 'Condiments', 'Bottle', 19, 50.00, '2026-06-05 01:46:53'),
('2026-06-05', 15, 'DATU PUTI - 1L', 'Condiments', 'Bottle', 0, 53.00, '2026-06-05 01:46:53'),
('2026-06-05', 16, 'TIMPLA SUKA', 'Condiments', 'Bottle', 16, 100.00, '2026-06-05 01:46:53'),
('2026-06-05', 17, 'VALUE PACK - 1L', 'Condiments', 'Bottle', 0, 90.00, '2026-06-05 01:46:53'),
('2026-06-05', 18, 'CANOLA OIL', 'Condiments', 'Bottle', 4, 132.00, '2026-06-05 01:46:53'),
('2026-06-05', 19, 'LIGO SARDINES - 155G', 'Groceries', 'Can', 0, 25.00, '2026-06-05 01:46:53'),
('2026-06-05', 20, 'IGAT', 'Dried Fish', 'Pack', 24, 100.00, '2026-06-05 01:46:53'),
('2026-06-05', 21, 'MUANG', 'Dried Fish', 'Pack', 1, 80.00, '2026-06-05 01:46:53'),
('2026-06-05', 22, 'FABRIC CONDITIONER', 'Detergents', 'Bottle', 34, 45.00, '2026-06-05 01:46:53'),
('2026-06-05', 23, 'DETERGENT POWDER', 'Detergents', 'Bottle', 35, 45.00, '2026-06-05 01:46:53'),
('2026-06-05', 24, 'DISHWASHING LIQUID', 'Dishwashing', 'Bottle', 34, 40.00, '2026-06-05 01:46:53'),
('2026-06-05', 26, 'PAPAYA', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-06-05 01:46:53'),
('2026-06-05', 27, 'CHARCOAL', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-06-05 01:46:53'),
('2026-06-05', 28, 'TUMBLER', 'Others', 'Pieces', 4, 390.00, '2026-06-05 01:46:53'),
('2026-06-08', 4, 'BUCO PANDAN', 'Rice', 'Sack', 1, 1400.00, '2026-06-08 01:56:22'),
('2026-06-08', 5, 'LA ROSA', 'Rice', 'Sack', 0, 1300.00, '2026-06-08 01:56:22'),
('2026-06-08', 6, 'SINANDOMENG', 'Rice', 'Sack', 6, 1300.00, '2026-06-08 01:56:22'),
('2026-06-08', 7, 'EGG - MEDIUM', 'Poultry', 'Tray', 0, 0.00, '2026-06-08 01:56:22'),
('2026-06-08', 8, 'EGG - LARGE', 'Poultry', 'Tray', 2, 230.00, '2026-06-08 01:56:22'),
('2026-06-08', 9, 'EGG - EXTRA LARGE', 'Poultry', 'Tray', 0, 0.00, '2026-06-08 01:56:22'),
('2026-06-08', 10, 'SUGAR - 1/4', 'Condiments', 'Pack', 10, 22.00, '2026-06-08 01:56:22'),
('2026-06-08', 11, 'SUGAR - 1/2', 'Condiments', 'Pack', 3, 40.00, '2026-06-08 01:56:22'),
('2026-06-08', 12, 'SUGAR - 1KG', 'Condiments', 'Kilo', 11, 80.00, '2026-06-08 01:56:22'),
('2026-06-08', 13, 'JUFRAN KETCHUP - 560G', 'Condiments', 'Pack', 0, 42.00, '2026-06-08 01:56:22'),
('2026-06-08', 14, 'THAI FISH SAUCE', 'Condiments', 'Bottle', 19, 50.00, '2026-06-08 01:56:22'),
('2026-06-08', 15, 'DATU PUTI - 1L', 'Condiments', 'Bottle', 0, 53.00, '2026-06-08 01:56:22'),
('2026-06-08', 16, 'TIMPLA SUKA', 'Condiments', 'Bottle', 16, 100.00, '2026-06-08 01:56:22'),
('2026-06-08', 17, 'VALUE PACK - 1L', 'Condiments', 'Bottle', 0, 90.00, '2026-06-08 01:56:22'),
('2026-06-08', 18, 'CANOLA OIL', 'Condiments', 'Bottle', 4, 132.00, '2026-06-08 01:56:22'),
('2026-06-08', 19, 'LIGO SARDINES - 155G', 'Groceries', 'Can', 0, 25.00, '2026-06-08 01:56:22'),
('2026-06-08', 20, 'IGAT', 'Dried Fish', 'Pack', 24, 100.00, '2026-06-08 01:56:22'),
('2026-06-08', 21, 'MUANG', 'Dried Fish', 'Pack', 1, 80.00, '2026-06-08 01:56:22'),
('2026-06-08', 22, 'FABRIC CONDITIONER', 'Detergents', 'Bottle', 34, 45.00, '2026-06-08 01:56:22'),
('2026-06-08', 23, 'DETERGENT POWDER', 'Detergents', 'Bottle', 35, 45.00, '2026-06-08 01:56:22'),
('2026-06-08', 24, 'DISHWASHING LIQUID', 'Dishwashing', 'Bottle', 34, 40.00, '2026-06-08 01:56:22'),
('2026-06-08', 26, 'PAPAYA', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-06-08 01:56:22'),
('2026-06-08', 27, 'CHARCOAL', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-06-08 01:56:22'),
('2026-06-08', 28, 'TUMBLER', 'Others', 'Pieces', 4, 390.00, '2026-06-08 01:56:22'),
('2026-06-10', 4, 'BUCO PANDAN', 'Rice', 'Sack', 1, 1400.00, '2026-06-10 02:51:02'),
('2026-06-10', 5, 'LA ROSA', 'Rice', 'Sack', 0, 1300.00, '2026-06-10 02:51:02'),
('2026-06-10', 6, 'SINANDOMENG', 'Rice', 'Sack', 6, 1300.00, '2026-06-10 02:51:02'),
('2026-06-10', 7, 'EGG - MEDIUM', 'Poultry', 'Tray', 0, 0.00, '2026-06-10 02:51:02'),
('2026-06-10', 8, 'EGG - LARGE', 'Poultry', 'Tray', 2, 230.00, '2026-06-10 02:51:02'),
('2026-06-10', 9, 'EGG - EXTRA LARGE', 'Poultry', 'Tray', 0, 0.00, '2026-06-10 02:51:02'),
('2026-06-10', 10, 'SUGAR - 1/4', 'Condiments', 'Pack', 10, 22.00, '2026-06-10 02:51:02'),
('2026-06-10', 11, 'SUGAR - 1/2', 'Condiments', 'Pack', 3, 40.00, '2026-06-10 02:51:02'),
('2026-06-10', 12, 'SUGAR - 1KG', 'Condiments', 'Kilo', 11, 80.00, '2026-06-10 02:51:02'),
('2026-06-10', 13, 'JUFRAN KETCHUP - 560G', 'Condiments', 'Pack', 0, 42.00, '2026-06-10 02:51:02'),
('2026-06-10', 14, 'THAI FISH SAUCE', 'Condiments', 'Bottle', 19, 50.00, '2026-06-10 02:51:02'),
('2026-06-10', 15, 'DATU PUTI - 1L', 'Condiments', 'Bottle', 0, 53.00, '2026-06-10 02:51:02'),
('2026-06-10', 16, 'TIMPLA SUKA', 'Condiments', 'Bottle', 16, 100.00, '2026-06-10 02:51:02'),
('2026-06-10', 17, 'VALUE PACK - 1L', 'Condiments', 'Bottle', 0, 90.00, '2026-06-10 02:51:02'),
('2026-06-10', 18, 'CANOLA OIL', 'Condiments', 'Bottle', 4, 132.00, '2026-06-10 02:51:02'),
('2026-06-10', 19, 'LIGO SARDINES - 155G', 'Groceries', 'Can', 0, 25.00, '2026-06-10 02:51:02'),
('2026-06-10', 20, 'IGAT', 'Dried Fish', 'Pack', 24, 100.00, '2026-06-10 02:51:02'),
('2026-06-10', 21, 'MUANG', 'Dried Fish', 'Pack', 1, 80.00, '2026-06-10 02:51:02'),
('2026-06-10', 22, 'FABRIC CONDITIONER', 'Detergents', 'Bottle', 34, 45.00, '2026-06-10 02:51:02'),
('2026-06-10', 23, 'DETERGENT POWDER', 'Detergents', 'Bottle', 35, 45.00, '2026-06-10 02:51:02'),
('2026-06-10', 24, 'DISHWASHING LIQUID', 'Dishwashing', 'Bottle', 34, 40.00, '2026-06-10 02:51:02'),
('2026-06-10', 26, 'PAPAYA', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-06-10 02:51:02'),
('2026-06-10', 27, 'CHARCOAL', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-06-10 02:51:02'),
('2026-06-10', 28, 'TUMBLER', 'Others', 'Pieces', 4, 390.00, '2026-06-10 02:51:02'),
('2026-06-13', 4, 'BUCO PANDAN', 'Rice', 'Sack', 1, 1400.00, '2026-06-13 02:08:37'),
('2026-06-13', 5, 'LA ROSA', 'Rice', 'Sack', 0, 1300.00, '2026-06-13 02:08:37'),
('2026-06-13', 6, 'SINANDOMENG', 'Rice', 'Sack', 4, 1300.00, '2026-06-13 02:08:37'),
('2026-06-13', 7, 'EGG - MEDIUM', 'Poultry', 'Tray', 0, 0.00, '2026-06-13 02:08:37'),
('2026-06-13', 8, 'EGG - LARGE', 'Poultry', 'Tray', 2, 230.00, '2026-06-13 02:08:37'),
('2026-06-13', 9, 'EGG - EXTRA LARGE', 'Poultry', 'Tray', 0, 0.00, '2026-06-13 02:08:37'),
('2026-06-13', 10, 'SUGAR - 1/4', 'Condiments', 'Pack', 10, 22.00, '2026-06-13 02:08:37'),
('2026-06-13', 11, 'SUGAR - 1/2', 'Condiments', 'Pack', 3, 40.00, '2026-06-13 02:08:37'),
('2026-06-13', 12, 'SUGAR - 1KG', 'Condiments', 'Kilo', 11, 80.00, '2026-06-13 02:08:37'),
('2026-06-13', 13, 'JUFRAN KETCHUP - 560G', 'Condiments', 'Pack', 0, 42.00, '2026-06-13 02:08:37'),
('2026-06-13', 14, 'THAI FISH SAUCE', 'Condiments', 'Bottle', 19, 50.00, '2026-06-13 02:08:37'),
('2026-06-13', 15, 'DATU PUTI - 1L', 'Condiments', 'Bottle', 0, 53.00, '2026-06-13 02:08:37'),
('2026-06-13', 16, 'TIMPLA SUKA', 'Condiments', 'Bottle', 16, 100.00, '2026-06-13 02:08:37'),
('2026-06-13', 17, 'VALUE PACK - 1L', 'Condiments', 'Bottle', 0, 90.00, '2026-06-13 02:08:37'),
('2026-06-13', 18, 'CANOLA OIL', 'Condiments', 'Bottle', 14, 132.00, '2026-06-13 02:08:37'),
('2026-06-13', 19, 'LIGO SARDINES - 155G', 'Groceries', 'Can', 0, 25.00, '2026-06-13 02:08:37'),
('2026-06-13', 20, 'IGAT', 'Dried Fish', 'Pack', 24, 100.00, '2026-06-13 02:08:37'),
('2026-06-13', 21, 'MUANG', 'Dried Fish', 'Pack', 1, 80.00, '2026-06-13 02:08:37'),
('2026-06-13', 22, 'FABRIC CONDITIONER', 'Detergents', 'Bottle', 34, 45.00, '2026-06-13 02:08:37'),
('2026-06-13', 23, 'DETERGENT POWDER', 'Detergents', 'Bottle', 35, 45.00, '2026-06-13 02:08:37'),
('2026-06-13', 24, 'DISHWASHING LIQUID', 'Dishwashing', 'Bottle', 34, 40.00, '2026-06-13 02:08:37'),
('2026-06-13', 26, 'PAPAYA', 'Bar Soaps', 'Pieces', 3, 80.00, '2026-06-13 02:08:37'),
('2026-06-13', 27, 'CHARCOAL', 'Bar Soaps', 'Pieces', 3, 80.00, '2026-06-13 02:08:37'),
('2026-06-13', 28, 'TUMBLER', 'Others', 'Pieces', 4, 390.00, '2026-06-13 02:08:37'),
('2026-06-15', 4, 'BUCO PANDAN', 'Rice', 'Sack', 1, 1400.00, '2026-06-15 00:34:50'),
('2026-06-15', 5, 'LA ROSA', 'Rice', 'Sack', 0, 1300.00, '2026-06-15 00:34:50'),
('2026-06-15', 6, 'SINANDOMENG', 'Rice', 'Sack', 4, 1300.00, '2026-06-15 00:34:50'),
('2026-06-15', 7, 'EGG - MEDIUM', 'Poultry', 'Tray', 0, 0.00, '2026-06-15 00:34:50'),
('2026-06-15', 8, 'EGG - LARGE', 'Poultry', 'Tray', 2, 230.00, '2026-06-15 00:34:50'),
('2026-06-15', 9, 'EGG - EXTRA LARGE', 'Poultry', 'Tray', 0, 0.00, '2026-06-15 00:34:50'),
('2026-06-15', 10, 'SUGAR - 1/4', 'Condiments', 'Pack', 10, 22.00, '2026-06-15 00:34:50'),
('2026-06-15', 11, 'SUGAR - 1/2', 'Condiments', 'Pack', 3, 40.00, '2026-06-15 00:34:50'),
('2026-06-15', 12, 'SUGAR - 1KG', 'Condiments', 'Kilo', 11, 80.00, '2026-06-15 00:34:50'),
('2026-06-15', 13, 'JUFRAN KETCHUP - 560G', 'Condiments', 'Pack', 0, 42.00, '2026-06-15 00:34:50'),
('2026-06-15', 14, 'THAI FISH SAUCE', 'Condiments', 'Bottle', 19, 50.00, '2026-06-15 00:34:50'),
('2026-06-15', 15, 'DATU PUTI - 1L', 'Condiments', 'Bottle', 0, 53.00, '2026-06-15 00:34:50'),
('2026-06-15', 16, 'TIMPLA SUKA', 'Condiments', 'Bottle', 16, 100.00, '2026-06-15 00:34:50'),
('2026-06-15', 17, 'VALUE PACK - 1L', 'Condiments', 'Bottle', 0, 90.00, '2026-06-15 00:34:50'),
('2026-06-15', 18, 'CANOLA OIL', 'Condiments', 'Bottle', 14, 132.00, '2026-06-15 00:34:50'),
('2026-06-15', 19, 'LIGO SARDINES - 155G', 'Groceries', 'Can', 0, 25.00, '2026-06-15 00:34:50'),
('2026-06-15', 20, 'IGAT', 'Dried Fish', 'Pack', 24, 100.00, '2026-06-15 00:34:50'),
('2026-06-15', 21, 'MUANG', 'Dried Fish', 'Pack', 1, 80.00, '2026-06-15 00:34:50'),
('2026-06-15', 22, 'FABRIC CONDITIONER', 'Detergents', 'Bottle', 34, 45.00, '2026-06-15 00:34:50'),
('2026-06-15', 23, 'DETERGENT POWDER', 'Detergents', 'Bottle', 35, 45.00, '2026-06-15 00:34:50'),
('2026-06-15', 24, 'DISHWASHING LIQUID', 'Dishwashing', 'Bottle', 34, 40.00, '2026-06-15 00:34:50'),
('2026-06-15', 26, 'PAPAYA', 'Bar Soaps', 'Pieces', 3, 80.00, '2026-06-15 00:34:50'),
('2026-06-15', 27, 'CHARCOAL', 'Bar Soaps', 'Pieces', 3, 80.00, '2026-06-15 00:34:50'),
('2026-06-15', 28, 'TUMBLER', 'Others', 'Pieces', 4, 390.00, '2026-06-15 00:34:50'),
('2026-06-15', 29, 'Datu puti', 'Condiments', 'Bottle', 3, 53.00, '2026-06-15 06:53:23'),
('2026-06-15', 30, 'Canola oil', 'Condiments', 'Bottle', 2, 132.00, '2026-06-15 06:54:01'),
('2026-06-15', 31, 'Ligo sardines', 'Groceries', 'Can', 18, 25.00, '2026-06-15 06:54:51'),
('2026-06-15', 32, 'Sugar', 'Condiments', 'Kilo', 10, 80.00, '2026-06-15 06:55:48'),
('2026-06-15', 33, 'Vanilla', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-06-15 06:57:18'),
('2026-06-15', 34, 'Papaya', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-06-15 06:58:13'),
('2026-06-15', 35, 'Charcoal', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-06-15 06:58:33'),
('2026-06-15', 36, 'Tumbler', 'Others', 'Pieces', 4, 390.00, '2026-06-15 06:59:00'),
('2026-06-18', 29, 'Datu puti', 'Condiments', 'Bottle', 3, 53.00, '2026-06-18 03:23:40'),
('2026-06-18', 30, 'Canola oil', 'Condiments', 'Bottle', 2, 132.00, '2026-06-18 03:23:40'),
('2026-06-18', 31, 'Ligo sardines', 'Groceries', 'Can', 18, 25.00, '2026-06-18 03:23:40'),
('2026-06-18', 32, 'Sugar', 'Condiments', 'Kilo', 10, 80.00, '2026-06-18 03:23:40'),
('2026-06-18', 33, 'Vanilla', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-06-18 03:23:40'),
('2026-06-18', 34, 'Papaya', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-06-18 03:23:40'),
('2026-06-18', 35, 'Charcoal', 'Bar Soaps', 'Pieces', 5, 80.00, '2026-06-18 03:23:40'),
('2026-06-18', 36, 'Tumbler', 'Others', 'Pieces', 4, 390.00, '2026-06-18 03:23:40');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_outsourcing`
--

CREATE TABLE `inventory_outsourcing` (
  `record_id` int(11) NOT NULL,
  `record_date` date NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity_out` int(11) NOT NULL,
  `payment_method` varchar(50) NOT NULL DEFAULT 'Cash',
  `receipt_no` varchar(100) NOT NULL DEFAULT 'N/A',
  `buyer_name` varchar(255) DEFAULT NULL,
  `buyer_contact` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'COMPLETED',
  `quantity_returned` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `member_id` int(11) NOT NULL,
  `form_id` varchar(100) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `birth_place` varchar(255) DEFAULT NULL,
  `civil_status` varchar(50) DEFAULT NULL,
  `religion` varchar(100) DEFAULT NULL,
  `sex` enum('MALE','FEMALE','RATHER NOT SAY') DEFAULT NULL,
  `tribe` varchar(100) DEFAULT NULL,
  `sss_gsis_no` varchar(50) DEFAULT NULL,
  `tin_no` varchar(50) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `business_office_address` text DEFAULT NULL,
  `educational_attainment` varchar(255) DEFAULT NULL,
  `present_employment_business` text DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `monthly_income` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`member_id`, `form_id`, `first_name`, `middle_name`, `last_name`, `date_of_birth`, `birth_place`, `civil_status`, `religion`, `sex`, `tribe`, `sss_gsis_no`, `tin_no`, `postal_code`, `address`, `business_office_address`, `educational_attainment`, `present_employment_business`, `occupation`, `monthly_income`, `created_at`) VALUES
(647, '26 - 067', 'MILAGROSA', 'OTACAN', 'BATURIANO', '1975-06-29', 'PROSPERIDAD AGUSAN DEL SUR', 'MARRIED', 'ROMAN CATHOLIC', 'FEMALE', 'CEBUANA', '', '', '', 'BLOCK 8, LOT 9, PHASE 1, SECTION 2, PABAHAY, BAGTAS, TANZA, CAVITE', '', 'COLLEGE UNDERGRAD', '', 'HOUSE KEEPER', '5,000 - 9,999', '2026-05-19 01:44:01'),
(648, '26 - 079', 'RAYMOND', 'ARMIJO', 'ANGKICO', '1980-10-27', 'TRECE MARTIRES, CITY', 'MARRIED', 'ROMAN CATHOLIC', 'MALE', '', '', '', '4108', '180 TEN. F. ARMIJO STREET. AMAYA 1, TANZA, CAVITE', '180 TEN. F. ARMIJO STREET. AMAYA 1, TANZA, CAVITE', 'COLLEGE GRADUATE', 'RETAIL', 'SELF - EMPLOYED', '', '2026-05-19 01:44:01'),
(649, '26 - 081', 'JOHN CEDRIC', 'ESTADARTE', 'ARMIJO', '1994-12-30', 'TANZA, CAVITE', 'SINGLE', 'ROMAN CATHOLIC', 'MALE', '', '', '', '4108', '180 TEN. F. ARMIJO STREET. AMAYA 1, TANZA, CAVITE', '', 'COLLEGE GRADUATE', 'LONDON STOCK EXCHANGE GROUP - LEARNING CONSULTANT', '', '', '2026-05-19 01:44:01'),
(650, '26 - 032', 'REBECCA', 'ARIGO', 'ARAñAS', '1964-11-07', 'TANZA, CAVITE', 'WIDOW', 'ROMAN CATHOLIC', 'FEMALE', '', '33-5192368-4', '', '4108', 'SILANGAN STREET, HALAYHAY, TANZA, CAVITE', '', 'HIGH SCHOOL GRADUATE', '', 'SELF - EMPLOYED', '', '2026-05-19 01:44:01'),
(651, '26 - 041', 'ZENAIDA', 'ARIGO', 'ARADO', '1971-11-12', 'HALAYHAY, TANZA, CAVITE', 'MARRIED', 'ROMAN CATHOLIC', 'FEMALE', '', '03-9514464-2', '', '4108', 'HALAYHAY, TANZA, CAVITE', '', 'HIGH SCHOOL GRADUATE', '', 'HOUSE KEEPER', '', '2026-05-19 01:44:01'),
(652, '26 - 068', 'DEXTER', 'ESTOLE', 'AMADA', '1988-06-09', 'TANZA, CAVITE', 'SINGLE', 'ROMAN CATHOLIC', 'MALE', '', '', '', '4108', '250 SILANGAN STREET, HALAYHAY, SAHUD-ULAN, TANZA, CAVITE', '868 A. SORIANO HIGHWAY, SAHUD-ULAN, TANZA, CAVITE', 'COLLEGE GRADUATE', 'XEDTECH GADGET REPAIR SHOP', '', '', '2026-05-19 01:44:01'),
(653, '26 - 042', 'KHARLENE', 'FAUSTINO', 'ARIGO', '1862-04-13', 'HALAYHAY, TANZA, CAVITE', 'SINGLE', 'ROMAN CATHOLIC', 'FEMALE', '', '', '', '4108', 'HALAYHAY, TANZA, CAVITE', '', 'HIGH SCHOOL GRADUATE', '', '', '', '2026-05-19 01:44:01'),
(654, '26 - 043', 'ARISSA', 'ARIGO', 'BUENAFLOR', '1975-01-15', 'HALAYHAY, TANZA, CAVITE', 'MARRIED', 'ROMAN CATHOLIC', 'FEMALE', '', '03-39514466-8', '', '', '', 'SILANGAN STREET, HALAYHAY, TANZA, CAVITE', 'HIGH SCHOOL GRADUATE', '', 'HOUSE KEEPER', '', '2026-05-19 01:44:01'),
(655, '26 - 089', 'LUCITA', 'MENDOZA', 'BARRIENTOS', '1975-07-14', 'CEBU, CITY', 'SINGLE', 'ROMAN CATHOLIC', 'FEMALE', '', '33-3-98580-5', '200-562-242-000', '4108', 'BLOCK 23, LOT 7, SOUTHGATE 2, SPRINGTOWN VILLA, BUCAL, TANZA', '', 'COLLEGE GRADUATE', 'PACIFIC CROSS INSURANCA INC.', 'SELF - EMPLOYED', '15,000', '2026-05-19 01:44:01'),
(656, '25 - 011', 'ARMINDA', 'VIRAY', 'BOBADILLA', '1970-05-30', 'TANZA, CAVITE', 'MARRIED', 'MCGI', 'FEMALE', '', '', '', '4108', '033 FLORENTINO JOYA STREET, JULUGAN II, TANZA, CAVITE', '', 'COLLEGE GRADUATE', '', '', '15,000', '2026-05-19 01:44:01'),
(657, '26 - 062', 'MARY JANE', 'ALDAMA', 'CLAVEL', '1989-06-09', 'TRECE MARTIRES, CITY', 'SINGLE', 'ROMAN CATHOLIC', 'MALE', '', '33-37744199-9', '271-740-034', '4108', 'BLOCK 13, LOT 18, SPRINGTWON NORTHGATE 2, BUCAL, TANZA, CAVITE', '', 'MASTER DEGREE', '', 'GOVERNMENT EMPLOYEE', '', '2026-05-19 01:44:01'),
(658, '26 - 090', 'AMRANA BERNARDITA', 'DULPINA', 'LIM', '1962-04-25', 'SAN JUAN, METRO, MANILA', 'WIDOW', 'SLAM', 'FEMALE', '', '', '', '4108', 'PHASE 2, BLOCK 1, LOT 1, SECTION 21, PABAHAY BAGTAS, TANZA, CAVITE', '', 'ELEMENTARY', '', '', '', '2026-05-19 01:44:01'),
(659, '26 - 074', 'ZALDY', 'ABAD', 'MALASAN', '1977-12-24', 'SANTA BARBARA, PANGASINAN', 'SEPERATED', 'ROMAN CATHOLIC', 'MALE', '', '', '', '', 'BLOCK 5, LOT 6, SECTION 18, PHASE 2 PABAHAY BAGTAS, TANZA, CAVITE', '', 'COLLEGE GRADUATE', '', 'PROJECT BASED PSA', '', '2026-05-19 01:44:01'),
(660, '26 - 099', 'REINE LEEN', 'SAPATUA', 'MENDOZA', '1992-01-15', 'CAVITE, CITY', 'MARRIED', 'ROMAN CATHOLIC', 'FEMALE', '', '', '', '4108', 'BLOCK 100, LOT 1, PHASE 4, SPRINGTOWN VILLAS, TANZA, CAVITE', '', 'COLLEGE GRADUATE', 'TGP/PHARMACY', '', '15,000', '2026-05-19 01:44:01'),
(661, '26 - 101', 'JOSILYN', 'ENCARNATION', 'MENDOZA', '1966-11-26', 'SILANG, CAVITE', 'MARRIED', 'ROMAN CATHOLIC', 'FEMALE', '', '', '', '', 'BLOCK 100, LOT 1, PHASE 4, SPRINGTOWN VILLAS, BUCAL, TANZA, CAVITE', '', 'VOCATIONAL', '', 'PRIVATE EMPLOYEE', '', '2026-05-19 01:44:01'),
(662, '26 - 071', 'CHARITO', 'EBORA', 'MERCADO', '1979-11-08', 'BAGTAS, TANZA, CAVITE', '', 'ROMAN CATHOLIC', 'MALE', '', '', '', '4108', 'BAGTAS, TANZA, CAVITE', '', '', 'MUNICIPALITY', '', '', '2026-05-19 01:44:01'),
(663, '26 - 033', 'MARIBEL', 'SARMIENTO', 'MAGLIAN', '1964-11-11', 'ROSARIO, CAVITE', 'SINGLE', 'ROMAN CATHOLIC', 'FEMALE', '', '', '161-149-385', '4108', '063 PARADAHAN I, TANZA, CAVITE', 'HEAVENLY GARDEN, OSORIO TRECE MARTINES', 'HIGH SCHOOL GRADUATE', 'TRECE MARTIRES, CITY', 'SELF EMPLOYED', '4,000 - 4,999', '2026-05-19 01:44:01'),
(664, '26 - 091', 'SHIRLEY', 'LANDICHO', 'MORALINA', '1987-11-27', 'SARIAYA, QUEZON', 'WIDOW', 'ROMAN CATHOLIC', 'FEMALE', '', '34-6720786-7', '700-182-577', '4108', 'BLOCK 7, LOT 2, PHASE 2, PABAHAY BAGTAS, TANZA, CAVITE', '', 'HIGH SCHOOL GRADUATE', 'GLOBALTEX', 'EMPLOYED', '10,000 - 15, 000', '2026-05-19 01:44:01'),
(665, '26 - 035', 'NEIL PATRICK', 'DUMO', 'MAICO', '1997-06-24', 'BACOOR, CAVITE', 'SINGLE', 'ROMAN CATHOLIC', 'MALE', '', '', '329-694-834', '', 'PHASE 7, SECTION 3, BLOCK 10, LOT 13, BELVEDERE TOWN, PARADAHAN I, TANZA, CAVITE', '', 'HIGH SCHOOL GRADUATE', '', 'SELF EMPLOYED', '10,000 - 15,000', '2026-05-19 01:44:01'),
(666, '26 - 056', 'MARLON', 'MABANTASAG', 'NUESTRO', '1979-09-02', 'MAKATI, MANILA', 'MARRIED', 'CHRISTIAN', 'MALE', '', '33-4097009-1', '', '4108', 'PHASE 1, BLOCK 6, LOT 16, CHERRY STREET, MICARA ESTATE, SAHUD ULAN, TANZA, CAVITE', '', 'COLLEGE GRADUATE', '', 'ENTREPRENEUR', '5,000 - 9,999', '2026-05-19 01:44:01'),
(667, '26 - 031', 'JANET', 'CREDO', 'OLALIA', '1973-04-05', 'LIB, CAM SUR', 'MARRIED', 'ROMAN CATHOLIC', 'FEMALE', '', '', '164-740-264-000', '', 'BLOCK 25, LOT 11, PHASE 6, CARISSA HOMES, PUNTA I, TANZA, CAVITE', '', 'SECOND YEAR COLLEGE', '', 'REAL ESTATE AGENT', '4,000 - 4,999', '2026-05-19 01:44:01'),
(668, '26 - 097', 'REINA VEE', 'SAPATHA', 'OMAY', '1992-06-15', 'CAVITE CITY', 'MARRIED', 'ROMAN CATHOLIC', 'FEMALE', '', '', '', '4108', 'BLOCK 46, LOT 30, PHASE 1, BPU, BUCAL, TANZA, CAVITE', '', 'COLLEGE GRADUATE', 'BIGASAN', 'PRIVATE EMPLOYEE', '5,000 - 9,999', '2026-05-19 01:44:01'),
(669, '26 - 060', 'JOCELYN', 'RODELOS', 'VILLANUEVA', '1976-11-14', 'QUEZON PROVINCE', 'MARRIED', 'CHRISTIAN', 'FEMALE', 'FILIPINO', '33-4301353-1', '446-388-460-000', '4108', 'REBISCO GILIDS, HDA I, SITIO POSTEMA, SAHUD-ULAN, TANZA, CAVITE', '', 'HIGSCHOOL GRADUATE', '', 'SELF - EMPLOYED', '4,000 - 4,999', '2026-05-19 01:44:01'),
(670, '26-083', 'GONZALES RANNAH', 'MAE', 'FUSEBIO', '1994-05-13', 'BACOOR CAVITE', 'SINGLE', 'CATHOLIC', 'FEMALE', '', '', '', '4108', 'BLOCK  3, LOT 12, SECTION 25, PHASE 2 PABAHAY BAGTAS, TANZA, CAVITE', '', 'HIGHSCHOOL', '', 'HOUSE KEEPER', '15,000', '2026-05-19 01:44:01'),
(671, '26-058', 'REYNAFEL', 'NOGIZA', 'GUARIN', '1980-08-18', 'BUNGA TANZA, CAVITE', 'SINGLE', 'CATHOLIC', 'FEMALE', '', '33-6371521-7', '', '4108', 'PUROK 3 BUNGA, TANZA, CAVITE', '', 'HIGHSCHOOL', '', '', '', '2026-05-19 01:44:01'),
(672, '26-098', 'PRINCESS ANNE', 'NERA', 'GALAROSA', '1985-03-03', 'ATIMONAN QUEZON', 'MARRIED', 'CATHOLIC', 'FEMALE', '', '', '', '4108', 'BLOCK 100, LOT 5, PHASE 4, SPRINGTOWN VILLAS, BUCAL TANZA, CAVITE', '', 'COLLEGE DEGREE', '', '', '', '2026-05-19 01:44:01'),
(673, '26-054', 'ELSA', 'GRANADO', 'GIMAO', '1976-11-21', 'BULAN, SORSOGON', 'MARRIED', 'CATHOLIC', 'FEMALE', '', '', '', '4108', 'BLOCK 26, LOT 20, LUMINA PHASE 4, BAGTAS, TANZA, CAVITE', '', 'COLLEGE UNDER GRADUATE', '', 'HOUSE KEEPER', '5,000 - 9,999', '2026-05-19 01:44:01'),
(674, '26-070', 'MICHELLE', 'FRANCISCO', 'GARCIA', '1978-04-30', 'CALAPAN, ORIENTAL MINDORO', 'MARRIED', 'CATHOLIC', 'FEMALE', '', '', '', '4108', '327 A. SORIANO HIGHWAY, SAHUD-ULAN, TANZA, CAVITE', '428 A. SORIANO HIGHWAY AMAYA II, TANZA, CAVITE', 'COLLEGE GRADUATE', 'R.A DEL ROSARIO CONSTRUCTION', 'PRIVATE EMPLOYEE', '10,000 - 15,000', '2026-05-19 01:44:01'),
(675, '25-017', 'CATHERINE', 'ARMINTIA', 'HORARIO', '1985-02-24', 'ROSARIO, CAVITE', 'MARRIED', 'CATHOLIC', 'FEMALE', '', '', '', '4108', 'BLOCK 7 LOT 33 TANZA GREEN HEIGHTS, SANJA MAYOR, TANZA, CAVITE', 'DAANG AMAYA II', 'HIGHSCHOOL GRAD', '', '', '', '2026-05-19 01:44:01'),
(676, '26-063', 'MEILNDA', 'NOSCAL', 'JARQUIO', '1974-05-24', 'MAKATI CITY', 'MARRIED', 'CATHOLIC', 'FEMALE', '', '', '', '4108', 'BLOCK 1 LOT 14 SECTION 19 PHASE 2, PABAHAY, BAGTAS, TANZA, CAVITE', '', 'SECOND HIGHSCHOOL', '', '', '', '2026-05-19 01:44:01'),
(677, '26-050', 'RUELYN', 'VILLARUEL', 'LITA', '1969-08-08', 'ROXAS CITY', 'MARRIED', 'CATHOLIC', 'FEMALE', '', '', '', '4108', 'BLOCK 3 LOT 28 PHASE 3, LUMINA BAGTAS', '', '', '', '', '', '2026-05-19 01:44:01'),
(678, '26-087', 'MYRA JOY', 'ENCINAS', 'LLAMASARES', '1971-08-20', 'MAYAO, OAS, ALBAY', 'MARRIED', 'CATHOLIC', 'FEMALE', '', '0033-150-1772-7', '', '4108', 'BLOCK 79 LOT 2 PHASE A, CARISSA HOMES, BRGY. BAGTAS, TANZA, CAVITE', '', 'COLLEGE', '', 'SELF-EMPLOYED', '10,000 - 15,000', '2026-05-19 01:44:01'),
(679, '26-064', 'PIA MARIS', 'CABUHAT', 'LUSABIA', '1993-08-26', 'ROSARIO, CAVITE', 'MARRIED', 'CATHOLIC', 'FEMALE', '', '', '', '4108', 'BLOCK 9 LOT 1 SECTION 2 PHASE 1, PABAHAY BAGTAS, TANZA, CAVITE', '', 'COLLEGE', '', 'ENTREPRENEUR/VENDOR', '4,000 - 4,999', '2026-05-19 01:44:01'),
(680, '26-086', 'ANNALIZA', 'TANILON', 'LADISLA', '1981-02-06', 'CARMONA CAVITE', 'MARRIED', 'CATHOLIC', 'FEMALE', '', '33-8377615-5', '', '4108', 'PHASE 2 BLOCK 4 LOT 1 AND 3 SECTION 21, PABAHAY 2000 BAGTAS, TANZA, CAVITE', '', 'HIGHSCHOOL GRADUATE', '', 'SELF-EMPLOYED', '10,000 - 15,000', '2026-05-19 01:44:01'),
(681, '26-039', 'JOEL', 'LAXINA', 'PRIJOLES', '1981-07-16', 'CAVITE CITY', 'SINGLE', 'CATHOLIC', 'MALE', '', '', '', '4108', '262 S.P. SANTILLAN STREET, BIWAS, TANZA, CAVITE', '', 'HIGHSCHOOL GRADUATE', 'BUSSINESS OWNER', '', '15,000', '2026-05-19 01:44:01'),
(682, '26-057', 'ROWENA', 'MAMALUMPONG', 'PANAYAMAN', '1982-06-05', 'SARANGANI PROVINCE', 'SINGLE', 'ISLAM', 'FEMALE', 'MUSLIM', '0111-8870515-8', '455-400-027', '4108', 'BLOCK 16 LOT 19 ISTANA B, BRGY. BIGA TANZA, CAVITE', '', 'HIGHSCHOOL GRADUATE', '', 'HOUSE KEEPER', '', '2026-05-19 01:44:01'),
(683, '26-034', 'JASMIN', 'SABORDO', 'PEREZ', '1978-01-07', 'TANZA, CAVITE', 'MARRIED', 'CATHOLIC', 'FEMALE', '', '3339304843', '184-170-568-000', '4108', '091 PARADAHAN I, TANZA, CAVITE', '', 'HIGHSCHOOL GRADUATE', '', 'HOME BAKER', '4,000 - 4,999', '2026-05-19 01:44:01'),
(684, '26-051', 'FE', 'REPUYA', 'PAPIO', '1970-10-25', 'CALAUAG QUEZON', 'MARRIED', 'CATHOLIC', 'FEMALE', '', '', '', '4108', 'BLOCK 36 LOT 20 PHASE 5, CARISSA HOME SUBDIVISION, BAGTAS, TANZA, CAVITE', '', '', '', 'MANICURIST', '15,000', '2026-05-19 01:44:01'),
(685, '26-080', 'AARON STEVEN', 'TULAY', 'PADUA', '1998-08-03', 'TANZA, CAVITE', 'SINGLE', 'CATHOLIC', 'MALE', '', '', '', '4108', 'BLOCK 17 LOT 3 LHINETTE HOMES, BIGA, TANZA, CAVITE', '428 A. SORIANO HIGHWAY, AMAYA II, TANZA, CAVITE', 'COLLEGE GRADUATE', 'R.A DEL ROSARIO CONSTRUCTION', 'PRIVATE EMPLOYEE', '15,000', '2026-05-19 01:44:01'),
(686, '26-049', 'LEAH', 'POLLAROSTE', 'QUINTANA', '1966-06-28', 'SILAY CITY, NEGROS OCCIDENTAL', 'MARRIED', 'IGLESIA NI CRISTO', 'FEMALE', '', '', '', '4108', 'BLOCK 43 LOT 21 PHASE 3, CARISSA HOMES, BAGTAS, TANZA, CAVITE', '', 'HIGHSCHOOL GRADUATE', '', 'PENSIONER', '5,000 - 9,999', '2026-05-19 01:44:01'),
(687, '26-048', 'RIZALITO', 'VERASTIGUE', 'QUISTO', '1963-06-14', 'ATIMONAN QUEZON', 'MARRIED', 'CATHOLIC', 'MALE', '', '404897479', '115-580-263', '4108', 'BLOCK 43 LOT 3 PHASE 3 CARISSA HOMES, BAGTAS, TANZA, CAVITE', '', 'SECONDARY', '', 'SELF-EMPLOYED', '3,000 - 3,999', '2026-05-19 01:44:01'),
(688, '26-092', 'ROJO', 'DYLAN', 'YU', '1982-08-31', 'MANILA', 'MARRIED', 'CATHOLIC', 'MALE', '', '33-9199821-3', '236-070-291', '4108', 'PHASE ! BLOCK 25 LOT 67, SANJA MAYOR SUBDIVISION BRGY. SANJA MAYOR, TANZA, CAVITE', '', '', '', '', '', '2026-05-19 01:44:01'),
(689, '26-053', 'ANALYN', 'ANDAL', 'RONQUILLO', '1973-03-17', 'CALAUAG, QUEZON', 'MARRIED', 'CATHOLIC', 'FEMALE', '', '', '', '4108', 'BLOCK 3 LOT 3 PHASE 2, CARRISA HOMES, BAGTAS, TANZA, CAVITE', '', 'COLLEGE GRADUATE', 'B.O.D PHASE 2 CARISSA HOMES (HDA)', 'B.O.D', '', '2026-05-19 01:44:01'),
(690, '26-038', 'EMMANUEL', 'OCIANA', 'ROBLES', '1970-12-24', 'NARRA, PALAWAN', 'MARRIED', 'CATHOLIC', 'MALE', 'FILIPINO', '33-1326408-0', '154-008-516', '4108', 'PHASE 4 BLOCK 39 LOT 9, SPRINGFIELD VIEW SUBDIVISION, BRGY. SAHUD-ULAN, TANZA, CAVITE', '', 'COLLEGE GRADUATE', 'SELF-EMPLOYED', 'ENTREPRENEUR/VENDOR', '', '2026-05-19 01:44:01'),
(691, '26-096', 'JUDY', 'BERMUNDO', 'ROMBAOA', '1979-10-31', 'MANILA', 'SINGLE', '', 'FEMALE', '', '33-5142270-3', '', '4108', 'BLOCK 22 LOT 24, ISTANA, TANZA PHASE B BRGY. BIGA,TANZA, CAVITE', '', '', '', 'SELF-EMPLOYED', '', '2026-05-19 01:44:01'),
(692, '25-027', 'GINA', 'BETIA', 'SOLIMAN', '1964-10-28', 'BACOLOD, CITY', 'MARRIED', 'CATHOLIC', 'FEMALE', '', '', '', '4108', 'BLOCK 33 LOT 15 PHASE 3, CARISSA HOMES, BAGTAS, TANZA, CAVITE', '', 'COLLEGE UNDER GRADUATE', '', '', '', '2026-05-19 01:44:01'),
(693, '26-085', 'RICHELOA', 'LATAGAN', 'SERRANO', '1970-09-19', 'BICOL', 'MARRIED', 'CHRISTIAN', 'FEMALE', '', '', '', '4108', 'BLOCK 37 LOT 5 PHASE 3, CARISSA HOMES, BAGTAS, TANZA, CAVITE', '', 'HIGHSCHOOL GRADUATE', '', 'PRIVATE EMPLOYEE', '', '2026-05-19 01:44:01'),
(694, '26-052', 'NIKKO RIEL', 'BETIA', 'SOLIMAN', '1991-03-17', 'MAKATI, MANILA', 'SINGLE', 'CATHOLIC', 'MALE', '', '34-2792704-8', '312190530000', '4108', 'BLOCK 33 LOT 15 PHASE 3, CARISSA HOMES, BAGTAS, TANZA, CAVITE', '', 'COLLEGE GRADUATE', 'INGRAM MICRO PHILIPPINES', 'PRIVATE EMPLOYEE', '15,000', '2026-05-19 01:44:01'),
(695, '26-077', 'ARCHIE', 'EUSEBIO', 'SARDILLA', '1983-11-02', 'BACOOR, CAVITE', 'SINGLE', 'CHRISTIAN', 'MALE', '', '33-8064641-1', '311326622-0000', '4108', 'BLOCK 2 LOT 6 SECTION 25, PABAHAY, BAGTAS, TANZA, CAVITE', '', 'COLLEGE GRADUATE', '', 'SELF-EMPLOYED', '10,000 - 15,000', '2026-05-19 01:44:01'),
(696, '26-036', 'ADELINA', 'ARIGO', 'SOLAYAO', '1986-02-02', 'TANZA, CAVITE', 'MARRIED', 'CATHOLIC', 'FEMALE', '', '03-9386488-5', '', '4108', '', '', '', '', '', '', '2026-05-19 01:44:01'),
(697, '26-045', 'NEIL', 'ARGIN', 'SOLIMAN', '1992-09-06', 'MANILA', 'SINGLE', 'CATHOLIC', 'FEMALE', '', '021-1628-5920-8', '476-369-294', '4108', 'BLOCK 33 LOT 15 PHASE 3, CARISSA HOMES, BAGTAS, TANZA, CAVITE', '', 'COLLEGE GRADUATE', 'METROPOLITAN TRIAL COURT', 'GOVERNMENT EMPLOYEE', '15,000', '2026-05-19 01:44:01'),
(698, '26-044', 'SOLIMAN MARC', 'GINEL', 'BETIA', '1987-10-08', 'VALENZUELA, CITY', 'SINGLE', 'CATHOLIC', 'FEMALE', '', '', '292216109', '4108', 'BLOCK 33 LOT 15 PHASE 3, CARISSA HOMES, BAGTAS, TANZA, CAVITE', '', 'POST GRADUATE', 'STA. ANA HOSPITAL', 'GOVERNMENT EMPLOYEE', '15,000', '2026-05-19 01:44:01'),
(699, '26-084', 'EMMA', 'RAMIREZ', 'SALVADOR', '1969-12-11', 'CEBU, CITY', 'WIDOWED', 'CATHOLIC', 'FEMALE', '', '', '', '4108', 'BLOCK 37 LOT 10 & 11 PHASE 1, LUMINA BAGTAS, TANZA, CAVITE', 'BLOCK 37 LOT 10 PHASE 1, LUMINA BAGTAS, TANZA, CAVITE', 'HIGHSCHOOL GRADUATE', 'ABOT KAMAY SARI-SARI STORE LIVELIHOOD', 'BUSINESS', '2,000 - 2,999', '2026-05-19 01:44:01'),
(700, '26-075', 'MA. VICTORIA', 'CABUHAT', 'SALVADOR', '1979-04-07', 'TANZA, CAVITE', 'MARRIED', 'CATHOLIC', 'FEMALE', '', '334208033-2', '', '4108', 'BLOCK 8 LOT 19 SECTION 2, PABAHAY, BAGTAS, TANZA, CAVITE', '', 'COLLEGE GRADUATE', '', '', '', '2026-05-19 01:44:01'),
(701, '26-094', 'MICHELL', 'HERNANDEZ', 'TECSON', NULL, 'CALOOCAN', 'MARRIED', 'CATHOLIC', 'FEMALE', '', '', '', '4108', 'PHASE 4 BLOCK 100 LOT 13, SPRINGTOWN VILLAS BRGY. BUCAL, TANZA, CAVITE', '', '', '', 'SELF-EMPLOYED', '5,000 - 9,999', '2026-05-19 01:44:01'),
(702, '26-073', 'MARIA SHELLA', 'MADRDEJO', 'VILLALON', '1973-03-10', 'PATEROS, RIZAL', 'WIDOWED', 'CATHOLIC', 'FEMALE', '', '', '', '4108', 'BLOCK 5 LOT 6 SECTION 18 PHASE 2, BAGTAS, TANZA, CAVITE', '', 'COLLEGE GRADUATE', '', 'PENSIONER', '5,000 - 9,999', '2026-05-19 01:44:01'),
(703, '26-061', 'VICTORIA', 'MORENO', 'VALIENTE', '1965-02-13', 'CALOOCAN, CITY', 'MARRIED', 'CATHOLIC', 'FEMALE', '', '', '', '4108', 'SOUTHGATE I BLOCK 18 LOT 34, BUCAL, TANZA, CAVITE', '', 'COLLEGE GRADUATE', 'PARTY NEEDS/TABLES & CHAIRS/BALLOOON/CLOWN', 'SELF-EMPLOYED', '', '2026-05-19 01:44:01'),
(704, '26-078', 'ANALISA', 'EUSEBIO', 'VASQUEZ', '1979-01-13', 'BACOOR, CAVITE', 'MARRIED', 'CATHOLIC', 'FEMALE', '', '', '', '4108', '', '', 'ELEMENTARY GRADUATE', '', 'SELF-EMPLOYED', '10,000 - 15,000', '2026-05-19 01:44:01'),
(705, '26-037', 'CONCEPCION', 'CHALAN', 'ZABALA', '1966-12-08', 'TANZA, CAVITE', 'SINGLE', 'CATHOLIC', 'FEMALE', '', '', '123-219-429-00000', '4108', '', '', 'HIGHSCHOOL GRADUATE', '', 'SCRAP BUYING', '', '2026-05-19 01:44:01'),
(706, '26-046', 'NATIVIDAD', 'EBORA', 'MERCADO', '1955-06-20', 'TAYSAN BATANGAS', 'MARRIED', 'CATHOLIC', 'FEMALE', '', '', '', '4108', '0317 CALLE 30 BAGTAS TANZA', '', 'ELEMENTARY GRADUATE', '', '', '', '2026-05-19 01:44:10'),
(707, NULL, 'LORELI', 'GUIRIBA', 'CRUZAT', '1983-06-24', 'CAMALIG ALBAY', 'MARRIED', '', 'FEMALE', '', '', '', '4108', 'PHASE 1 BLOCK 8 LOT 15 LUMINA  HOMES BAGTAS', '', '', '', '', '', '2026-05-19 01:44:10'),
(709, NULL, 'LEA', 'D.', 'CAREL', '1985-08-17', 'GENERAL TRIAS CAVITE', 'MARRIED', '', 'FEMALE', '', '', '286-776-000', '4108', 'BLOCK 6 LOT 1 HIDDEN BROOKE EXECUTIVE VILLAGE AMAYA 2 TANZA CAVITE', '', '', '', '', '', '2026-05-19 01:44:10'),
(710, '25-012', 'SHARON', '', 'DELA CRUZ', '1983-01-24', 'MASBATE', 'MARRIED', 'BORN AGAIN CHRIST', 'FEMALE', '', '', '300-778-829-00000', '4108', 'BLOCK 20 LOT 10 PHASE 1 SANJA MAYOR SUB. TANZA CAVITE', '', 'COLLEGE UNDERGRADUATE', '', '', '', '2026-05-19 01:44:10'),
(712, '26-103', 'MARIFI', 'MEDINA', 'CAMPANER', '1982-02-05', 'MANILA', 'SINGLE', 'CATHOLIC', 'FEMALE', '', '0033-663-0701-7', '', '4108', 'SPRINGTOWN VILLAS PH2 BLOCK 95 LOT 25, BUCAL, TANZA, CAVITE', '', 'HIGHSCHOOL GRADUATE', '', '', '', '2026-05-19 01:44:10'),
(713, '26-040', 'LETICIA', 'HORNACHO', 'COSTA', '1975-02-25', 'MANILA', 'MARRIED', 'CATHOLIC', 'FEMALE', '', '', '', '4108', '0509 PARADAHAN 1 TANZA CAVITE', '', 'VOCATIONAL GRADUATE', '', '', '', '2026-05-19 01:44:10'),
(714, NULL, 'JOSEFINA', 'BARTOLOME', 'DORMIDO', '1971-11-23', 'CAUAYAN ISABELA', 'MARRIED', 'CHRISTIAN', 'FEMALE', '', '', '', '4108', 'BLOCK 12 LOT 8 SECTION 17 PH2 PABAHAY BAGTAS TANZA CAVITE', '', 'HIGHSCHOOL GRADUATE', '', '', '', '2026-05-19 01:44:10'),
(715, '26-055', 'JOAN', 'ANTONA', 'DELOS SANTOS', '1980-11-13', 'CALOOCAN CITY', 'SINGLE', 'CATHOLIC', 'FEMALE', '', '33-5531479-4', '', '4108', 'BLOCK 8 LOT 7 PHASE 2 SANJA MAYOR TANZA CAVITE', '', 'COLLEGE GRADUATE', '', '', '', '2026-05-19 01:44:10'),
(716, '26-069', 'MARICIS', 'TIMPOC', 'DIAZ', '1982-07-09', 'HALAYHAY, TANZA, CAVITE', 'MARRIED', 'CATHOLIC', 'FEMALE', '', '', '301-614-880-000', '4108', 'HALAYHAY, TANZA, CAVITE', '', 'COLLEGE GRADUATE', '', '', '', '2026-05-19 01:44:10'),
(717, '26-100', 'REDENTOR', 'DELORA', 'DELA CUEVA', '1980-07-08', 'PASIG, MANILA', 'MARRIED', 'CATHOLIC', 'MALE', '', '071756417-2', '', '4108', 'PHASE 7 PUNTA 1 TANZA CAVITE', '', 'VOCATIONAL GRADUATE', '', '', '', '2026-05-19 01:44:10'),
(718, '26-095', 'MARIE JOY', 'SALVADOR', 'DALANGIN', '1983-03-30', 'BIñAN, LAGUNA', 'MARRIED', 'CATHOLIC', 'FEMALE', '', '04-1069284-6', '251-195-211', '4108', 'BLOCK 30 LOT 49 PHASE 1 SPRINGTOWN VILLAS BRGY BUCAL TANZA', '', 'COLLEGE GRADUATE', '', '', '', '2026-05-19 01:44:10'),
(719, NULL, 'JENNELYN', 'ALINDUGAN', 'DELA PEñA', '1987-01-31', 'PASAY', 'SINGLE', 'CATHOLIC', 'FEMALE', '', '', '', '4108', 'BLOCK 4 LOT 211 PHASE B ISTANA TANZA SUBD. 17TH STREET BIGA CAVITE', '', 'COLLEGE GRADUATE', '', '', '', '2026-05-19 01:44:10'),
(720, '25-013', 'EMELINA', 'TOLEDO', 'DILIDILI', '1970-06-26', 'TRES CRUSES TANZA CAVITE', '', 'CATHOLIC', 'FEMALE', '', '', '', '4108', 'TRES CRUSES TANZA CAVITE', '', 'COLLEGE GRADUATE', '', '', '', '2026-05-19 01:44:10'),
(721, NULL, 'LUCIA', 'R.', 'DALAY', '1976-04-17', 'SALCEDO EASTERN SAMAR', 'MARRIED', '', 'FEMALE', '', '', '', '4108', 'SANJA MAYOR TANZA CAVITE', '', '', '', '', '', '2026-05-19 01:44:10'),
(722, NULL, 'RHESA', 'MALAYO', 'DE GUZMAN', '1979-01-22', 'AGBALUTO ROMBLON', 'MARRIED', '', 'FEMALE', '', '', '', '4108', 'BLOCK 54 LOT 24 PHASE 1 LUMINA HOMES BAGTAS TANZA CAVITE', '', '', '', '', '', '2026-05-19 01:44:10'),
(723, '26-088', 'LAUDEMAR', 'REAL', 'FERRER', '1963-12-26', 'BAIS NEGROS', 'MARRIED', 'CATHOLIC', 'MALE', '', '33-0287347-9', '172-8854-88', '4108', 'BLOCK 21 LOT 1 PHASE 1 B CARRISA HOMES BAGTAS TANZA CAVITE', '', 'COLLEGE GRADUTE', '', '', '', '2026-05-19 01:44:10'),
(724, '26-076', 'RAMON MIGUEL', 'ALTAMIA', 'CENTINO', '2001-11-08', 'PASIG CITY', 'SINGLE', 'CATHOLIC', 'MALE', '', '', '', '4108', 'BLOCK 4 LOT 42 SECTION 35 BELLEVIEW MEADOWS BAGTAS TANZA CAVITE', '', 'COLLEGE GRADUATE', '', '', '', '2026-05-21 06:46:12'),
(725, '26-105', 'MARIE PAZ', 'TENEDERO', 'CABUHAT', '1993-08-06', 'TONDO, MANILA', 'MARRIED', 'CATHOLIC', 'FEMALE', '', '', '', '4108', 'BLOCK 8 LOT 8 SECTION 18 PHASE 2 PABAHAY BAGTAS TANZA CAVITE', '', 'COLLEGE GRADUATE', '', '', '', '2026-05-21 06:46:12');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `member_id` int(11) DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `member_name` varchar(255) NOT NULL,
  `transaction_type` varchar(100) NOT NULL,
  `share_payment_type_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `items_details` text DEFAULT NULL,
  `invoice_no` varchar(100) DEFAULT NULL,
  `payment_status` varchar(50) DEFAULT NULL,
  `downpayment` decimal(10,2) DEFAULT NULL,
  `remaining_balance` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `member_id`, `transaction_date`, `member_name`, `transaction_type`, `share_payment_type_id`, `amount`, `items_details`, `invoice_no`, `payment_status`, `downpayment`, `remaining_balance`, `created_at`) VALUES
(272, 662, '2026-02-05', 'MERCADO, CHARITO EBORA', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '48', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(273, 650, '2026-01-25', 'ARAñAS, REBECCA ARIGO', 'Share Capital', 2, 4000.00, 'Payment for Share Capital', '52', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(274, 696, '2026-01-25', 'SOLAYAO, ADELINA ARIGO', 'Share Capital', 2, 4000.00, 'Payment for Share Capital', '57', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(275, 705, '2026-01-25', 'ZABALA, CONCEPCION CHALAN', 'Share Capital', 2, 4000.00, 'Payment for Share Capital', '58', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(276, 690, '2026-01-25', 'ROBLES, EMMANUEL OCIANA', 'Share Capital', 2, 1000.00, 'Payment for Share Capital', '59', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(277, 655, '2026-01-25', 'BARRIENTOS, LUCITA MENDOZA', 'Share Capital', 2, 1000.00, 'Payment for Share Capital', '60', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(278, 706, '2026-01-25', 'MERCADO, NATIVIDAD EBORA', 'Share Capital', 2, 1000.00, 'Payment for Share Capital', '70', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(279, 686, '2026-01-25', 'QUINTANA, LEAH POLLAROSTE', 'Share Capital', 2, 1000.00, 'Payment for Share Capital', '71', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(280, 673, '2026-01-25', 'GIMAO, ELSA GRANADO', 'Share Capital', 2, 1000.00, 'Payment for Share Capital', '76', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(281, 682, '2026-01-25', 'PANAYAMAN, ROWENA MAMALUMPONG', 'Share Capital', 2, 3000.00, 'Payment for Share Capital', '79', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(282, 671, '2026-01-25', 'GUARIN, REYNAFEL NOGIZA', 'Share Capital', 2, 1000.00, 'Payment for Share Capital', '80', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(283, 681, '2026-01-25', 'PRIJOLES, JOEL LAXINA', 'Share Capital', 2, 10000.00, 'Payment for Share Capital', '81', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(284, 669, '2026-01-25', 'VILLANUEVA, JOCELYN RODELOS', 'Share Capital', 2, 1000.00, 'Payment for Share Capital', '84', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(285, 713, '2026-01-25', 'COSTA, LETICIA HORNACHO', 'Share Capital', 2, 1000.00, 'Payment for Share Capital', '90', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(286, 651, '2026-01-25', 'ARADO, ZENAIDA ARIGO', 'Share Capital', 2, 1000.00, 'Payment for Share Capital', '91', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(287, 717, '2026-01-25', 'DELA CUEVA, REDENTOR DELORA', 'Share Capital', 2, 1000.00, 'Payment for Share Capital', '94', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(288, 675, '2026-01-25', 'HORARIO, CATHERINE ARMINTIA', 'Share Capital', 2, 1000.00, 'Payment for Share Capital', '96', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(289, 682, '2026-01-25', 'PANAYAMAN, ROWENA MAMALUMPONG', 'Share Capital', 2, 1000.00, 'Payment for Share Capital', '104', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(290, 699, '2026-01-25', 'SALVADOR, EMMA RAMIREZ', 'Share Capital', 2, 1000.00, 'Payment for Share Capital', '109', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(291, 719, '2026-01-25', 'DELA PEñA, JENNELYN ALINDUGAN', 'Share Capital', 2, 1000.00, 'Payment for Share Capital', '110', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(292, 650, '2026-01-25', 'ARAñAS, REBECCA ARIGO', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '115', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(293, 663, '2026-01-25', 'MAGLIAN, MARIBEL SARMIENTO', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '116', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(294, 696, '2026-01-25', 'SOLAYAO, ADELINA ARIGO', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '119', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(295, 705, '2026-01-25', 'ZABALA, CONCEPCION CHALAN', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '120', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(296, 690, '2026-01-25', 'ROBLES, EMMANUEL OCIANA', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '121', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(297, 655, '2026-01-25', 'BARRIENTOS, LUCITA MENDOZA', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '122', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(298, 713, '2026-01-25', 'COSTA, LETICIA HORNACHO', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '123', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(299, 651, '2026-01-25', 'ARADO, ZENAIDA ARIGO', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '124', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(300, 706, '2026-01-25', 'MERCADO, NATIVIDAD EBORA', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '129', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(301, 656, '2026-01-09', 'BOBADILLA, ARMINDA VIRAY', 'Share Capital', 2, 1000.00, 'Payment for Share Capital', '142', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(302, 720, '2026-01-09', 'DILIDILI, EMELINA TOLEDO', 'Share Capital', 2, 1000.00, 'Payment for Share Capital', '147', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(303, 699, '2026-02-09', 'SALVADOR, EMMA RAMIREZ', 'Share Capital', 2, 1000.00, 'Payment for Share Capital', '177', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(304, 686, '2026-01-25', 'QUINTANA, LEAH POLLAROSTE', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '188', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(305, 684, '2026-01-25', 'PAPIO, FE REPUYA', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '190', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(306, 689, '2026-01-25', 'RONQUILLO, ANALYN ANDAL', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '192', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(307, 673, '2026-01-25', 'GIMAO, ELSA GRANADO', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '193', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(308, 666, '2026-01-25', 'NUESTRO, MARLON MABANTASAG', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '195', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(309, 682, '2026-01-25', 'PANAYAMAN, ROWENA MAMALUMPONG', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '196', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(310, 671, '2026-01-25', 'GUARIN, REYNAFEL NOGIZA', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '197', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(311, 681, '2026-01-25', 'PRIJOLES, JOEL LAXINA', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '198', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(312, 669, '2026-01-25', 'VILLANUEVA, JOCELYN RODELOS', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '199', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(313, 656, '2026-01-09', 'BOBADILLA, ARMINDA VIRAY', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '210', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(314, 720, '2026-01-09', 'DILIDILI, EMELINA TOLEDO', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '213', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(315, 675, '2026-01-09', 'HORARIO, CATHERINE ARMINTIA', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '216', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(316, 692, '2026-01-09', 'SOLIMAN, GINA BETIA', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '227', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(317, 702, '2026-02-20', 'VILLALON, MARIA SHELLA MADRDEJO', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '235', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(318, 659, '2026-02-20', 'MALASAN, ZALDY ABAD', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '236', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(319, 700, '2026-02-20', 'SALVADOR, MA. VICTORIA CABUHAT', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '240', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(320, 724, '2026-02-20', 'CENTINO, RAMON MIGUEL ALTAMIA', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '241', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(321, 695, '2026-02-20', 'SARDILLA, ARCHIE EUSEBIO', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '242', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(322, 704, '2026-02-20', 'VASQUEZ, ANALISA EUSEBIO', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '243', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(323, 648, '2026-02-20', 'ANGKICO, RAYMOND ARMIJO', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '244', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(324, 649, '2026-02-22', 'ARMIJO, JOHN CEDRIC ESTADARTE', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '248', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(325, 685, '2026-02-22', 'PADUA, AARON STEVEN TULAY', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '252', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(326, 714, '2026-02-22', 'DORMIDO, JOSEFINA BARTOLOME', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '253', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(327, 723, '2026-02-22', 'FERRER, LAUDEMAR REAL', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '255', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(328, 658, '2026-02-22', 'LIM, AMRANA BERNARDITA DULPINA', 'Share Capital', 2, 500.00, 'Payment for Share Capital', '256', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(329, 717, '2026-02-22', 'DELA CUEVA, REDENTOR DELORA', 'Share Capital', 2, 3000.00, 'Payment for Share Capital', '271', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(330, 689, '2026-02-22', 'RONQUILLO, ANALYN ANDAL', 'Membership Fee', 1, 200.00, 'Payment for Membership Fee', '272', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(331, 699, '2026-02-22', 'SALVADOR, EMMA RAMIREZ', 'Share Capital', 2, 1000.00, 'Payment for Share Capital', '305', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(332, 680, '2026-02-22', 'LADISLA, ANNALIZA TANILON', 'Share Capital', 2, 500.00, 'Payment for Share Capital', '307', 'COMPLETED', 0.00, 0.00, '2026-06-13 05:56:25'),
(337, NULL, '2026-03-29', 'Salvador, Maria Victoria', 'Sales', NULL, 5600.00, '4 Sack Rice @ ₱1400.00 = ₱5600.00', '236', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(338, NULL, '2026-03-29', 'Calibo, Cherry', 'Sales', NULL, 7200.00, '6 Sack Rice @ ₱1200.00 = ₱7200.00', '237', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(339, NULL, '2026-03-29', 'Salvador, Bek', 'Sales', NULL, 185.00, '1 Tray Egg @ ₱185.00 = ₱185.00', '238', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(340, 682, '2026-03-29', 'PANAYAMAN, ROWENA MAMALUMPONG', 'Sales', NULL, 1400.00, '1 Sack Rice @ ₱1400.00 = ₱1400.00', '239', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(341, NULL, '2026-03-27', 'Bamba, Berna', 'Sales', NULL, 140.00, '1 Pieces Chicharon @ ₱140.00 = ₱140.00', '232', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(342, NULL, '2026-03-27', 'Morales, Maricel', 'Sales', NULL, 420.00, '3 Pieces Chicharon @ ₱140.00 = ₱420.00', '233', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(343, NULL, '2026-03-27', 'Morales, Maricel', 'Sales', NULL, 980.00, '7 Pieces Chicharon @ ₱140.00 = ₱980.00', '234', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(344, 674, '2026-03-26', 'GARCIA, MICHELLE FRANCISCO', 'Sales', NULL, 80.00, '2 Bottle Fab-Con @ ₱40.00 = ₱80.00', '230', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(345, NULL, '2026-03-26', 'Colon, Maria Eda', 'Sales', NULL, 100.00, '4 Pieces Sardines @ ₱25.00 = ₱100.00', '231', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(346, 682, '2026-03-25', 'PANAYAMAN, ROWENA MAMALUMPONG', 'Sales', NULL, 2800.00, '2 Sack Rice @ ₱1400.00 = ₱2800.00', '229', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(347, NULL, '2026-03-22', 'Void', 'Purchase', NULL, 50.00, '1 Pieces Clothes @ ₱50.00 = ₱50.00', '203', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(348, 699, '2026-03-22', 'SALVADOR, EMMA RAMIREZ', 'Purchase', NULL, 120.00, '1 Pieces Clothes @ ₱120.00 = ₱120.00', '203', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(349, 699, '2026-03-22', 'SALVADOR, EMMA RAMIREZ', 'Purchase', NULL, 100.00, '1 Pieces Clothes @ ₱100.00 = ₱100.00', '204', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(350, NULL, '2026-03-22', 'Cruz, Sharon', 'Purchase', NULL, 200.00, '2 Pieces Clothes @ ₱100.00 = ₱200.00', '205', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(351, NULL, '2026-03-22', 'Void', 'Purchase', NULL, 100.00, '1 Pieces Clothes @ ₱100.00 = ₱100.00', '207', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(352, NULL, '2026-03-22', 'Void', 'Purchase', NULL, 200.00, '1 Pieces Clothes @ ₱200.00 = ₱200.00', '208', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(353, NULL, '2026-03-22', 'Legaspi, Maricel', 'Purchase', NULL, 80.00, '1 Pieces Clothes @ ₱80.00 = ₱80.00', '209', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(354, NULL, '2026-03-22', 'Maclay, Virginia', 'Sales', NULL, 80.00, '1 Pieces Clothes @ ₱80.00 = ₱80.00', '210', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(355, NULL, '2026-03-22', 'Void', 'Purchase', NULL, 140.00, '1 Pack Chicharon @ ₱140.00 = ₱140.00', '211', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(356, NULL, '2026-03-22', 'Void', 'Sales', NULL, 80.00, '1 Pack Asukal @ ₱80.00 = ₱80.00', '212', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(357, NULL, '2026-03-22', 'Void', 'Sales', NULL, 120.00, '1 Pack Dried Fish @ ₱120.00 = ₱120.00', '213', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(358, NULL, '2026-03-22', 'Void', 'Sales', NULL, 120.00, '1 Pack Dried Fish @ ₱120.00 = ₱120.00', '214', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(359, NULL, '2026-03-22', 'Void', 'Purchase', NULL, 80.00, '1 Pieces Clothes @ ₱80.00 = ₱80.00', '215', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(360, NULL, '2026-03-22', 'Arcis, Marifer', 'Sales', NULL, 100.00, '1 Bottle Vinegar @ ₱100.00 = ₱100.00', '216', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(361, NULL, '2026-03-22', 'Void', 'Purchase', NULL, 200.00, '1 Pieces Clothes @ ₱200.00 = ₱200.00', '217', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(362, NULL, '2026-03-22', 'Void', 'Sales', NULL, 45.00, '1 Bottle Dishwashing @ ₱45.00 = ₱45.00', '218', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(363, NULL, '2026-03-22', 'Void', 'Purchase', NULL, 40.00, '1 Bottle Fab-Con @ ₱40.00 = ₱40.00', '219', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(364, NULL, '2026-03-22', 'Void', 'Purchase', NULL, 100.00, '1 Pieces Clothes @ ₱100.00 = ₱100.00', '220', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(365, NULL, '2026-03-22', 'De Guzman, Risa', 'Sales', NULL, 45.00, '1 Bottle Dishwashing @ ₱45.00 = ₱45.00', '221', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(366, 699, '2026-03-22', 'SALVADOR, EMMA RAMIREZ', 'Sales', NULL, 40.00, '1 Bottle Fab-Con @ ₱40.00 = ₱40.00', '222', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(367, NULL, '2026-03-22', 'Morales, Maricel', 'Sales', NULL, 140.00, '1 Pack Chicharon @ ₱140.00 = ₱140.00', '223', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(368, NULL, '2026-03-22', 'Legaspi, Maricel', 'Purchase', NULL, 80.00, '1 Pieces Clothes @ ₱80.00 = ₱80.00', '224', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(369, NULL, '2026-03-22', 'Void', 'Sales', NULL, 100.00, '1 Bottle Vinegar @ ₱100.00 = ₱100.00', '225', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(370, NULL, '2026-03-22', 'Legaspi, Puring', 'Sales', NULL, 280.00, '2 Pack Chicharon @ ₱140.00 = ₱280.00', '226', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(371, NULL, '2026-03-22', 'Void', 'Purchase', NULL, 80.00, '1 Pieces Clothes @ ₱80.00 = ₱80.00', '227', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(372, NULL, '2026-03-22', 'Cruz, Sharon', 'Purchase', NULL, 80.00, '1 Pieces Clothes @ ₱80.00 = ₱80.00', '228', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(373, NULL, '2026-03-20', 'Ferrer, Rebecca', 'Sales', NULL, 1000.00, '5 Tray Egg @ ₱200.00 = ₱1000.00', '194', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(374, NULL, '2026-03-20', 'Salvador, Maria Victoria', 'Sales', NULL, 7000.00, '5 Sack Rice @ ₱1400.00 = ₱7000.00', '195', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(375, NULL, '2026-03-20', 'Garcia, Michelle T', 'Sales', NULL, 80.00, '1 Pack Detergent @ ₱80.00 = ₱80.00', '196', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(376, NULL, '2026-03-20', 'Orate, Yolanda', 'Sales', NULL, 210.00, '1 Tray Egg @ ₱210.00 = ₱210.00', '198', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(377, NULL, '2026-03-20', 'Malasan, Zaldy A', 'Sales', NULL, 1400.00, '1 Sack Rice @ ₱1400.00 = ₱1400.00', '200', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(378, NULL, '2026-03-19', 'Gesta, Helena', 'Sales', NULL, 210.00, '1 Tray Egg @ ₱210.00 = ₱210.00', '191', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(379, 692, '2026-03-19', 'SOLIMAN, GINA BETIA', 'Purchase', NULL, 210.00, '1 Tray Egg @ ₱210.00 = ₱210.00', '192', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(380, NULL, '2026-03-19', 'Colon, Maria Eda', 'Sales', NULL, 80.00, '2 Pack Detergent Soap @ ₱80.00 = ₱80.00', '193', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(381, 699, '2026-03-19', 'SALVADOR, EMMA RAMIREZ', 'Sales', NULL, 1050.00, '5 Tray Egg @ ₱210.00 = ₱1050.00', '197', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(382, NULL, '2026-03-18', 'Portuguese, Vrian Andrew', 'Sales', NULL, 240.00, '1 Tray Egg @ ₱240.00 = ₱240.00', '189', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(383, NULL, '2026-03-18', 'Armijo, Airene', 'Sales', NULL, 28000.00, '20 Sack Rice @ ₱1400.00 = ₱28000.00', '190', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(384, NULL, '2026-03-17', 'Armijo, Airene', 'Sales', NULL, 46900.00, '35 Sack Rice @ ₱1340.00 = ₱46900.00', '186', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(385, NULL, '2026-03-17', 'Diaz, Maricris T', 'Sales', NULL, 70.00, '1 Kilo Asukal @ ₱70.00 = ₱70.00', '187', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(386, 720, '2026-03-17', 'DILIDILI, EMELINA TOLEDO', 'Sales', NULL, 120.00, '1 Pack Tuyo @ ₱120.00 = ₱120.00', '188', 'PAID', 0.00, 0.00, '2026-06-18 03:23:59'),
(387, NULL, '2026-03-16', 'Delos Santos, Joann A', 'Sales', NULL, 630.00, '3 Tray Egg @ ₱210.00 = ₱630.00', '183', '', 0.00, 0.00, '2026-06-18 03:23:59'),
(388, NULL, '2026-03-16', 'Amada, Dexter E', 'Sales', NULL, 1340.00, '1 Sack Rice @ ₱1340.00 = ₱1340.00', '184', '', 0.00, 0.00, '2026-06-18 03:23:59'),
(389, NULL, '2026-03-16', 'Araracap, Julia', 'Sales', NULL, 70.00, '1 Kilo Asukal @ ₱70.00 = ₱70.00', '185', '', 0.00, 0.00, '2026-06-18 03:23:59'),
(390, NULL, '2026-03-26', 'Colon, Maria Eda', 'Sales', NULL, 80.00, '2 Bottle Fab-Con @ ₱40.00 = ₱80.00', '231', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(391, NULL, '2026-03-26', 'Colon, Maria Eda', 'Sales', NULL, 80.00, '1 Pack Detergent Soap @ ₱80.00 = ₱80.00', '231', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(392, NULL, '2026-03-22', 'Void', 'Purchase', NULL, 70.00, '1 Pieces Clothes @ ₱70.00 = ₱70.00', '203', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(393, 699, '2026-03-22', 'SALVADOR, EMMA RAMIREZ', 'Purchase', NULL, 600.00, '4 Pieces Clothes @ ₱150.00 = ₱600.00', '203', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(394, NULL, '2026-03-22', 'Cruz, Sharon', 'Purchase', NULL, 150.00, '1 Pieces Clothes @ ₱150.00 = ₱150.00', '205', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(395, NULL, '2026-03-22', 'Legaspi, Maricel', 'Purchase', NULL, 120.00, '1 Pieces Clothes @ ₱120.00 = ₱120.00', '209', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(396, NULL, '2026-03-22', 'Legaspi, Maricel', 'Purchase', NULL, 50.00, '1 Pieces Clothes @ ₱50.00 = ₱50.00', '209', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(397, NULL, '2026-03-22', 'Maclay, Virginia', 'Sales', NULL, 140.00, '1 Pack Chicharon @ ₱140.00 = ₱140.00', '210', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(398, NULL, '2026-03-22', 'Maclay, Virginia', 'Purchase', NULL, 125.00, '5 Pieces Lip @ ₱25.00 = ₱125.00', '210', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(399, NULL, '2026-03-22', 'Maclay, Virginia', 'Purchase', NULL, 50.00, '1 Pieces Clothes @ ₱50.00 = ₱50.00', '210', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(400, NULL, '2026-03-22', 'Maclay, Virginia', 'Purchase', NULL, 80.00, '1 Pieces Clothes @ ₱80.00 = ₱80.00', '210', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(401, NULL, '2026-03-22', 'Void', 'Purchase', NULL, 50.00, '1 Pieces Clothes @ ₱50.00 = ₱50.00', '211', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(402, NULL, '2026-03-22', 'Void', 'Sales', NULL, 230.00, '1 Tray Egg @ ₱230.00 = ₱230.00', '211', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(403, NULL, '2026-03-22', 'Void', 'Sales', NULL, 140.00, '1 Pack Chicharon @ ₱140.00 = ₱140.00', '211', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(404, NULL, '2026-03-22', 'Void', 'Sales', NULL, 160.00, '2 Pack Asukal @ ₱80.00 = ₱160.00', '213', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(405, NULL, '2026-03-22', 'Void', 'Sales', NULL, 100.00, '1 Bottle Vinegar @ ₱100.00 = ₱100.00', '213', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(406, NULL, '2026-03-22', 'Void', 'Sales', NULL, 200.00, '2 Pack Dried Fish @ ₱100.00 = ₱200.00', '213', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(407, NULL, '2026-03-22', 'Arcis, Marifer', 'Sales', NULL, 140.00, '1 Pack Chicharon @ ₱140.00 = ₱140.00', '216', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(408, NULL, '2026-03-22', 'Arcis, Marifer', 'Sales', NULL, 230.00, '1 Tray Egg @ ₱230.00 = ₱230.00', '216', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(409, NULL, '2026-03-22', 'Arcis, Marifer', 'Sales', NULL, 80.00, '1 Pack Asukal @ ₱80.00 = ₱80.00', '216', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(410, NULL, '2026-03-22', 'Void', 'Sales', NULL, 130.00, '1 Bottle Canola Oil @ ₱130.00 = ₱130.00', '218', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(411, NULL, '2026-03-22', 'Void', 'Purchase', NULL, 160.00, '2 Pieces Clothes @ ₱80.00 = ₱160.00', '219', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(412, NULL, '2026-03-22', 'Void', 'Sales', NULL, 40.00, '1 Bottle Fab-Con @ ₱40.00 = ₱40.00', '219', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(413, NULL, '2026-03-22', 'De Guzman, Risa', 'Sales', NULL, 40.00, '1 Bottle Fab-Con @ ₱40.00 = ₱40.00', '221', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(414, NULL, '2026-03-22', 'Morales, Maricel', 'Sales', NULL, 1000.00, '10 Sack Rice @ ₱100.00 = ₱1000.00', '223', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(415, NULL, '2026-03-20', 'Garcia, Michelle T', 'Sales', NULL, 1340.00, '1 Sack Rice @ ₱1340.00 = ₱1340.00', '196', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(416, NULL, '2026-03-20', 'Orate, Yolanda', 'Sales', NULL, 8400.00, '6 Sack Rice @ ₱1400.00 = ₱8400.00', '198', 'PAID', 0.00, 0.00, '2026-06-18 04:02:21'),
(417, NULL, '2026-03-19', 'Gesta, Helena', 'Sales', NULL, 240.00, '1 Tray Egg @ ₱240.00 = ₱240.00', '191', 'PAID', 0.00, 0.00, '2026-06-18 04:02:22'),
(418, 692, '2026-03-19', 'SOLIMAN, GINA BETIA', 'Purchase', NULL, 800.00, '1 Pieces Clothes @ ₱800.00 = ₱800.00', '192', 'PAID', 0.00, 0.00, '2026-06-18 04:02:22'),
(419, 692, '2026-03-19', 'SOLIMAN, GINA BETIA', 'Purchase', NULL, 390.00, '1 Pieces Clothes @ ₱390.00 = ₱390.00', '192', 'PAID', 0.00, 0.00, '2026-06-18 04:02:22'),
(420, 692, '2026-03-19', 'SOLIMAN, GINA BETIA', 'Sales', NULL, 210.00, '1 Tray Egg @ ₱210.00 = ₱210.00', '192', 'PAID', 0.00, 0.00, '2026-06-18 04:02:22'),
(421, 699, '2026-03-19', 'SALVADOR, EMMA RAMIREZ', 'Sales', NULL, 2800.00, '2 Sack Rice @ ₱1400.00 = ₱2800.00', '197', 'PAID', 0.00, 0.00, '2026-06-18 04:02:22'),
(422, 699, '2026-03-19', 'SALVADOR, EMMA RAMIREZ', 'Sales', NULL, 400.00, '2 Tray Egg @ ₱200.00 = ₱400.00', '197', 'PAID', 0.00, 0.00, '2026-06-18 04:02:22'),
(423, NULL, '2026-03-18', 'Portuguese, Vrian Andrew', 'Sales', NULL, 210.00, '2 Tray Egg @ ₱210.00 = ₱210.00', '189', 'PAID', 0.00, 0.00, '2026-06-18 04:02:22'),
(424, NULL, '2026-03-17', 'Diaz, Maricris T', 'Sales', NULL, 210.00, '1 Tray Egg @ ₱210.00 = ₱210.00', '187', 'PAID', 0.00, 0.00, '2026-06-18 04:02:22'),
(425, NULL, '2026-03-17', 'Diaz, Maricris T', 'Sales', NULL, 1340.00, '1 Sack Rice @ ₱1340.00 = ₱1340.00', '187', 'PAID', 0.00, 0.00, '2026-06-18 04:02:22'),
(426, 720, '2026-03-17', 'DILIDILI, EMELINA TOLEDO', 'Sales', NULL, 210.00, '1 Tray Egg @ ₱210.00 = ₱210.00', '188', 'PAID', 0.00, 0.00, '2026-06-18 04:02:22'),
(427, NULL, '2026-03-16', 'Delos Santos, Joann A', 'Sales', NULL, 720.00, '3 Tray Egg @ ₱240.00 = ₱720.00', '183', 'PAID', 0.00, 0.00, '2026-06-18 04:02:22'),
(428, NULL, '2026-03-16', 'Delos Santos, Joann A', 'Sales', NULL, 1125.00, '5 Tray Egg @ ₱225.00 = ₱1125.00', '183', 'PAID', 0.00, 0.00, '2026-06-18 04:02:22'),
(429, NULL, '2026-03-16', 'Delos Santos, Joann A', 'Sales', NULL, 630.00, '3 Tray Egg @ ₱210.00 = ₱630.00', '183', 'PAID', 0.00, 0.00, '2026-06-18 04:02:22'),
(430, NULL, '2026-03-16', 'Amada, Dexter E', 'Sales', NULL, 1340.00, '1 Sack Rice @ ₱1340.00 = ₱1340.00', '184', 'PAID', 0.00, 0.00, '2026-06-18 04:02:22'),
(431, NULL, '2026-03-16', 'Araracap, Julia', 'Sales', NULL, 53.00, '1 Bottle Toyo @ ₱53.00 = ₱53.00', '185', 'PAID', 0.00, 0.00, '2026-06-18 04:02:22'),
(432, NULL, '2026-03-16', 'Araracap, Julia', 'Sales', NULL, 130.00, '1 Bottle Oil @ ₱130.00 = ₱130.00', '185', 'PAID', 0.00, 0.00, '2026-06-18 04:02:22'),
(433, NULL, '2026-03-16', 'Araracap, Julia', 'Sales', NULL, 160.00, '2 Bottle Fab-Con @ ₱80.00 = ₱160.00', '185', 'PAID', 0.00, 0.00, '2026-06-18 04:02:22'),
(434, NULL, '2026-03-16', 'Araracap, Julia', 'Sales', NULL, 70.00, '1 Kilo Asukal @ ₱70.00 = ₱70.00', '185', 'PAID', 0.00, 0.00, '2026-06-18 04:02:22');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_module` (`module`),
  ADD KEY `idx_action` (`action`);

--
-- Indexes for table `beneficiaries`
--
ALTER TABLE `beneficiaries`
  ADD PRIMARY KEY (`beneficiary_id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `config_civil_status`
--
ALTER TABLE `config_civil_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `config_excel_headers`
--
ALTER TABLE `config_excel_headers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `config_inventory_settings`
--
ALTER TABLE `config_inventory_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `config_monthly_income`
--
ALTER TABLE `config_monthly_income`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `config_occupations`
--
ALTER TABLE `config_occupations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `config_product_categories`
--
ALTER TABLE `config_product_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `config_share_payment_types`
--
ALTER TABLE `config_share_payment_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_share_payment_type_name` (`name`),
  ADD KEY `idx_share_payment_type_active` (`is_active`);

--
-- Indexes for table `config_transaction_types`
--
ALTER TABLE `config_transaction_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_transaction_type_name` (`name`),
  ADD KEY `idx_transaction_type_active` (`is_active`);

--
-- Indexes for table `config_unit_types`
--
ALTER TABLE `config_unit_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `inventory_daily_snapshot`
--
ALTER TABLE `inventory_daily_snapshot`
  ADD PRIMARY KEY (`snapshot_date`,`product_id`);

--
-- Indexes for table `inventory_outsourcing`
--
ALTER TABLE `inventory_outsourcing`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`member_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `idx_share_payment_type_id` (`share_payment_type_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `beneficiaries`
--
ALTER TABLE `beneficiaries`
  MODIFY `beneficiary_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1415;

--
-- AUTO_INCREMENT for table `config_civil_status`
--
ALTER TABLE `config_civil_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `config_excel_headers`
--
ALTER TABLE `config_excel_headers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `config_monthly_income`
--
ALTER TABLE `config_monthly_income`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `config_occupations`
--
ALTER TABLE `config_occupations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `config_product_categories`
--
ALTER TABLE `config_product_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `config_share_payment_types`
--
ALTER TABLE `config_share_payment_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1993;

--
-- AUTO_INCREMENT for table `config_transaction_types`
--
ALTER TABLE `config_transaction_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3456;

--
-- AUTO_INCREMENT for table `config_unit_types`
--
ALTER TABLE `config_unit_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `inventory_outsourcing`
--
ALTER TABLE `inventory_outsourcing`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `member_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=727;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=438;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `beneficiaries`
--
ALTER TABLE `beneficiaries`
  ADD CONSTRAINT `beneficiaries_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_outsourcing`
--
ALTER TABLE `inventory_outsourcing`
  ADD CONSTRAINT `inventory_outsourcing_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `inventory` (`product_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
