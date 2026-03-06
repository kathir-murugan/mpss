# TODO - Page Management System

## Task: Add all pages in database and only show enabled pages

### Steps to Complete:

- [ ] 1. Update database_setup.sql - Add pages table
- [ ] 2. Update admin.php - Add page management section for CEO
- [ ] 3. Update employee.php - Fetch pages from database for navigation

### Pages to Add:
1. Home (home.php)
2. Login (login.php)
3. Register (register.php)
4. Admin Dashboard (admin.php)
5. Employee Management (employee.php)
6. User Dashboard (user.php)

### Database Table Structure (pages):
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- page_name (VARCHAR) - Display name
- page_file (VARCHAR) - File name
- page_title (VARCHAR) - Page title
- description (TEXT) - Description
- is_active (TINYINT) - 1 = show, 0 = hide
- display_order (INT) - Order in navigation
- created_at (TIMESTAMP)

### Features:
- Add new pages
- Edit page details
- Toggle active/inactive
- Delete pages
- Reorder pages

