# SCNHS Deployment Guide

## Architecture Overview

```
[User Browser]
     │
     ├── visits InfinityFree (PHP site) ──► [InfinityFree]
     │         home.html, registration, admin/
     │         MySQL DB (students, admins, sections)
     │
     └── Admin clicks "Risk" button
               │
               ▼
          [PHP curl] ──► [Render.com] Flask AI API
                          /health  /predict
```

---

## Step 0 — Train the Model Locally First

> Run this BEFORE pushing to GitHub. The model file must exist.

```bash
# In your project directory (c:\xampp\htdocs\Final)
pip install scikit-learn pandas
python train_ai.py
```

You should see:
```
✅ Model saved to: dropout_model.pkl
```

---

## Step 1 — Deploy Flask AI API to Render.com

### 1.1 Push to GitHub

Make sure these files are in your GitHub repository:
- `ai_api.py`
- `train_ai.py`
- `requirements.txt`
- `render.yaml`
- `Procfile`
- `dropout_model.pkl`

```bash
git add ai_api.py train_ai.py requirements.txt render.yaml Procfile dropout_model.pkl
git commit -m "Add AI dropout risk API with Render deployment"
git push origin main
```

### 1.2 Create Render Account & Deploy

1. Go to https://render.com → Sign Up (free)
2. Click **New** → **Web Service**
3. Connect your GitHub account → select your repo
4. Render auto-detects `render.yaml` — click **Deploy**
5. Wait ~3 minutes for first deploy

### 1.3 Get Your API URL

After deploy, Render gives you a URL like:
```
https://scnhs-dropout-api.onrender.com
```

Test it in a browser:
```
GET  https://scnhs-dropout-api.onrender.com/health
```

### 1.4 Update config.php

Open config.php and replace:
```php
define('AI_API_URL', 'https://YOUR-APP-NAME.onrender.com');
```
with your actual Render URL.

> NOTE: Render free tier sleeps after 15 minutes of inactivity.
> The first request after sleep takes ~30 seconds. This is normal.

---

## Step 2 — Deploy PHP Site to InfinityFree

### 2.1 Create InfinityFree Account

1. Go to https://infinityfree.com → Sign Up (free)
2. Create a new hosting account
3. Choose a free subdomain (e.g. scnhs.rf.gd)

### 2.2 Create the MySQL Database

In InfinityFree Control Panel → MySQL Databases, note these 4 values:
- **Host**: e.g. `sql200.epizy.com`
- **Database name**: e.g. `epiz_12345678_enrollment_db`
- **Username**: e.g. `epiz_12345678`
- **Password**: your chosen password

### 2.3 Export Your Local Database

1. Open phpMyAdmin: http://localhost/phpmyadmin
2. Select `enrollment_db`
3. Click **Export** → Format: SQL → Go
4. Save the `.sql` file

### 2.4 Import to InfinityFree

1. InfinityFree Control Panel → phpMyAdmin
2. Select your new database
3. Click **Import** → choose your `.sql` file → Go

### 2.5 Update config.php with InfinityFree Credentials

```php
define('DB_HOST',     'sql200.epizy.com');
define('DB_USER',     'epiz_12345678');
define('DB_PASS',     'YOUR_DB_PASSWORD');
define('DB_NAME',     'epiz_12345678_enrollment_db');
```

### 2.6 Upload Files via FTP

Use FileZilla (https://filezilla-project.org/):
- Host / Username / Password: from InfinityFree FTP Accounts panel
- Port: 21
- Upload all Final/ contents to /htdocs/ on the server

**DO NOT upload these Python-only files:**
- ai_api.py
- train_ai.py
- dropout_model.pkl
- requirements.txt
- render.yaml
- Procfile

### 2.7 Test the Live Site

Visit your InfinityFree subdomain and verify:
- [ ] Home page loads
- [ ] Registration form submits
- [ ] Admin login works
- [ ] Admin dashboard shows students
- [ ] Risk button opens dropout_check.php and calls Render API

---

## File Reference

| File | Purpose | Host |
|---|---|---|
| ai_api.py | Flask AI API | Render |
| train_ai.py | Train & save model | Local only |
| render.yaml | Render deployment config | Render |
| Procfile | Gunicorn start command | Render |
| requirements.txt | Python dependencies | Render |
| config.php | DB + API URL config | InfinityFree |
| admin/dropout_check.php | Admin risk check UI | InfinityFree |
| .htaccess | Apache security + rewrites | InfinityFree |

---

## Troubleshooting

| Problem | Fix |
|---|---|
| Render API returns 503 | Run train_ai.py and push dropout_model.pkl |
| "Could not reach AI API" | Verify AI_API_URL in config.php matches Render URL |
| DB connection failed on InfinityFree | Check all 4 values in config.php production block |
| Admin login broken on InfinityFree | Verify admins table was exported and imported |
| Render slow first request | Expected on free tier — first wake-up takes ~30 seconds |
