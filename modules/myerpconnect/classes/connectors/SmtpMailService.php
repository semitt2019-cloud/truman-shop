<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class SmtpMailService implements INotificationService
{
    public function send(string $to, string $subject, string $body): bool
    {
        return (bool)Mail::Send(
            (int)Configuration::get('PS_LANG_DEFAULT'),
            'contact_form',
            $subject,
            ['{message}' => nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'))],
            $to
        );
    }

    public function sendAdminAlert(string $subject, string $body): bool
    {
        $admin_email = Configuration::get('MYERPCONNECT_ADMIN_EMAIL')
            ?: Configuration::get('PS_SHOP_EMAIL');

        if (!Validate::isEmail($admin_email)) {
            return false;
        }

        return $this->send($admin_email, $subject, $body);
    }
}
