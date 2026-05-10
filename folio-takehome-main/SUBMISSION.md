# Folio Take-Home Submission

This repository contains the completed take-home assignment.

## Features Implemented

1. **Lightweight Database Migrations**
   - Added `lib/migrate.php` to handle schema changes gracefully without destroying the base schema file. It supports catching "duplicate column" errors to remain robust if run on an already migrated database.
   - Migrations are automatically run in `seed.php` immediately after the base schema is loaded, ensuring that the `docker compose up` command still works perfectly from a fresh clone.

2. **Scheduled Publishing**
   - Staff can select a "Publish At" date when creating a new document.
   - Added a new `schedule.php` page allowing staff to edit the schedule of an existing document.
   - Any modifications to a schedule trigger an `update_schedule` event in the `audit_log`, capturing both the old and new timestamps.
   - When users visit a share link before the scheduled time, they are gracefully met with a "Not yet available" page.

3. **Human-Readable Document IDs**
   - Implemented an ID generator that automatically creates a readable ID (e.g., `DOC-B4X2`) if the staff member doesn't manually specify one during creation.
   - *Design Tradeoff:* Documents can be viewed globally using this ID (`view.php?doc=DOC-B4X2`). This satisfies the requirement of "typing into a URL or pasting into an email," but consciously bypasses the strict 1-to-1 recipient tracking of the opaque share tokens.

4. **Share by Name (Search)**
   - Added a title search bar in the admin dashboard (`admin.php?q=...`) that uses fuzzy substring matching (`LIKE '%query%'`) to easily locate documents.

## Testing
- Unit tests have been added to `tests/test.php` covering the readable ID generation, title search accuracy, and schedule persistence. All tests are passing via the local docker container.
