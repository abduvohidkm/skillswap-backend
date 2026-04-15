<?php

namespace SkillSwap\Utils;

class Validator {
    private array $errors = [];
    private array $data;
    
    public function __construct(array $data) {
        $this->data = $data;
    }
    
    /**
     * Validate required fields
     */
    public function required(array $fields): self {
        foreach ($fields as $field) {
            if (!isset($this->data[$field]) || trim($this->data[$field]) === '') {
                $this->errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        return $this;
    }
    
    /**
     * Validate email format
     */
    public function email(string $field): self {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = 'Invalid email format';
        }
        return $this;
    }
    
    /**
     * Validate minimum length
     */
    public function minLength(string $field, int $min): self {
        if (isset($this->data[$field]) && strlen($this->data[$field]) < $min) {
            $this->errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " must be at least {$min} characters";
        }
        return $this;
    }
    
    /**
     * Validate maximum length
     */
    public function maxLength(string $field, int $max): self {
        if (isset($this->data[$field]) && strlen($this->data[$field]) > $max) {
            $this->errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " must not exceed {$max} characters";
        }
        return $this;
    }
    
    /**
     * Validate password strength (8+ chars, 1 uppercase, 1 number)
     */
    public function password(string $field): self {
        if (isset($this->data[$field])) {
            $password = $this->data[$field];
            
            if (strlen($password) < 8) {
                $this->errors[$field] = 'Password must be at least 8 characters';
            } elseif (!preg_match('/[A-Z]/', $password)) {
                $this->errors[$field] = 'Password must contain at least one uppercase letter';
            } elseif (!preg_match('/[0-9]/', $password)) {
                $this->errors[$field] = 'Password must contain at least one number';
            }
        }
        return $this;
    }
    
    /**
     * Validate phone number
     */
    public function phone(string $field): self {
        if (isset($this->data[$field])) {
            $phone = preg_replace('/[^0-9+]/', '', $this->data[$field]);
            if (strlen($phone) < 10) {
                $this->errors[$field] = 'Invalid phone number';
            }
        }
        return $this;
    }
    
    /**
     * Validate numeric value
     */
    public function numeric(string $field): self {
        if (isset($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be a number';
        }
        return $this;
    }
    
    /**
     * Validate enum value
     */
    public function enum(string $field, array $allowedValues): self {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $allowedValues)) {
            $this->errors[$field] = 'Invalid value for ' . str_replace('_', ' ', $field);
        }
        return $this;
    }
    
    /**
     * Validate date format
     */
    public function date(string $field, string $format = 'Y-m-d'): self {
        if (isset($this->data[$field])) {
            $date = \DateTime::createFromFormat($format, $this->data[$field]);
            if (!$date || $date->format($format) !== $this->data[$field]) {
                $this->errors[$field] = 'Invalid date format';
            }
        }
        return $this;
    }
    
    /**
     * Check if validation passed
     */
    public function passes(): bool {
        return empty($this->errors);
    }
    
    /**
     * Check if validation failed
     */
    public function fails(): bool {
        return !$this->passes();
    }
    
    /**
     * Get validation errors
     */
    public function getErrors(): array {
        return $this->errors;
    }
    
    /**
     * Get validated data
     */
    public function validated(): array {
        return $this->data;
    }
}
