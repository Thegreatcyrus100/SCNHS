<?php
use PHPMailer\PHPMailer\PHPMailer;
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

function sendConfirmationEmail($toEmail, $name) {
    $mail = new PHPMailer();
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'your-email@gmail.com';  // Replace with your email
    $mail->Password = 'your-password';        // Replace with your email password
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('your-email@gmail.com', 'Enrollment System');
    $mail->addAddress($toEmail);
    $mail->Subject = 'Enrollment Confirmation';
    $mail->Body = "Dear $name, \n\nYour enrollment has been received. Thank you!";

    return $mail->send();
}
?>
