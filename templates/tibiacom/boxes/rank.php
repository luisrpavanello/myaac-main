<?php
global $db, $config;

require_once LIBS . 'ZealotMarket.php';

$topPlayers = getTopPlayers(5);
$outfitRenderer = new ZealotMarket($db, $config);
$appearanceByPlayer = [];
$topPlayerIds = array_values(array_filter(array_map(static fn($player) => (int) ($player['id'] ?? 0), $topPlayers)));
if ($topPlayerIds) {
    $columns = '`id`, `looktype`, `lookhead`, `lookbody`, `looklegs`, `lookfeet`';
    if ($db->hasColumn('players', 'lookaddons')) {
        $columns .= ', `lookaddons`';
    }
    foreach ($db->query('SELECT ' . $columns . ' FROM `players` WHERE `id` IN (' . implode(', ', $topPlayerIds) . ')') as $appearance) {
        $appearanceByPlayer[(int) $appearance['id']] = $appearance;
    }
}

foreach ($topPlayers as &$player) {
    $appearance = $appearanceByPlayer[(int) $player['id']] ?? $player;
    $player['outfit'] = $outfitRenderer->currentOutfitUrl([
        'looktype' => $appearance['looktype'] ?? 0,
        'addons' => $appearance['lookaddons'] ?? 0,
        'head' => $appearance['lookhead'] ?? 0,
        'body' => $appearance['lookbody'] ?? 0,
        'legs' => $appearance['looklegs'] ?? 0,
        'feet' => $appearance['lookfeet'] ?? 0,
    ]) ?? getVocationImage($player['vocation']);
}
unset($player);
?>
<style>
    .rank{
        width: 180px;
        max-height: none;
    }
    .rank_header{
        height: 45px;
        width: 180px;
        background-image: url('templates/tibiacom/images/themeboxes/box_top.png');
        font-family: Verdana;
        font-weight: bold;
        color: #d5c3af;
        line-height: 65px;
    }
    .rank_bottom{
        height: 30px;
        width: 180px;
        margin-top: 0;
        background-image: url('templates/tibiacom/images/themeboxes/box_bottom.png');
    }
    .rank_content{
        box-sizing: border-box;
        padding: 4px 10px 12px;
        width: 160px;
        max-height: none;
        background-image: url('templates/tibiacom/images/themeboxes/box_bg.png');
    }
    .rank_player{
        font-family: Verdana;
        color: #d5c3af;
        text-align: left;
        display: flex;
        align-items: center;
        min-height: 54px;
        gap: 3px;
        padding: 5px 2px;
    }
    .rank_outfit{
        position: relative;
        flex: 0 0 54px;
        width: 54px;
        height: 54px;
        margin: 0 -2px 0 -7px;
        object-fit: contain;
        image-rendering: pixelated;
    }
    .rank_text{
        min-width: 0;
        margin-left: 0;
        line-height: 1.2;
        text-overflow: ellipsis;
        overflow: hidden;
        white-space: nowrap;
    }
    .rank_text a{
        text-decoration: none;
        color: #d5c3af;
    }
    .rank_button{
        height: 30px;
        width: 148px;
        margin: 5px 6px 0;
        border: 0;
        background: url('templates/tibiacom/images/themeboxes/button.png');
        font-family: Verdana;
        font-weight: 100;
        color: #d5c3af;
        font-size: 12px;
        cursor: pointer;
    }
    .rank_button:hover{
        background: url('templates/tibiacom/images/themeboxes/button_over.png');
        color: #fff;
    }
</style>
<div class="rank">
    <div class="rank_header">Highscores</div>
    <div class="rank_content">
        <?php
        foreach($topPlayers as $player){
            $player_voc = $config['vocations'][$player['vocation']] ?? 'Adventurer';
        ?>
        <div class="rank_player">
            <img class="rank_outfit" src="<?php echo htmlspecialchars($player['outfit'], ENT_QUOTES, 'UTF-8') ?>" alt=""/>
            <div class="rank_text">
                <a href="<?php echo getPlayerLink($player['name'], false) ?>"><b><?php echo htmlspecialchars($player['name'], ENT_QUOTES, 'UTF-8') ?></b></a><br>
                <small>Level: <?php echo (int) $player['level'] ?> / <?php echo htmlspecialchars($player_voc, ENT_QUOTES, 'UTF-8') ?></small>
            </div>
        </div>
        <?php } ?>
        <a href="<?php echo BASE_URL ?>?highscores">
            <button type="button" class="rank_button">View Highscores</button>
        </a>
    </div>
    <div class="rank_bottom"></div>
</div>
