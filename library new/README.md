# Library Management System

A comprehensive web-based library management system built with PHP and MySQL, designed to efficiently manage books, users, borrowings, reservations, and more.

## Features

- **User Management**: Add, edit, and manage library users with different access levels
- **Book Management**: Catalog books with details like title, author, ISBN, and availability
- **Borrowing System**: Track book borrowings, returns, and due dates
- **Reservation System**: Allow users to reserve books that are currently unavailable
- **Notification System**: Send alerts for due dates, available reservations, etc.
- **Activity Logging**: Track all user and admin activities for security and auditing
- **Responsive Design**: Works on desktop and mobile devices

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern web browser

## Installation

1. Clone the repository to your web server directory:
   ```
   git clone https://github.com/yourusername/library-management-system.git
   ```

2. Import the database schema from `database/library_db.sql`

3. Configure the database connection in `config.php`:
   ```php
   $db_host = "localhost";
   $db_user = "your_username";
   $db_pass = "your_password";
   $db_name = "library_db";
   ```

4. Access the application through your web browser:
   ```
   http://localhost/library-management-system/
   ```

5. Log in with the default admin credentials:
   - Username: admin
   - Password: admin123

## Usage

### Admin Panel

- **Dashboard**: Overview of library statistics and recent activities
- **Books**: Add, edit, delete books and manage inventory
- **Users**: Manage library members and staff accounts
- **Borrowings**: Process book checkouts and returns
- **Reservations**: Handle book reservation requests
- **Reports**: Generate various reports on library operations
- **Settings**: Configure system parameters and preferences

### User Panel

- Browse available books
- View borrowing history
- Make reservations
- Receive notifications
- Update personal information

## Security Features

- Password hashing
- CSRF protection
- Input validation
- Session management
- Activity logging

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgments

- FontAwesome for icons
- Bootstrap for responsive design components
- Chart.js for statistical visualizations