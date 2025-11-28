<?php
class Helpers {
    // Display error messages
    public static function displayError($message) {
        if (!empty($message)) {
            return '<div class="error-message" style="color: red; padding: 10px; margin: 10px 0; border: 1px solid red; background: #ffe6e6;">' . htmlspecialchars($message) . '</div>';
        }
        return '';
    }

    // Display success messages
    public static function displaySuccess($message) {
        if (!empty($message)) {
            return '<div class="success-message" style="color: green; padding: 10px; margin: 10px 0; border: 1px solid green; background: #e6ffe6;">' . htmlspecialchars($message) . '</div>';
        }
        return '';
    }

    // Generate form input field
    public static function inputField($type, $name, $value = '', $placeholder = '', $required = false) {
        $required_attr = $required ? 'required' : '';
        return '<input type="' . htmlspecialchars($type) . '" 
                       name="' . htmlspecialchars($name) . '" 
                       value="' . htmlspecialchars($value) . '" 
                       placeholder="' . htmlspecialchars($placeholder) . '" 
                       ' . $required_attr . ' 
                       style="width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 4px;">';
    }

    // Sanitize input data
    public static function sanitize($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }
}
?>