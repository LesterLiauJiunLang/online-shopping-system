<?php
class Verification {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function sendVerificationEmail($user_id, $email) {
        $token = bin2hex(random_bytes(50));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Store verification token
        $query = "INSERT INTO email_verifications (user_id, token, expires_at) 
                  VALUES (:user_id, :token, :expires)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['user_id' => $user_id, 'token' => $token, 'expires' => $expires]);
        
        // In real application, send actual email
        $verification_link = "http://localhost/online-shopping-system/public/verify-email.php?token=" . $token;
        
        return $verification_link; // For demo purposes
    }
    
    public function verifyEmail($token) {
        $query = "SELECT user_id FROM email_verifications 
                  WHERE token = :token AND expires_at > NOW() AND used = 0";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['token' => $token]);
        
        if ($stmt->rowCount() == 1) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Mark user as verified
            $query = "UPDATE users SET email_verified = 1 WHERE user_id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(['user_id' => $row['user_id']]);
            
            // Mark token as used
            $query = "UPDATE email_verifications SET used = 1 WHERE token = :token";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(['token' => $token]);
            
            return true;
        }
        return false;
    }
}
?>