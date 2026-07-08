-- ============================================================
--  Created Roles Table
-- ============================================================
CREATE TABLE roles (
    role_id     NUMBER        PRIMARY KEY,
    role_name   VARCHAR2(50)  NOT NULL UNIQUE,
    description VARCHAR2(255)
);

-- Trigger for Auto-Increment ID
CREATE OR REPLACE TRIGGER trg_roles_id
BEFORE INSERT ON roles FOR EACH ROW
BEGIN
    SELECT NVL(MAX(role_id), 0) + 1 INTO :NEW.role_id FROM roles;
END;
/

-- Insert the default Roles
INSERT INTO roles (role_name, description) VALUES ('Admin',   'System Administrator');
INSERT INTO roles (role_name, description) VALUES ('Visitor', 'Public User');
COMMIT;

-- ============================================================
--  Created Users Table
-- ============================================================
CREATE TABLE users (
    user_id    NUMBER        PRIMARY KEY,
    username   VARCHAR2(50)  NOT NULL UNIQUE,
    email      VARCHAR2(100) NOT NULL UNIQUE,
    password   VARCHAR2(255) NOT NULL,
    role_id    NUMBER        NOT NULL REFERENCES roles(role_id),
    status     VARCHAR2(20)  DEFAULT 'Active' CHECK (status IN ('Active', 'Suspended', 'Pending')),
    created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

-- Trigger for Auto-Increment ID
CREATE OR REPLACE TRIGGER trg_users_id
BEFORE INSERT ON users FOR EACH ROW
BEGIN
    SELECT NVL(MAX(user_id), 0) + 1 INTO :NEW.user_id FROM users;
END;
/

-- ============================================================
--  Created Visitors Table
-- ============================================================
CREATE TABLE visitors (
    visitor_id NUMBER NOT NULL UNIQUE REFERENCES users(user_id) ON DELETE CASCADE,
    user_id    NUMBER NOT NULL UNIQUE REFERENCES users(user_id) ON DELETE CASCADE,
    phone      VARCHAR2(30),
    country    VARCHAR2(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Trigger for Auto-Increment ID
CREATE OR REPLACE TRIGGER trg_visitors_id
BEFORE INSERT ON visitors FOR EACH ROW
BEGIN
    SELECT NVL(MAX(visitor_id), 0) + 1 INTO :NEW.visitor_id FROM visitors;
END;
/

-- ============================================================
--  Created Audit Logs Table
-- ============================================================
CREATE TABLE audit_logs (
    log_id           NUMBER        PRIMARY KEY,
    user_id          NUMBER,
    action_performed VARCHAR2(255) NOT NULL,
    table_affected   VARCHAR2(100) NOT NULL,
    ip_address       VARCHAR2(45)  NOT NULL,
    user_agent       VARCHAR2(255) NOT NULL,
    log_timestamp    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Trigger for Auto-Increment ID
CREATE OR REPLACE TRIGGER trg_audit_logs_id
BEFORE INSERT ON audit_logs FOR EACH ROW
BEGIN
    SELECT NVL(MAX(log_id), 0) + 1 INTO :NEW.log_id FROM audit_logs;
END;
/

-- ============================================================
--  PL/SQL PROCEDURE: Visitor Registration
-- ============================================================

CREATE OR REPLACE PROCEDURE sp_RegisterVisitor(
    p_username IN VARCHAR2,
    p_email    IN VARCHAR2,
    p_password IN VARCHAR2,
    p_phone    IN VARCHAR2,
    p_country  IN VARCHAR2,
    o_user_id  OUT NUMBER
) AS
    v_role_id NUMBER;
BEGIN
    -- role_name is UNIQUE so a simple SELECT INTO works with no FETCH FIRST needed
    SELECT role_id INTO v_role_id FROM roles WHERE role_name = 'Visitor';

    -- Insert into Users (trigger handles user_id auto-increment)
    INSERT INTO users (username, email, password, role_id, status)
    VALUES (p_username, p_email, p_password, v_role_id, 'Active')
    RETURNING user_id INTO o_user_id;

    -- Insert into Visitors (trigger handles visitor_id auto-increment)
    INSERT INTO visitors (user_id, phone, country)
    VALUES (o_user_id, p_phone, p_country);

    COMMIT;
EXCEPTION
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/


