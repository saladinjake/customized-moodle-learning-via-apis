# 🔑 Lumina Moodle Platform Credentials

Below are the default identities provisioned by the **Master Seeder** and synchronized via the **Global Repair Tool**.

**ALL PERSONAS** (Admin, Instructors, and Students) now share the same definitive password for simplicity.

### 🏁 Unified Credential
| Target | Password |
| :--- | :--- |
| **All Active Users** | `Victor123!` |

### 🛡️ Staff & Administration
| Role | Username | Email |
| :--- | :--- | :--- |
| **System Admin** | `admin` | `admin@lumina.com` |
| **Victor Instructor** | `victor_instructor` | `victor_instructor@lumina.example.com` |

### 🎓 Learner Access
| Type | Username | Email |
| :--- | :--- | :--- |
| **Enrolled Student** | `victor_student` | `victor_student@lumina.example.com` |
| **Prospect Alpha** | `student_alpha` | `student_alpha@lumina.example.com` |
| **Prospect Zeta** | `student_zeta` | `student_zeta@lumina.example.com` |
| **Prospect Omega** | `student_omega` | `student_omega@lumina.example.com` |
| **Prospect Theta** | `student_theta` | `student_theta@lumina.example.com` |

---

### 🚀 Management Tools

#### 1. Remote Seeding (HTTP)
Trigger the seeding pipeline via authenticated `curl`. These endpoints reside directly in the web root for absolute accessibility.

**Master Suite (Recommended):**
```bash
curl -X POST "https://lumina-moodle-backend.onrender.com/local_run_seed.php" \
     -H "X-Seed-Token: lumina-seed-2026" \
     -d "run=master" \
     --no-buffer
```

**Legacy 500-Course Matrix:**
```bash
curl -X POST "https://lumina-moodle-backend.onrender.com/local_seed_moodle.php" \
     -H "X-Seed-Token: lumina-seed-2026" \
     -d "run=courses" \
     --no-buffer
```

#### 2. Platform Repair (Global)
If passwords are out of sync or courses are hidden, trigger this global repair:
```bash
# Force All Passwords to Victor123! and Fix All Visibility
curl "https://lumina-moodle-backend.onrender.com/local_fix_passwords.php"
```

#### 3. Audit Tools (HTTP)
Verify live database state via these endpoints:
```bash
# Audit Users
curl "https://lumina-moodle-backend.onrender.com/local_audit_users.php"

# Audit Catalog (Visibility & Categories)
curl "https://lumina-moodle-backend.onrender.com/local_audit_catalog.php"
```

#### 4. Manual Execution (Render Shell)
If you are logged into the Render web shell:
```bash
cd /var/www/html/public
php local_seed_master.php
php local_fix_passwords.php
```

#### 5. Database Reset
To clear existing `MX-500` nodes before a fresh run:
```bash
PGPASSWORD='[HIDDEN]' psql \
  "host=dpg-d7922lk50q8c73f9u2m0-a.oregon-postgres.render.com port=5432 dbname=moodle_databases user=moodle_databases_user sslmode=require" \
  -c "DELETE FROM MDL_COURSE WHERE SHORTNAME LIKE 'MX-500-%';"
```
