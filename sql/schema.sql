SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS roles (
 role_id INT AUTO_INCREMENT PRIMARY KEY, role_name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS categories (
 category_id INT AUTO_INCREMENT PRIMARY KEY, category_name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS publishers (
 publisher_id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS users (
 user_id INT AUTO_INCREMENT PRIMARY KEY,
 role_id INT NOT NULL,
 first_name VARCHAR(50) NOT NULL, last_name VARCHAR(50) NOT NULL,
 email VARCHAR(100) NOT NULL UNIQUE, phone VARCHAR(20) DEFAULT NULL,
 password_hash VARCHAR(255) DEFAULT NULL,
 account_status ENUM('Active','Suspended') NOT NULL DEFAULT 'Active',
 archived_at DATETIME DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_user_role FOREIGN KEY(role_id) REFERENCES roles(role_id),
 INDEX idx_users_role_state(role_id,account_status,archived_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS books (
 book_id INT AUTO_INCREMENT PRIMARY KEY,
 category_id INT NOT NULL, publisher_id INT NOT NULL,
 isbn VARCHAR(20) NOT NULL UNIQUE,
 title VARCHAR(255) NOT NULL, author VARCHAR(255) NOT NULL,
 shelf_location VARCHAR(80) NOT NULL DEFAULT '',
 status ENUM('Available','On Loan') NOT NULL DEFAULT 'Available',
 archived_at DATETIME DEFAULT NULL,
 CONSTRAINT fk_book_category FOREIGN KEY(category_id) REFERENCES categories(category_id),
 CONSTRAINT fk_book_publisher FOREIGN KEY(publisher_id) REFERENCES publishers(publisher_id),
 INDEX idx_books_status(status,archived_at), INDEX idx_books_title(title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS loans (
 loan_id INT AUTO_INCREMENT PRIMARY KEY,
 book_id INT NOT NULL, patron_id INT NOT NULL, issued_by INT NOT NULL,
 issue_date DATE NOT NULL, due_date DATE NOT NULL, return_date DATE DEFAULT NULL,
 fine_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
 fine_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
 fine_waived DECIMAL(10,2) NOT NULL DEFAULT 0.00,
 status ENUM('Active','Returned') NOT NULL DEFAULT 'Active',
 archived_at DATETIME DEFAULT NULL,
 CONSTRAINT fk_loan_book FOREIGN KEY(book_id) REFERENCES books(book_id),
 CONSTRAINT fk_loan_patron FOREIGN KEY(patron_id) REFERENCES users(user_id),
 CONSTRAINT fk_loan_issuer FOREIGN KEY(issued_by) REFERENCES users(user_id),
 INDEX idx_loans_patron(patron_id,status), INDEX idx_loans_due(status,due_date),
 INDEX idx_loans_issue(issue_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS activity_logs (
 log_id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
 action VARCHAR(255) NOT NULL, timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_log_user FOREIGN KEY(user_id) REFERENCES users(user_id),
 INDEX idx_log_time(timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS notifications (
 notification_id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL, loan_id INT NOT NULL,
 kind ENUM('due_soon','overdue') NOT NULL,
 message VARCHAR(255) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 read_at DATETIME DEFAULT NULL, emailed_at DATETIME DEFAULT NULL,
 CONSTRAINT fk_note_user FOREIGN KEY(user_id) REFERENCES users(user_id),
 CONSTRAINT fk_note_loan FOREIGN KEY(loan_id) REFERENCES loans(loan_id),
 UNIQUE KEY uq_reminder(user_id,loan_id,kind),
 INDEX idx_note_user(user_id,read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO roles(role_name) VALUES ('Staff'),('Patron') ON DUPLICATE KEY UPDATE role_name=VALUES(role_name);
INSERT INTO categories(category_name) VALUES ('Fiction'),('Non-fiction'),('Science'),('History'),('Technology'),('Literature') ON DUPLICATE KEY UPDATE category_name=VALUES(category_name);
INSERT INTO publishers(name) VALUES ('Independent Publisher') ON DUPLICATE KEY UPDATE name=VALUES(name);
