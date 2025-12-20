<?php
class Captcha {
    // ==================== YOUR KEYS ====================
    // Site Key (for HTML frontend - starts with 6L...)
    private const SITE_KEY = "6Lf4tiwsAAAAAL0uTrKUsazU1XAKljV2_E6XbcVl"; // ← YOU NEED THIS!
    
    // Secret Key (for PHP backend - starts with 6L...)
    private const SECRET_KEY = "6Lf4tiwsAAAAAAgYHittJV3q9Lo-gvWZNrZ0IUfN"; // ← YOU ALREADY HAVE THIS
    
    // ===================================================
    
    /**
     * Verify reCAPTCHA response
     */
    public static function verify($response) {
        if (empty($response)) {
            return false;
        }
        
        // Using Google reCAPTCHA v2
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        
        $data = [
            'secret' => self::SECRET_KEY,
            'response' => $response,
            'remoteip' => $_SERVER['REMOTE_ADDR'] // Optional
        ];
        
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
                'timeout' => 5 // 5 second timeout
            ]
        ];
        
        try {
            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            
            if ($result === false) {
                // For assignment demo, allow if Google is unreachable
                return true;
            }
            
            $response = json_decode($result);
            return $response->success ?? false;
            
        } catch (Exception $e) {
            // For assignment, allow if error occurs
            return true;
        }
    }
    
    /**
     * Render CAPTCHA widget
     */
    public static function render() {
        return '<div class="g-recaptcha" data-sitekey="' . self::SITE_KEY . '"></div>
                <script src="https://www.google.com/recaptcha/api.js" async defer></script>';
    }
}
?>