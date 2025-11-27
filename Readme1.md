# 1. Project Title and Description:
## Project Title: 
Smart Nutrition
## Brief Overview
Smart Nutrition is a web-based nutrition management system that helps users:
1.  Track pantry items and their expiration dates.
2.  Log daily meals with calorie and macronutrient information.
3.  Visualize calorie consumption against a daily goal using charts.
4.  Receive recipe recommendations based on available ingredients.
5.  Manage profile measurements (height, weight, BMI) and auto-derive calorie goals
6.  Reset passwords securely using email-based OTP (One-Time Password).
The application is built with PHP (backend), MySQL (database), HTML/CSS/JS (frontend), and uses PHPMailer for OTP email delivery and Chart.js for dashboards.

#  2. Prerequisites:
## Software Requirements:
 Component | Recommended Version | Purpose
 --- | --- | --- 
 PHP | 8.0+ | Server-side scripting
 MySQL | 5.7+ / 8.0+ | Relational database
 XAMPP | xAMP | Local web + DB server
 Web Browser | Chrome / Edge / Firefox | UI access
 PHPMailer | Latest | Sending OTP emails
 Chart.js | Latest | Dashboard & charts

 ## Hardware Requirements:
 1. CPU: Any modern multi-core processor
 2. RAM: At least 4 GB (8+ GB recommended for smooth multitasking)
 3. Disk Space: ~1 GB for XAMPP, database, and project files
 4. OS: Windows / Linux / macOS with support for PHP & MySQL

# 3. Installation Instructions:
Follow these steps to set up Smart Nutrition on a local machine:
- **Step 1: Clone / Download the Project**
 From git click the link https://github.com/your-username/smart-nutrition.git and download the zip file and extract to C:\xampp\htdocs\smartnutrition\   (make sure the project folder is this)
- **Step 2: Start XAMPP**
1. Open XAMPP Control Panel
2. Start:
   a. Apache
   b. Mysql
- **Step 3: Create and Configure Database**
1. Open a browser and go to:
   http://localhost/phpmyadmin
2. Click New → Create Database:
   CREATE DATABASE smart_nutrition;
3. You can: download Import the sql file which we have in the extracted folder. Make sure all the tables are created once you import
- **Step 5: Configure Email for OTP**
1. Update your sendEmail.php code with your email and password.
''' $mail->isSMTP();
$mail->Host       = 'smtp.gmail.com';          // or smtp.office365.com for Outlook
$mail->SMTPAuth   = true;
$mail->Username   = 'your_email@example.com';  // your email
$mail->Password   = 'YOUR_APP_PASSWORD';       // app password, not main password
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port       = 587;

$mail->setFrom('your_email@example.com', 'Smart Nutrition'); '''

   

