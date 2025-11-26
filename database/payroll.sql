-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 26, 2025 at 09:19 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `nigeria_payroll_hr`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance_records`
--

CREATE TABLE `attendance_records` (
  `attendance_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `total_hours` decimal(4,2) DEFAULT NULL,
  `overtime_hours` decimal(4,2) DEFAULT 0.00,
  `status` enum('present','absent','late','half_day','leave') DEFAULT 'present',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_trail`
--

CREATE TABLE `audit_trail` (
  `audit_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `banks`
--

CREATE TABLE `banks` (
  `id` int(11) NOT NULL,
  `bank_name` varchar(255) NOT NULL,
  `bank_code` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `banks`
--

INSERT INTO `banks` (`id`, `bank_name`, `bank_code`, `created_at`) VALUES
(1, '9mobile 9Payment Service Bank', '120001', '2025-11-10 08:40:07'),
(2, 'Abbey Mortgage Bank', '404', '2025-11-10 08:40:07'),
(3, 'Above Only MFB', '51204', '2025-11-10 08:40:07'),
(4, 'Abulesoro MFB', '51312', '2025-11-10 08:40:07'),
(5, 'Access Bank', '044', '2025-11-10 08:40:07'),
(6, 'Access Bank (Diamond)', '063', '2025-11-10 08:40:07'),
(7, 'Accion Microfinance Bank', '602', '2025-11-10 08:40:07'),
(8, 'Aella MFB', '50315', '2025-11-10 08:40:07'),
(9, 'AG Mortgage Bank', '90077', '2025-11-10 08:40:07'),
(10, 'Ahmadu Bello University Microfinance Bank', '50036', '2025-11-10 08:40:07'),
(11, 'Airtel Smartcash PSB', '120004', '2025-11-10 08:40:07'),
(12, 'AKU Microfinance Bank', '51336', '2025-11-10 08:40:07'),
(13, 'Akuchukwu Microfinance Bank Limited', '090561', '2025-11-10 08:40:07'),
(14, 'Al-Barakah Microfinance Bank', '50055', '2025-11-10 08:40:07'),
(15, 'ALAT by WEMA', '035A', '2025-11-10 08:40:07'),
(16, 'Alpha Morgan Bank', '108', '2025-11-10 08:40:07'),
(17, 'Alternative bank', '000304', '2025-11-10 08:40:07'),
(18, 'Amegy Microfinance Bank', '090629', '2025-11-10 08:40:07'),
(19, 'Amju Unique MFB', '50926', '2025-11-10 08:40:07'),
(20, 'Aramoko MFB', '50083', '2025-11-10 08:40:07'),
(21, 'ASO Savings and Loans', '401', '2025-11-10 08:40:07'),
(22, 'Assets Microfinance Bank', '50092', '2025-11-10 08:40:07'),
(23, 'Astrapolaris MFB LTD', 'MFB50094', '2025-11-10 08:40:07'),
(24, 'AVUENEGBE MICROFINANCE BANK', '090478', '2025-11-10 08:40:07'),
(25, 'AWACASH MICROFINANCE BANK', '51351', '2025-11-10 08:40:07'),
(26, 'AZTEC MICROFINANCE BANK LIMITED', '51337', '2025-11-10 08:40:07'),
(27, 'Bainescredit MFB', '51229', '2025-11-10 08:40:07'),
(28, 'Banc Corp Microfinance Bank', '50117', '2025-11-10 08:40:07'),
(29, 'BANKIT MICROFINANCE BANK LTD', '50572', '2025-11-10 08:40:07'),
(30, 'BANKLY MFB', '51341', '2025-11-10 08:40:07'),
(31, 'Baobab Microfinance Bank', 'MFB50992', '2025-11-10 08:40:07'),
(32, 'BellBank Microfinance Bank', '51100', '2025-11-10 08:40:07'),
(33, 'Benysta Microfinance Bank Limited', '51267', '2025-11-10 08:40:07'),
(34, 'Beststar Microfinance Bank', '50123', '2025-11-10 08:40:07'),
(35, 'BOLD MFB', '50725', '2025-11-10 08:40:07'),
(36, 'Bosak Microfinance Bank', '650', '2025-11-10 08:40:07'),
(37, 'Bowen Microfinance Bank', '50931', '2025-11-10 08:40:07'),
(38, 'Branch International Finance Company Limited', 'FC40163', '2025-11-10 08:40:07'),
(39, 'Brent Mortgage bank', '90070', '2025-11-10 08:40:07'),
(40, 'BuyPower MFB', '50645', '2025-11-10 08:40:07'),
(41, 'Carbon', '565', '2025-11-10 08:40:07'),
(42, 'Cashbridge Microfinance Bank Limited', '51353', '2025-11-10 08:40:07'),
(43, 'CASHCONNECT MFB', '865', '2025-11-10 08:40:07'),
(44, 'CEMCS Microfinance Bank', '50823', '2025-11-10 08:40:07'),
(45, 'Chanelle Microfinance Bank Limited', '50171', '2025-11-10 08:40:07'),
(46, 'Chikum Microfinance bank', '312', '2025-11-10 08:40:07'),
(47, 'Citibank Nigeria', '023', '2025-11-10 08:40:07'),
(48, 'CITYCODE MORTAGE BANK', '070027', '2025-11-10 08:40:07'),
(49, 'Consumer Microfinance Bank', '50910', '2025-11-10 08:40:07'),
(50, 'Cool Microfinance Bank Limited', '51458', '2025-11-10 08:40:07'),
(51, 'Corestep MFB', '50204', '2025-11-10 08:40:07'),
(52, 'Coronation Merchant Bank', '559', '2025-11-10 08:40:07'),
(53, 'County Finance Limited', 'FC40128', '2025-11-10 08:40:07'),
(54, 'Credit Direct Limited', '40119', '2025-11-10 08:40:07'),
(55, 'Crescent MFB', '51297', '2025-11-10 08:40:07'),
(56, 'Crust Microfinance Bank', '090560', '2025-11-10 08:40:07'),
(57, 'CRUTECH MICROFINANCE BANK LTD', '50216', '2025-11-10 08:40:07'),
(58, 'Dash Microfinance Bank', '51368', '2025-11-10 08:40:07'),
(59, 'Davenport MICROFINANCE BANK', '51334', '2025-11-10 08:40:07'),
(60, 'Dillon Microfinance Bank', '51450', '2025-11-10 08:40:07'),
(61, 'Dot Microfinance Bank', '50162', '2025-11-10 08:40:07'),
(62, 'EBSU Microfinance Bank', '50922', '2025-11-10 08:40:07'),
(63, 'Ecobank Nigeria', '050', '2025-11-10 08:40:07'),
(64, 'Ekimogun MFB', '50263', '2025-11-10 08:40:07'),
(65, 'Ekondo Microfinance Bank', '098', '2025-11-10 08:40:07'),
(66, 'EXCEL FINANCE BANK', '090678', '2025-11-10 08:40:07'),
(67, 'Eyowo', '50126', '2025-11-10 08:40:07'),
(68, 'Fairmoney Microfinance Bank', '51318', '2025-11-10 08:40:07'),
(69, 'Fedeth MFB', '50298', '2025-11-10 08:40:07'),
(70, 'Fidelity Bank', '070', '2025-11-10 08:40:07'),
(71, 'Firmus MFB', '51314', '2025-11-10 08:40:07'),
(72, 'First Bank of Nigeria', '011', '2025-11-10 08:40:07'),
(73, 'First City Monument Bank', '214', '2025-11-10 08:40:07'),
(74, 'FIRST ROYAL MICROFINANCE BANK', '090164', '2025-11-10 08:40:07'),
(75, 'FIRSTMIDAS MFB', '51333', '2025-11-10 08:40:07'),
(76, 'FirstTrust Mortgage Bank Nigeria', '413', '2025-11-10 08:40:07'),
(77, 'FSDH Merchant Bank Limited', '501', '2025-11-10 08:40:07'),
(78, 'FUTMINNA MICROFINANCE BANK', '832', '2025-11-10 08:40:07'),
(79, 'Garun Mallam MFB', 'MFB51093', '2025-11-10 08:40:07'),
(80, 'Gateway Mortgage Bank LTD', '812', '2025-11-10 08:40:07'),
(81, 'Globus Bank', '00103', '2025-11-10 08:40:07'),
(82, 'Goldman MFB', '090574', '2025-11-10 08:40:07'),
(83, 'GoMoney', '100022', '2025-11-10 08:40:07'),
(84, 'GOOD SHEPHERD MICROFINANCE BANK', '090664', '2025-11-10 08:40:07'),
(85, 'Goodnews Microfinance Bank', '50739', '2025-11-10 08:40:07'),
(86, 'Greenwich Merchant Bank', '562', '2025-11-10 08:40:07'),
(87, 'GROOMING MICROFINANCE BANK', '51276', '2025-11-10 08:40:07'),
(88, 'GTI MFB', '50368', '2025-11-10 08:40:07'),
(89, 'Guaranty Trust Bank', '058', '2025-11-10 08:40:07'),
(90, 'Hackman Microfinance Bank', '51251', '2025-11-10 08:40:07'),
(91, 'Hasal Microfinance Bank', '50383', '2025-11-10 08:40:07'),
(92, 'HopePSB', '120002', '2025-11-10 08:40:07'),
(93, 'IBANK Microfinance Bank', '51211', '2025-11-10 08:40:07'),
(94, 'IBBU MFB', '51279', '2025-11-10 08:40:07'),
(95, 'Ibile Microfinance Bank', '51244', '2025-11-10 08:40:07'),
(96, 'Ibom Mortgage Bank', '90012', '2025-11-10 08:40:07'),
(97, 'Ikoyi Osun MFB', '50439', '2025-11-10 08:40:07'),
(98, 'Ilaro Poly Microfinance Bank', '50442', '2025-11-10 08:40:07'),
(99, 'Imowo MFB', '50453', '2025-11-10 08:40:07'),
(100, 'IMPERIAL HOMES MORTAGE BANK', '415', '2025-11-10 08:40:07'),
(101, 'INDULGE MFB', '51392', '2025-11-10 08:40:07'),
(102, 'Infinity MFB', '50457', '2025-11-10 08:40:07'),
(103, 'Infinity trust  Mortgage Bank', '070016', '2025-11-10 08:40:07'),
(104, 'ISUA MFB', '090701', '2025-11-10 08:40:07'),
(105, 'Jaiz Bank', '301', '2025-11-10 08:40:07'),
(106, 'Kadpoly MFB', '50502', '2025-11-10 08:40:07'),
(107, 'KANOPOLY MFB', '51308', '2025-11-10 08:40:07'),
(108, 'Keystone Bank', '082', '2025-11-10 08:40:07'),
(109, 'Kolomoni MFB', '899', '2025-11-10 08:40:07'),
(110, 'KONGAPAY (Kongapay Technologies Limited)(formerly Zinternet)', '100025', '2025-11-10 08:40:07'),
(111, 'Kredi Money MFB LTD', '50200', '2025-11-10 08:40:07'),
(112, 'Kuda Bank', '50211', '2025-11-10 08:40:07'),
(113, 'Lagos Building Investment Company Plc.', '90052', '2025-11-10 08:40:07'),
(114, 'Letshego Microfinance Bank', '090420', '2025-11-10 08:40:07'),
(115, 'Links MFB', '50549', '2025-11-10 08:40:07'),
(116, 'Living Trust Mortgage Bank', '031', '2025-11-10 08:40:07'),
(117, 'LOMA MFB', '50491', '2025-11-10 08:40:07'),
(118, 'Lotus Bank', '303', '2025-11-10 08:40:07'),
(119, 'Maal MFB', '51444', '2025-11-10 08:40:07'),
(120, 'MAINSTREET MICROFINANCE BANK', '090171', '2025-11-10 08:40:07'),
(121, 'Mayfair MFB', '50563', '2025-11-10 08:40:07'),
(122, 'Mint MFB', '50304', '2025-11-10 08:40:07'),
(123, 'MINT-FINEX MFB', '09', '2025-11-10 08:40:07'),
(124, 'Money Master PSB', '946', '2025-11-10 08:40:07'),
(125, 'Moniepoint MFB', '50515', '2025-11-10 08:40:07'),
(126, 'MTN Momo PSB', '120003', '2025-11-10 08:40:07'),
(127, 'MUTUAL BENEFITS MICROFINANCE BANK', '090190', '2025-11-10 08:40:07'),
(128, 'NDCC MICROFINANCE BANK', '090679', '2025-11-10 08:40:07'),
(129, 'NET MICROFINANCE BANK', '51361', '2025-11-10 08:40:07'),
(130, 'Nigerian Navy Microfinance Bank Limited', '51142', '2025-11-10 08:40:07'),
(131, 'Nombank MFB', '50072', '2025-11-10 08:40:07'),
(132, 'NOVA BANK', '561', '2025-11-10 08:40:07'),
(133, 'Novus MFB', '51371', '2025-11-10 08:40:07'),
(134, 'NPF MICROFINANCE BANK', '50629', '2025-11-10 08:40:07'),
(135, 'NSUK MICROFINANACE BANK', '51261', '2025-11-10 08:40:07'),
(136, 'Olabisi Onabanjo University Microfinance Bank', '50689', '2025-11-10 08:40:07'),
(137, 'OLUCHUKWU MICROFINANCE BANK LTD', '50697', '2025-11-10 08:40:07'),
(138, 'OPay Digital Services Limited (OPay)', '999992', '2025-11-10 08:40:07'),
(139, 'Optimus Bank Limited', '107', '2025-11-10 08:40:07'),
(140, 'Paga', '100002', '2025-11-10 08:40:07'),
(141, 'PalmPay', '999991', '2025-11-10 08:40:07'),
(142, 'Parallex Bank', '104', '2025-11-10 08:40:07'),
(143, 'Parkway - ReadyCash', '311', '2025-11-10 08:40:07'),
(144, 'PATHFINDER MICROFINANCE BANK LIMITED', '090680', '2025-11-10 08:40:07'),
(145, 'Paystack-Titan', '100039', '2025-11-10 08:40:07'),
(146, 'Peace Microfinance Bank', '50743', '2025-11-10 08:40:07'),
(147, 'PECANTRUST MICROFINANCE BANK LIMITED', '51226', '2025-11-10 08:40:07'),
(148, 'Personal Trust MFB', '51146', '2025-11-10 08:40:07'),
(149, 'Petra Mircofinance Bank Plc', '50746', '2025-11-10 08:40:07'),
(150, 'Pettysave MFB', 'MFB51452', '2025-11-10 08:40:07'),
(151, 'PFI FINANCE COMPANY LIMITED', '050021', '2025-11-10 08:40:07'),
(152, 'Platinum Mortgage Bank', '268', '2025-11-10 08:40:07'),
(153, 'Pocket App', '00716', '2025-11-10 08:40:07'),
(154, 'Polaris Bank', '076', '2025-11-10 08:40:07'),
(155, 'Polyunwana MFB', '50864', '2025-11-10 08:40:07'),
(156, 'PremiumTrust Bank', '105', '2025-11-10 08:40:07'),
(157, 'Prospa Capital Microfinance Bank', '50739', '2025-11-10 08:40:07'),
(158, 'PROSPERIS FINANCE LIMITED', '050023', '2025-11-10 08:40:07'),
(159, 'Providus Bank', '101', '2025-11-10 08:40:07'),
(160, 'QuickFund MFB', '51293', '2025-11-10 08:40:07'),
(161, 'Rand Merchant Bank', '502', '2025-11-10 08:40:07'),
(162, 'RANDALPHA MICROFINANCE BANK', '090496', '2025-11-10 08:40:07'),
(163, 'Refuge Mortgage Bank', '90067', '2025-11-10 08:40:07'),
(164, 'REHOBOTH MICROFINANCE BANK', '50761', '2025-11-10 08:40:07'),
(165, 'Rephidim Microfinance Bank', '50994', '2025-11-10 08:40:07'),
(166, 'Rigo Microfinance Bank Limited', '51286', '2025-11-10 08:40:07'),
(167, 'ROCKSHIELD MICROFINANCE BANK', '50767', '2025-11-10 08:40:07'),
(168, 'Rubies MFB', '125', '2025-11-10 08:40:07'),
(169, 'Safe Haven MFB', '51113', '2025-11-10 08:40:07'),
(170, 'SAGE GREY FINANCE LIMITED', '40165', '2025-11-10 08:40:07'),
(171, 'Shield MFB', '50582', '2025-11-10 08:40:07'),
(172, 'Signature Bank Ltd', '106', '2025-11-10 08:40:07'),
(173, 'Solid Allianze MFB', '51062', '2025-11-10 08:40:07'),
(174, 'Solid Rock MFB', '50800', '2025-11-10 08:40:07'),
(175, 'Sparkle Microfinance Bank', '51310', '2025-11-10 08:40:07'),
(176, 'Springfield Microfinance Bank', '51429', '2025-11-10 08:40:07'),
(177, 'Stanbic IBTC Bank', '221', '2025-11-10 08:40:07'),
(178, 'Standard Chartered Bank', '068', '2025-11-10 08:40:07'),
(179, 'STANFORD MICROFINANCE BANK', '090162', '2025-11-10 08:40:07'),
(180, 'STATESIDE MICROFINANCE BANK', '50809', '2025-11-10 08:40:07'),
(181, 'STB Mortgage Bank', '070022', '2025-11-10 08:40:07'),
(182, 'Stellas MFB', '51253', '2025-11-10 08:40:07'),
(183, 'Sterling Bank', '232', '2025-11-10 08:40:07'),
(184, 'Summit Bank', '00305', '2025-11-10 08:40:07'),
(185, 'Suntrust Bank', '100', '2025-11-10 08:40:07'),
(186, 'Supreme MFB', '50968', '2025-11-10 08:40:07'),
(187, 'TAJ Bank', '302', '2025-11-10 08:40:07'),
(188, 'Tangerine Money', '51269', '2025-11-10 08:40:07'),
(189, 'TENN', '51403', '2025-11-10 08:40:07'),
(190, 'Think Finance Microfinance Bank', '677', '2025-11-10 08:40:07'),
(191, 'Titan Bank', '102', '2025-11-10 08:40:07'),
(192, 'TransPay MFB', '090708', '2025-11-10 08:40:07'),
(193, 'TRUSTBANC J6 MICROFINANCE BANK', '51118', '2025-11-10 08:40:07'),
(194, 'U&C Microfinance Bank Ltd (U AND C MFB)', '50840', '2025-11-10 08:40:07'),
(195, 'UCEE MFB', '090706', '2025-11-10 08:40:07'),
(196, 'Uhuru MFB', '51322', '2025-11-10 08:40:07'),
(197, 'Ultraviolet Microfinance Bank', '51080', '2025-11-10 08:40:07'),
(198, 'Unaab Microfinance Bank Limited', '50870', '2025-11-10 08:40:07'),
(199, 'UNIABUJA MFB', '51447', '2025-11-10 08:40:07'),
(200, 'Unical MFB', '50871', '2025-11-10 08:40:07'),
(201, 'Unilag Microfinance Bank', '51316', '2025-11-10 08:40:07'),
(202, 'UNIMAID MICROFINANCE BANK', '50875', '2025-11-10 08:40:07'),
(203, 'Union Bank of Nigeria', '032', '2025-11-10 08:40:07'),
(204, 'United Bank For Africa', '033', '2025-11-10 08:40:07'),
(205, 'Unity Bank', '215', '2025-11-10 08:40:07'),
(206, 'Uzondu Microfinance Bank Awka Anambra State', '50894', '2025-11-10 08:40:07'),
(207, 'Vale Finance Limited', '050020', '2025-11-10 08:40:07'),
(208, 'VFD Microfinance Bank Limited', '566', '2025-11-10 08:40:07'),
(209, 'Waya Microfinance Bank', '51355', '2025-11-10 08:40:07'),
(210, 'Wema Bank', '035', '2025-11-10 08:40:07'),
(211, 'Weston Charis MFB', '51386', '2025-11-10 08:40:07'),
(212, 'Xpress Wallet', '100040', '2025-11-10 08:40:07'),
(213, 'Yes MFB', '594', '2025-11-10 08:40:07'),
(214, 'Zap', '00zap', '2025-11-10 08:40:07'),
(215, 'Zenith Bank', '057', '2025-11-10 08:40:07'),
(216, 'Zitra MFB', '51373', '2025-11-10 08:40:07');

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `company_id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `rc_number` varchar(50) DEFAULT NULL,
  `tax_identification_number` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Nigeria',
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `industry_type` enum('construction','manufacturing','other') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`company_id`, `company_name`, `rc_number`, `tax_identification_number`, `address`, `city`, `state`, `country`, `phone`, `email`, `website`, `industry_type`, `created_at`, `updated_at`) VALUES
(1, 'Nigerian Construction Ltd', 'RC123456', 'TIN123456789', '123 Industrial Layout', 'Lagos', 'Lagos', 'Nigeria', '+2348012345678', 'info@ncl.com', NULL, 'construction', '2025-10-29 16:40:56', '2025-10-29 16:40:56');

-- --------------------------------------------------------

--
-- Table structure for table `currency_rates`
--

CREATE TABLE `currency_rates` (
  `rate_id` int(11) NOT NULL,
  `base_currency` varchar(3) NOT NULL,
  `target_currency` varchar(3) NOT NULL,
  `exchange_rate` decimal(10,4) NOT NULL,
  `effective_date` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `currency_rates`
--

INSERT INTO `currency_rates` (`rate_id`, `base_currency`, `target_currency`, `exchange_rate`, `effective_date`, `is_active`, `created_at`) VALUES
(1, 'NGN', 'NGN', 1.0000, '2025-10-29', 1, '2025-10-29 15:24:08'),
(2, 'USD', 'NGN', 1500.0000, '2025-10-29', 1, '2025-10-29 15:24:08'),
(3, 'GBP', 'NGN', 1900.0000, '2025-10-29', 1, '2025-10-29 15:24:08'),
(4, 'EUR', 'NGN', 1600.0000, '2025-10-29', 1, '2025-10-29 15:24:08');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `department_name` varchar(255) NOT NULL,
  `department_code` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `company_id`, `department_name`, `department_code`, `description`, `is_active`, `created_at`) VALUES
(1, 1, 'Information Technology', 'IT', 'IT Department', 1, '2025-11-21 14:09:46');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL,
  `employee_code` varchar(50) NOT NULL,
  `company_id` int(11) NOT NULL,
  `title` enum('Mr','Mrs','Miss','Dr','Prof') DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `date_of_birth` date NOT NULL,
  `marital_status` enum('Single','Married','Divorced','Widowed') NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `alternate_phone` varchar(20) DEFAULT NULL,
  `residential_address` text DEFAULT NULL,
  `state_of_origin` varchar(100) DEFAULT NULL,
  `lga_of_origin` varchar(100) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT 'Nigerian',
  `employee_type_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `employment_date` date NOT NULL,
  `confirmation_date` date DEFAULT NULL,
  `status` enum('active','inactive','suspended','terminated') DEFAULT 'active',
  `bank_id` int(11) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `account_number` varchar(20) DEFAULT NULL,
  `account_name` varchar(255) DEFAULT NULL,
  `bvn` varchar(11) DEFAULT NULL,
  `pension_pin` varchar(50) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `insurance_number` varchar(50) DEFAULT NULL,
  `tax_state` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employee_id`, `employee_code`, `company_id`, `title`, `first_name`, `middle_name`, `last_name`, `gender`, `date_of_birth`, `marital_status`, `email`, `phone_number`, `alternate_phone`, `residential_address`, `state_of_origin`, `lga_of_origin`, `nationality`, `employee_type_id`, `department_id`, `employment_date`, `confirmation_date`, `status`, `bank_id`, `bank_name`, `account_number`, `account_name`, `bvn`, `pension_pin`, `tax_id`, `insurance_number`, `tax_state`, `created_at`, `updated_at`) VALUES
(1, '241001', 1, 'Mr', 'Quadri', 'Olanrewaju', 'Adekunle', '', '1996-05-13', '', 'adekunlequadri3@gmail.com', '+2347042277326', '+2348033798657', '11, Rafiu Street Baruwa, Ipaja, Lagos', 'Ogun', 'Abeokuta-South', 'Nigerian', 1, 1, '2024-10-01', '2024-10-01', 'active', 5, NULL, '1486449653', 'Quadri Olanrewaju Adekunle', '77727626626', 'PEN110193544379', 'N-15425625', NULL, NULL, '2025-11-19 12:54:58', '2025-11-24 13:51:07');

-- --------------------------------------------------------

--
-- Table structure for table `employee_benefits`
--

CREATE TABLE `employee_benefits` (
  `employee_benefit_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `benefit_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'NGN',
  `effective_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_documents`
--

CREATE TABLE `employee_documents` (
  `document_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(512) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_loans`
--

CREATE TABLE `employee_loans` (
  `loan_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `loan_type_id` int(11) NOT NULL,
  `loan_amount` decimal(12,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'NGN',
  `application_date` date NOT NULL,
  `approval_date` date DEFAULT NULL,
  `disbursement_date` date DEFAULT NULL,
  `interest_rate` decimal(5,2) NOT NULL,
  `tenure_months` int(11) NOT NULL,
  `monthly_repayment` decimal(10,2) NOT NULL,
  `total_repayable_amount` decimal(12,2) NOT NULL,
  `start_repayment_date` date DEFAULT NULL,
  `end_repayment_date` date DEFAULT NULL,
  `remaining_balance` decimal(12,2) NOT NULL,
  `status` enum('pending','approved','disbursed','active','completed','defaulted','rejected') DEFAULT 'pending',
  `purpose` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_loans`
--

INSERT INTO `employee_loans` (`loan_id`, `employee_id`, `loan_type_id`, `loan_amount`, `currency`, `application_date`, `approval_date`, `disbursement_date`, `interest_rate`, `tenure_months`, `monthly_repayment`, `total_repayable_amount`, `start_repayment_date`, `end_repayment_date`, `remaining_balance`, `status`, `purpose`, `approved_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 10000.00, 'NGN', '2025-11-25', '2025-11-25', '2025-11-25', 0.00, 2, 5000.00, 10000.00, '2025-12-25', NULL, 10000.00, 'approved', 'dfbv', NULL, '2025-11-25 12:25:55', NULL),
(2, 1, 4, 300000.00, 'NGN', '2025-11-26', NULL, NULL, 0.00, 12, 25000.00, 300000.00, NULL, NULL, 300000.00, 'rejected', 'sdf', NULL, '2025-11-26 08:10:22', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `employee_project_assignments`
--

CREATE TABLE `employee_project_assignments` (
  `assignment_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `assignment_start_date` date NOT NULL,
  `assignment_end_date` date DEFAULT NULL,
  `base_wage_rate` decimal(12,2) NOT NULL,
  `wage_currency` varchar(3) DEFAULT 'NGN',
  `overtime_rate` decimal(5,2) DEFAULT 1.50,
  `hazard_allowance` decimal(10,2) DEFAULT 0.00,
  `site_allowance` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_salary_structure`
--

CREATE TABLE `employee_salary_structure` (
  `salary_structure_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `component_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'NGN',
  `effective_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_salary_structure`
--

INSERT INTO `employee_salary_structure` (`salary_structure_id`, `employee_id`, `component_id`, `amount`, `currency`, `effective_date`, `end_date`, `is_active`, `created_at`) VALUES
(1, 1, 1, 46655.00, 'NGN', '2025-11-19', NULL, 1, '2025-11-19 12:54:58'),
(2, 1, 2, 13125.00, 'NGN', '2025-11-19', NULL, 1, '2025-11-19 12:54:58'),
(3, 1, 3, 5600.00, 'NGN', '2025-11-19', NULL, 1, '2025-11-19 12:54:58'),
(4, 1, 5, 2625.00, 'NGN', '2025-11-19', NULL, 1, '2025-11-19 12:54:58'),
(5, 1, 4, 1995.00, 'NGN', '2025-11-19', NULL, 1, '2025-11-19 12:54:58');

-- --------------------------------------------------------

--
-- Table structure for table `employee_types`
--

CREATE TABLE `employee_types` (
  `employee_type_id` int(11) NOT NULL,
  `type_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `payment_frequency` enum('daily','weekly','monthly') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_types`
--

INSERT INTO `employee_types` (`employee_type_id`, `type_name`, `description`, `payment_frequency`, `is_active`) VALUES
(1, 'Monthly Paid', 'Staff paid on monthly basis', 'monthly', 1),
(2, 'Weekly Paid', 'Staff paid on weekly basis', 'weekly', 1),
(3, 'Daily Paid', 'Staff paid on daily basis', 'daily', 1),
(4, 'Casual Worker', 'Temporary or casual workers', 'daily', 1),
(5, 'Foreign Expatriate', 'Foreign staff paid in foreign currency', 'monthly', 1);

-- --------------------------------------------------------

--
-- Table structure for table `expatriate_employees`
--

CREATE TABLE `expatriate_employees` (
  `expatriate_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `passport_number` varchar(50) NOT NULL,
  `passport_expiry` date NOT NULL,
  `country_of_origin` varchar(100) NOT NULL,
  `work_permit_number` varchar(100) DEFAULT NULL,
  `work_permit_expiry` date DEFAULT NULL,
  `residential_permit_number` varchar(100) DEFAULT NULL,
  `residential_permit_expiry` date DEFAULT NULL,
  `base_currency` varchar(3) NOT NULL DEFAULT 'USD',
  `conversion_rate` decimal(10,4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `integration_logs`
--

CREATE TABLE `integration_logs` (
  `log_id` int(11) NOT NULL,
  `app_id` int(11) NOT NULL,
  `integration_type` varchar(100) NOT NULL,
  `request_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_data`)),
  `response_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_data`)),
  `status` enum('success','failed','pending') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leaves`
--

CREATE TABLE `leaves` (
  `leave_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `days` int(11) NOT NULL,
  `comments` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `leave_type_id` int(11) NOT NULL,
  `type_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`leave_type_id`, `type_name`, `description`, `is_active`) VALUES
(1, 'Annual Leave', 'Paid time off for vacation or rest', 1),
(2, 'Sick Leave', 'Leave granted for health-related issues', 1),
(3, 'Maternity Leave', 'Leave for childbirth and recovery', 1),
(4, 'Paternity Leave', 'Leave for fathers after childbirth', 1),
(5, 'Study Leave', 'Time off for academic or professional studies', 1),
(6, 'Unpaid Leave', 'Leave without salary for personal reasons', 1),
(7, 'Compassionate Leave', 'Leave for bereavement or family emergencies', 1),
(8, 'Public Holiday', 'Official non-working days recognized by law', 0);

-- --------------------------------------------------------

--
-- Table structure for table `loan_repayments`
--

CREATE TABLE `loan_repayments` (
  `repayment_id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `payroll_id` int(11) DEFAULT NULL,
  `installment_number` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `amount_due` decimal(10,2) NOT NULL,
  `principal_amount` decimal(10,2) NOT NULL,
  `interest_amount` decimal(10,2) NOT NULL,
  `paid_date` date DEFAULT NULL,
  `amount_paid` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','paid','overdue','partial') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_repayments`
--

INSERT INTO `loan_repayments` (`repayment_id`, `loan_id`, `payroll_id`, `installment_number`, `due_date`, `amount_due`, `principal_amount`, `interest_amount`, `paid_date`, `amount_paid`, `status`, `created_at`) VALUES
(1, 1, NULL, 1, '2025-12-25', 5000.00, 5000.00, 0.00, NULL, 0.00, 'pending', '2025-11-25 12:26:18'),
(2, 1, NULL, 2, '2026-01-25', 5000.00, 5000.00, 0.00, NULL, 0.00, 'pending', '2025-11-25 12:26:18');

-- --------------------------------------------------------

--
-- Table structure for table `loan_types`
--

CREATE TABLE `loan_types` (
  `loan_type_id` int(11) NOT NULL,
  `loan_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `interest_rate` decimal(5,2) NOT NULL,
  `max_amount` decimal(12,2) DEFAULT NULL,
  `max_tenure_months` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_types`
--

INSERT INTO `loan_types` (`loan_type_id`, `loan_name`, `description`, `interest_rate`, `max_amount`, `max_tenure_months`, `is_active`) VALUES
(1, 'Personal Loan', 'Unsecured loan for personal expenses', 12.50, 2000000.00, 24, 1),
(2, 'Car Loan', 'Loan for purchasing a new or used vehicle', 10.00, 5000000.00, 36, 1),
(3, 'Home Improvement Loan', 'Funds for renovating or upgrading a home', 11.00, 3000000.00, 30, 1),
(4, 'Education Loan', 'Loan for tuition and academic expenses', 9.50, 4000000.00, 48, 1),
(5, 'Business Loan', 'Capital for small business operations', 13.00, 10000000.00, 60, 1),
(6, 'Emergency Loan', 'Quick access loan for urgent needs', 15.00, 500000.00, 12, 1),
(7, 'Travel Loan', 'Loan for vacation or travel-related costs', 14.00, 1000000.00, 18, 0);

-- --------------------------------------------------------

--
-- Table structure for table `paye_tax_bands`
--

CREATE TABLE `paye_tax_bands` (
  `band_id` int(11) NOT NULL,
  `tax_year` year(4) NOT NULL,
  `lower_limit` decimal(12,2) NOT NULL,
  `upper_limit` decimal(12,2) DEFAULT NULL,
  `tax_rate` decimal(5,2) NOT NULL,
  `fixed_amount` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `paye_tax_bands`
--

INSERT INTO `paye_tax_bands` (`band_id`, `tax_year`, `lower_limit`, `upper_limit`, `tax_rate`, `fixed_amount`) VALUES
(1, '2024', 0.00, 300000.00, 7.00, 0.00),
(2, '2024', 300001.00, 600000.00, 11.00, 21000.00),
(3, '2024', 600001.00, 1100000.00, 15.00, 54000.00),
(4, '2024', 1100001.00, 1600000.00, 19.00, 129000.00),
(5, '2024', 1600001.00, NULL, 21.00, 224000.00);

-- --------------------------------------------------------

--
-- Table structure for table `payroll_details`
--

CREATE TABLE `payroll_details` (
  `payroll_detail_id` int(11) NOT NULL,
  `payroll_id` int(11) NOT NULL,
  `component_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `component_type` enum('earning','deduction') NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` enum('loan','advance') DEFAULT NULL,
  `is_taxable` tinyint(1) DEFAULT 0,
  `loan_id` int(11) DEFAULT NULL,
  `advance_id` int(11) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_details`
--

INSERT INTO `payroll_details` (`payroll_detail_id`, `payroll_id`, `component_id`, `amount`, `component_type`, `reference_id`, `reference_type`, `is_taxable`, `loan_id`, `advance_id`, `notes`) VALUES
(1, 1, 1, 46655.00, 'earning', NULL, NULL, 0, NULL, NULL, NULL),
(2, 1, 2, 13125.00, 'earning', NULL, NULL, 0, NULL, NULL, NULL),
(3, 1, 3, 5600.00, 'earning', NULL, NULL, 0, NULL, NULL, NULL),
(4, 1, 5, 2625.00, 'earning', NULL, NULL, 0, NULL, NULL, NULL),
(5, 1, 4, 1995.00, 'earning', NULL, NULL, 0, NULL, NULL, NULL),
(6, 1, 11, 5230.40, 'earning', NULL, NULL, 0, NULL, NULL, NULL),
(7, 1, 10, 2751.32, 'earning', NULL, NULL, 0, NULL, NULL, NULL),
(8, 2, 1, 46655.00, 'earning', NULL, NULL, 0, NULL, NULL, NULL),
(9, 2, 2, 13125.00, 'earning', NULL, NULL, 0, NULL, NULL, NULL),
(10, 2, 3, 5600.00, 'earning', NULL, NULL, 0, NULL, NULL, NULL),
(11, 2, 5, 2625.00, 'earning', NULL, NULL, 0, NULL, NULL, NULL),
(12, 2, 4, 1995.00, 'earning', NULL, NULL, 0, NULL, NULL, NULL),
(13, 2, 11, 5230.40, 'earning', NULL, NULL, 0, NULL, NULL, NULL),
(14, 2, 10, 2751.32, 'earning', NULL, NULL, 0, NULL, NULL, NULL),
(29, 3, 1, 46655.00, 'earning', NULL, NULL, 0, NULL, NULL, NULL),
(30, 3, 2, 13125.00, 'earning', NULL, NULL, 0, NULL, NULL, NULL),
(31, 3, 3, 5600.00, 'earning', NULL, NULL, 0, NULL, NULL, NULL),
(32, 3, 5, 2625.00, 'earning', NULL, NULL, 0, NULL, NULL, NULL),
(33, 3, 4, 1995.00, 'earning', NULL, NULL, 0, NULL, NULL, NULL),
(34, 3, 11, 5230.40, 'earning', NULL, NULL, 0, NULL, NULL, NULL),
(35, 3, 10, 2751.32, 'earning', NULL, NULL, 0, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payroll_master`
--

CREATE TABLE `payroll_master` (
  `payroll_id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `basic_salary` decimal(12,2) NOT NULL,
  `total_earnings` decimal(12,2) NOT NULL,
  `total_deductions` decimal(12,2) NOT NULL,
  `gross_salary` decimal(12,2) NOT NULL,
  `net_salary` decimal(12,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'NGN',
  `exchange_rate` decimal(10,4) DEFAULT 1.0000,
  `paid_amount` decimal(12,2) DEFAULT NULL,
  `payment_status` enum('pending','paid','partial','hold') DEFAULT 'pending',
  `payment_date` date DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_master`
--

INSERT INTO `payroll_master` (`payroll_id`, `period_id`, `employee_id`, `project_id`, `basic_salary`, `total_earnings`, `total_deductions`, `gross_salary`, `net_salary`, `currency`, `exchange_rate`, `paid_amount`, `payment_status`, `payment_date`, `payment_reference`, `created_at`) VALUES
(1, 1, 1, NULL, 46655.00, 70000.00, 7981.72, 70000.00, 62018.28, 'NGN', 1.0000, NULL, 'paid', '2025-11-25', NULL, '2025-11-25 14:56:30'),
(2, 2, 1, NULL, 46655.00, 70000.00, 7981.72, 70000.00, 62018.28, 'NGN', 1.0000, NULL, 'paid', '2025-11-25', NULL, '2025-11-25 14:56:48'),
(3, 3, 1, NULL, 46655.00, 70000.00, 7981.72, 70000.00, 62018.28, 'NGN', 1.0000, NULL, 'pending', NULL, NULL, '2025-11-25 15:06:57');

-- --------------------------------------------------------

--
-- Table structure for table `payroll_periods`
--

CREATE TABLE `payroll_periods` (
  `period_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `period_name` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `payment_date` date NOT NULL,
  `period_type` enum('monthly','weekly','daily') NOT NULL,
  `status` enum('draft','processing','validation','review','approved','locked') NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_periods`
--

INSERT INTO `payroll_periods` (`period_id`, `company_id`, `period_name`, `start_date`, `end_date`, `payment_date`, `period_type`, `status`, `created_by`, `approved_by`, `created_at`) VALUES
(1, 1, 'November 2025 Payroll', '2025-11-01', '2025-11-30', '2025-11-27', 'monthly', 'locked', NULL, NULL, '2025-11-25 14:56:27'),
(2, 1, 'December 2025 Payroll', '2025-12-01', '2025-12-31', '2025-12-19', 'monthly', 'locked', NULL, NULL, '2025-11-25 14:56:44'),
(3, 1, 'January 2026 Payroll', '2026-01-01', '2026-01-31', '2026-01-27', 'monthly', 'processing', NULL, NULL, '2025-11-25 15:06:54');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `permission_id` int(11) NOT NULL,
  `permission_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `module` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `project_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `project_code` varchar(50) NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `project_type` enum('construction','manufacturing','other') NOT NULL,
  `location_address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `expected_end_date` date DEFAULT NULL,
  `actual_end_date` date DEFAULT NULL,
  `project_status` enum('planned','ongoing','completed','on_hold') DEFAULT 'planned',
  `project_budget` decimal(15,2) DEFAULT NULL,
  `project_manager_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`project_id`, `company_id`, `project_code`, `project_name`, `project_type`, `location_address`, `city`, `state`, `start_date`, `expected_end_date`, `actual_end_date`, `project_status`, `project_budget`, `project_manager_id`, `created_at`) VALUES
(1, 1, 'PROJ001', 'Lekki Residential Complex', 'construction', 'Lekki Phase 1', 'Lagos', 'Lagos', '2024-01-01', '2024-12-31', NULL, 'ongoing', 500000000.00, NULL, '2025-10-29 16:40:56'),
(2, 1, 'PROJ002', 'Abuja Commercial Plaza', 'construction', 'Central Business District', 'Abuja', 'FCT', '2024-02-01', '2024-11-30', NULL, 'ongoing', 750000000.00, NULL, '2025-10-29 16:40:56');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_permission_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `salary_advances`
--

CREATE TABLE `salary_advances` (
  `advance_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `advance_amount` decimal(12,2) NOT NULL,
  `request_date` date NOT NULL,
  `status` enum('pending','approved','rejected','deducted') NOT NULL DEFAULT 'pending',
  `deduction_type` enum('current_month','next_month') NOT NULL,
  `deduction_date` date DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approval_date` date DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `reason` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deducted_amount` decimal(12,2) DEFAULT 0.00,
  `deduction_payroll_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `salary_components`
--

CREATE TABLE `salary_components` (
  `component_id` int(11) NOT NULL,
  `component_name` varchar(255) NOT NULL,
  `component_type` enum('earning','deduction','allowance') NOT NULL,
  `component_code` varchar(50) NOT NULL,
  `is_taxable` tinyint(1) DEFAULT 0,
  `is_statutory` tinyint(1) DEFAULT 0,
  `calculation_type` enum('fixed','percentage','variable') DEFAULT 'fixed',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `salary_components`
--

INSERT INTO `salary_components` (`component_id`, `component_name`, `component_type`, `component_code`, `is_taxable`, `is_statutory`, `calculation_type`, `description`, `is_active`) VALUES
(1, 'Basic Salary', 'earning', 'BASIC', 1, 0, 'fixed', NULL, 1),
(2, 'Housing Allowance', 'allowance', 'HOUSE', 1, 0, 'fixed', NULL, 1),
(3, 'Transport Allowance', 'allowance', 'TRANS', 1, 0, 'fixed', NULL, 1),
(4, 'Meal Allowance', 'allowance', 'MEAL', 1, 0, 'fixed', NULL, 1),
(5, 'Utility Allowance', 'allowance', 'UTIL', 1, 0, 'fixed', NULL, 1),
(6, '13th Month', 'earning', '13TH', 1, 0, 'fixed', NULL, 1),
(7, 'Hazard Allowance', 'allowance', 'HAZARD', 1, 0, 'fixed', NULL, 1),
(8, 'Non-Accident Bonus', 'earning', 'NOACC', 0, 0, 'fixed', NULL, 1),
(9, 'Project Allowance', 'allowance', 'PROJ', 1, 0, 'fixed', NULL, 1),
(10, 'PAYE Tax', 'deduction', 'PAYE', 0, 1, 'fixed', NULL, 1),
(11, 'Pension Contribution', 'deduction', 'PENS', 0, 1, 'fixed', NULL, 1),
(12, 'NHF Contribution', 'deduction', 'NHF', 0, 1, 'fixed', NULL, 1),
(13, 'Loan Repayment', 'deduction', 'LOAN', 0, 0, 'fixed', NULL, 1),
(14, 'Salary Advance', 'deduction', 'ADVANCE', 0, 0, 'fixed', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `salary_levels`
--

CREATE TABLE `salary_levels` (
  `level_id` int(11) NOT NULL,
  `level_name` varchar(50) NOT NULL,
  `level_code` varchar(10) NOT NULL,
  `basic_salary` decimal(12,2) NOT NULL,
  `housing_allowance` decimal(12,2) DEFAULT 0.00,
  `transport_allowance` decimal(12,2) DEFAULT 0.00,
  `meal_allowance` decimal(12,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `special_benefits`
--

CREATE TABLE `special_benefits` (
  `benefit_id` int(11) NOT NULL,
  `benefit_name` varchar(255) NOT NULL,
  `benefit_code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `is_recurring` tinyint(1) DEFAULT 0,
  `is_taxable` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `special_benefits`
--

INSERT INTO `special_benefits` (`benefit_id`, `benefit_name`, `benefit_code`, `description`, `is_recurring`, `is_taxable`, `is_active`) VALUES
(1, '13th Month', '13TH_MONTH', '13th month salary payment', 1, 1, 1),
(2, 'Hazard Allowance', 'HAZARD_PAY', 'Payment for hazardous work conditions', 1, 1, 1),
(3, 'Non-Accident Bonus', 'DRIVER_SAFETY', 'Bonus for drivers with no accidents', 0, 0, 1),
(4, 'Project Completion Bonus', 'PROJ_BONUS', 'Bonus for project completion', 0, 1, 1),
(5, 'Annual Leave Bonus', 'LEAVE_BONUS', 'Bonus paid with annual leave', 0, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','number','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tax_configurations`
--

CREATE TABLE `tax_configurations` (
  `config_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `tax_year` year(4) NOT NULL,
  `pension_employee_rate` decimal(5,2) DEFAULT 8.00,
  `pension_employer_rate` decimal(5,2) DEFAULT 10.00,
  `nhf_employee_rate` decimal(5,2) DEFAULT 2.50,
  `itf_rate` decimal(5,2) DEFAULT 1.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `third_party_apps`
--

CREATE TABLE `third_party_apps` (
  `app_id` int(11) NOT NULL,
  `app_name` varchar(255) NOT NULL,
  `app_type` enum('accounting','expense','hr','other') NOT NULL,
  `api_key` varchar(255) DEFAULT NULL,
  `api_secret` varchar(255) DEFAULT NULL,
  `base_url` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `user_type` enum('admin','payroll_master','hr_manager','employee','project_manager') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `employee_id`, `username`, `email`, `password_hash`, `user_type`, `is_active`, `last_login`, `created_at`) VALUES
(3, NULL, 'admin', 'admin@company.com', '$2y$10$k.aTOUjmC8UVGg0i6vPA6eOjmwDby4z/Drkc2QOFV2d29N0TzwYCG', 'admin', 1, '2025-11-26 08:05:01', '2025-10-29 16:01:29');

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`role_id`, `role_name`, `description`, `is_active`) VALUES
(1, 'System Administrator', 'Full system access and configuration', 1),
(2, 'Payroll Manager', 'Payroll processing and management', 1),
(3, 'HR Manager', 'Human resources management', 1),
(4, 'Project Manager', 'Project and site management', 1),
(5, 'Employee', 'Basic employee self-service', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_role_assignments`
--

CREATE TABLE `user_role_assignments` (
  `assignment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `idx_attendance_employee_date` (`employee_id`,`attendance_date`);

--
-- Indexes for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD PRIMARY KEY (`audit_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `banks`
--
ALTER TABLE `banks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`company_id`),
  ADD UNIQUE KEY `rc_number` (`rc_number`);

--
-- Indexes for table `currency_rates`
--
ALTER TABLE `currency_rates`
  ADD PRIMARY KEY (`rate_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`),
  ADD KEY `company_id` (`company_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `employee_code` (`employee_code`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_employees_company` (`company_id`),
  ADD KEY `idx_employees_department` (`department_id`),
  ADD KEY `idx_employees_type` (`employee_type_id`),
  ADD KEY `idx_employees_status` (`status`),
  ADD KEY `fk_bank` (`bank_id`);

--
-- Indexes for table `employee_benefits`
--
ALTER TABLE `employee_benefits`
  ADD PRIMARY KEY (`employee_benefit_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `benefit_id` (`benefit_id`);

--
-- Indexes for table `employee_documents`
--
ALTER TABLE `employee_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `employee_loans`
--
ALTER TABLE `employee_loans`
  ADD PRIMARY KEY (`loan_id`),
  ADD KEY `loan_type_id` (`loan_type_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_loan_employee` (`employee_id`,`status`);

--
-- Indexes for table `employee_project_assignments`
--
ALTER TABLE `employee_project_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD UNIQUE KEY `unique_active_assignment` (`employee_id`,`is_active`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `idx_project_assignments` (`employee_id`,`project_id`);

--
-- Indexes for table `employee_salary_structure`
--
ALTER TABLE `employee_salary_structure`
  ADD PRIMARY KEY (`salary_structure_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `component_id` (`component_id`);

--
-- Indexes for table `employee_types`
--
ALTER TABLE `employee_types`
  ADD PRIMARY KEY (`employee_type_id`);

--
-- Indexes for table `expatriate_employees`
--
ALTER TABLE `expatriate_employees`
  ADD PRIMARY KEY (`expatriate_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`);

--
-- Indexes for table `integration_logs`
--
ALTER TABLE `integration_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `app_id` (`app_id`);

--
-- Indexes for table `leaves`
--
ALTER TABLE `leaves`
  ADD PRIMARY KEY (`leave_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `leave_type_id` (`leave_type_id`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`leave_type_id`);

--
-- Indexes for table `loan_repayments`
--
ALTER TABLE `loan_repayments`
  ADD PRIMARY KEY (`repayment_id`),
  ADD KEY `loan_id` (`loan_id`),
  ADD KEY `fk_loan_payroll_id` (`payroll_id`);

--
-- Indexes for table `loan_types`
--
ALTER TABLE `loan_types`
  ADD PRIMARY KEY (`loan_type_id`);

--
-- Indexes for table `paye_tax_bands`
--
ALTER TABLE `paye_tax_bands`
  ADD PRIMARY KEY (`band_id`);

--
-- Indexes for table `payroll_details`
--
ALTER TABLE `payroll_details`
  ADD PRIMARY KEY (`payroll_detail_id`),
  ADD KEY `payroll_id` (`payroll_id`),
  ADD KEY `component_id` (`component_id`),
  ADD KEY `fk_advance_id` (`reference_id`),
  ADD KEY `fk_payroll_loan_id` (`loan_id`),
  ADD KEY `fk_payroll_advance_id` (`advance_id`);

--
-- Indexes for table `payroll_master`
--
ALTER TABLE `payroll_master`
  ADD PRIMARY KEY (`payroll_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `idx_payroll_period` (`period_id`,`employee_id`),
  ADD KEY `idx_payroll_employee` (`employee_id`);

--
-- Indexes for table `payroll_periods`
--
ALTER TABLE `payroll_periods`
  ADD PRIMARY KEY (`period_id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`permission_id`),
  ADD UNIQUE KEY `permission_name` (`permission_name`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`project_id`),
  ADD UNIQUE KEY `project_code` (`project_code`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `project_manager_id` (`project_manager_id`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_permission_id`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `salary_advances`
--
ALTER TABLE `salary_advances`
  ADD PRIMARY KEY (`advance_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `status` (`status`),
  ADD KEY `deduction_date` (`deduction_date`),
  ADD KEY `fk_advance_payroll` (`deduction_payroll_id`);

--
-- Indexes for table `salary_components`
--
ALTER TABLE `salary_components`
  ADD PRIMARY KEY (`component_id`),
  ADD UNIQUE KEY `component_code` (`component_code`);

--
-- Indexes for table `salary_levels`
--
ALTER TABLE `salary_levels`
  ADD PRIMARY KEY (`level_id`),
  ADD UNIQUE KEY `level_code` (`level_code`);

--
-- Indexes for table `special_benefits`
--
ALTER TABLE `special_benefits`
  ADD PRIMARY KEY (`benefit_id`),
  ADD UNIQUE KEY `benefit_code` (`benefit_code`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `tax_configurations`
--
ALTER TABLE `tax_configurations`
  ADD PRIMARY KEY (`config_id`),
  ADD KEY `company_id` (`company_id`);

--
-- Indexes for table `third_party_apps`
--
ALTER TABLE `third_party_apps`
  ADD PRIMARY KEY (`app_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD KEY `idx_users_employee` (`employee_id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `user_role_assignments`
--
ALTER TABLE `user_role_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_trail`
--
ALTER TABLE `audit_trail`
  MODIFY `audit_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `banks`
--
ALTER TABLE `banks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=217;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `company_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `currency_rates`
--
ALTER TABLE `currency_rates`
  MODIFY `rate_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `employee_benefits`
--
ALTER TABLE `employee_benefits`
  MODIFY `employee_benefit_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_documents`
--
ALTER TABLE `employee_documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_loans`
--
ALTER TABLE `employee_loans`
  MODIFY `loan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `employee_project_assignments`
--
ALTER TABLE `employee_project_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_salary_structure`
--
ALTER TABLE `employee_salary_structure`
  MODIFY `salary_structure_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `employee_types`
--
ALTER TABLE `employee_types`
  MODIFY `employee_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `expatriate_employees`
--
ALTER TABLE `expatriate_employees`
  MODIFY `expatriate_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `integration_logs`
--
ALTER TABLE `integration_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leaves`
--
ALTER TABLE `leaves`
  MODIFY `leave_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `leave_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `loan_repayments`
--
ALTER TABLE `loan_repayments`
  MODIFY `repayment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `loan_types`
--
ALTER TABLE `loan_types`
  MODIFY `loan_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `paye_tax_bands`
--
ALTER TABLE `paye_tax_bands`
  MODIFY `band_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payroll_details`
--
ALTER TABLE `payroll_details`
  MODIFY `payroll_detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `payroll_master`
--
ALTER TABLE `payroll_master`
  MODIFY `payroll_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payroll_periods`
--
ALTER TABLE `payroll_periods`
  MODIFY `period_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `permission_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `project_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `role_permission_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `salary_advances`
--
ALTER TABLE `salary_advances`
  MODIFY `advance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `salary_components`
--
ALTER TABLE `salary_components`
  MODIFY `component_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `salary_levels`
--
ALTER TABLE `salary_levels`
  MODIFY `level_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `special_benefits`
--
ALTER TABLE `special_benefits`
  MODIFY `benefit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tax_configurations`
--
ALTER TABLE `tax_configurations`
  MODIFY `config_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `third_party_apps`
--
ALTER TABLE `third_party_apps`
  MODIFY `app_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_role_assignments`
--
ALTER TABLE `user_role_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `attendance_records_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`);

--
-- Constraints for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD CONSTRAINT `audit_trail_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`) ON DELETE CASCADE;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`),
  ADD CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`employee_type_id`) REFERENCES `employee_types` (`employee_type_id`),
  ADD CONSTRAINT `employees_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`),
  ADD CONSTRAINT `fk_bank` FOREIGN KEY (`bank_id`) REFERENCES `banks` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `employee_benefits`
--
ALTER TABLE `employee_benefits`
  ADD CONSTRAINT `employee_benefits_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `employee_benefits_ibfk_2` FOREIGN KEY (`benefit_id`) REFERENCES `special_benefits` (`benefit_id`);

--
-- Constraints for table `employee_documents`
--
ALTER TABLE `employee_documents`
  ADD CONSTRAINT `employee_documents_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_loans`
--
ALTER TABLE `employee_loans`
  ADD CONSTRAINT `employee_loans_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `employee_loans_ibfk_2` FOREIGN KEY (`loan_type_id`) REFERENCES `loan_types` (`loan_type_id`),
  ADD CONSTRAINT `employee_loans_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `employees` (`employee_id`);

--
-- Constraints for table `employee_project_assignments`
--
ALTER TABLE `employee_project_assignments`
  ADD CONSTRAINT `employee_project_assignments_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `employee_project_assignments_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`);

--
-- Constraints for table `employee_salary_structure`
--
ALTER TABLE `employee_salary_structure`
  ADD CONSTRAINT `employee_salary_structure_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `employee_salary_structure_ibfk_2` FOREIGN KEY (`component_id`) REFERENCES `salary_components` (`component_id`);

--
-- Constraints for table `expatriate_employees`
--
ALTER TABLE `expatriate_employees`
  ADD CONSTRAINT `expatriate_employees_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `integration_logs`
--
ALTER TABLE `integration_logs`
  ADD CONSTRAINT `integration_logs_ibfk_1` FOREIGN KEY (`app_id`) REFERENCES `third_party_apps` (`app_id`);

--
-- Constraints for table `leaves`
--
ALTER TABLE `leaves`
  ADD CONSTRAINT `leaves_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `leaves_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`leave_type_id`);

--
-- Constraints for table `loan_repayments`
--
ALTER TABLE `loan_repayments`
  ADD CONSTRAINT `fk_loan_payroll_id` FOREIGN KEY (`payroll_id`) REFERENCES `payroll_master` (`payroll_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `loan_repayments_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `employee_loans` (`loan_id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_details`
--
ALTER TABLE `payroll_details`
  ADD CONSTRAINT `fk_payroll_advance_id` FOREIGN KEY (`advance_id`) REFERENCES `salary_advances` (`advance_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_payroll_loan_id` FOREIGN KEY (`loan_id`) REFERENCES `employee_loans` (`loan_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payroll_details_ibfk_1` FOREIGN KEY (`payroll_id`) REFERENCES `payroll_master` (`payroll_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payroll_details_ibfk_2` FOREIGN KEY (`component_id`) REFERENCES `salary_components` (`component_id`);

--
-- Constraints for table `payroll_master`
--
ALTER TABLE `payroll_master`
  ADD CONSTRAINT `payroll_master_ibfk_1` FOREIGN KEY (`period_id`) REFERENCES `payroll_periods` (`period_id`),
  ADD CONSTRAINT `payroll_master_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `payroll_master_ibfk_3` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`);

--
-- Constraints for table `payroll_periods`
--
ALTER TABLE `payroll_periods`
  ADD CONSTRAINT `payroll_periods_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`),
  ADD CONSTRAINT `payroll_periods_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `payroll_periods_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `employees` (`employee_id`);

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`),
  ADD CONSTRAINT `projects_ibfk_2` FOREIGN KEY (`project_manager_id`) REFERENCES `employees` (`employee_id`);

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`role_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`permission_id`);

--
-- Constraints for table `salary_advances`
--
ALTER TABLE `salary_advances`
  ADD CONSTRAINT `fk_advance_payroll` FOREIGN KEY (`deduction_payroll_id`) REFERENCES `payroll_master` (`payroll_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `salary_advances_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `tax_configurations`
--
ALTER TABLE `tax_configurations`
  ADD CONSTRAINT `tax_configurations_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`company_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`);

--
-- Constraints for table `user_role_assignments`
--
ALTER TABLE `user_role_assignments`
  ADD CONSTRAINT `user_role_assignments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_role_assignments_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`role_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
