# 🚀 Deployment Guide: GitHub and Free Hosting

This guide provides step-by-step instructions to upload your ProjectCrew platform to **GitHub** and make it live online for **free** using a PHP & MySQL hosting service.

---

## 📂 Step 1: Upload to GitHub

Follow these commands in your terminal or Command Prompt to push the project to GitHub:

### 1. Initialize Git Repository
Make sure you are in your project root directory (`c:\New folder\htdocs\major.co\`) and run:
```bash
git init
```

### 2. Add files and Commit
Create a commit with all project files:
```bash
git add .
git commit -m "Initialize ProjectCrew v2 with secure uploads, password recovery, and Kanban workspace"
```

### 3. Link to GitHub and Push
1. Go to [GitHub](https://github.com/) and create a new **public or private repository** named `major.co`.
2. Do **not** check the boxes to initialize with a README, `.gitignore`, or License (these are already present in your project!).
3. Copy the repository URL (e.g., `https://github.com/your-username/major.co.git`).
4. Run the following commands in your terminal:
```bash
# Rename default branch to main
git branch -M main

# Link your local folder to GitHub (replace with your copied URL)
git remote add origin https://github.com/your-username/major.co.git

# Push the files
git push -u origin main
```

---

## 🌐 Step 2: Make the Project Live Online (For Free)

Because ProjectCrew runs on **PHP** and **MySQL**, static hosts like GitHub Pages, Netlify, or Vercel **cannot** host it directly. You must use a free PHP hosting provider. We recommend **InfinityFree** (100% free, unlimited disk space, and MySQL support).

### 1. Create a Free Account
1. Visit [InfinityFree](https://www.infinityfree.com/) and register a free account.
2. In your dashboard, click **Create Account**.
3. Choose a domain/subdomain type (e.g., `projectcrew.infinityfreeapp.com`) and complete the creation process.

### 2. Set Up the Database Online
1. In your InfinityFree dashboard, go to **MySQL Databases**.
2. Create a new database named `majorco`.
3. Note down your Database hostname, Database username, and Database password.
4. Click **Admin** (or open phpMyAdmin) next to your database.
5. In phpMyAdmin, click **Import** in the top menu.
6. Click **Choose File**, select `backend/database/database_complete.sql` from your project folder, and click **Go** to import the entire schema and seeded user accounts.

### 3. Configure Credentials on Server
Before uploading files, update `backend/db.php` with the database credentials you obtained from InfinityFree:
```php
$db_host = 'your_infinityfree_sql_hostname';
$db_name = 'your_infinityfree_db_name';
$db_user = 'your_infinityfree_db_username';
$db_pass = 'your_infinityfree_db_password';
```

### 4. Upload Files
1. In your InfinityFree account details, click **File Manager** (or connect via FTP using FileZilla with the provided FTP details).
2. Open the **`htdocs/`** directory.
3. Upload all the files and folders from your local `major.co` folder into the `htdocs/` folder (drag and drop or upload everything).
4. Verify that `index.html` is directly inside `htdocs/` (not in a subfolder).

### 5. Visit Your Website
Open `http://projectcrew.infinityfreeapp.com` (your subdomain) in your browser and test the login with:
* **Email:** `alex@test.com`
* **Password:** `alex123`
