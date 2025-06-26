CREATE DATABASE IF NOT EXISTS library_management;
USE library_management;

CREATE TABLE roles (
    id INT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT
);

INSERT INTO roles (id, name, description) VALUES
(1, 'Administrator', 'Full system access and management'),
(2, 'Librarian', 'General library operations'),
(3, 'Patron', 'Library user with borrowing privileges'),
(4, 'Cataloger', 'Catalog and book management'),
(5, 'Acquisitions Manager', 'Book acquisition and inventory'),
(6, 'Systems Administrator', 'Technical system management'),
(7, 'Researcher/Analyst', 'Data analysis and research'),
(8, 'Book Keeper', 'Financial and record keeping'),
(9, 'Event Planner', 'Library events and programs'),
(10, 'Help Desk Staff', 'User support and assistance');

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active',
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

CREATE TABLE books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(20) UNIQUE,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    publisher VARCHAR(255),
    publication_year YEAR,
    category VARCHAR(100),
    copies_total INT DEFAULT 1,
    copies_available INT DEFAULT 1,
    location VARCHAR(100),
    status ENUM('available', 'borrowed', 'reserved', 'maintenance') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE borrowings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    borrow_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE NULL,
    status ENUM('active', 'returned', 'overdue') DEFAULT 'active',
    fine_amount DECIMAL(10,2) DEFAULT 0.00,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (book_id) REFERENCES books(id)
);

CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    reservation_date DATE NOT NULL,
    status ENUM('active', 'fulfilled', 'cancelled') DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (book_id) REFERENCES books(id)
);

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'warning', 'reminder', 'alert') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert default admin user (password: admin123)
-- Insert secure admin account (password: Wallerpo) with advanced security hash
INSERT INTO users (username, password, email, full_name, role_id) VALUES
('admin_secure', '$2y$12$Ht0vEzYVXsHrMY7Fb.s.3.QQU8YU4wNSFXGEbvEP7Lj0aDUFgCyMm', 'admin@gmail.com', 'System Administrator', 1),
('librarian1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'librarian@gmail.com', 'Main Librarian', 2),
('patron1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patron@gmail.com', 'John Patron', 3),
('cataloger1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cataloger@gmail.com', 'Book Cataloger', 4),
('acquisitions1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'acquisitions@gmail.com', 'Acquisitions Manager', 5),
('sysadmin1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'sysadmin@gmail.com', 'Systems Admin', 6),
('researcher1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'researcher@gmail.com', 'Data Researcher', 7),
('bookkeeper1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'bookkeeper@gmail.com', 'Book Keeper', 8),
('eventplanner1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'events@gmail.com', 'Event Planner', 9),
('helpdesk1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'helpdesk@gmail.com', 'Help Desk Staff', 10);

-- Sample books
INSERT INTO books (isbn, title, author, publisher, publication_year, category, copies_total, copies_available) VALUES
('978-0-123456-78-9', 'Introduction to Programming', 'John Smith', 'Tech Publishers', 2023, 'Computer Science', 5, 5),
('978-0-987654-32-1', 'History of Literature', 'Jane Doe', 'Academic Press', 2022, 'Literature', 3, 3),
('978-0-456789-01-2', 'Mathematics Fundamentals', 'Bob Johnson', 'Math Books Inc', 2023, 'Mathematics', 4, 4),
('978-0-111222-33-4', 'Advanced Physics', 'Dr. Sarah Wilson', 'Science Press', 2023, 'Science', 3, 3),
('978-0-555666-77-8', 'World History Chronicles', 'Michael Brown', 'History House', 2022, 'History', 6, 6),
('978-0-999888-77-6', 'Digital Marketing Guide', 'Lisa Chen', 'Business Books', 2024, 'Business', 2, 2),
('978-0-444333-22-1', 'Creative Writing Workshop', 'Emma Davis', 'Writers Guild', 2023, 'Literature', 4, 4),
('978-0-777666-55-4', 'Data Structures & Algorithms', 'Robert Kim', 'Tech Publishers', 2023, 'Computer Science', 5, 5),
('978-0-222111-00-9', 'Environmental Science', 'Dr. Green Taylor', 'Nature Press', 2022, 'Science', 3, 3),
('978-0-888999-11-2', 'Psychology Today', 'Dr. Amanda White', 'Mind Publishers', 2024, 'Psychology', 4, 4),
('978-0-333444-55-6', 'Art Through the Ages', 'Vincent Colors', 'Art House', 2023, 'Art', 2, 2),
('978-0-666777-88-9', 'Financial Management', 'David Money', 'Finance Corp', 2023, 'Business', 3, 3),
('978-0-123987-45-6', 'Modern Philosophy', 'Prof. Wisdom Stone', 'Philosophy Press', 2022, 'Philosophy', 2, 2);