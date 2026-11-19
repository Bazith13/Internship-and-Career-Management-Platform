# CareerBridge — Internship and Career Management Platform

A plain PHP, HTML, CSS, and JavaScript application backed by MySQL. It is designed to run locally with XAMPP and has three roles: student, employer, and administrator.

## Run with XAMPP

1. Copy this project folder into `C:\xampp\htdocs\careerbridge`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open phpMyAdmin at `http://localhost/phpmyadmin`, create a database named `careerbridge`, then import `database/schema.sql`.
4. Copy `config/config.example.php` to `config/config.php`; edit the copy if your MySQL username or password differs from the XAMPP defaults. The local file is ignored by Git so credentials don't get committed.
5. Open `http://localhost/careerbridge/public/` and register a student or employer account.
6. Create the first admin from XAMPP Shell (or a terminal with PHP on PATH):

   ```text
   cd C:\xampp\htdocs\careerbridge
   php scripts\create_admin.php admin@example.com
   ```

   The command prompts for a password and stores only its password hash. Keep admin credentials private.

## Five-person GitHub workflow

Use one repository and merge each member's work into `main` through pull requests. Push progress to GitHub regularly; a pull request is the review/merge request, while `git push` uploads the branch. Start each branch from the latest `main`:

| Member | Branch | Ownership |
|---|---|---|
| 1 | `feat/project-foundation` | Shared layout, authentication, sessions, security helpers |
| 2 | `feat/student-portal` | Student dashboard, profile, internship search and applications |
| 3 | `feat/employer-portal` | Employer dashboard, listing management and applicant decisions |
| 4 | `feat/admin-portal` | Admin dashboard, account/listing moderation |
| 5 | `feat/database-and-integration` | Schema, database setup, integration notes and final coordination |

Before coding in parallel, agree on the schema in `database/schema.sql`, the routes in `public/index.php`, shared helpers in `app/helpers.php`, and ownership of shared files. Member 5 should merge the schema and shared database conventions early. Keep each pull request focused, review it, then merge it into `main`. The supervisor can review the complete application on `main` and each member's contribution in merged pull requests.

After the repository is created, each member can start and publish their branch like this (replace the branch name with their assigned one):

```text
git switch main
git pull origin main
git switch -c feat/student-portal
git add .
git commit -m "Add student portal"
git push -u origin feat/student-portal
```

Repeat `git add`, `git commit`, and `git push` to publish daily progress. Open or update a pull request on GitHub when the work is ready for review.

## Security and project limits

- Database access uses PDO prepared statements; passwords use PHP's `password_hash()` and `password_verify()`.
- Forms use session-bound CSRF tokens, output is HTML-escaped, and role checks protect each dashboard/action.
- Registration is public for student/employer accounts only; the admin is created from the command line.
- This starter intentionally does not accept resume uploads or send email. Those need additional safe storage/mail configuration.
- Change the default database password for any non-local deployment. This project is intended for a classroom XAMPP setup, not exposed production hosting without a security review.

## Project map

```text
app/                 Shared PHP logic and page views
config/              Local database settings
database/            MySQL schema
public/              Web entry point and static assets
scripts/             CLI-only admin setup
team-reference/      Copies of assigned files grouped by member (reference only)
```

Do not run or edit the copies under `team-reference/`; make changes to the original application files listed in `CONTRIBUTING.md` on the appropriate feature branch.
