# MuseoX — Museum Management System

A full-stack museum management web application built with **PHP 8 + Oracle Database**, developed as a university Database Management Systems (DBMS) project. The system demonstrates the complete spectrum of Oracle SQL and PL/SQL features across 8 structured development phases.

---

## 🏛 Project Overview

MuseoX covers the full lifecycle of a museum system:
- Visitor registration, authentication, and profile management
- Artifact and virtual gallery catalog browsing
- Exhibition management with ticket booking
- Admin CRUD panels for all entities
- Visitor engagement (feedback, donations, search)
- Advanced Oracle analytics and reporting

---

## 🛠 Tech Stack

| Layer       | Technology                      |
|-------------|---------------------------------|
| Backend     | PHP 8.x                         |
| Database    | Oracle Database 11g+            |
| DB Driver   | PHP PDO with OCI8 extension     |
| Frontend    | Vanilla HTML5 + CSS3            |
| Server      | Apache (XAMPP)                  |

---

## 📁 Project Structure

```
museox/
├── assets/
│   ├── css/style.css          # Global stylesheet
│   └── js/main.js             # Client-side scripts
├── config/
│   └── config.php             # DB connection config
├── database/                  # All SQL files (run in order)
│   ├── oracle.sql             # Phase 1: Schema + triggers
│   ├── artifacts.sql          # Phase 2: Artifact seed data
│   ├── gallery.sql            # Phase 2: Gallery seed data
│   ├── exhibitions.sql        # Phase 3: Exhibitions + sp_BookTicket
│   ├── tickets.sql            # Phase 3: Ticket seed data
│   ├── indexes.sql            # Phase 4: Indexes + functions
│   ├── feedback-donations.sql # Phase 5: pkg_MuseoX package
│   ├── advance.sql            # Phase 6: MV + Synonym + Cursor
│   ├── analytics.sql          # Phase 7: Window function views
│   └── final_queries.sql      # Phase 8: CTE + PIVOT + NTILE + PERCENTILE
├── includes/
│   ├── db.php                 # PDO connection singleton
│   ├── auth.php               # Authentication class
│   └── functions.php          # Shared utility functions
├── pages/                     # All application pages (22 files)
├── tools/
│   └── seed_admin.php         # Admin account seeder
└── index.php                  # Homepage
```

---

## 🗄 Database Schema (9 Tables)

| Table        | Description                                  |
|--------------|----------------------------------------------|
| `roles`      | Admin / Visitor role definitions             |
| `users`      | Authentication — username, email, password   |
| `visitors`   | Extended visitor profile (phone, country)    |
| `artifacts`  | Core artifact catalog                        |
| `gallery`    | Virtual gallery artworks                     |
| `exhibitions`| Museum exhibitions with capacity & pricing   |
| `tickets`    | Visitor ticket bookings                      |
| `feedback`   | Visitor ratings (1–5 stars) per exhibition   |
| `donations`  | Museum contributions by purpose              |
| `audit_logs` | System-wide action audit trail               |

> **Note:** Auto-increment IDs use **TRIGGERS** (no sequences, as per project requirement).

---

## 🚀 Setup Instructions

### Prerequisites
- XAMPP with PHP 8.x + OCI8 extension enabled
- Oracle Database 11g+ (or Oracle XE)
- Oracle Instant Client (for PDO OCI)

### 1. Configure Database Connection

Edit `config/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_PORT', '1521');
define('DB_SID',  'XE');          // Your Oracle SID
define('DB_USER', 'your_user');
define('DB_PASS', 'your_password');
```

### 2. Run SQL Files in Oracle SQL Developer

Execute the files in this order:

```
1. database/oracle.sql             ← Schema + triggers + sp_RegisterVisitor
2. database/artifacts.sql          ← Artifact catalog seed data
3. database/gallery.sql            ← Gallery artworks seed data
4. database/exhibitions.sql        ← Exhibitions + sp_BookTicket procedure
5. database/tickets.sql            ← Ticket seed data
6. database/indexes.sql            ← Performance indexes + fn_* functions
7. database/feedback-donations.sql ← pkg_MuseoX package + sample data
8. database/advance.sql            ← Materialized View + Synonyms + sp_GenerateArtifactReport
9. database/analytics.sql          ← Window function views
10. database/final_queries.sql     ← CTE views + PIVOT + NTILE + PERCENTILE views
```

### 3. Create Admin Account

Open your browser and go to:
```
http://localhost/museox/tools/seed_admin.php
```

This creates the default admin account:
- **Email:** `admin@museox.com`
- **Password:** `Admin@123`

### 4. Access the Application
```
http://localhost/museox/
```

---

## 📋 Oracle SQL Features Demonstrated

### DDL
`CREATE TABLE` · `CREATE TRIGGER` · `CREATE INDEX` · `CREATE VIEW` · `CREATE MATERIALIZED VIEW` · `CREATE SYNONYM` · `CREATE OR REPLACE PROCEDURE` · `CREATE OR REPLACE FUNCTION` · `CREATE OR REPLACE PACKAGE`

### DML
`INSERT INTO` · `UPDATE ... SET` · `DELETE FROM` · `MERGE INTO ... WHEN MATCHED ... WHEN NOT MATCHED`

### DQL
`SELECT` · `JOIN (INNER, LEFT)` · `GROUP BY` · `HAVING` · `ORDER BY` · `UNION ALL` · `FETCH FIRST N ROWS ONLY` · `ROWNUM` · `BETWEEN ... AND` · `ROLLUP` · `PIVOT` (native Oracle)

### Analytic / Window Functions
`RANK()` · `DENSE_RANK()` · `ROW_NUMBER()` · `LAG()` · `LEAD()` · `NTILE()` · `FIRST_VALUE()` · `LAST_VALUE()` · `SUM() OVER (PARTITION BY ...)` — all with `PARTITION BY` and `ORDER BY`

### Oracle Functions
`NVL` · `NVL2` · `NULLIF` · `COALESCE` · `DECODE` · `TO_CHAR` · `TO_DATE` · `TRUNC` · `ROUND` · `MONTHS_BETWEEN` · `ADD_MONTHS` · `UPPER` · `LPAD` · `SUBSTR` · `LISTAGG` · `SYSDATE` · `INTERVAL`

### Aggregates
`COUNT` · `SUM` · `AVG` · `MIN` · `MAX` · `GROUPING()` · `PERCENTILE_CONT` · `PERCENTILE_DISC`

### PL/SQL
`PROCEDURE` · `FUNCTION` · `PACKAGE (SPEC + BODY)` · `TRIGGER` · `Explicit CURSOR` · `%ROWTYPE` · `%TYPE` · `RAISE_APPLICATION_ERROR` · `RETURNING INTO` · `EXCEPTION ... WHEN OTHERS` · `DBMS_OUTPUT.PUT_LINE` · `CONNECT BY LEVEL`

### Constraints
`PRIMARY KEY` · `FOREIGN KEY ... REFERENCES` · `CHECK` · `NOT NULL` · `DEFAULT` · `UNIQUE` · `ON DELETE CASCADE` · `ON DELETE SET NULL`

---

## 🔑 Default Credentials

| Role    | Email               | Password    |
|---------|---------------------|-------------|
| Admin   | admin@museox.com    | Admin@123   |
| Visitor | Register normally   | Your choice |

---

## 📄 Development Phases

| Phase | Title                            | SQL File                  | Key Features |
|-------|----------------------------------|---------------------------|--------------|
| 1     | Foundation & Auth                | `oracle.sql`              | Schema, Triggers, PDO OCI |
| 2     | Artifact & Gallery Catalogs      | `artifacts.sql`, `gallery.sql` | ROWNUM pagination, LIKE, GROUP BY |
| 3     | Exhibitions, Tickets & Profile   | `exhibitions.sql`, `tickets.sql` | sp_BookTicket, RAISE_APPLICATION_ERROR, RETURNING INTO |
| 4     | Admin CRUD & Indexes             | `indexes.sql`             | Indexes, Functions, TO_DATE, CASE WHEN |
| 5     | Feedback, Donations & Search     | `feedback-donations.sql`  | Oracle Package, UNION ALL, HAVING |
| 6     | Advanced Features & Reports      | `advance.sql`             | Materialized View, Synonym, Explicit Cursor, ROLLUP, MERGE |
| 7     | Analytics & Audit Trail          | `analytics.sql`           | RANK, DENSE_RANK, ROW_NUMBER, LAG, LEAD, LISTAGG, BETWEEN, INTERVAL |
| 8     | Final Features & Visit Page      | `final_queries.sql`       | WITH CTE, PIVOT, NTILE, FIRST_VALUE, LAST_VALUE, PERCENTILE_CONT, CONNECT BY LEVEL |

---

## 👤 Developer

**Torikul** — University DBMS Project, 2026  
Built with PHP + Oracle Database · All SQL features demonstrated using Oracle-specific syntax

---

## 📝 Notes

- The project uses **triggers** for ID auto-increment instead of sequences (project requirement)
- Bind variables follow Oracle PDO OCI naming rules (no reuse of same name in single query)
- The `config.php` file is committed intentionally (teacher requirement)
- All admin pages are protected with `$_SESSION['role'] === 'Admin'` checks
