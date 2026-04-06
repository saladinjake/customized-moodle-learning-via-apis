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


## === CATALOG CATEGORY AUDIT ===
```

ID    | CATEGORY NAME                  | COURSE COUNT
-------------------------------------------------------
1     | Category 1                     | 13          
2     | Software Architecture          | 21          
3     | Machine Learning Ops           | 17          
4     | Quantum Electrodynamics        | 16          
5     | Cybersecurity Audit            | 9           
6     | Financial Modeling             | 18          
7     | Digital Transformation         | 10          
8     | UX/UI Research                 | 9           
9     | Aerospace Systems              | 14          
10    | Biomedical Engineering         | 14          
11    | Blockchain Infrastructure      | 7           
12    | Cloud Systems Core             | 15          
13    | Data Science Mastery           | 16          
14    | Strategy & Leadership          | 17          
15    | Global Economics               | 14          
16    | Creative Direction             | 10          
17    | Ethics in AI                   | 16          
18    | Behavioral Psychology          | 17          
19    | Supply Chain Logic             | 16          
20    | Embedded Systems               | 10          
21    | Neural Networks                | 7           
22    | DevOps Engineering             | 9           
23    | RegTech Solutions              | 15          
24    | Green Energy Tech              | 12          
25    | Robotic Process Automation     | 14          
26    | AR/VR Environments             | 18          
27    | Legal Compliance               | 18          
28    | Market Research                | 16          
29    | Human Capital Mgmt             | 15          
30    | High-Performance Computing     | 14          
31    | Digital Forensic Audit         | 16          
32    | Machine Learning               | 13          
33    | Cybersecurity                  | 17          
34    | Financial Engineering          | 11          
35    | UX Research                    | 11          
36    | Blockchain                     | 23    


```

---

### 🚀 Management Tools

#### 1. Remote Seeding (HTTP)
Trigger the seeding pipeline via authenticated `curl`. These endpoints reside directly in the web root for absolute accessibility.

**Follow thes steps to seed Master Suite (Recommended):**


## 2. rbac seeder
```bash
curl -X POST "https://lumina-moodle-backend.onrender.com/run_seed.php" \
     -H "X-Seed-Token: lumina-seed-2026" \
     -d "run=rbac"
```
#### 3. Platform Repair (Global)
If passwords are out of sync or courses are hidden, trigger this global repair:

```bash
# Force All Passwords to Victor123! and Fix All Visibility
curl "https://lumina-moodle-backend.onrender.com/local_fix_passwords.php"
```



## 4. categories seeder
```bash
curl -X POST "https://lumina-moodle-backend.onrender.com/local_run_seed.php" \
     -H "X-Seed-Token: lumina-seed-2026" \
     -d "run=categories" \
     --no-buffer
```

## **5  Legacy 500-Course Matrix: **  courses with one directional sections


```bash
curl -X POST "https://lumina-moodle-backend.onrender.com/local_seed_moodle.php" \
     -H "X-Seed-Token: lumina-seed-2026" \
     -d "run=courses" \
     --no-buffer

```

## **6 Legacy 500-Course Matrix:**  courses with two directional sections or nested subsection
```bash
curl -X POST "https://lumina-moodle-backend.onrender.com/local_seed_moodle.php" \
     -H "X-Seed-Token: lumina-seed-2026" \
     -d "run=master" \
     --no-buffer
```


# Update first 100 courses with flat structure
curl -X POST "https://lumina-moodle-backend.onrender.com/local_run_seed.php" \
     -H "X-Seed-Token: lumina-seed-2026" \
     -d "run=curriculum_flat"

# Update remaining courses with nested structure
curl -X POST "https://lumina-moodle-backend.onrender.com/local_run_seed.php" \
     -H "X-Seed-Token: lumina-seed-2026" \
     -d "run=curriculum_nested"

# Re-run full seeding (including new curriculum steps)
curl -X POST "https://lumina-moodle-backend.onrender.com/local_run_seed.php" \
     -H "X-Seed-Token: lumina-seed-2026" \
     -d "run=all"



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


## users audit log
```
USERNAME             | FULL NAME                      | EMAIL                         
-------------------------------------------------------------------------------------
admin                | Admin User                     |                               
victor_instructor    | Victor Instructor              | victor_instructor@lumina.example.com
victor_student       | Victor Student                 | victor_student@lumina.example.com
student_alpha        | Alpha Scholar                  | student_alpha@lumina.example.com
student_zeta         | Zeta Scholar                   | student_zeta@lumina.example.com
student_omega        | Omega Scholar                  | student_omega@lumina.example.com
student_theta        | Theta Scholar                  | student_theta@lumina.example.com
sysadmin_1           | System Admin 1                 | sysadmin_1@lumina.example.com 
sysadmin_2           | System Admin 2                 | sysadmin_2@lumina.example.com 
sysadmin_3           | System Admin 3                 | sysadmin_3@lumina.example.com 
sysadmin_4           | System Admin 4                 | sysadmin_4@lumina.example.com 
sysadmin_5           | System Admin 5                 | sysadmin_5@lumina.example.com 
sysadmin_6           | System Admin 6                 | sysadmin_6@lumina.example.com 
sysadmin_7           | System Admin 7                 | sysadmin_7@lumina.example.com 
sysadmin_8           | System Admin 8                 | sysadmin_8@lumina.example.com 
sysadmin_9           | System Admin 9                 | sysadmin_9@lumina.example.com 
sysadmin_10          | System Admin 10                | sysadmin_10@lumina.example.com
instructor_1         | Expert Instructor 1            | instructor_1@lumina.example.com
instructor_2         | Expert Instructor 2            | instructor_2@lumina.example.com
instructor_3         | Expert Instructor 3            | instructor_3@lumina.example.com
instructor_4         | Expert Instructor 4            | instructor_4@lumina.example.com
instructor_5         | Expert Instructor 5            | instructor_5@lumina.example.com
instructor_6         | Expert Instructor 6            | instructor_6@lumina.example.com
instructor_7         | Expert Instructor 7            | instructor_7@lumina.example.com
instructor_8         | Expert Instructor 8            | instructor_8@lumina.example.com
instructor_9         | Expert Instructor 9            | instructor_9@lumina.example.com
instructor_10        | Expert Instructor 10           | instructor_10@lumina.example.com
user_manager_1       | Diverse Persona 1              | user_manager_1@lumina.example.com
user_editingteacher_2 | Diverse Persona 2              | user_editingteacher_2@lumina.example.com
user_support_3       | Diverse Persona 3              | user_support_3@lumina.example.com
user_support_4       | Diverse Persona 4              | user_support_4@lumina.example.com
user_teacher_5       | Diverse Persona 5              | user_teacher_5@lumina.example.com
user_teacher_6       | Diverse Persona 6              | user_teacher_6@lumina.example.com
user_reviewer_7      | Diverse Persona 7              | user_reviewer_7@lumina.example.com
user_editingteacher_8 | Diverse Persona 8              | user_editingteacher_8@lumina.example.com
user_manager_9       | Diverse Persona 9              | user_manager_9@lumina.example.com
user_support_10      | Diverse Persona 10             | user_support_10@lumina.example.com
user_support_11      | Diverse Persona 11             | user_support_11@lumina.example.com
user_editingteacher_12 | Diverse Persona 12             | user_editingteacher_12@lumina.example.com
user_teacher_13      | Diverse Persona 13             | user_teacher_13@lumina.example.com
user_teacher_14      | Diverse Persona 14             | user_teacher_14@lumina.example.com
user_auditor_15      | Diverse Persona 15             | user_auditor_15@lumina.example.com
user_editingteacher_16 | Diverse Persona 16             | user_editingteacher_16@lumina.example.com
user_teacher_17      | Diverse Persona 17             | user_teacher_17@lumina.example.com
user_editingteacher_18 | Diverse Persona 18             | user_editingteacher_18@lumina.example.com
user_auditor_19      | Diverse Persona 19             | user_auditor_19@lumina.example.com
user_reviewer_20     | Diverse Persona 20             | user_reviewer_20@lumina.example.com
user_editingteacher_1 | Diverse Persona 1              | user_editingteacher_1@lumina.example.com
user_student_2       | Diverse Persona 2              | user_student_2@lumina.example.com
user_auditor_3       | Diverse Persona 3              | user_auditor_3@lumina.example.com
user_auditor_4       | Diverse Persona 4              | user_auditor_4@lumina.example.com
user_reviewer_5      | Diverse Persona 5              | user_reviewer_5@lumina.example.com
user_support_6       | Diverse Persona 6              | user_support_6@lumina.example.com
user_manager_7       | Diverse Persona 7              | user_manager_7@lumina.example.com
user_reviewer_8      | Diverse Persona 8              | user_reviewer_8@lumina.example.com
user_reviewer_9      | Diverse Persona 9              | user_reviewer_9@lumina.example.com
user_editingteacher_10 | Diverse Persona 10             | user_editingteacher_10@lumina.example.com
user_student_11      | Diverse Persona 11             | user_student_11@lumina.example.com
user_auditor_13      | Diverse Persona 13             | user_auditor_13@lumina.example.com
user_editingteacher_14 | Diverse Persona 14             | user_editingteacher_14@lumina.example.com
user_teacher_15      | Diverse Persona 15             | user_teacher_15@lumina.example.com
user_reviewer_16     | Diverse Persona 16             | user_reviewer_16@lumina.example.com
user_manager_17      | Diverse Persona 17             | user_manager_17@lumina.example.com
user_auditor_20      | Diverse Persona 20             | user_auditor_20@lumina.example.com
user_reviewer_1      | Diverse Persona 1              | user_reviewer_1@lumina.example.com
user_manager_2       | Diverse Persona 2              | user_manager_2@lumina.example.com
user_reviewer_3      | Diverse Persona 3              | user_reviewer_3@lumina.example.com
user_student_4       | Diverse Persona 4              | user_student_4@lumina.example.com
user_support_5       | Diverse Persona 5              | user_support_5@lumina.example.com
user_manager_6       | Diverse Persona 6              | user_manager_6@lumina.example.com
user_student_7       | Diverse Persona 7              | user_student_7@lumina.example.com
user_auditor_10      | Diverse Persona 10             | user_auditor_10@lumina.example.com
user_teacher_11      | Diverse Persona 11             | user_teacher_11@lumina.example.com
user_student_13      | Diverse Persona 13             | user_student_13@lumina.example.com
user_support_14      | Diverse Persona 14             | user_support_14@lumina.example.com
user_support_15      | Diverse Persona 15             | user_support_15@lumina.example.com
user_support_16      | Diverse Persona 16             | user_support_16@lumina.example.com
user_support_18      | Diverse Persona 18             | user_support_18@lumina.example.com
user_support_20      | Diverse Persona 20             | user_support_20@lumina.example.com
user_support_1       | Diverse Persona 1              | user_support_1@lumina.example.com
user_editingteacher_5 | Diverse Persona 5              | user_editingteacher_5@lumina.example.com
user_editingteacher_6 | Diverse Persona 6              | user_editingteacher_6@lumina.example.com
user_editingteacher_7 | Diverse Persona 7              | user_editingteacher_7@lumina.example.com
user_teacher_8       | Diverse Persona 8              | user_teacher_8@lumina.example.com
user_auditor_9       | Diverse Persona 9              | user_auditor_9@lumina.example.com
user_auditor_11      | Diverse Persona 11             | user_auditor_11@lumina.example.com
user_auditor_12      | Diverse Persona 12             | user_auditor_12@lumina.example.com
user_reviewer_13     | Diverse Persona 13             | user_reviewer_13@lumina.example.com
user_reviewer_14     | Diverse Persona 14             | user_reviewer_14@lumina.example.com
user_editingteacher_17 | Diverse Persona 17             | user_editingteacher_17@lumina.example.com
user_reviewer_18     | Diverse Persona 18             | user_reviewer_18@lumina.example.com
user_editingteacher_19 | Diverse Persona 19             | user_editingteacher_19@lumina.example.com
user_auditor_1       | Diverse Persona 1              | user_auditor_1@lumina.example.com
user_teacher_2       | Diverse Persona 2              | user_teacher_2@lumina.example.com
user_editingteacher_4 | Diverse Persona 4              | user_editingteacher_4@lumina.example.com
user_auditor_5       | Diverse Persona 5              | user_auditor_5@lumina.example.com
user_manager_8       | Diverse Persona 8              | user_manager_8@lumina.example.com
user_auditor_14      | Diverse Persona 14             | user_auditor_14@lumina.example.com
user_editingteacher_15 | Diverse Persona 15             | user_editingteacher_15@lumina.example.com
user_manager_16      | Diverse Persona 16             | user_manager_16@lumina.example.com
user_student_17      | Diverse Persona 17             | user_student_17@lumina.example.com
user_manager_18      | Diverse Persona 18             | user_manager_18@lumina.example.com
user_support_19      | Diverse Persona 19             | user_support_19@lumina.example.com
user_manager_20      | Diverse Persona 20             | user_manager_20@lumina.example.com
user_student_1       | Diverse Persona 1              | user_student_1@lumina.example.com
user_student_5       | Diverse Persona 5              | user_student_5@lumina.example.com
user_auditor_8       | Diverse Persona 8              | user_auditor_8@lumina.example.com
user_teacher_10      | Diverse Persona 10             | user_teacher_10@lumina.example.com
user_reviewer_11     | Diverse Persona 11             | user_reviewer_11@lumina.example.com
user_student_16      | Diverse Persona 16             | user_student_16@lumina.example.com


```