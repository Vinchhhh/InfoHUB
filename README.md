# InfoHUB - Local Government Information Hub

InfoHUB is a comprehensive web application designed to serve as an information hub for local government services. It features an AI-powered chatbot interface, user management system, and administrative panel for managing government information and user interactions.

## 🚀 Features

### Core Functionality
- **AI-Powered Information Chat**: Interactive chatbot for local government service inquiries
- **User Authentication**: Secure login/registration system with Google OAuth integration
- **Guest Access**: Limited access for non-registered users
- **User Management**: Profile editing and user administration
- **Survey System**: Comprehensive feedback collection with multiple sections
- **Real-time Metrics**: Live user activity tracking and visit statistics
- **Database Management**: Backup and restore functionality

### Security Features
- **reCAPTCHA Integration**: Bot protection for login and registration
- **Session Management**: Custom session handling with secure cookie settings
- **Access Control**: Role-based access (admin/user/guest)
- **SQL Injection Protection**: Prepared statements and input validation

### User Interface
- **Responsive Design**: Mobile-friendly interface
- **Modern UI**: Clean and intuitive user experience
- **Custom Styling**: Comprehensive CSS framework
- **Interactive Elements**: JavaScript-enhanced functionality

## 🛠️ Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL (MariaDB)
- **Frontend**: HTML5, CSS3, JavaScript
- **Authentication**: Google OAuth 2.0
- **Security**: reCAPTCHA v2
- **Server**: Apache (XAMPP)
- **Session Management**: Custom PHP sessions

## 📁 Project Structure

```
InfoHUB/
├── assets/                 # Static assets (images, icons)
│   ├── bg1.jpg
│   ├── card.svg
│   └── roxas_seal.png
├── metrics/               # Analytics and tracking data
│   ├── active_users.json
│   ├── daily_visits.json
│   └── visits_count.txt
├── sessions/              # Custom session storage
├── admin_panel.php        # Administrative interface
├── admin_style.css        # Admin panel styling
├── backup.php            # Database backup functionality
├── connect.php           # Database connection
├── delete_user.php       # User deletion
├── edit_profile.php      # User profile editing
├── edit_user.php         # Admin user editing
├── guest_login.php       # Guest access
├── google_login.php      # Google OAuth integration
├── home.php              # Landing page
├── index.php             # Main entry point
├── login.php             # User authentication
├── logout.php            # Session termination
├── main.php              # Main application interface
├── main_style.css        # Main application styling
├── register.php          # User registration
├── restore.php           # Database restore functionality
├── script.js             # Client-side JavaScript
├── style.css             # Global styling
├── survey.php            # Feedback survey system
└── .htaccess             # URL rewriting rules
```

## 🚀 Installation & Setup

### Prerequisites
- XAMPP (Apache, MySQL, PHP)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web browser with JavaScript enabled

### Installation Steps

1. **Clone/Download the Project**
   ```bash
   git clone <repository-url>
   # or download and extract to your XAMPP htdocs folder
   ```

2. **Database Setup**
   - Start XAMPP services (Apache and MySQL)
   - Create a database named `infohubdb`
   - Import any provided SQL schema files
   - Update database credentials in `connect.php` if needed

3. **Configuration**
   - Update `connect.php` with your database credentials:
     ```php
     $servername = "localhost";
     $username = "root";
     $password = "";
     $dbname = "infohubdb";
     ```

4. **Google OAuth Setup** (Optional)
   - Obtain Google OAuth credentials
   - Update `google_login.php` with your client ID and secret
   - Configure authorized redirect URIs

5. **reCAPTCHA Setup** (Optional)
   - Obtain reCAPTCHA site and secret keys
   - Update the keys in `login.php` and `register.php`

6. **File Permissions**
   - Ensure write permissions for:
     - `sessions/` directory
     - `metrics/` directory
     - Any backup/restore directories

7. **Access the Application**
   - Navigate to `http://localhost/InfoHUB/`
   - The application should load with the home page

## 🔧 Configuration

### URL Rewriting
The application uses Apache mod_rewrite for clean URLs. The `.htaccess` file handles:
- Route mapping (e.g., `/main` → `main.php`)
- PHP extension removal
- Redirect handling

### Session Configuration
Custom session handling is configured in each PHP file:
- Custom session storage path
- Secure cookie settings
- Session lifetime management

### Database Configuration
Update `connect.php` with your database settings:
```php
$servername = "localhost";
$username = "your_username";
$password = "your_password";
$dbname = "infohubdb";
```

## 👥 User Roles & Access

### Guest Users
- Limited access to basic information
- Cannot access main application features
- Redirected to guest login

### Registered Users
- Full access to AI chatbot
- Profile management
- Survey participation
- Access to main application

### Administrators
- All user privileges
- User management (create, edit, delete)
- Database backup/restore
- System metrics and analytics
- Access to admin panel

## 📊 Features Overview

### AI Chatbot Interface
- Natural language processing for government service inquiries
- Context-aware responses
- Real-time interaction tracking

### User Management
- Secure registration with email verification
- Profile editing and management
- Password reset functionality
- Google OAuth integration

### Survey System
- Multi-section feedback collection
- Rating scales and text responses
- Data export and analysis
- User satisfaction tracking

### Analytics & Metrics
- Real-time active user tracking
- Daily visit statistics
- User engagement metrics
- System performance monitoring

### Database Management
- Automated backup creation
- Database restore functionality
- Data integrity checks
- Export/import capabilities

## 🔒 Security Considerations

- **Input Validation**: All user inputs are validated and sanitized
- **SQL Injection Protection**: Prepared statements used throughout
- **XSS Prevention**: Output escaping and content security policies
- **Session Security**: Secure session configuration and management
- **Access Control**: Role-based permissions and authentication
- **Bot Protection**: reCAPTCHA integration for form submissions

## 🐛 Troubleshooting

### Common Issues

1. **Database Connection Errors**
   - Verify MySQL service is running
   - Check database credentials in `connect.php`
   - Ensure database `infohubdb` exists

2. **Session Issues**
   - Check write permissions for `sessions/` directory
   - Verify session configuration in PHP files
   - Clear browser cookies and session data

3. **URL Rewriting Not Working**
   - Ensure Apache mod_rewrite is enabled
   - Check `.htaccess` file is present and readable
   - Verify Apache configuration allows .htaccess overrides

4. **Google OAuth Issues**
   - Verify OAuth credentials are correct
   - Check authorized redirect URIs
   - Ensure HTTPS is used in production

5. **reCAPTCHA Errors**
   - Verify site and secret keys are correct
   - Check network connectivity to Google services
   - Ensure JavaScript is enabled in browser

## 📝 Development Notes

- The application uses custom session management for better control
- Real-time metrics are stored in JSON files for quick access
- The codebase follows a modular structure for easy maintenance
- All user inputs are properly validated and escaped
- The application is designed to be scalable and maintainable

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📄 License

This project is developed for local government use. Please ensure compliance with your organization's policies and regulations.

## 📞 Support

For technical support or questions about the InfoHUB system, please contact your system administrator or development team.

---

**Note**: This application is designed for local government information services. Ensure all data handling complies with relevant privacy and security regulations.
