# Team contribution guide

The project is one integrated application. Each member works on an assigned area in their own branch, then sends a pull request into `main`. Avoid making direct commits to `main` once the team begins collaborating.

## Member ownership

| Branch | Primary files and work |
|---|---|
| `feat/project-foundation` | `app/bootstrap.php`, `app/helpers.php`, authentication handlers, and shared layout |
| `feat/student-portal` | `app/views/pages/student.php`, `applications.php`, `profile.php`, and student application flow |
| `feat/employer-portal` | `app/views/pages/employer.php`, `employer_jobs.php`, and employer listing/application flow |
| `feat/admin-portal` | `app/views/pages/admin.php` and account/listing moderation |
| `feat/database-and-integration` | `database/schema.sql`, `app/db.php`, setup documentation, and integration coordination |

`public/index.php` and `app/handlers.php` are shared integration points. If your feature needs changes there, agree with the other owners before editing the same lines. Member 5 should merge the database schema first; the other branches can then use the same fields and table names.

## Daily contribution loop

1. Update your local `main` from GitHub before starting a task.
2. Create or switch to your assigned feature branch.
3. Make a small, focused change and save it with a clear commit message.
4. Push the branch to GitHub so the team can see your progress.
5. Open or update a pull request when you want review; merge only after review and conflict resolution.

Use commits to show progress. A pull request bundles the branch changes for review; it does not replace pushing the branch.
