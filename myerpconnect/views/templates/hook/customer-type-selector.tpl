{*
  Hook: displayCustomerAccountForm
  แสดง Customer Type Selector ในฟอร์มสมัครสมาชิก
  จะแสดงก่อนช่องกรอกข้อมูล ใช้ JS เลื่อนไปไว้ก่อนปุ่ม Submit
*}
<div id="mec-type-selector" style="
  background: linear-gradient(135deg, #0F0F0F 0%, #171717 100%);
  border: 1px solid rgba(192,39,45,.35);
  border-radius: 10px;
  padding: 20px 24px 24px;
  margin-bottom: 24px;
  font-family: 'Sarabun', sans-serif;
">
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
      <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z" fill="#C0272D"/>
    </svg>
    <span style="color:#F2F2F2;font-size:15px;font-weight:600;letter-spacing:.3px;">กรุณาเลือกประเภทบัญชีของคุณ</span>
  </div>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;">

    {* Retail *}
    <label for="mec_type_retail" style="cursor:pointer;">
      <input type="radio" id="mec_type_retail" name="customer_type"
             value="retail" checked
             style="position:absolute;opacity:0;pointer-events:none;">
      <div class="mec-type-card mec-type-selected" data-value="retail" style="
        border: 2px solid #C0272D;
        border-radius: 8px;
        padding: 14px 16px;
        background: rgba(192,39,45,.08);
        transition: all .2s;
      ">
        <div style="font-size:22px;margin-bottom:6px;">🛒</div>
        <div style="color:#F2F2F2;font-weight:600;font-size:14px;">ลูกค้าทั่วไป</div>
        <div style="color:#909090;font-size:12px;margin-top:4px;line-height:1.4;">ซื้อใช้เอง · ราคาปลีก<br>สมัครได้ทันที</div>
      </div>
    </label>

    {* Mechanic *}
    <label for="mec_type_mechanic" style="cursor:pointer;">
      <input type="radio" id="mec_type_mechanic" name="customer_type"
             value="mechanic"
             style="position:absolute;opacity:0;pointer-events:none;">
      <div class="mec-type-card" data-value="mechanic" style="
        border: 2px solid rgba(255,255,255,.1);
        border-radius: 8px;
        padding: 14px 16px;
        background: rgba(255,255,255,.03);
        transition: all .2s;
      ">
        <div style="font-size:22px;margin-bottom:6px;">🔧</div>
        <div style="color:#F2F2F2;font-weight:600;font-size:14px;">ช่างซ่อม</div>
        <div style="color:#909090;font-size:12px;margin-top:4px;line-height:1.4;">ราคาช่าง (ส่งช่าง)<br>ต้องส่งเอกสารยืนยัน</div>
      </div>
    </label>

    {* Dealer *}
    <label for="mec_type_dealer" style="cursor:pointer;">
      <input type="radio" id="mec_type_dealer" name="customer_type"
             value="dealer"
             style="position:absolute;opacity:0;pointer-events:none;">
      <div class="mec-type-card" data-value="dealer" style="
        border: 2px solid rgba(255,255,255,.1);
        border-radius: 8px;
        padding: 14px 16px;
        background: rgba(255,255,255,.03);
        transition: all .2s;
      ">
        <div style="font-size:22px;margin-bottom:6px;">🏪</div>
        <div style="color:#F2F2F2;font-weight:600;font-size:14px;">ร้านขายอะไหล่</div>
        <div style="color:#909090;font-size:12px;margin-top:4px;line-height:1.4;">ราคาส่ง · เห็นราคาทุกระดับ<br>ต้องส่งเอกสารยืนยัน</div>
      </div>
    </label>

  </div>

  <div id="mec-doc-notice" style="
    display:none;
    background: rgba(212,175,55,.08);
    border: 1px solid rgba(212,175,55,.3);
    border-radius: 6px;
    padding: 10px 14px;
    margin-top:14px;
    color: #D4AF37;
    font-size: 13px;
    line-height: 1.5;
  ">
    หลังสมัครสมาชิก ระบบจะให้คุณอัปโหลดเอกสารยืนยัน 3 รายการ:
    รูปถ่ายหน้าร้าน · ทะเบียนการค้า/หนังสือรับรอง · บัตรประชาชน
    (Admin จะอนุมัติและเปิดราคาพิเศษให้ภายใน 24 ชั่วโมง)
  </div>
</div>

<style>
.mec-type-card:hover {
  border-color: rgba(192,39,45,.6) !important;
  background: rgba(192,39,45,.06) !important;
}
.mec-type-selected {
  border-color: #C0272D !important;
  background: rgba(192,39,45,.1) !important;
  box-shadow: 0 0 0 3px rgba(192,39,45,.15);
}
</style>

<script>
(function () {
  var cards   = document.querySelectorAll('.mec-type-card');
  var radios  = document.querySelectorAll('input[name="customer_type"]');
  var notice  = document.getElementById('mec-doc-notice');

  function selectType(val) {
    cards.forEach(function (card) {
      var selected = card.dataset.value === val;
      card.classList.toggle('mec-type-selected', selected);
    });
    radios.forEach(function (r) { r.checked = r.value === val; });
    notice.style.display = (val !== 'retail') ? 'block' : 'none';
  }

  cards.forEach(function (card) {
    card.addEventListener('click', function () { selectType(card.dataset.value); });
  });
})();
</script>
