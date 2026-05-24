-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 24, 2026 at 04:31 PM
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
-- Database: `ims_db`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `products_supply_log` (IN `p_supplier_id` VARCHAR(10), IN `p_product_id` VARCHAR(10), IN `p_quantity` INT, IN `p_delivery_date` DATE)   BEGIN
    INSERT INTO products_supply (supplier_id, product_id, quantity, delivery_date)
    VALUES (p_supplier_id, p_product_id, p_quantity, p_delivery_date);

    UPDATE products 
    SET stock = stock + p_quantity 
    WHERE REPLACE(product_id, ' ', '') = REPLACE(p_product_id, ' ', '');
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` varchar(10) NOT NULL,
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email_address` varchar(50) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `username` varchar(25) NOT NULL,
  `password` varchar(255) NOT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employee_id`, `id`, `first_name`, `last_name`, `email_address`, `contact_number`, `username`, `password`, `reset_token`, `token_expiry`, `created_at`) VALUES
('EID4', 4, 'Pochacco', 'Beagle', 'p.beagle.4@kawaiistore.com', '+639912345678', 'pachi_0229', '$2y$10$xHUlg/BhzRC2edcc/bl9U.VIG.umgMTSIQ8YSiEm4zJTj2j.MaNi2', NULL, NULL, '2026-05-18 05:01:43'),
('EID5', 5, 'Sweet Piano', 'Lamb', 's.lamb.5@kawaiistore.com', '+639987654321', 'sweetie_76', '$2y$10$lMjTr.Ja1Pk0A9puACE54ODsCqF31nJsh8Apscp04acusSWgNYelq', NULL, NULL, '2026-05-18 23:45:10'),
('EID6', 6, 'Pompompurin', 'Retriever', 'p.retriever.6@kawaiistore.com', '+639912348765', 'PomPom416', '$2y$10$YIbV0CQ0vS6O0YcuFl3F6.tY2YCXi/rnu3MBDy/99j67CD0FFpxPK', NULL, NULL, '2026-05-19 18:40:44'),
('EID7', 7, 'Tuxedosam', 'Penguin', 't.penguin.7@kawaiistore.com', '+639987651234', 'TuxeDeluxe512', '$2y$10$dnVMOfpeFh5sRLSgZuoxfOh864Kv1PmBrpTYGRdhUHI5LqDcyquAe', NULL, NULL, '2026-05-21 21:58:43'),
('EID8', 8, 'Aggretsuko', 'Panda', 'a.panda.8@kawaiistore.com', '+639987126534', 'xxmadpandaxx', '$2y$10$Gm4N4Vz0C5gWGNNhCE7BzeM6zkz6k2Uo2ZD2WjcSCI1yOoIyZLf52', NULL, NULL, '2026-05-21 22:43:51');

--
-- Triggers `employees`
--
DELIMITER $$
CREATE TRIGGER `employee_id_generator` BEFORE INSERT ON `employees` FOR EACH ROW BEGIN
    SET NEW.employee_id = CONCAT('EID', (SELECT AUTO_INCREMENT 
                                       FROM information_schema.TABLES 
                                       WHERE TABLE_SCHEMA = DATABASE() 
                                       AND TABLE_NAME = 'employees'));
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` varchar(10) NOT NULL,
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `brand` varchar(50) NOT NULL,
  `model` varchar(50) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `supplier_id` varchar(10) DEFAULT NULL,
  `stock` int(10) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `id`, `name`, `brand`, `model`, `color`, `expiry_date`, `supplier_id`, `stock`, `created_at`) VALUES
('PID1', 1, 'Pochacco Mini Plush & Acrylic Stand Set', 'Sanrio', 'Nuikatsu Plushie Life Series', NULL, NULL, 'SID1', 50, '2026-05-18 05:25:17'),
('PID10', 10, 'Strawberry Shortcake Cupcakes', 'Nana Pastries', NULL, NULL, '2026-05-30', 'SID3', 10, '2026-05-18 22:19:15'),
('PID12', 12, 'Sanrio Angel Ballerina Plush Keychain', 'Sanrio', 'Hangyodon', '', NULL, 'SID1', 20, '2026-05-21 22:08:42'),
('PID2', 2, 'Ebifurai Strawberry 9\" Plush', 'San-X', NULL, NULL, NULL, 'SID2', 50, '2026-05-18 21:23:21'),
('PID3', 3, 'Pompompurin 12\" Squishmallows Plush', 'Sanrio', '30th Anniversary', NULL, NULL, 'SID1', 25, '2026-05-18 21:31:40'),
('PID4', 4, 'Cogimyun 7\" Plush', 'Sanrio', 'I Love Me Series', NULL, NULL, 'SID1', 35, '2026-05-18 21:32:47'),
('PID5', 5, 'My Sweet Piano Plush Mascot Keychain', 'Sanrio', 'Sakura Petals Series', NULL, NULL, 'SID1', 25, '2026-05-18 21:37:26'),
('PID6', 6, 'Rilakkuma Smartphone Plush Charm', 'San-X', 'Chairoikoguma', NULL, NULL, 'SID2', 30, '2026-05-18 21:40:56'),
('PID7', 7, 'Kiiroitori 3D Drawstring Pouch', 'San-X', NULL, NULL, NULL, 'SID2', 25, '2026-05-18 21:41:32'),
('PID8', 8, 'Sumikko Gurashi Sitting Doll Pouch', 'San-X', 'Tonkatsu', NULL, NULL, 'SID2', 25, '2026-05-18 21:42:17'),
('PID9', 9, 'Sanrio Marble Sugar Cookies', 'Nana Pastries', NULL, 'Pink', '2026-06-15', 'SID3', 15, '2026-05-18 21:48:32');

--
-- Triggers `products`
--
DELIMITER $$
CREATE TRIGGER `product_id_generator` BEFORE INSERT ON `products` FOR EACH ROW BEGIN
    SET NEW.product_id = CONCAT('PID', (SELECT AUTO_INCREMENT 
                                        FROM information_schema.TABLES 
                                        WHERE TABLE_SCHEMA = DATABASE() 
                                        AND TABLE_NAME = 'products'));
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `products_supply`
--

CREATE TABLE `products_supply` (
  `product_supply_id` varchar(10) NOT NULL,
  `id` int(11) NOT NULL,
  `supplier_id` varchar(10) DEFAULT NULL,
  `product_id` varchar(10) DEFAULT NULL,
  `quantity` int(10) NOT NULL,
  `delivery_date` date DEFAULT NULL,
  `record_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products_supply`
--

INSERT INTO `products_supply` (`product_supply_id`, `id`, `supplier_id`, `product_id`, `quantity`, `delivery_date`, `record_at`) VALUES
('PSID1', 1, 'SID1', 'PID1', 25, '2026-05-18', '2026-05-17 21:57:54'),
('PSID2', 2, 'SID1', 'PID12', 15, '2026-05-20', '2026-05-21 14:11:01');

--
-- Triggers `products_supply`
--
DELIMITER $$
CREATE TRIGGER `product_supply_id` BEFORE INSERT ON `products_supply` FOR EACH ROW BEGIN
    SET NEW.product_supply_id = CONCAT('PSID', (SELECT AUTO_INCREMENT 
                                              FROM information_schema.TABLES 
                                              WHERE TABLE_SCHEMA = DATABASE() 
                                              AND TABLE_NAME = 'products_supply'));
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` varchar(10) NOT NULL,
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `email_address` varchar(100) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `id`, `name`, `email_address`, `contact_number`, `created_at`) VALUES
('SID1', 1, 'Sanrio Company, Ltd.', 'customercare@sanrio.com', '+18442636274', '2026-05-18 05:18:01'),
('SID2', 2, 'San-X net', 'overseas@san-x.co.jp', '+81332567621', '2026-05-18 21:17:44'),
('SID3', 3, 'Nana Pastries', 'nanapastries@gmail.com', '+81312345678', '2026-05-18 21:47:45'),
('SID4', 4, 'Mercis bv', 'info@mercis.nl', '+310206758036', '2026-05-21 22:12:25');

--
-- Triggers `suppliers`
--
DELIMITER $$
CREATE TRIGGER `supplier_id_generator` BEFORE INSERT ON `suppliers` FOR EACH ROW BEGIN
    SET NEW.supplier_id = CONCAT('SID', (SELECT AUTO_INCREMENT 
                                       FROM information_schema.TABLES 
                                       WHERE TABLE_SCHEMA = DATABASE() 
                                       AND TABLE_NAME = 'suppliers'));
END
$$
DELIMITER ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD UNIQUE KEY `email_address` (`email_address`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD KEY `fk_products_supplier` (`supplier_id`);

--
-- Indexes for table `products_supply`
--
ALTER TABLE `products_supply`
  ADD PRIMARY KEY (`product_supply_id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD KEY `fk_ps_supplier` (`supplier_id`),
  ADD KEY `fk_ps_product` (`product_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD UNIQUE KEY `email_address` (`email_address`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `products_supply`
--
ALTER TABLE `products_supply`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `products_supply`
--
ALTER TABLE `products_supply`
  ADD CONSTRAINT `fk_ps_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ps_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
