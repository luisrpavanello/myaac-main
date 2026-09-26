<style>
    .donate{
        width: 180px;
        height: auto;
    }
    .donate_header{
        min-height: 72px;
        height: auto;
        width: 180px;
        box-sizing: border-box;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 7px 10px;
        background-image: url('templates/tibiacom/images/themeboxes/box_top.png');
        font-family: Verdana;
        font-weight: bold;
        color: #d5c3af;
        line-height: 1.08;
        font-size: 17px;
        overflow-wrap: anywhere;
        text-align: center;
        text-wrap: balance;
    }
    .donate_bottom{
        height: 30px;
        width: 180px;
        margin-top: 0;
        background-image: url('templates/tibiacom/images/themeboxes/box_bottom.png');
    }
    .donate_content{
        box-sizing: border-box;
        padding: 0px 10px;
        width: 160px;
        min-height: 125px;
        height: auto;
        background-image: url('templates/tibiacom/images/themeboxes/box_bg.png');
        display: flex;
        flex-direction: column;
        gap: 6px;
        justify-content: center;
        align-items: center;
    }
    .donate_content img{
        display: block;
        max-width: 160px;
        max-height: 82px;
        width: auto;
        height: auto;
        object-fit: contain;
    }
    .donate_content > .donate_shop_icons{
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0;
        min-height: 42px;
    }
    .donate_content .donate_shop_icons img{
        width: 50px;
        height: 50px;
        max-width: none;
        max-height: none;
        image-rendering: pixelated;
    }
    .donate_content > div,
    .donate_content a{
        display: block;
        line-height: 0;
    }
    .donate_outfit{
        position: absolute;
        width: 64px;
        height: 64px;
        background-position: bottom right;
        left: 10px;
        margin-top: -15px;
    }
    .donate_text{
        margin-left: 45px;
        font-family: Verdana;
        color: #d5c3af;
        text-align: left;
    }
    .donate_button{
        height: 30px;
        width: 148px;
        border: 0;
        background: url('templates/tibiacom/images/themeboxes/button.png');
        font-family: Verdana;
        font-weight: 100;
        color: #d5c3af;
        font-size: 12px;
        cursor: pointer;
    }
    .donate_button:hover{
        background: url('templates/tibiacom/images/themeboxes/button_over.png');
        color: #fff;
    }
</style>
<div class="donate">
    <div class="donate_header"><?= htmlspecialchars(t('payment.title'), ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="donate_content">
        <div class="donate_shop_icons" aria-label="<?= htmlspecialchars(t('payment.shop_icons'), ENT_QUOTES, 'UTF-8'); ?>">
            <img src="<?= $template_path; ?>/images/menu/anim/icon-shops02.gif?v=<?= filemtime(__DIR__ . '/../images/menu/anim/icon-shops02.gif'); ?>" alt="<?= htmlspecialchars(t('payment.shop_alt'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <a href="<?php echo BASE_URL ?>?subtopic=donate&type=coins">
            <button type="button" class="donate_button"><?= htmlspecialchars(t('payment.buy_coins'), ENT_QUOTES, 'UTF-8'); ?></button>
        </a>
    </div>
    <div class="donate_bottom"></div>
</div>
