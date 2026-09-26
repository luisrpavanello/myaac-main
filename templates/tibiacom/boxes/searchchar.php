<style>
    .searchchar{
        width: 180px;
        height: auto;
    }
    .searchchar_header{
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
    .searchchar_bottom{
        height: 30px;
        width: 180px;
        margin-top: 0;
        background-image: url('templates/tibiacom/images/themeboxes/box_bottom.png');
    }
    .searchchar_content{
        box-sizing: border-box;
        padding: 9px 10px 10px;
        width: 160px;
        min-height: 80px;
        height: auto;
        background-image: url('templates/tibiacom/images/themeboxes/box_bg.png');
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .searchchar_text{
        margin-left: 45px;
        font-family: Verdana;
        color: #d5c3af;
        text-align: left;
    }
    .searchchar_button{
        height: 30px;
        width: 148px;
        border: 0;
        background: url('templates/tibiacom/images/themeboxes/button.png');
        font-family: Verdana;
        font-weight: 100;
        color: #d5c3af;
        font-size: 12px;
        cursor: pointer;
        margin-top: 0;
    }
    .searchchar_button:hover{
        background: url('templates/tibiacom/images/themeboxes/button_over.png');
        color: #fff;
    }
    .searchchar_input{
        display: block;
        box-sizing: border-box;
        width: 100%;
        padding: 0.375rem 0.75rem;
        border-radius: 0.25rem;
        font-size: 0.8rem;
        font-weight: 400;
        line-height: 1.5;
        text-align: center;
        border: 0;
    }
</style>
<form method="post" action="<?php echo BASE_URL ?>?characters" style="margin-bottom: 0;">
<div class="searchchar">
    <div class="searchchar_header"><?= htmlspecialchars(t('search.title'), ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="searchchar_content">
        <input type="text" class="searchchar_input" name="name" maxlength="29" placeholder="<?= htmlspecialchars(t('search.placeholder'), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" class="searchchar_button"><?= htmlspecialchars(t('search.submit'), ENT_QUOTES, 'UTF-8'); ?></button>
    </div>
    <div class="searchchar_bottom"></div>
</div>
</form>
