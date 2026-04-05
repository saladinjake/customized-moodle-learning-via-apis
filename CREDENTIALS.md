# 🔑 Lumina Moodle Platform Credentials

Below are the default identities provisioned by the **Master Seeder**. These are used to validate the headless frontend across different access tiers.

### 🛡️ Staff & Administration
| Role | Username | Password | Email |
| :--- | :--- | :--- | :--- |
| **System Admin** | `admin` | `Admin1234!` | `admin@lumina.com` |
| **Victor Instructor** | `victor_instructor` | `Victor123!` | `victor_instructor@lumina.example.com` |

### 🎓 Learner Access
| Type | Username | Password | Email |
| :--- | :--- | :--- | :--- |
| **Enrolled Student** | `victor_student` | `Victor123!` | `victor_student@lumina.example.com` |
| **Prospect Alpha** | `student_alpha` | `Victor123!` | `student_alpha@lumina.example.com` |
| **Prospect Zeta** | `student_zeta` | `Victor123!` | `student_zeta@lumina.example.com` |
| **Prospect Omega** | `student_omega` | `Victor123!` | `student_omega@lumina.example.com` |
| **Prospect Theta** | `student_theta` | `Victor123!` | `student_theta@lumina.example.com` |

---

### 🚀 Management Tools

#### 1. Remote Seeding (HTTP)
Trigger the seeding pipeline via authenticated `curl`. The endpoint is located in the `local/api` folder.



**Master Suite (Recommended):**
```bash
curl -X POST "https://lumina-moodle-backend.onrender.com/local/api/run_seed.php" \
     -H "X-Seed-Token: lumina-seed-2026" \
     -d "run=master" \
     --no-buffer
```

**Engagement/Grades Suite:**
```bash
curl -X POST "https://lumina-moodle-backend.onrender.com/local/api/run_seed.php" \
     -H "X-Seed-Token: lumina-seed-2026" \
     -d "run=categories" \
     --no-buffer
```

**Legacy 500-Course Matrix:**
```bash
curl -X POST "https://lumina-moodle-backend.onrender.com/local/api/run_seed.php" \
     -H "X-Seed-Token: lumina-seed-2026" \
     -d "run=courses" \
     --no-buffer
```

**Engagement/Grades Suite:**
```bash
curl -X POST "https://lumina-moodle-backend.onrender.com/local/api/run_seed.php" \
     -H "X-Seed-Token: lumina-seed-2026" \
     -d "run=grades" \
     --no-buffer
```

#### 2. Audit Tools (HTTP)
Verify live database state via these endpoints:
```bash
# Audit Users
curl "https://lumina-moodle-backend.onrender.com/local/api/audit_users.php"

# Audit Catalog (Visibility & Categories)
curl "https://lumina-moodle-backend.onrender.com/local/api/audit_catalog.php"
```

#### 3. Manual Execution (Render Shell)
If you are logged into the Render web shell:
```bash
cd /var/www/html/public/local/api
php seed_master.php
```

#### 4. Database Reset
To clear existing `MX-500` nodes before a fresh run:
```bash
PGPASSWORD='[HIDDEN]' psql \
  "host=dpg-d7922lk50q8c73f9u2m0-a.oregon-postgres.render.com port=5432 dbname=moodle_databases user=moodle_databases_user sslmode=require" \
  -c "DELETE FROM mdl_course WHERE shortname LIKE 'MX-500-%';"
```
