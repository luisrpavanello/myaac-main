<?php
defined('MYAAC') or die('Direct access not allowed!');
$title = t('download.title', 'Download Client');


$getpage_download = $_GET['step'] ?? '';
$download_subtopic = $_GET['subtopic'] ?? PAGE;
if (empty($getpage_download)) {
    ?>
    <div class="TableContainer">
        <div class="CaptionContainer">
            <div class="CaptionInnerContainer">
                <span class="CaptionEdgeLeftTop"
                      style="background-image:url(<?= $template_path; ?>/images/global/content/box-frame-edge.gif);"></span>
                <span class="CaptionEdgeRightTop"
                      style="background-image:url(<?= $template_path; ?>/images/global/content/box-frame-edge.gif);"></span>
                <span class="CaptionBorderTop"
                      style="background-image:url(<?= $template_path; ?>/images/global/content/table-headline-border.gif);"></span>
                <span class="CaptionVerticalLeft"
                      style="background-image:url(<?= $template_path; ?>/images/global/content/box-frame-vertical.gif);"></span>
                <div class="Text"><?= t('download.title', 'Download Client') ?></div>
                <span class="CaptionVerticalRight"
                      style="background-image:url(<?= $template_path; ?>/images/global/content/box-frame-vertical.gif);"></span>
                <span class="CaptionBorderBottom"
                      style="background-image:url(<?= $template_path; ?>/images/global/content/table-headline-border.gif);"></span>
                <span class="CaptionEdgeLeftBottom"
                      style="background-image:url(<?= $template_path; ?>/images/global/content/box-frame-edge.gif);"></span>
                <span class="CaptionEdgeRightBottom"
                      style="background-image:url(<?= $template_path; ?>/images/global/content/box-frame-edge.gif);"></span>
            </div>
        </div>
        <table class="Table5" cellpadding="0" cellspacing="0">
            <tbody>
            <tr>
                <td>
                    <div class="InnerTableContainer">
                        <table style="width:100%;">
                            <tbody>
                            <tr>
                                <td>
                                    <div class="TableContentContainer">
                                        <table class="TableContent" width="100%" style="border:1px solid #faf0d7;">
                                            <tbody>
                                            <tr>
                                                <td class="zealot-client-download">
                                                    <h1><?= t('download.official_client', 'Official {server} Client', ['server' => configLua('serverName')]) ?></h1>
                                                    <a class="zealot-client-download__link" href="<?= $config['client_link'] ?? '' ?>" target="_new">
                                                        <img alt="<?= t('download.client_alt', '{server} Client', ['server' => configLua('serverName')]) ?>"
                                                             class="zealot-client-download__art"
                                                             src="<?= $template_path ?>/images/zealot-client-download.png">
                                                        <br>
                                                        <span class="zealot-client-download__title">
                                                        <?= t('download.download_client', 'Download {server} Client', ['server' => configLua('serverName')]) ?>
                                                        <br>
                                                        <span class="zealot-client-download__platform" data-platform-label><?= t('download.platform.default', 'Client for your device') ?></span></span>
                                                        <br>
                                                        <small>Version <?= config('client') / 100 ?></small>
                                                    </a>
                                                    <script>
                                                        (() => {
                                                            const source = [
                                                                navigator.userAgentData?.platform,
                                                                navigator.platform,
                                                                navigator.userAgent
                                                            ].filter(Boolean).join(' ').toLowerCase();

                                                            let platform = <?= json_encode(t('download.platform.default', 'Client for your device')) ?>;
                                                            if (source.includes('win')) {
                                                                platform = <?= json_encode(t('download.platform.windows', 'Windows Client')) ?>;
                                                            } else if (source.includes('mac')) {
                                                                platform = <?= json_encode(t('download.platform.macos', 'macOS Client')) ?>;
                                                            } else if (source.includes('linux')) {
                                                                platform = <?= json_encode(t('download.platform.linux', 'Linux Client')) ?>;
                                                            }

                                                            document.querySelectorAll('[data-platform-label]').forEach((element) => {
                                                                element.textContent = platform;
                                                            });
                                                        })();
                                                    </script>
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="TableContentContainer">
                                        <table class="TableContent" width="100%" style="border:1px solid #faf0d7;">
                                            <tbody>
                                            <tr>
                                                <td class="LabelV"><?= t('download.disclaimer', 'Disclaimer') ?></td>
                                            </tr>
                                            <tr>
                                                <td><?= t('download.disclaimer_copy', 'The software and any related documentation is provided "as is" without warranty of any kind. The entire risk arising out of use of the software remains with you. In no event shall Zealot be liable for any damages to your computer or loss of data.') ?>
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </td>
            </tr>
            </tbody>
        </table>
    </div>
    <?php
}

if ($download_subtopic == 'downloadclient' and $getpage_download == 'downloadagreement') {
    ?>
    <p><?= t('download.agreement_intro', 'Before you can download the client program please read the Tibia Service Agreement and state if you agree to it by clicking on the appropriate button below.') ?></p>

    <div class="TableContainer">
        <div class="CaptionContainer">
            <div class="CaptionInnerContainer">
                <span class="CaptionEdgeLeftTop"
                      style="background-image:url(<?= $template_path; ?>/images/global/content/box-frame-edge.gif);"></span>
                <span class="CaptionEdgeRightTop"
                      style="background-image:url(<?= $template_path; ?>/images/global/content/box-frame-edge.gif);"></span>
                <span class="CaptionBorderTop"
                      style="background-image:url(<?= $template_path; ?>/images/global/content/table-headline-border.gif);"></span>
                <span class="CaptionVerticalLeft"
                      style="background-image:url(<?= $template_path; ?>/images/global/content/box-frame-vertical.gif);"></span>
                <div class="Text"><?= t('download.agreement_title', 'Tibia Service Agreement') ?></div>
                <span class="CaptionVerticalRight"
                      style="background-image:url(<?= $template_path; ?>/images/global/content/box-frame-vertical.gif);"></span>
                <span class="CaptionBorderBottom"
                      style="background-image:url(<?= $template_path; ?>/images/global/content/table-headline-border.gif);"></span>
                <span class="CaptionEdgeLeftBottom"
                      style="background-image:url(<?= $template_path; ?>/images/global/content/box-frame-edge.gif);"></span>
                <span class="CaptionEdgeRightBottom"
                      style="background-image:url(<?= $template_path; ?>/images/global/content/box-frame-edge.gif);"></span>
            </div>
        </div>
        <table class="Table1" cellpadding="0" cellspacing="0">
            <tbody>
            <tr>
                <td>
                    <div class="InnerTableContainer"><p><?= t('download.agreement.paragraph_1', 'This agreement describes the terms on which Zealot offers you access to an account for being able to play the online role playing game "Tibia". By creating an account or downloading the client software you accept the terms and conditions below and state that you are of full legal age in your country or have the permission of your parents to play this game.') ?></p>
                        <p><?= t('download.agreement.paragraph_2', 'You agree that the use of the software is at your sole risk. We provide the software, the game, and all other services "as is". We disclaim all warranties or conditions of any kind, expressed, implied or statutory, including without limitation the implied warranties of title, non-infringement, merchantability and fitness for a particular purpose. We do not ensure continuous, error-free, secure or virus-free operation of the software, the game, or your account.') ?></p>
                        <p><?= t('download.agreement.paragraph_3', 'We are not liable for any lost profits or special, incidental or consequential damages arising out of or in connection with the game, including, but not limited to, loss of data, items, accounts, or characters from errors, system downtime, or adjustments of the gameplay.') ?></p>
                        <p><?= t('download.agreement.paragraph_4', 'While you are playing "Tibia", you must abide by some rules ("Tibia Rules") that are stated on this homepage. If you break any of these rules, your account may be removed and all other services terminated immediately.') ?></p>
                        <p><?= t('download.agreement.paragraph_5', 'Zealot is neither willing nor required to take part in out-of-court dispute resolution.') ?></p>
                        <p><?= t('download.agreement.paragraph_6', 'By creating an account or downloading the client software, you also accept the terms and conditions stated in the BattlEye End-User Licence Agreement.') ?></p>
                        <table style="width:100%;"></table>
                    </div>
                </td>
            </tr>
            </tbody>
        </table>
    </div>
    <br>
    <center>
        <form action="?subtopic=downloadclient" method="post" style="padding:0px;margin:0px;">
            <div class="BigButton"
                 style="background-image:url(<?= $template_path; ?>/images/global/buttons/sbutton.gif)">
                <div onmouseover="MouseOverBigButton(this);" onmouseout="MouseOutBigButton(this);">
                    <div class="BigButtonOver"
                         style="background-image:url(<?= $template_path; ?>/images/global/buttons/sbutton_over.gif);"></div>
                    <input class="BigButtonText" type="submit" value="<?= t('download.agree', 'I agree') ?>"></div>
            </div>
        </form>
    </center>
    <?php
}
