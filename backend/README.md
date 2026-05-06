# Backend Project Overview

This backend is a modular PHP API for an education platform. It handles admin and student authentication, course and lesson management, quizzes, assignments, purchases, access control, announcements, and dashboard reporting.

## Stack

- PHP with file-based endpoints
- MySQL via `mysqli`
- JSON API responses
- Token-based session authentication
- Local file uploads for documents and images
- Cloudflare Stream integration for lesson videos

## Project Structure

Each feature lives in its own folder under `backend/` and usually exposes separate endpoint files such as `create.php`, `get.php`, `update.php`, and `delete.php`.

Main folders:

- `auth/`: admin and student login, registration, logout
- `items/`: courses, chapters, lessons, packages, and passes
- `quizzes/`: quiz definitions, questions, options, attempts, and student answers
- `assignments/`: assignment creation, downloads, and submissions
- `students/`: student CRUD and access management
- `payments/`: purchases and payment records
- `codes/`: redeemable codes and code batches
- `student_progress/`: lesson completion and last-viewed tracking
- `announcements/`, `grades/`, `centers/`, `admins/`, `platform_settings/`, `stats/`, `admin_logs/`
- `helpers/`: reusable upload, access, and media helpers
- `sql/schema.sql`: database schema

## Core Request Flow

Most endpoint files start by including `config.php`. That file is the shared bootstrap for the whole API and provides:

- database connection
- CORS headers
- request method validation
- required parameter validation
- standard JSON response formatting
- token extraction and session verification
- role-based authorization helpers
- admin activity logging

Typical endpoint flow:

1. include `config.php`
2. validate request method
3. require authentication if needed
4. validate input
5. run database queries
6. return a JSON response using `respond()`

## Authentication Model

The backend supports two user types:

- admins
- students

Sessions are stored in:

- `admins_sessions`
- `student_sessions`

Clients send the session token in the `X-Authorization` header, usually as `Bearer <token>`.

Authorization is role-based for admin endpoints:

- `super_admin`
- `admin`
- `assistant`

Student endpoints use `requireAuth('student')`.

## Response Format

The API returns a consistent JSON envelope:

Successful response:

```json
{
  "status": "success",
  "data": {}
}
```

Error response:

```json
{
  "status": "error",
  "message": "Explanation here"
}
```

## Main Business Domains

### 1. Content and Catalog

The `items` table is the center of the platform content model. It stores:

- courses
- chapters
- lessons
- packages
- passes

An item can be hierarchical through `parent_id`, which allows courses to contain chapters and chapters to contain lessons.

Important item fields:

- `item_type`
- `content_type` (`video`, `pdf`, `none`)
- `price`
- `access_type` (`lifetime`, `limited`)
- `duration_days`
- `grade_id`
- `is_free`
- `is_published`
- `sort_order`

Media behavior:

- PDFs are uploaded locally with helper functions
- videos are uploaded to Cloudflare Stream and the stored `video_url` is the Cloudflare video UID
- images are uploaded through helper utilities

### 2. Access Control

Student entitlement is stored in `student_access`.

The backend separates access into two checks:

- `studentHasAccessToItem()`: does the student own or directly have access to the item
- `studentCanOpenItem()`: does the student have access and satisfy prerequisites

Prerequisites are stored in `item_prerequisites` and can require:

- a lesson to be completed
- a quiz to be passed with an optional minimum score

Additional item unlocking relationships are stored in `item_access_map`, which maps one purchased item to other items it grants access to.

### 3. Quizzes

Quiz-related data is split across:

- `quizzes`
- `quiz_questions`
- `quiz_options`
- `quiz_attempts`
- `student_quiz_answers`

This supports:

- quiz authoring
- randomized quiz settings
- attempt tracking
- answer saving during attempts
- final submission
- grading and pass/fail prerequisite checks

### 4. Assignments

Assignments belong to items and support:

- file-based assignment definitions
- student submissions
- uploaded submission files
- grading and feedback

Key tables:

- `assignments`
- `assignment_submissions`

### 5. Payments and Wallet Access

Students can purchase items through `payments/student_purchase_item.php`.

The purchase flow:

1. validates the item
2. checks whether the student already has access
3. allows free items to unlock immediately
4. deducts from `students.wallet_balance` for paid items
5. records the transaction in `payments`
6. grants access in `student_access`

The backend also supports code-based access and wallet top-ups through:

- `codes`
- `code_batches`
- `codes/redeem.php`

### 6. Progress Tracking

Lesson progress is stored in `lesson_progress`.

Current progress features include:

- mark lesson complete
- update last viewed time
- fetch student progress

This progress data is also used to enforce lesson-based prerequisites.

### 7. Admin Operations and Reporting

Admins can manage:

- students
- other admins
- grades
- centers
- items
- quizzes
- assignments
- platform settings
- announcements

Operational visibility is provided by:

- `admin_logs` for audit-style action logging
- `stats/dashboard.php` for aggregated dashboard numbers

## Database Design Summary

The schema is relational and centered around these main entities:

- users: `admins`, `students`
- sessions: `admins_sessions`, `student_sessions`
- catalog: `items`, `item_access_map`, `item_prerequisites`, `grades`
- learning: `lesson_progress`, `quizzes`, `quiz_questions`, `quiz_options`, `quiz_attempts`, `student_quiz_answers`, `assignments`, `assignment_submissions`
- commerce: `payments`, `codes`, `code_batches`, `student_access`
- operations: `platform_settings`, `announcements`, `admin_logs`, `centers`

Several tables enforce uniqueness for business rules, for example:

- one student submission per assignment
- one active access record per student/item pair
- one lesson progress record per student/item pair

## Integration Notes

### Local uploads

Helpers in `helpers/upload_file.php` and related files create folders under `../uploads/...` and store generated file names there.

### Cloudflare Stream

Video uploads depend on these constants in `config.php`:

- `CLOUDFLARE_ACCOUNT_ID`
- `CLOUDFLARE_STREAM_TOKEN`

The returned Cloudflare Stream UID is saved to the database and can be used to build an embed URL.

## Strengths of This Architecture

- simple feature-based organization
- easy to add CRUD endpoints per module
- shared auth and response helpers reduce repeated code
- clear separation between admin and student behavior
- schema covers both content delivery and monetization
