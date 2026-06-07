<?php

class AdminCustomerVerificationController extends ModuleAdminController
{
    public function __construct()
    {
        $this->table            = 'myerpconnect_verification';
        $this->identifier       = 'id_verification';
        $this->className        = 'Configuration';
        $this->lang             = false;
        $this->bootstrap        = true;
        $this->context          = Context::getContext();

        parent::__construct();

        $this->meta_title = 'ยืนยันตัวตนลูกค้า — 2M RACING';
    }

    // ---------------------------------------------------------------
    // Main page
    // ---------------------------------------------------------------
    public function initContent(): void
    {
        parent::initContent();

        // Handle approve / reject actions
        if (Tools::isSubmit('approve_verification')) {
            $this->processApprove((int)Tools::getValue('id_verification'));
        }

        if (Tools::isSubmit('reject_verification')) {
            $this->processReject(
                (int)Tools::getValue('id_verification'),
                pSQL(Tools::getValue('admin_notes', ''))
            );
        }

        // Handle file download (secure)
        if (Tools::getValue('action') === 'download') {
            $this->processDownload();
        }

        $this->context->smarty->assign($this->buildTemplateVars());
        $this->setTemplate('module:myerpconnect/views/templates/admin/verification.tpl');
    }

    // ---------------------------------------------------------------
    // Approve
    // ---------------------------------------------------------------
    private function processApprove(int $id_verification): void
    {
        if (!$id_verification) {
            return;
        }

        $row = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'myerpconnect_verification`
             WHERE `id_verification` = ' . $id_verification
        );

        if (!$row) {
            $this->errors[] = 'ไม่พบข้อมูล';
            return;
        }

        Db::getInstance()->update('myerpconnect_verification', [
            'status'     => 'approved',
            'date_upd'   => date('Y-m-d H:i:s'),
        ], '`id_verification` = ' . $id_verification);

        MyErpConnect::applyCustomerGroup(
            (int)$row['id_customer'],
            $row['customer_type'],
            'approved'
        );

        $this->notifyCustomer((int)$row['id_customer'], 'approved', '');
        $this->confirmations[] = 'อนุมัติลูกค้า #' . $row['id_customer'] . ' เรียบร้อย';
    }

    // ---------------------------------------------------------------
    // Reject
    // ---------------------------------------------------------------
    private function processReject(int $id_verification, string $notes): void
    {
        if (!$id_verification) {
            return;
        }

        $row = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'myerpconnect_verification`
             WHERE `id_verification` = ' . $id_verification
        );

        if (!$row) {
            $this->errors[] = 'ไม่พบข้อมูล';
            return;
        }

        Db::getInstance()->update('myerpconnect_verification', [
            'status'      => 'rejected',
            'admin_notes' => $notes,
            'date_upd'    => date('Y-m-d H:i:s'),
        ], '`id_verification` = ' . $id_verification);

        // Reject → ย้ายกลับ retail group
        MyErpConnect::applyCustomerGroup(
            (int)$row['id_customer'],
            'retail',
            'approved'
        );

        $this->notifyCustomer((int)$row['id_customer'], 'rejected', $notes);
        $this->confirmations[] = 'ปฏิเสธคำขอลูกค้า #' . $row['id_customer'] . ' เรียบร้อย';
    }

    // ---------------------------------------------------------------
    // Secure file download
    // ---------------------------------------------------------------
    private function processDownload(): void
    {
        $id_verification = (int)Tools::getValue('id_verification');
        $field           = Tools::getValue('field');

        $allowed_fields = ['doc_shop_photo', 'doc_trade_reg', 'doc_id_card'];
        if (!in_array($field, $allowed_fields, true)) {
            die('Invalid field');
        }

        $row = Db::getInstance()->getRow(
            'SELECT v.`id_customer`, v.`' . bqSQL($field) . '`
             FROM `' . _DB_PREFIX_ . 'myerpconnect_verification` v
             WHERE `id_verification` = ' . $id_verification
        );

        if (!$row || empty($row[$field])) {
            die('File not found');
        }

        $id_customer = (int)$row['id_customer'];
        $filename    = basename((string)$row[$field]);
        $filepath    = MyErpConnect::getVerificationUploadDir() . $id_customer . DIRECTORY_SEPARATOR . $filename;

        if (!is_file($filepath)) {
            die('File missing from disk');
        }

        $ext  = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'pdf'         => 'application/pdf',
            default       => 'application/octet-stream',
        };

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . addslashes($filename) . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: private, no-cache');
        readfile($filepath);
        exit;
    }

    // ---------------------------------------------------------------
    // Template data
    // ---------------------------------------------------------------
    private function buildTemplateVars(): array
    {
        $filter_status = Tools::getValue('filter_status', '');
        $where         = '1';
        if (in_array($filter_status, ['approved', 'pending', 'rejected'], true)) {
            $where .= " AND v.`status` = '" . pSQL($filter_status) . "'";
        }

        $rows = Db::getInstance()->executeS(
            'SELECT v.*, c.`firstname`, c.`lastname`, c.`email`
             FROM `' . _DB_PREFIX_ . 'myerpconnect_verification` v
             LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON c.`id_customer` = v.`id_customer`
             WHERE ' . $where . '
             ORDER BY v.`date_upd` DESC
             LIMIT 200'
        );

        $type_labels = [
            'retail'   => 'ลูกค้าทั่วไป (ปลีก)',
            'mechanic' => 'ช่างซ่อม',
            'dealer'   => 'ร้านขายอะไหล่ (ส่ง)',
        ];

        $status_labels = [
            'approved' => 'อนุมัติแล้ว',
            'pending'  => 'รออนุมัติ',
            'rejected' => 'ปฏิเสธ',
        ];

        $download_base = $this->context->link->getAdminLink('AdminCustomerVerification');

        return [
            'mec_rows'          => $rows ?: [],
            'mec_type_labels'   => $type_labels,
            'mec_status_labels' => $status_labels,
            'mec_filter_status' => $filter_status,
            'mec_download_base' => $download_base,
            'mec_token'         => Tools::getAdminTokenLite('AdminCustomerVerification'),
            'mec_errors'        => $this->errors,
            'mec_confirmations' => $this->confirmations,
        ];
    }

    // ---------------------------------------------------------------
    // Email notification to customer
    // ---------------------------------------------------------------
    private function notifyCustomer(int $id_customer, string $status, string $notes): void
    {
        $customer  = new Customer($id_customer);
        if (!Validate::isLoadedObject($customer)) {
            return;
        }

        $shop_name = Configuration::get('PS_SHOP_NAME');
        $template  = ($status === 'approved') ? 'verification_approved' : 'verification_rejected';
        $subject   = ($status === 'approved')
            ? 'บัญชีของคุณได้รับการอนุมัติแล้ว — ' . $shop_name
            : 'เอกสารยืนยันตัวตนของคุณถูกปฏิเสธ — ' . $shop_name;

        Mail::Send(
            (int)$customer->id_lang ?: (int)Configuration::get('PS_LANG_DEFAULT'),
            $template,
            $subject,
            [
                '{firstname}'   => $customer->firstname,
                '{lastname}'    => $customer->lastname,
                '{admin_notes}' => $notes ?: '-',
                '{shop_name}'   => $shop_name,
                '{upload_url}'  => Context::getContext()->link->getModuleLink('myerpconnect', 'verification'),
            ],
            $customer->email,
            $customer->firstname . ' ' . $customer->lastname,
            Configuration::get('PS_SHOP_EMAIL'),
            $shop_name,
            null,
            null,
            _PS_MODULE_DIR_ . 'myerpconnect/mails/'
        );
    }
}
