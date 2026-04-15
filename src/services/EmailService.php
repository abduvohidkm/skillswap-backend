<?php

namespace SkillSwap\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private PHPMailer $mailer;
    
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->configure();
    }
    
    /**
     * Configure PHPMailer with Gmail SMTP
     */
    private function configure(): void {
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = $_ENV['MAIL_HOST'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $_ENV['MAIL_USERNAME'];
            $this->mailer->Password = $_ENV['MAIL_PASSWORD'];
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = (int)$_ENV['MAIL_PORT'];
            
            // Default sender
            $this->mailer->setFrom(
                $_ENV['MAIL_FROM_ADDRESS'],
                $_ENV['MAIL_FROM_NAME']
            );
            
            // UTF-8 encoding
            $this->mailer->CharSet = 'UTF-8';
        } catch (Exception $e) {
            error_log("Email configuration failed: " . $e->getMessage());
        }
    }
    
    /**
     * Send welcome email to new user
     */
    public function sendWelcomeEmail(string $to, string $name): bool {
        try {
            $this->mailer->addAddress($to, $name);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Welcome to Skill Swap Academy!';
            $this->mailer->Body = $this->getWelcomeEmailTemplate($name);
            
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Welcome email failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send course application approved email
     */
    public function sendCourseApprovedEmail(string $to, string $name, string $courseName): bool {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to, $name);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Your Course Application Has Been Approved!';
            $this->mailer->Body = $this->getCourseApprovedTemplate($name, $courseName);
            
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Course approved email failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send course application rejected email
     */
    public function sendCourseRejectedEmail(string $to, string $name, string $courseName, string $reason = ''): bool {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to, $name);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Course Application Update';
            $this->mailer->Body = $this->getCourseRejectedTemplate($name, $courseName, $reason);
            
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Course rejected email failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail(string $to, string $name, string $resetToken): bool {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to, $name);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Password Reset Request';
            $this->mailer->Body = $this->getPasswordResetTemplate($name, $resetToken);
            
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Password reset email failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Welcome email template
     */
    private function getWelcomeEmailTemplate(string $name): string {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; background-color: #000; color: #fff; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #111; border: 1px solid #333; border-radius: 10px; padding: 30px;'>
                <h1 style='color: #fff; text-align: center;'>Welcome to Skill Swap Academy!</h1>
                <p>Hi {$name},</p>
                <p>Thank you for joining Skill Swap Academy! Your account has been successfully created.</p>
                <p>You can now:</p>
                <ul>
                    <li>Browse and purchase courses</li>
                    <li>Access exam preparation materials</li>
                    <li>Connect with instructors</li>
                    <li>Track your learning progress</li>
                </ul>
                <p>If you have any questions, feel free to reach out to us on Telegram: <a href='https://t.me/skillswapadmin' style='color: #4a9eff;'>@skillswapadmin</a></p>
                <p style='margin-top: 30px;'>Best regards,<br>Skill Swap Academy Team</p>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Course approved email template
     */
    private function getCourseApprovedTemplate(string $name, string $courseName): string {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; background-color: #000; color: #fff; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #111; border: 1px solid #333; border-radius: 10px; padding: 30px;'>
                <h1 style='color: #4ade80; text-align: center;'>Course Application Approved! ✅</h1>
                <p>Hi {$name},</p>
                <p>Great news! Your application for <strong>{$courseName}</strong> has been approved.</p>
                <p>You can now access the course materials in your dashboard.</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='" . $_ENV['APP_URL'] . "/mycourses.html' style='background-color: #fff; color: #000; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;'>View My Courses</a>
                </p>
                <p>Happy learning!</p>
                <p style='margin-top: 30px;'>Best regards,<br>Skill Swap Academy Team</p>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Course rejected email template
     */
    private function getCourseRejectedTemplate(string $name, string $courseName, string $reason): string {
        $reasonText = $reason ? "<p><strong>Reason:</strong> {$reason}</p>" : '';
        
        return "
        <html>
        <body style='font-family: Arial, sans-serif; background-color: #000; color: #fff; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #111; border: 1px solid #333; border-radius: 10px; padding: 30px;'>
                <h1 style='color: #f87171; text-align: center;'>Course Application Update</h1>
                <p>Hi {$name},</p>
                <p>We regret to inform you that your application for <strong>{$courseName}</strong> has not been approved at this time.</p>
                {$reasonText}
                <p>If you have any questions, please contact us on Telegram: <a href='https://t.me/skillswapadmin' style='color: #4a9eff;'>@skillswapadmin</a></p>
                <p style='margin-top: 30px;'>Best regards,<br>Skill Swap Academy Team</p>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Password reset email template
     */
    private function getPasswordResetTemplate(string $name, string $resetToken): string {
        $resetLink = $_ENV['APP_URL'] . "/reset-password.html?token={$resetToken}";
        
        return "
        <html>
        <body style='font-family: Arial, sans-serif; background-color: #000; color: #fff; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #111; border: 1px solid #333; border-radius: 10px; padding: 30px;'>
                <h1 style='color: #fff; text-align: center;'>Password Reset Request</h1>
                <p>Hi {$name},</p>
                <p>We received a request to reset your password. Click the button below to create a new password:</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='{$resetLink}' style='background-color: #fff; color: #000; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;'>Reset Password</a>
                </p>
                <p>This link will expire in 1 hour.</p>
                <p>If you didn't request a password reset, please ignore this email.</p>
                <p style='margin-top: 30px;'>Best regards,<br>Skill Swap Academy Team</p>
            </div>
        </body>
        </html>
        ";
    }
}
