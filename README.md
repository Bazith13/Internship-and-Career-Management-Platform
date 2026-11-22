# Internship and Career Management Platform

## About the project

CareerBridge is a web platform that helps students find internships, employers connect with applicants, and administrators review internship listings.

The project uses PHP, MySQL, HTML, CSS, and JavaScript. The application files are kept together in the `careerbridge/` folder.

### Main user roles

- **Student:** Create an account, browse approved internships, apply, and track application status.
- **Employer:** Create an account, post internships, and review applicants.
- **Admin:** Review and approve or reject internship listings.

The platform also includes sign-in, registration, and password recovery.

## Member contributions

| Member | Branch | Contribution | Main files |
|---|---|---|---|
| **1** | `feat/authentication` | Shared helpers, sign-in, registration, password recovery, and the small registration form script. | `careerbridge/common.php`, `careerbridge/auth.php`, `careerbridge/app.js` |
| **2** | `feat/student-portal` | Student dashboard, internship browsing, profile, applications, and application tracking. | `careerbridge/student.php` |
| **3** | `feat/employer-portal` | Employer dashboard, internship listings, and applicant review. | `careerbridge/employer.php` |
| **4** | `feat/admin-portal` | Admin dashboard, internship review, and the shared website design. | `careerbridge/admin.php`, `careerbridge/style.css` |
| **5** | `feat/database-and-routing` | Main page routing, database structure, first admin account setup, and project documentation. | `careerbridge/index.php`, `careerbridge/database.sql`, `careerbridge/setup_admin.php`, `README.md` |

