CREATE DATABASE IF NOT EXISTS barber_shop
	CHARACTER SET utf8mb4
	COLLATE utf8mb4_unicode_ci;
USE barber_shop;

CREATE TABLE IF NOT EXISTS users (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	name VARCHAR(120) NOT NULL,
	email VARCHAR(190) NOT NULL,
	password_hash VARCHAR(255) NOT NULL,
	role ENUM('customer', 'barber', 'admin') NOT NULL DEFAULT 'customer',
	is_active TINYINT(1) NOT NULL DEFAULT 1,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_users_email (email),
	KEY idx_users_role_active (role, is_active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS services (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	name VARCHAR(120) NOT NULL,
	description VARCHAR(500) NOT NULL DEFAULT '',
	price DECIMAL(10, 2) NOT NULL,
	duration_minutes SMALLINT UNSIGNED NOT NULL,
	is_active TINYINT(1) NOT NULL DEFAULT 1,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_services_name (name),
	CHECK (price >= 0),
	CHECK (duration_minutes > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS barber_schedules (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	barber_id BIGINT UNSIGNED NOT NULL,
	day_of_week TINYINT UNSIGNED NOT NULL COMMENT 'ISO weekday: Monday=1 through Sunday=7',
	start_time TIME NOT NULL,
	end_time TIME NOT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY uq_barber_schedule_day (barber_id, day_of_week),
	CONSTRAINT fk_schedule_barber FOREIGN KEY (barber_id) REFERENCES users (id)
		ON UPDATE CASCADE ON DELETE CASCADE,
	CHECK (day_of_week BETWEEN 1 AND 7),
	CHECK (start_time < end_time)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointments (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	customer_id BIGINT UNSIGNED NULL,
	customer_name VARCHAR(120) NOT NULL,
	customer_email VARCHAR(190) NOT NULL DEFAULT '',
	customer_phone VARCHAR(40) NOT NULL DEFAULT '',
	barber_id BIGINT UNSIGNED NOT NULL,
	service_id BIGINT UNSIGNED NOT NULL,
	starts_at DATETIME NOT NULL,
	ends_at DATETIME NOT NULL,
	status ENUM('pending', 'completed', 'no_show', 'cancelled') NOT NULL DEFAULT 'pending',
	notes VARCHAR(1000) NOT NULL DEFAULT '',
	created_by BIGINT UNSIGNED NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_appointments_barber_time_status (barber_id, starts_at, ends_at, status),
	KEY idx_appointments_customer_time (customer_id, starts_at),
	KEY idx_appointments_service (service_id),
	CONSTRAINT fk_appointment_customer FOREIGN KEY (customer_id) REFERENCES users (id)
		ON UPDATE CASCADE ON DELETE SET NULL,
	CONSTRAINT fk_appointment_barber FOREIGN KEY (barber_id) REFERENCES users (id)
		ON UPDATE CASCADE ON DELETE RESTRICT,
	CONSTRAINT fk_appointment_service FOREIGN KEY (service_id) REFERENCES services (id)
		ON UPDATE CASCADE ON DELETE RESTRICT,
	CONSTRAINT fk_appointment_creator FOREIGN KEY (created_by) REFERENCES users (id)
		ON UPDATE CASCADE ON DELETE SET NULL,
	CHECK (starts_at < ends_at)
) ENGINE=InnoDB;

INSERT IGNORE INTO services (name, description, price, duration_minutes) VALUES
	('The classic cut', 'A fresh shape-up, finished just how you like it.', 30.00, 30),
	('Cut and beard', 'A cut and beard tidy, finished with a hot towel.', 42.00, 45),
	('Beard tidy', 'A trim and hot towel to leave your beard feeling sharp.', 18.00, 30);
