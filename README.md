# 🎓 Headless Moodle Matrix

Welcome to the **Headless Enterprise Matrix**. This project decouples the monolithic Moodle architecture into a high-performance, React-driven persona suite.

##  Quick Start (Development)

### 1. Seeding the Database
To populate your environment with **50 professional courses**, **200 assessment hubs**, and **Live YouTube resources**, run the seeder:
```bash
php seed_moodle.php
```

### 2. Launch the Backend (Moodle API)
From the root directory:
```bash
php -S localhost:8000 -t public
```

### 3. Launch the Frontend (React SPA)
From the `frontend` directory:
```bash
cd frontend
npm run dev
```

---

##  Persona Suites (Credentials)

The following personas are seeded with the matrix. Use these for testing individual learner/instructor experiences across the platform:

| Persona | Username | Identity Email | Password | Access Level |
| :--- | :--- | :--- | :--- | :--- |
| **System Admin** | `victor_admin` | `admin@gmail.com` | `Moodle@123` | System Mutation & Plugin Control |
| **Lead Instructor** | `victor_instructor` | `juwavictor2@gmail.com` | `Moodle@123` | Course Management & Grading Hub |
| **Student (Learner)** | `victor_student` | `juwavictor1@gmail.com` | `Moodle@123` | Learner Timeline & Monthly Calendar |

---

##  Identity & Social Auth
To enable **Google, Facebook, LinkedIn, or GitHub** authentication:
1.  Open the `.env` file at the root.
2.  Add your `CLIENT_ID` and `CLIENT_SECRET`.
3.  The backend will automatically provision the identity bridge on the next request.

## 🛠 Features Integrated
- **Enterprise Parity**: 50 High-Density courses spanning multiple modern categories (*Blockchain Infrastructure*, *Quantum Cryptography*, etc.).
- **Complete Course Entities**: Fully linked entity relationships encompassing Core Course data, Course Sections, Modules (Resources, Assignments, Quizzes), and Course Modules (CMID bridging).
- **Persona-Driven Enrolment**: Automatic role association and cohort grouping for the seeded user base.
- **Headless Router**: 100% decoupled API routing via `local/api/index.php`.
