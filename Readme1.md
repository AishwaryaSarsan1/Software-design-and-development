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
#### Inputs ####
1. User Authentication:
   a. Enter Email as username.
   b. Passowrd
2. Pantry Items:
   Enter name of pantry item, quantity, purchased date expiry date, category, storage type(pantry, refrigerator, freezer), unit.
3. Meal Logs:
   a. Select meal type(breakfast, lunch or dinner)
   b. selected the pantry item used for the meal
4. In Profile:
   a. Enter your height and weight
5. Recipes tab:
   a. Selected the pantry items from your pantry, diet, max time, intolerances, number of recipes you required to see.
   b. Next click on find recipes.

#### Outputs ####
##### Dashboard Stats #####
1. Total pantry items
2. Calories consumed today
3. Food wasted (expired items)
4. Fresh vs expired item counts

##### Charts (via Chart.js) #####
1. Donut chart: Calories Today vs Goal
2. Donut chart: Fresh vs Expired

# 5. Input/Output Explanation:
- **1. Sign up**
##### Input #####
1. User full name
2. Email address
3. Password (minimum 6 characters)
4. Optional fields: Age, Height (cm), Weight (kg)

##### Output #####
1. Account creation confirmation
2. User saved into database with the passoword hashed
3. Profile displayed on dashboard after login

- **2 Sign In**
##### Input #####
1. Email OR Username
2. Password
3. Clicks Sign in button

##### Outout #####
Successful login → redirect to Index.php dashboard
Failed login → “Invalid credentials” error

- **3. Authentication – Forgot Password (OTP Flow)**
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

- **4. Pantry Management**
##### Inputs #####

1. Food item name (e.g., Oats, Milk)
2. Category (e.g., Grain, Dairy)
3. Quantity value
4. Quantity unit (e.g., kg, pcs, cups)
5. Storage type (Pantry / Refrigerator / Freezer)
6. Expiry date
7. Purchase date (optional)

##### Outputs #####

1. Item saved in food_items table
2. Visible in Pantry list for the user
3. Displayed in dashboards for:
 --> Fresh items count
 -->Expiring soon notifications
 -->Low stock notifications
   


   
   

