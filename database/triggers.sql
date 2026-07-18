USE museox_db;

DELIMITER //

CREATE TRIGGER tr_AfterUserInsert
AFTER INSERT ON users
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (user_id, action_performed, table_affected, record_id, ip_address, user_agent)
    VALUES (NEW.user_id, 'USER_REGISTRATION', 'users', NEW.user_id, 'SYSTEM', 'SYSTEM_PROCEDURE');
END //

DELIMITER ;