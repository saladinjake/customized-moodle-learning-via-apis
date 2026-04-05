# 🔑 Lumina Moodle Platform Credentials

Below are the default identities provisioned by the **Master Seeder**. These are used to validate the headless frontend across different access tiers.

| Role | Username | Password | Email |
| :--- | :--- | :--- | :--- |
| **System Admin** | `admin` | `Admin1234!` | `admin@lumina.com` |
| **Victor Instructor** | `victor_instructor` | `Victor123!` | `victor_instructor@lumina.example.com` |
| **Victor Student** | `victor_student` | `Victor123!` | `victor_student@lumina.example.com` |

---

### 🚀 Management Tools

#### 1. Reseed Trigger (HTTP)
If you need to re-provision the environment from scratch without a full deployment:

```bash
curl -X POST "https://lumina-moodle-backend.onrender.com/run_seed.php" \
     -H "X-Seed-Token: lumina-seed-2026" \
     -d "run=master" \
     --no-buffer
```

#### 2. Manual Reseed (Render Shell)
Execute this from the Render service shell:
```bash
cd /var/www/html/public
php seed_master.php
```

#### 3. Database Cleardown
To reset all seeding nodes before a fresh run:
```bash
# From Render Shell
PGPASSWORD='83Ide1Yyu7Pg5l4T9f2YYbdO0tE81iti' psql \
  "host=dpg-d7922lk50q8c73f9u2m0-a.oregon-postgres.render.com port=5432 dbname=moodle_databases user=moodle_databases_user sslmode=require" \
  -c "DELETE FROM mdl_course WHERE shortname LIKE 'MX-500-%';"
```
