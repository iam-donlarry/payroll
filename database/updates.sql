-- Create table for Occasional Taxable Payments
CREATE TABLE IF NOT EXISTS `occasional_payments` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `pay_month` date NOT NULL, -- The first day of the month this applies to (e.g., '2025-12-01')
  `status` enum('pending','paid') NOT NULL DEFAULT 'pending',
  `payroll_id` int(11) DEFAULT NULL, -- Links to the payroll_master record when paid
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`payment_id`),
  KEY `employee_id` (`employee_id`),
  KEY `pay_month` (`pay_month`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add 'Occasional Payment' to salary_components if it doesn't exist
-- We use INSERT IGNORE or check existence to avoid duplicates if run multiple times
INSERT IGNORE INTO `salary_components` (`component_name`, `component_type`, `component_code`, `is_taxable`, `is_statutory`, `calculation_type`, `description`, `is_active`)
VALUES ('Occasional Payment', 'earning', 'OCCASIONAL', 1, 0, 'fixed', 'One-time taxable payment', 1);

-- Note: 13th Month component already exists in the schema provided (component_id 6, code 13TH).
-- If not, uncomment the following:
-- INSERT IGNORE INTO `salary_components` (`component_name`, `component_type`, `component_code`, `is_taxable`, `is_statutory`, `calculation_type`, `description`, `is_active`)
-- VALUES ('13th Month', 'earning', '13TH', 1, 0, 'fixed', 'Automatic 13th Month Salary', 1);
