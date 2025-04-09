# StudyBud Learning Platform

A modern e-learning platform built with PHP, MySQL, and modern frontend technologies. StudyBud provides a comprehensive learning environment for students and educators.

## Features

### User Management
- **User Authentication**: Secure login and registration system
- **Password Recovery**: Forgot password functionality with email-based reset
- **User Profiles**: Customizable user profiles with profile pictures and bio
- **Account Settings**: Email, password, and profile management

### Learning Features
- **Course Management**: Browse, enroll, and track progress in courses
- **Video Learning System**: Watch educational videos with progress tracking
- **Personal Library**: Save favorite courses and videos for quick access
- **Watch History**: Track previously watched content
- **Progress Tracking**: Monitor completion status across all courses

### Admin System
- **User Management**: View, edit, and manage user accounts
- **Course Administration**: Add, edit, and remove courses and videos
- **Login Activity Monitoring**: Track user login attempts and activity
- **System Settings**: Configure platform-wide settings
- **Analytics Dashboard**: View statistics on users, courses, and platform usage

### UI/UX
- **Responsive Design**: Fully responsive interface that works on all devices
- **Modern Interface**: Clean, intuitive design using Bootstrap 5
- **Accessibility**: Designed with accessibility considerations

## Tech Stack
- **Backend**: PHP 8.0+
- **Database**: MySQL 8.0+
- **Frontend**: HTML5, CSS3, JavaScript
- **CSS Framework**: Bootstrap 5
- **JavaScript Libraries**: jQuery
- **Icons**: Bootstrap Icons

## System Requirements
- Web server (Apache/Nginx)
- PHP 8.0 or higher
- MySQL 8.0 or higher
- XAMPP/WAMP/MAMP for local development

## Installation
1. Clone the repository to your web server directory
2. Import the database schema from `database/schema.sql`
3. Configure database connection in `config/database.php`
4. Set up the appropriate environment variables in `config/config.php`
5. Ensure the web server has write permissions to the necessary directories
6. Access the application through your web browser

## Admin Setup
1. Navigate to `public/create_admin.php` to create an admin account
2. Login with your admin credentials
3. Access the admin dashboard at `public/admin/index.php`

## Security Features
- **Prepared Statements**: Protection against SQL injection
- **CSRF Protection**: Cross-Site Request Forgery prevention
- **Secure Session Management**: Protection against session hijacking
- **Password Hashing**: Secure password storage using Argon2id with pepper
- **Input Validation**: Comprehensive server-side validation
- **XSS Prevention**: Output escaping to prevent cross-site scripting

## Development
- Follow the coding standards in the existing codebase
- Use prepared statements for all database queries
- Validate all user inputs
- Escape all outputs to prevent XSS attacks

## License
This project is proprietary and confidential. Unauthorized copying, distribution, or use is strictly prohibited.
