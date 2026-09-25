<style>
    .serversave {
        width: 180px;
        height: auto;
        min-height: 0;
        box-sizing: border-box;
    }

    .serversave_header {
        height: 56px;
        min-height: 56px;
        width: 180px;
        box-sizing: border-box;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 5px 10px;
        background-image: url('templates/tibiacom/images/themeboxes/box_top.png');
        font-family: Verdana;
        font-weight: bold;
        color: #d5c3af;
        font-size: 17px;
        line-height: 1.08;
        overflow: hidden;
        text-align: center;
        text-wrap: balance;
    }

    .serversave_bottom {
        height: 12px;
        width: 180px;
        margin-top: 0;
        box-sizing: border-box;
        background-image: url('templates/tibiacom/images/themeboxes/box_bottom.png');
    }

    .serversave_content {
        padding: 9px 10px 10px;
        width: 160px;
        height: auto;
        min-height: 94px;
        box-sizing: content-box;
        background-image: url('templates/tibiacom/images/themeboxes/box_bg.png');
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: stretch;
        justify-content: center;
        gap: 8px;
    }

    .serversave_text {
        font-family: Verdana;
        color: #d5c3af;
        flex: 0 0 auto;
        font-size: 11px !important;
        line-height: 1.2;
        overflow-wrap: anywhere;
    }

    .serversave_text small {
        display: block;
        font-size: inherit;
    }

    .serversave_countdown {
        font-family: Verdana;
        flex: 0 0 auto;
        min-height: 38px;
        box-sizing: border-box;
        padding: 5px 2px;
        font-size: 21px !important;
        font-weight: bold;
        color: #d5c3af;
        border: 1px solid #d5c3af;
        border-radius: 3px;
        line-height: 26px;
        white-space: nowrap;
    }
</style>
<?php
$explodeServerSave = explode(':', configLua('globalServerSaveTime') ?? '05:00:00');
$hours_ServerSave = $explodeServerSave[0];
$minutes_ServerSave = $explodeServerSave[1];
$seconds_ServerSave = $explodeServerSave[2];

$now = new DateTime();
$serverSaveTime = new DateTime();
$serverSaveTime->setTime($hours_ServerSave, $minutes_ServerSave, $seconds_ServerSave);

if ($now > $serverSaveTime) {
    $serverSaveTime->modify('+1 day');
}

$interval = $now->diff($serverSaveTime);
?>
<script>
    var serverSaveTime = new Date(<?= $serverSaveTime->format('Y, n-1, j, G, i, s') ?>);

    var x = setInterval(function () {
        var now = new Date().getTime();
        var distance = serverSaveTime - now;

        var hours = Math.floor(distance / (1000 * 60 * 60));
        var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        var seconds = Math.floor((distance % (1000 * 60)) / 1000);

        // Adiciona zeros à esquerda, se necessário
        hours = hours < 10 ? "0" + hours : hours;
        minutes = minutes < 10 ? "0" + minutes : minutes;
        seconds = seconds < 10 ? "0" + seconds : seconds;

        document.getElementById("timerServerSave").innerHTML = hours + ":" + minutes + ":" + seconds;

        if (distance < 0) {
            clearInterval(x);
            document.getElementById("timerServerSave").innerHTML = <?= json_encode(t('server_save.now'), JSON_UNESCAPED_UNICODE); ?>;
        }
    }, 1000);
</script>
<div class="serversave">
    <div class="serversave_header"><?= htmlspecialchars(t('server_save.title'), ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="serversave_content">
        <div class="serversave_text">
            <small><?= htmlspecialchars(t('server_save.countdown'), ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
        <div class="serversave_countdown" id="timerServerSave"></div>
    </div>
    <div class="serversave_bottom"></div>
</div>
