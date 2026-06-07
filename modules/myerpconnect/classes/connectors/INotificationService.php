<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

interface INotificationService
{
    public function send(string $to, string $subject, string $body): bool;
    public function sendAdminAlert(string $subject, string $body): bool;
}
