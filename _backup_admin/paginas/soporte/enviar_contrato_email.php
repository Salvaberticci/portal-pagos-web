<?php
// paginas/soporte/enviar_contrato_email.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Cargar autoloader de Composer (ajusta la ruta según tu estructura)
require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

function enviarContratoEmail($destinatario, $nombre_cliente, $ruta_pdf)
{

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_SMTP_SECURE; // 'tls' o PHPMailer::ENCRYPTION_STARTTLS
        $mail->Port = MAIL_PORT;

        // Charset
        $mail->CharSet = 'UTF-8';

        // Recipients
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($destinatario, $nombre_cliente);

        // Attachments
        if (file_exists($ruta_pdf)) {
            $mail->addAttachment($ruta_pdf, 'Contrato_Servicio_Wireless.pdf');
        } else {
            error_log("Error: El archivo PDF del contrato no existe en la ruta: $ruta_pdf");
            return false;
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Bienvenido a Wireless Supply - Tu Contrato de Servicio';

        $body = "
        <h2>¡Bienvenido a Wireless Supply!</h2>
        <p>Estimado(a) <strong>$nombre_cliente</strong>,</p>
        <p>Adjunto encontrarás tu contrato de servicio de internet por fibra óptica, debidamente procesado y con las firmas digitales registradas.</p>
        <p>Agradecemos tu confianza en nosotros.</p>
        <br>
        <p>Atentamente,</p>
        <p><strong>El Equipo de Wireless Supply</strong></p>
        ";

        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error al enviar correo: {$mail->ErrorInfo}");
        return false;
    }
}
?>