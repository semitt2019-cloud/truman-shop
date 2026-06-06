{**
 * MEC — Hero Banner + Flash Sale + Filter + Sort Bar
 * Hook: displayProductListHeader
 *}

{* ================================================================
   HERO SECTION (simple Shopee-style banner)
   ================================================================ *}
<section class="mec-hero">
  <div class="mec-hero__inner">
    <div>
      <div style="font-size:22px;font-weight:800;letter-spacing:-.5px;">
        {if $current_category_name}{$current_category_name|escape:'html':'UTF-8'}{else}ร้านค้าของเรา{/if}
      </div>
      <div style="font-size:13px;opacity:.9;margin-top:4px;">
        สินค้าคุณภาพ · จัดส่งรวดเร็ว · บริการดีเยี่ยม
      </div>
    </div>
  </div>
</section>

{* ================================================================
   FLASH SALE BANNER (แสดงเมื่อ flash_sale_active = true)
   ================================================================ *}
{if isset($flash_sale_active) && $flash_sale_active}
<div class="mec-flash"
     data-flash-end="{$flash_sale_end_timestamp|intval}"
     role="region"
     aria-label="Flash Sale">

  <div class="mec-flash__left">
    <span class="mec-flash__icon" aria-hidden="true">&#9889;</span>
    <div>
      <div class="mec-flash__title">FLASH SALE</div>
      <div class="mec-flash__subtitle">สิ้นสุดใน</div>
    </div>
  </div>

  <div class="mec-flash__divider" aria-hidden="true"></div>

  <div class="mec-flash__timer" aria-live="off">
    <div class="mec-flash__unit">
      <div class="mec-flash__timer-block" data-unit="h">00</div>
      <span class="mec-flash__unit-label">ชม.</span>
    </div>
    <span class="mec-flash__sep" aria-hidden="true">:</span>
    <div class="mec-flash__unit">
      <div class="mec-flash__timer-block" data-unit="m">00</div>
      <span class="mec-flash__unit-label">นาที</span>
    </div>
    <span class="mec-flash__sep" aria-hidden="true">:</span>
    <div class="mec-flash__unit">
      <div class="mec-flash__timer-block" data-unit="s">00</div>
      <span class="mec-flash__unit-label">วินาที</span>
    </div>
  </div>

  {if isset($flash_sale_url) && $flash_sale_url}
    <a class="mec-flash__cta" href="{$flash_sale_url|escape:'html':'UTF-8'}">ดูทั้งหมด</a>
  {/if}

</div>
{/if}

{* ================================================================
   CONTROL BAR — Category filter + Sort
   ================================================================ *}
<div class="mec-control-bar">

  {* Category filter strip *}
  <div class="mec-filter-wrap">
    <nav class="mec-filter-strip" aria-label="หมวดหมู่สินค้า">

      <a href="{$urls.pages.index|default:'/'}"
         class="mec-chip{if !$current_category} mec-chip--active{/if}">
        ทั้งหมด
      </a>

      {foreach from=$categories item=cat}
        <a href="{$cat.url}"
           class="mec-chip{if $current_category == $cat.id_category|intval} mec-chip--active{/if}">
          {$cat.name|escape:'html':'UTF-8'}
        </a>
      {/foreach}

    </nav>
  </div>

  {* Sort strip *}
  <div class="mec-sort-strip">
    <span class="mec-sort-strip__label">เรียงโดย</span>

    <button class="mec-sort-btn{if $orderby == 'sales' && $orderway == 'desc'} mec-sort-btn--active{/if}"
            data-orderby="sales"
            data-orderway="desc"
            type="button"
            aria-pressed="{if $orderby == 'sales' && $orderway == 'desc'}true{else}false{/if}">
      ขายดี
    </button>

    <button class="mec-sort-btn{if $orderby == 'price' && $orderway == 'asc'} mec-sort-btn--active{/if}"
            data-orderby="price"
            data-orderway="asc"
            type="button"
            aria-pressed="{if $orderby == 'price' && $orderway == 'asc'}true{else}false{/if}">
      ราคา &#8593;
    </button>

    <button class="mec-sort-btn{if $orderby == 'price' && $orderway == 'desc'} mec-sort-btn--active{/if}"
            data-orderby="price"
            data-orderway="desc"
            type="button"
            aria-pressed="{if $orderby == 'price' && $orderway == 'desc'}true{else}false{/if}">
      ราคา &#8595;
    </button>

    <button class="mec-sort-btn{if $orderby == 'date_add'} mec-sort-btn--active{/if}"
            data-orderby="date_add"
            data-orderway="desc"
            type="button"
            aria-pressed="{if $orderby == 'date_add'}true{else}false{/if}">
      ใหม่ล่าสุด
    </button>

    <button class="mec-sort-btn{if $orderby == 'name'} mec-sort-btn--active{/if}"
            data-orderby="name"
            data-orderway="asc"
            type="button"
            aria-pressed="{if $orderby == 'name'}true{else}false{/if}">
      A&#8211;Z
    </button>

  </div>

</div>

{* ================================================================
   SECTION HEADER — category name + item count
   ================================================================ *}
<div class="mec-section-header">
  <h2>
    {if $current_category_name}
      {$current_category_name|escape:'html':'UTF-8'}
    {else}
      สินค้าทั้งหมด
    {/if}
  </h2>
  {if isset($pagination) && $pagination.total_items}
    <span class="mec-section-header__count">
      {$pagination.total_items|intval} รายการ
    </span>
  {/if}
</div>
