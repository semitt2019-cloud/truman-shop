<?php

class MyerpconnectVerificationModuleFrontController extends ModuleFrontController
{
    public $auth         = true;
    public $guestAllowed = false;

    public function initContent(): void
    {
        parent::initContent();

        $id_customer = (int)$this->context->customer->id;
        $verification = $this->getOrCreateRecord($id_customer);

        $errors  = [];
        $success = false;

        if (Tools::isSubmit('submit_verification')) {
            [$errors, $success] = $this->handleUpload($verification, $id_customer);
            if ($success) {
                // Reload after save
                $verification = $this->getOrCreateRecord($id_customer);
            }
        }

        $this->context->smarty->assign([
            'mec_verification'  => $verification,
            'mec_errors'        => $errors,
            'mec_success'       => $success,
            'mec_type_labels'   => [
                'retail'   => 'ลูกค้าทั่วไป (ปลีก)',
                'mechanic' => 'ช่างซ่อม',
                'dealer'   => 'ร้านขายอะไหล่ (ส่ง)',
            ],
            'mec_form_token'    => Tools::getToken(),
        ]);

        $this->setTemplate('module:myerpconnect/views/templates/front/verification.tpl');
    }

    private function getOrCreateRecord(int $id_customer): array
    {
        $row = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'myerpconnect_verification`
             WHERE `id_customer` = ' . $id_customer
        );

        if (!$row) {
            $now = date('Y-m-d H:i:s');
            Db::getInstance()->insert('myerpconnect_verification', [
                'id_customer'   => $id_customer,
                'customer_type' => 'retail',
                'status'        => 'approved',
                'date_add'      => $now,
                'date_upd'      => $now,
            ], false, true, Db::INSERT_IGNORE);
            $row = Db::getInstance()->getRow(
                'SELECT * FROM `' . _DB_PREFIX_ . 'myerpconnect_verification`
                 WHERE `id_customer` = ' . $id_customer
            );
        }

        return $row ?: [];
    }

    private function handleUpload(array $verification, int $id_customer): array
    {
        $errors  = [];
        $success = false;

        if (!Tools::checkToken()) {
            return [['คำขอไม่ถูกต้อง กรุณาลองใหม่'], false];
        }

        $type = Tools::getValue('customer_type', $verification['customer_type'] ?? 'retail');
        if (!in_array($type, ['retail', 'mechanic', 'dealer'], true)) {
            $type = 'retail';
        }

        // ถ้าเปลี่ยนเป็น retail → approved ทันที ไม่ต้องอัปโหลด doc
        if ($type === 'retail') {
            $this->updateRecord($id_customer, $type, 'approved', [], $verification);
            MyErpConnect::applyCustomerGroup($id_customer, $type, 'approved');
            return [[], true];
        }

        // mechanic / dealer → ต้องการเอกสาร 3 ชิ้น
        $doc_fields = [
            'doc_shop_photo' => 'รูปถ่ายหน้าร้าน',
            'doc_trade_reg'  => 'ทะเบียนการค้า/หนังสือรับรอง',
            'doc_id_card'    => 'บัตรประชาชน',
        ];

        $upload_dir = MyErpConnect::getVerificationUploadDir() . $id_customer . DIRECTORY_SEPARATOR;
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0750, true);
        }

        $saved = [
            'doc_shop_photo' => $verification['doc_shop_photo'] ?? '',
            'doc_trade_reg'  => $verification['doc_trade_reg']  ?? '',
            'doc_id_card'    => $verification['doc_id_card']    ?? '',
        ];

        $allowed_mime = ['image/jpeg', 'image/png', 'application/pdf'];
        $max_bytes    = 5 * 1024 * 1024;

        foreach ($doc_fields as $field => $label) {
            if (empty($_FILES[$field]['name'])) {
                continue;
            }

            $file = $_FILES[$field];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = "{$label}: เกิดข้อผิดพลาดในการอัปโหลด (code {$file['error']})";
                continue;
            }

            if ($file['size'] > $max_bytes) {
                $errors[] = "{$label}: ขนาดไฟล์เกิน 5MB";
                continue;
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']);

            if (!in_array($mime, $allowed_mime, true)) {
                $errors[] = "{$label}: รองรับเฉพาะ JPG, PNG, PDF";
                continue;
            }

            $ext = match ($mime) {
                'image/jpeg'     => 'jpg',
                'image/png'      => 'png',
                'application/pdf'=> 'pdf',
                default          => 'bin',
            };

            $filename = bin2hex(random_bytes(16)) . '.' . $ext;
            $dest     = $upload_dir . $filename;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                $errors[] = "{$label}: บันทึกไฟล์ไม่สำเร็จ";
                continue;
            }

            // ลบไฟล์เก่า
            if (!empty($saved[$field])) {
                $old = $upload_dir . basename((string)$saved[$field]);
                if (is_file($old)) {
                    @unlink($old);
                }
            }

            $saved[$field] = $filename;
        }

        if (!empty($errors)) {
            return [$errors, false];
        }

        $docs_complete = !empty($saved['doc_shop_photo'])
            && !empty($saved['doc_trade_reg'])
            && !empty($saved['doc_id_card']);

        $status = 'pending';

        $this->updateRecord($id_customer, $type, $status, $saved, $verification);

        if ($docs_complete) {
            $this->notifyAdmin($id_customer);
        }

        return [[], true];
    }

    private function updateRecord(
        int    $id_customer,
        string $type,
        string $status,
        array  $docs,
        array  $current
    ): void {
        $data = [
            'customer_type' => pSQL($type),
            'status'        => pSQL($status),
            'date_upd'      => date('Y-m-d H:i:s'),
        ];

        if (!empty($docs)) {
            $data['doc_shop_photo'] = pSQL($docs['doc_shop_photo'] ?? $current['doc_shop_photo'] ?? '');
            $data['doc_trade_reg']  = pSQL($docs['doc_trade_reg']  ?? $current['doc_trade_reg']  ?? '');
            $data['doc_id_card']    = pSQL($docs['doc_id_card']    ?? $current['doc_id_card']    ?? '');
        }

        Db::getInstance()->update(
            'myerpconnect_verification',
            $data,
            '`id_customer` = ' . $id_customer
        );
    }

    private function notifyAdmin(int $id_customer): void
    {
        $admin_email = Configuration::get('MYERPCONNECT_ADMIN_EMAIL')
            ?: Configuration::get('PS_SHOP_EMAIL');

        if (!$admin_email) {
            return;
        }

        $customer  = new Customer($id_customer);
        $shop_name = Configuration::get('PS_SHOP_NAME');

        Mail::Send(
            (int)Configuration::get('PS_LANG_DEFAULT'),
            'verification_submitted',
            'มีคำขอยืนยันตัวตนใหม่ — ' . $shop_name,
            [
                '{firstname}'      => $customer->firstname,
                '{lastname}'       => $customer->lastname,
                '{email}'          => $customer->email,
                '{id_customer}'    => $id_customer,
                '{shop_name}'      => $shop_name,
            ],
            $admin_email,
            null,
            Configuration::get('PS_SHOP_EMAIL'),
            $shop_name,
            null,
            null,
            _PS_MODULE_DIR_ . 'myerpconnect/mails/'
        );
    }
}
