CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    subscription_until DATETIME DEFAULT NULL,
    plan_type VARCHAR(20) DEFAULT 'personal',
    theme VARCHAR(10) DEFAULT 'dark',
    role VARCHAR(20) DEFAULT 'owner',
    company_owner_id INT DEFAULT NULL,
    invite_code VARCHAR(64) DEFAULT NULL,
    max_seats INT DEFAULT 5,
    logo_url VARCHAR(255) DEFAULT NULL,
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_owner_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    task_type VARCHAR(50) NOT NULL,
    source_file LONGBLOB NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    target_file LONGBLOB DEFAULT NULL,
    target_file_name VARCHAR(255) DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    result_file VARCHAR(255) DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);