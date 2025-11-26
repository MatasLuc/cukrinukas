<?php
require_once __DIR__ . '/env.php';
loadEnvFile();
require_once __DIR__ . '/lib/PHPMailer/Exception.php';
require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmail(string $to, string $subject, string $bodyHtml): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = requireEnv('SMTP_HOST');
        $mail->SMTPAuth   = true;
        $mail->Username   = requireEnv('SMTP_USER');
        $mail->Password   = requireEnv('SMTP_PASS');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = (int)requireEnv('SMTP_PORT');
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(requireEnv('SMTP_FROM_EMAIL'), requireEnv('SMTP_FROM_NAME'));
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $bodyHtml));

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Sukuria gražų HTML laiško šabloną
 * * @param string $title Pagrindinė antraštė
 * @param string $content Pagrindinis tekstas (gali turėti HTML)
 * @param string|null $btnUrl Jei norite mygtuko - nuoroda
 * @param string|null $btnText Mygtuko tekstas
 */
function getEmailTemplate(string $title, string $content, ?string $btnUrl = null, ?string $btnText = null): string {
    $year = date('Y');
    
    // Mygtuko HTML (rodomas tik jei pateikta nuoroda)
    $buttonHtml = '';
    if ($btnUrl && $btnText) {
        $buttonHtml = "
        <table role='presentation' border='0' cellpadding='0' cellspacing='0' style='margin: 24px 0;'>
          <tr>
            <td align='center'>
              <a href='{$btnUrl}' style='background-color: #7c3aed; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block; font-family: sans-serif;'>
                {$btnText}
              </a>
            </td>
          </tr>
        </table>";
    }

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f7f7fb; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
  <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
      <td style="padding: 40px 20px;" align="center">
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e4e7ec;">
          
          <tr>
            <td style="background-color: #ffffff; padding: 30px 40px; text-align: center; border-bottom: 1px solid #f0f0f0;">
              <h2 style="margin: 0; color: #0b0b0b; font-size: 24px; letter-spacing: -0.5px;">Cukrinukas.lt</h2>
            </td>
          </tr>

          <tr>
            <td style="padding: 40px; color: #333333; font-size: 16px; line-height: 1.6;">
              <h1 style="margin-top: 0; margin-bottom: 16px; font-size: 20px; color: #111827;">{$title}</h1>
              
              <div style="color: #4b5563;">
                {$content}
              </div>

              {$buttonHtml}

              <p style="margin-top: 24px; font-size: 14px; color: #6b7280;">
                Jei turite klausimų, atsakykite į šį laišką.<br>
                Linkėjimai,<br>
                <strong>Cukrinukas komanda</strong>
              </p>
            </td>
          </tr>

          <tr>
            <td style="background-color: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #9ca3af;">
              &copy; {$year} Cukrinukas.lt. Visos teisės saugomos.<br>
              Tai yra automatinis pranešimas.
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}
?>