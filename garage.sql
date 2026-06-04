-- 911 Garage database schema and sample seed data

CREATE DATABASE IF NOT EXISTS `garage` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `garage`;

DROP TABLE IF EXISTS `invoices`;
DROP TABLE IF EXISTS `job_cards`;
DROP TABLE IF EXISTS `vehicles`;
DROP TABLE IF EXISTS `inventory`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('ADMIN','CUSTOMER','MECHANIC') NOT NULL DEFAULT 'CUSTOMER',
  `address` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `vehicles` (
  `vehicle_no` VARCHAR(20) PRIMARY KEY,
  `customer_id` INT NOT NULL,
  `brand` VARCHAR(100) NOT NULL,
  `model` VARCHAR(120) NOT NULL,
  `category` ENUM('CAR','SUV','EV','HYBRID') NOT NULL DEFAULT 'CAR',
  `fuel_type` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `job_cards` (
  `job_card_id` VARCHAR(40) PRIMARY KEY,
  `vehicle_no` VARCHAR(20) NOT NULL,
  `mechanic_id` INT DEFAULT NULL,
  `repair_notes` TEXT NOT NULL,
  `status` ENUM('PENDING','IN_PROGRESS','COMPLETED') NOT NULL DEFAULT 'PENDING',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`vehicle_no`) REFERENCES `vehicles`(`vehicle_no`) ON DELETE CASCADE,
  FOREIGN KEY (`mechanic_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `invoices` (
  `invoice_no` VARCHAR(40) PRIMARY KEY,
  `job_card_id` VARCHAR(40) NOT NULL,
  `total_payable` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`job_card_id`) REFERENCES `job_cards`(`job_card_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `inventory` (
  `part_code` VARCHAR(40) PRIMARY KEY,
  `part_name` VARCHAR(255) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 0,
  `price_per_unit` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `low_stock_threshold` INT NOT NULL DEFAULT 5,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample admin user (password: admin123)
INSERT INTO `users` (`name`, `phone`, `email`, `password`, `role`, `address`) VALUES
('911 Garage Admin', '0000000000', 'admin@911garage.local', '$2y$10$N9qo8uLOickgx2ZMRZo4i.e9EeH/9Z09v1yMqzKyzSj8vYqS/1FO2', 'ADMIN', 'Headquarters');

-- Sample mechanic
INSERT INTO `users` (`name`, `phone`, `email`, `password`, `role`, `address`) VALUES
('Rohan Mehta', '9988776655', 'rohan@911garage.local', '$2y$10$N9qo8uLOickgx2ZMRZo4i.e9EeH/9Z09v1yMqzKyzSj8vYqS/1FO2', 'MECHANIC', 'Pit Lane 7');

-- Sample customer
INSERT INTO `users` (`name`, `phone`, `email`, `password`, `role`, `address`) VALUES
('Simran Kaur', '9876543210', 'simran@example.com', '$2y$10$N9qo8uLOickgx2ZMRZo4i.e9EeH/9Z09v1yMqzKyzSj8vYqS/1FO2', 'CUSTOMER', 'Mumbai, India');

INSERT INTO `vehicles` (`vehicle_no`, `customer_id`, `brand`, `model`, `category`, `fuel_type`) VALUES
('MH01AB1234', 3, 'Toyota', 'Camry', 'CAR', 'PETROL');

INSERT INTO `job_cards` (`job_card_id`, `vehicle_no`, `mechanic_id`, `repair_notes`, `status`) VALUES
('JG-202606020001-1001', 'MH01AB1234', 2, 'Full systems check and brake tune', 'PENDING');

INSERT INTO `inventory` (`part_code`, `part_name`, `quantity`, `price_per_unit`, `low_stock_threshold`) VALUES
('BRK-001', 'Premium Brake Pad Set', 24, 1999.00, 4),
('OIL-001', 'Synthetic Engine Oil 5W-40', 46, 749.00, 7),
('FLT-001', 'High-Flow Air Filter', 33, 650.00, 5),
('SPK-001', 'Performance Spark Plug Set', 28, 1299.00, 6);
