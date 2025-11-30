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
 Spoonacular API | Latest | Recipe Suggestions and calorie calculation
 API ninja | Latest | Pantry items calorie calculation

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
- **Step 4: Configure Email for OTP**
1. Update your sendEmail.php code with your email and password.
- *Note: Use an App Password generated from Gmail/Outlook security settings. Never hard-code your real login password in production.*

- **Step 5: (Optional) Configure API Key for Recipes**
1. Configure API key for recipes in recipes.php
   $apiKey = 'YOUR_API_KEY_HERE';

# 4. Execution Instructions:
- **Step 1: Start Server**
1. Open XAMPP Control Panel
2. Make sure Apache and MySQL are running
- **Step 2: Launch Application**
Open the browser and navigate to:
http://localhost/smartnutrition/Home.php
- **Step 3: Typical Usage Flow**
1. Sign Up via signup.php
2. Sign In via signin.php
3. Once signed in will be routed to Home page which is Index.php
4. Navigate from header to
   - Pantry (food_items.php) – Manage pantry items like adding or deleting, purchase date and expiry date
   - Meal Logs (meallogs.php) – Log meals and view history
   - Recipes (recipes.php) – Get recipe suggestions
   - Profile (profile.php) – Update height, weight, and see BMI/calorie goal

# 5. Input/Output Explanation:
**1. Sign up**
##### Input #####
1. Enter user full name
2. Enter Email address
3. Enter Password (minimum 6 characters)
4. Optional fields: Age, Height (cm), Weight (kg)

##### Output #####
1. Account creation confirmation
2. User saved into database with the passoword hashed
3. Profile displayed on dashboard after login

**2 Sign In**
##### Input #####
1. Email OR Username
2. Password
3. Clicks Sign in button

##### Outout #####
Successful login → redirect to Index.php dashboard
Failed login → “Invalid credentials” error

**3. Authentication – Forgot Password (OTP Flow)**
- *Step 1: Request OTP*
##### Inputs: #####
1. Clicks Forget Passoword
2. Registered email

##### Outputs: #####
1. Email with 6-digit OTP sent to user
2. Database stores:
3. OTP
4. OTP expiry time (10 mins)

- *Step 2: Verify OTP & Set New Password*
##### Inputs: #####
1. Email
2. 6-digit OTP
3. New password
4. Confirm new password

##### Outputs: #####
1. Password updated
2. OTP cleared from database
Message: “Your password has been reset. Please sign in.”

**4. Pantry Management**
##### Inputs #####

1. Enter name of pantry item, quantity, purchased date expiry date, category, storage type(pantry, refrigerator, freezer), unit.
2. Clicks save

##### Outputs #####

1. Item saved in food_items table in the database
2. Visible in Pantry list for the user
3. Displayed in dashboards for:
 - Fresh items count
 - Expiring soon notifications
 - Low stock notifications

**5. Meal Logs**
##### Inputs #####
1. Select Meal type (Breakfast / Lunch / Dinner / Snack)
2. Select Pantry item used for the meal (optional)
3. Enter number of Quantity consumed
4. Unit (serving, cup, grams, etc.)

##### Internal  #####
1. Fetches calorie-per-unit from pantry item
2. Calculates:
   - Total calories
   - Total protein
   - Total carbs
   - Total fat

##### Outputs #####

1. New meal record saved in meal_logs table
2. Displayed under Recent Meal Logs
Affects:
In Calories Today in home page
In Doughnut Chart (Consumed vs Goal)

**6. Recipes Tab**
##### Inputs #####
1. Selected the pantry items from your pantry, diet, max time, intolerances, number of recipes you required to see.
2. Click find recipes

##### Instructions #####
Optional nutritional information

##### Outputs #####
1. Display reccipes to the user
2. User can log a meal if he likes the recipes and the calories will be added to the dashboard
3. Meal will be saved to recent meal logs

**7. Profile**
##### Inputs #####

1. Updated height (cm), weight (kg)
2. Selects activity level
3. Goal mode ( selects check box = true else false)
4. clicks save

##### Outputs #####
1. automatically calculated the BMI
2. BMI category label (Underweight / Normal / Overweight / Obesity)
3. Auto-generated daily calorie goal (if goal mode is enabled):
4. Underweight → Higher calorie target
6. Normal → Balanced target
7. Overweight → Lower target
8. Obesity → Weight-loss oriented target

# 6. Features #
##### User Management #####
- Signup, login, logout
- Secure password hashing
- OTP-based password reset
- Profile editing (height, weight)
- Automatic BMI calculation
- Auto-generated calorie goal based on BMI

##### Pantry Management #####
- Add/edit/delete pantry items
- Expiring soon alerts
- Expired item detection
- Low-stock notification

#### Meal Logging System #####
- Select pantry item
- Quantity-based calorie calculation
- Macronutrient tracking (protein, carbs, fat)
- Daily history display

##### Dashboard #####
- Calories consumed vs daily goal
- Fresh vs expired item chart
- Recently logged meals
- Notification dropdown for expiry alerts

##### Recipe Integration (Optional) #####
- API-ready design for future expansion

# 7. Troubleshooting #
**1. OTP Email Not Delivered**
- Cause: Incorrect SMTP configuration
- Fix:Use Gmail App Password
- Enable 2FA
- Use SMTP port 587

**2. Dashboard Calories Not Updating**
- Cause: consumed_at date mismatch
- Fix:Ensure timezone in PHP and MySQL match
- Verify meal_logs has calories column populated

**3. “Blank Page” Error**
- Enable error display:

ini_set('display_errors',1);

error_reporting(E_ALL);

**4. Database Connection Error**

- Verify:

DB_USER = "root";

DB_PASS = "";

- Ensure MySQL running.

**5. CSS Not Loading**

Check:

- assets/css/
- folder path in HTML.

# 8. Acknowledgements #
**Tools Used**
- PHP
- MySQL Database
- XAMPP Server
- Chart.js
- PHPMailer
- HTML/CSS/JavaScript

# 9. AI Assistance Disclaimer #

*This project utilized ChatGPT for:*
- Debugging PHP errors
- Improving UI/UX styling
- Optimizing SQL queries
- Clarifying architectural decisions

   
   

