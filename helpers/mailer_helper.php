<?php

// shared mailer helper — iisang lugar na lang ang Gmail SMTP setup
// para hindi na kopyahin sa bawat file na nagpapadala ng email

require_once __DIR__ . "/../vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


// credentials galing sa Admin/mail_config.php (hindi naka-commit)

if (!function_exists("figurify_mail_config")) {

    function figurify_mail_config(): array
    {
        $configPath = __DIR__ . "/../Admin/mail_config.php";

        if (!file_exists($configPath)) {
            throw new \RuntimeException(
                "Admin/mail_config.php ay wala pa. Kopyahin ang " .
                "Admin/mail_config.sample.php papuntang Admin/mail_config.php " .
                "tapos ilagay doon ang totoong Gmail App Password."
            );
        }

        return require $configPath;
    }
}


if (!function_exists("figurify_send_mail")) {

    /**
     * Sends an HTML email from the Clay and Stuff Gmail account.
     * $embeddedImages is optional: [ ["path" => "...", "cid" => "..."] ]
     * to embed images inline via <img src="cid:...">.
     */
    function figurify_send_mail(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $altBody,
        array $embeddedImages = []
    ): bool {

        $mail = new PHPMailer(true);

        try {

            $mailConfig = figurify_mail_config();

            $mail->isSMTP();

            $mail->CharSet = "UTF-8";
            $mail->Encoding = "base64";

            $mail->Host = "smtp.gmail.com";
            $mail->SMTPAuth = true;

            $mail->Username = $mailConfig["username"];
            $mail->Password = $mailConfig["app_password"];

            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom(
                $mailConfig["username"],
                $mailConfig["from_name"] ?? "Clay and Stuff"
            );

            $mail->addAddress($toEmail, $toName);

            foreach ($embeddedImages as $embed) {

                if (!empty($embed["path"]) && file_exists($embed["path"])) {

                    $mail->addEmbeddedImage(
                        $embed["path"],
                        $embed["cid"] ?? "embeddedimage"
                    );

                }

            }

            $mail->isHTML(true);

            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $altBody;

            $mail->send();

            return true;

        } catch (\Throwable $e) {

            error_log("figurify_send_mail failed: " . $e->getMessage());

            return false;

        }

    }

}
