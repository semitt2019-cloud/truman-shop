{*
  Hook: displayCustomerAccount
  แสดงสถานะยืนยันตัวตนในหน้า My Account
*}

{if $mec_show_banner}
<div style="
  background: linear-gradient(135deg, rgba(192,39,45,.12) 0%, rgba(192,39,45,.05) 100%);
  border: 1px solid rgba(192,39,45,.5);
  border-left: 4px solid #C0272D;
  border-radius: 8px;
  padding: 16px 20px;
  margin-bottom: 24px;
  font-family: 'Sarabun', sans-serif;
">
  <div style="color:#F2F2F2;font-weight:700;font-size:15px;margin-bottom:6px;">
    กรุณาอัปโหลดเอกสารยืนยันตัวตน
  </div>
  <div style="color:#B0B0B0;font-size:13px;line-height:1.5;margin-bottom:12px;">
    บัญชีของคุณถูกตั้งค่าเป็นช่างซ่อม/ร้านอะไหล่ กรุณาส่งเอกสารเพื่อรับราคาพิเศษ
  </div>
  <a href="{$mec_upload_url|escape:'htmlall'}"
     style="
       display:inline-block;
       background: #C0272D;
       color: #fff;
       padding: 8px 18px;
       border-radius: 6px;
       text-decoration: none;
       font-size: 13px;
       font-weight: 600;
     ">
    อัปโหลดเอกสารเลย →
  </a>
</div>
{/if}

{if $mec_verification}
<div style="
  background: #0F0F0F;
  border: 1px solid rgba(255,255,255,.08);
  border-radius: 10px;
  padding: 20px 24px;
  margin-bottom: 24px;
  font-family: 'Sarabun', sans-serif;
">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
    <span style="color:#F2F2F2;font-weight:700;font-size:15px;">สถานะบัญชีของคุณ</span>

    {if $mec_verification.status == 'approved'}
      <span style="background:rgba(46,204,154,.15);color:#2ECC9A;border:1px solid rgba(46,204,154,.3);border-radius:20px;padding:3px 12px;font-size:12px;font-weight:600;">
        ✓ {$mec_status_labels[$mec_verification.status]|escape:'htmlall'}
      </span>
    {elseif $mec_verification.status == 'pending'}
      <span style="background:rgba(212,175,55,.15);color:#D4AF37;border:1px solid rgba(212,175,55,.3);border-radius:20px;padding:3px 12px;font-size:12px;font-weight:600;">
        ⏳ {$mec_status_labels[$mec_verification.status]|escape:'htmlall'}
      </span>
    {else}
      <span style="background:rgba(192,39,45,.15);color:#E55;border:1px solid rgba(192,39,45,.3);border-radius:20px;padding:3px 12px;font-size:12px;font-weight:600;">
        ✗ {$mec_status_labels[$mec_verification.status]|escape:'htmlall'}
      </span>
    {/if}
  </div>

  <div style="color:#909090;font-size:13px;margin-bottom:6px;">
    ประเภทบัญชี:
    <strong style="color:#F2F2F2;">
      {$mec_type_labels[$mec_verification.customer_type]|escape:'htmlall'}
    </strong>
  </div>

  {if $mec_verification.customer_type != 'retail'}
  <div style="margin-top:14px;">
    <div style="color:#606060;font-size:12px;margin-bottom:8px;">เอกสารที่อัปโหลดแล้ว</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      {assign var="doc_labels" value=['doc_shop_photo'=>'รูปหน้าร้าน','doc_trade_reg'=>'ทะเบียนการค้า','doc_id_card'=>'บัตรประชาชน']}
      {foreach $doc_labels as $field => $label}
        <span style="
          display:inline-flex;align-items:center;gap:5px;
          padding:4px 10px;
          border-radius:4px;
          font-size:12px;
          {if $mec_verification.$field}
            background:rgba(46,204,154,.1);color:#2ECC9A;border:1px solid rgba(46,204,154,.2);
          {else}
            background:rgba(255,255,255,.04);color:#505050;border:1px solid rgba(255,255,255,.07);
          {/if}
        ">
          {if $mec_verification.$field}✓{else}—{/if} {$label|escape:'htmlall'}
        </span>
      {/foreach}
    </div>
  </div>

  {if $mec_verification.status == 'rejected' && $mec_verification.admin_notes}
  <div style="
    background:rgba(192,39,45,.08);
    border:1px solid rgba(192,39,45,.25);
    border-radius:6px;
    padding:10px 14px;
    margin-top:12px;
    color:#DDA0A0;
    font-size:13px;
  ">
    <strong>หมายเหตุจาก Admin:</strong>
    {$mec_verification.admin_notes|escape:'htmlall'}
  </div>
  {/if}

  {if $mec_verification.status != 'approved'}
  <div style="margin-top:14px;">
    <a href="{$mec_upload_url|escape:'htmlall'}"
       style="
         display:inline-block;
         background: {if $mec_verification.status == 'rejected'}#C0272D{else}rgba(255,255,255,.08){/if};
         color: #F2F2F2;
         padding: 8px 16px;
         border-radius: 6px;
         text-decoration: none;
         font-size: 13px;
         border: 1px solid rgba(255,255,255,.1);
       ">
      {if $mec_verification.status == 'rejected'}อัปโหลดเอกสารใหม่{else}จัดการเอกสาร{/if}
    </a>
  </div>
  {/if}

  {/if}
</div>
{/if}
