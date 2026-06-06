{**
 * MEC — Shopee Orange Product Card
 * ใช้แทน themes/classic/templates/catalog/_partials/miniatures/product.tpl
 *}

{* ---- computed variables ---- *}
{assign var='is_on_sale'   value=($product.discount_percentage && $product.discount_percentage != '0%')}
{assign var='is_soldout'   value=(!$product.available_for_order && !$product.allow_oosp)}
{assign var='show_rating'  value=(isset($product.rating) && $product.rating.total > 0)}

<article class="mec-card{if $is_soldout} mec-card--soldout{/if}"
  itemscope itemtype="https://schema.org/Product"
  data-id-product="{$product.id_product|intval}"
  data-id-product-attribute="{$product.id_product_attribute|intval}">

  {* ================================================================
     IMAGE ZONE
     ================================================================ *}
  <a class="mec-card__img-wrap"
     href="{$product.url}"
     title="{$product.name|escape:'html':'UTF-8'}">

    {* Product image *}
    {if $product.cover.medium.url}
      <img
        class="mec-card__img"
        src="{$product.cover.medium.url}"
        alt="{$product.name|escape:'html':'UTF-8'}"
        itemprop="image"
        loading="lazy"
      />
    {else}
      <div class="mec-card__img--placeholder">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <rect x="3" y="3" width="18" height="18" rx="2"/><path d="m3 9 4-4 4 4 4-4 4 4"/>
          <circle cx="8.5" cy="13.5" r="1.5"/>
        </svg>
      </div>
    {/if}

    {* DISCOUNT BADGE *}
    {if $is_on_sale}
      <span class="mec-badge mec-badge--discount">{$product.discount_percentage|escape:'html':'UTF-8'}</span>
    {/if}

    {* NEW PRODUCT BADGE *}
    {if $product.new}
      <span class="mec-badge mec-badge--new">NEW</span>
    {/if}

    {* HOT SELLER BADGE *}
    {if isset($product.sold_count) && $product.sold_count > 50}
      <span class="mec-badge mec-badge--hot">HOT</span>
    {/if}

    {* SOLD OUT OVERLAY *}
    {if $is_soldout}
      <div class="mec-card__soldout-overlay">
        <span class="mec-card__soldout-label">สินค้าหมด</span>
      </div>
    {/if}

    {* QUICK ADD BUTTON — โผล่ตอน hover, ซ่อนถ้า soldout *}
    {if !$is_soldout}
      <button
        class="mec-card__quick-add js-quick-add"
        data-id-product="{$product.id_product|intval}"
        data-id-product-attribute="{$product.id_product_attribute|intval}"
        aria-label="เพิ่มลงตะกร้า"
        type="button"
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
          <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
        </svg>
        เพิ่มลงตะกร้า
      </button>
    {/if}

  </a>

  {* ================================================================
     INFO ZONE
     ================================================================ *}
  <div class="mec-card__info">

    {* Product Name *}
    <a class="mec-card__name"
       href="{$product.url}"
       title="{$product.name|escape:'html':'UTF-8'}"
       itemprop="name">
      {$product.name|escape:'html':'UTF-8'|truncate:72:'...':true}
    </a>

    {* PRICE BLOCK *}
    <div class="mec-card__price-row" itemprop="offers" itemscope itemtype="https://schema.org/Offer">
      {if $is_on_sale}
        <span class="mec-card__price-sale" itemprop="price"
              content="{$product.price_amount|escape:'html':'UTF-8'}">
          {$product.price|escape:'html':'UTF-8'}
        </span>
        <span class="mec-card__price-original">{$product.regular_price|escape:'html':'UTF-8'}</span>
      {else}
        <span class="mec-card__price-sale" itemprop="price"
              content="{$product.price_amount|escape:'html':'UTF-8'}">
          {$product.price|escape:'html':'UTF-8'}
        </span>
      {/if}
    </div>

    {* Wholesale price (จาก hookDisplayProductMiniatureCustom) *}
    {if isset($product.wholesale_display) && $product.wholesale_display}
      <span class="mec-card__price-ws">ขายส่ง: {$product.wholesale_display|escape:'html':'UTF-8'}</span>
    {/if}

    {* TAGS ROW *}
    <div class="mec-card__tags">
      {if isset($product.additional_shipping_cost) && $product.additional_shipping_cost == 0}
        <span class="mec-tag mec-tag--free">ส่งฟรี</span>
      {/if}
      {if isset($product.is_wholesale) && $product.is_wholesale}
        <span class="mec-tag mec-tag--ws">ขายส่ง</span>
      {/if}
    </div>

    {* RATING + SOLD *}
    {if $show_rating || (isset($product.sold_count) && $product.sold_count > 0)}
      <div class="mec-card__meta">
        {if $show_rating}
          <span class="mec-stars" aria-label="คะแนน {$product.rating.total|intval|escape:'html':'UTF-8'} จาก 5">
            {for $i=1 to 5}
              <i class="mec-star{if $i <= $product.rating.total|round} mec-star--filled{/if}"
                 aria-hidden="true"></i>
            {/for}
            <span class="mec-stars__count">({$product.rating.count|default:0|intval})</span>
          </span>
        {/if}
        {if isset($product.sold_count) && $product.sold_count > 0}
          <span class="mec-card__sold">ขาย {$product.sold_count|intval}</span>
        {/if}
      </div>
    {/if}

    {* WISHLIST *}
    <button class="mec-card__wishlist js-wishlist"
      data-id-product="{$product.id_product|intval}"
      aria-label="บันทึกสินค้า"
      type="button">&#9825;</button>

  </div>

</article>
