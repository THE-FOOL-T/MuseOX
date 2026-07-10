<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

class Authentication {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function authenticateUser(string $email, string $password): bool {
        // Oracle 11g query format (Omitted LIMIT 1 syntax since email is unique)
        $sql = "SELECT u.user_id, u.username, u.email, u.password, r.role_name, u.status 
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                WHERE u.email = :email";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        // Fetch Row: Oracle maps column returns to UPPERCASE keys
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['PASSWORD'])) {
            if ($user['STATUS'] !== 'Active') {
                setFlashMessage('error', 'Your account has been suspended. Please contact support.');
                return false;
            }
            
            $_SESSION['user_id'] = $user['USER_ID'];
            $_SESSION['username'] = $user['USERNAME'];
            $_SESSION['role'] = $user['ROLE_NAME'];
            
            // Generate audit log entry
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
            $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN', 0, 255);
            
            $logSql = "INSERT INTO audit_logs (user_id, action_performed, table_affected, ip_address, user_agent)
                       VALUES (:log_uid, 'LOGIN', 'USERS', :log_ip, :log_ua)";
            
            try {
                $logStmt = $this->db->prepare($logSql);
                $logStmt->bindValue(':log_uid', $user['USER_ID'], PDO::PARAM_INT);
                $logStmt->bindValue(':log_ip',  substr($ip, 0, 45));
                $logStmt->bindValue(':log_ua',  $ua);
                $logStmt->execute();
            } catch (PDOException $logEx) {
                // Audit log failure must not block login
                error_log("Audit log error: " . $logEx->getMessage());
            }
            
            return true;
        }
        
        return false;
    }

    public function registersVisitorProfile(string $username, string $email, string $password, string $phone, string $country): bool {
        // Check if email or username exists
        $checkSql = "SELECT user_id FROM users WHERE email = :email OR username = :username";
        $checkStmt = $this->db->prepare($checkSql);
        
        $checkStmt->bindParam(':email', $email);
        $checkStmt->bindParam(':username', $username);
        $checkStmt->execute();
        
        if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
            setFlashMessage('error', 'An account with this email or username already exists.');
            return false;
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $out_id = 0; // Buffer container variable for Oracle OUT parameter registration

        // Call your compiled PL/SQL Stored Procedure using an anonymous block execution context
        $spSql = "BEGIN sp_RegisterVisitor(:username, :email, :password, :phone, :country, :out_id); END;";
        $spStmt = $this->db->prepare($spSql);
        
        $spStmt->bindParam(':username', $username);
        $spStmt->bindParam(':email', $email);
        $spStmt->bindParam(':password', $hashedPassword);
        $spStmt->bindParam(':phone', $phone);
        $spStmt->bindParam(':country', $country);
        $spStmt->bindParam(':out_id', $out_id, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 4000); // Explicit integer mapping binding

        try {
            $spStmt->execute();
            return true;
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            setFlashMessage('error', 'A database error occurred during registration. Please try again.');
            return false;
        }
    }
}