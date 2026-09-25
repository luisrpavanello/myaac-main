<?php
global $config, $db, $template_path, $logged, $status, $content, $hooks, $twig_loader, $title;

defined('MYAAC') or die('Direct access not allowed!');

//templates\tibiacom\config.ini
if (isset($config['boxes']))
    $config['boxes'] = explode(",", $config['boxes']);
?>
<html xmlns="http://www.w3.org/1999/xhtml" lang="<?= htmlspecialchars(siteLanguage(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <?= template_place_holder('head_start'); ?>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL; ?>images/favicon.ico?v=<?= filemtime(__DIR__ . '/../../images/favicon.ico'); ?>"/>
    <link rel="shortcut icon" type="image/x-icon" href="<?= BASE_URL; ?>images/favicon.ico?v=<?= filemtime(__DIR__ . '/../../images/favicon.ico'); ?>"/>
    <link href="<?= $template_path; ?>/basic.css?v=<?= filemtime(__DIR__ . '/basic.css'); ?>" rel="stylesheet" type="text/css"/>
    <link href="<?= $template_path; ?>/zealot-ui.css?v=<?= filemtime(__DIR__ . '/zealot-ui.css'); ?>" rel="stylesheet" type="text/css"/>

    <script type="text/javascript" src="tools/basic.js"></script>
    <script type="text/javascript" src="<?= $template_path; ?>/ticker.js"></script>
    <script id="twitter-wjs" src="<?= $template_path; ?>/js/twitter.js"></script>
    <script id="facebook-jssdk" async src="https://connect.facebook.net/en_US/all.js"></script>

    <link href="<?= $template_path; ?>/css/facebook.css" rel="stylesheet" type="text/css">

    <link rel="stylesheet" href="tools/fonts/fontawesome/all.css">
    <script src="tools/fonts/fontawesome/all.js"></script>

    <script src="admin/bootstrap/jquery-3.6.0.min.js"></script>
    <script src="admin/bootstrap/popper.min.js"></script>
    <script src="admin/bootstrap/js/bootstrap.min.js"></script>
    <link href="admin/bootstrap/bootstrap-myaac.css" rel="stylesheet" type="text/css">

    <?php if ($config['pace_load']) { ?>
        <script src="admin/bootstrap/pace/pace.js"></script>
        <link
            href="admin/bootstrap/pace/themes/<?= $config['pace_color'] ?>/pace-theme-<?= $config['pace_theme'] ?>.css"
            rel="stylesheet"/>
    <?php } ?>

    <script>
        function CollapseTable(a_ID) {
            $('#' + a_ID).slideToggle('slow');
            if ($('#Indicator_' + a_ID).hasClass('CircleSymbolPlus')) {
                // Do not discard any page-specific marker styling while
                // changing the legacy open/closed state.
                $('#Indicator_' + a_ID).removeClass('CircleSymbolPlus').addClass('CircleSymbolMinus');
                $('#Indicator_' + a_ID).css('background-image', 'url(' + IMAGES + '/global/content/circle-symbol-plus.gif)');
            } else {
                $('#Indicator_' + a_ID).css('background-image', 'url(' + IMAGES + '/global/content/circle-symbol-minus.gif)');
                $('#Indicator_' + a_ID).removeClass('CircleSymbolMinus').addClass('CircleSymbolPlus');
            }
        }
    </script>

    <script type="text/javascript">
        var menus = '';
        var loginStatus = "<?= ($logged ? 'true' : 'false'); ?>";
        <?php
        if (PAGE !== 'news') {
            if (strpos(URI, 'subtopic=') !== false) {
                $tmp = escapeHtml($_REQUEST['subtopic']);
                if ($tmp === 'accountmanagement') {
                    $tmp = 'accountmanage';
                }
            } else {
                $tmp = str_replace('/', '', URI);
                $exp = explode('/', URI);
                if (URI !== 'account/create' && URI !== 'account/lost' && isset($exp[1])) {
                    if ($exp[0] === 'account') {
                        $tmp = 'accountmanage';
                    } else if ($exp[0] === 'news' && $exp[1] === 'archive') {
                        $tmp = 'newsarchive';
                    } else
                        $tmp = $exp[0];
                }
            }
        } else {
            $tmp = 'news';
        }
        ?>
        var activeSubmenuItem = "<?= $tmp; ?>";
        var IMAGES = "<?= $template_path; ?>/images";
        var LINK_ACCOUNT = "<?= BASE_URL; ?>";

        function rowOverEffect(object) {
            if (object.className == 'moduleRow') object.className = 'moduleRowOver';
        }

        function rowOutEffect(object) {
            if (object.className == 'moduleRowOver') object.className = 'moduleRow';
        }

        function InitializePage() {
            LoadLoginBox();
            LoadMenu();
        }

        // initialisation of the loginbox status by the value of the variable 'loginStatus' which is provided to the HTML-document by PHP in the file 'header.inc'
        function LoadLoginBox() {
            if (loginStatus == "false") {
                document.getElementById('ButtonText').style.backgroundImage = "url('" + IMAGES + "/global/buttons/mediumbutton_login.png')";
                document.getElementById('LoginstatusText_2').style.backgroundImage = "url('" + IMAGES + "/loginbox/loginbox-font-create-account.gif')";
                document.getElementById('LoginstatusText_2_1').style.backgroundImage = "url('" + IMAGES + "/loginbox/loginbox-font-create-account.gif')";
                document.getElementById('LoginstatusText_2_2').style.backgroundImage = "url('" + IMAGES + "/loginbox/loginbox-font-create-account-over.gif')";
            } else {
                document.getElementById('ButtonText').style.backgroundImage = "url('" + IMAGES + "/global/buttons/mediumbutton_myaccount.png')";
                document.getElementById('LoginstatusText_2').style.backgroundImage = "url('" + IMAGES + "/loginbox/loginbox-font-logout.gif')";
                document.getElementById('LoginstatusText_2_1').style.backgroundImage = "url('" + IMAGES + "/loginbox/loginbox-font-logout.gif')";
                document.getElementById('LoginstatusText_2_2').style.backgroundImage = "url('" + IMAGES + "/loginbox/loginbox-font-logout-over.gif')";
            }
        }

        function LoginButtonAction() {
            window.location = "<?= getLink('account/manage'); ?>";
        }

        function LoginstatusTextAction(source) {
            if (loginStatus === "false") {
                window.location = "<?= getLink('account/create'); ?>";
            } else {
                window.location = "<?= getLink('account/logout'); ?>";
            }
        }

        var menu = [];
        menu[0] = {};
        var unloadhelper = false;

        // load the menu and set the active submenu item by using the variable 'activeSubmenuItem'
        function LoadMenu() {
            var activeMenuItem = document.getElementById("submenu_" + activeSubmenuItem);
            var activeMenuIcon = document.getElementById("ActiveSubmenuItemIcon_" + activeSubmenuItem);
            if (activeMenuItem) {
                activeMenuItem.style.color = "#E7C47D";
                activeMenuItem.classList.add("zealot-menu-current");
            }
            if (activeMenuIcon) {
                activeMenuIcon.style.visibility = "visible";
            }
            menus = localStorage.getItem('menus');
            if (menus.lastIndexOf("&") === -1) {
                menus = "news=1&account=0&community=0&library=0&forum=0<?php if ($config['gifts_system']) echo '&shops=0'; ?>&charactertrade=0&";
            }
            FillMenuArray();
            InitializeMenu();
        }

        function SaveMenu() {
            if (!unloadhelper) {
                SaveMenuArray();
                unloadhelper = true;
            }
        }

        // store the values of the variable 'self.name' in the array menu
        function FillMenuArray() {
            while (menus.length > 0) {
                var mark1 = menus.indexOf("=");
                var mark2 = menus.indexOf("&");
                var menuItemName = menus.substr(0, mark1);
                menu[0][menuItemName] = menus.substring(mark1 + 1, mark2);
                menus = menus.substr(mark2 + 1, menus.length);
            }
        }

        // hide or show the corresponding submenus
        function InitializeMenu() {
            for (menuItemName in menu[0]) {
                if (menu[0][menuItemName] == "0") {
                    document.getElementById(menuItemName + "_Submenu").style.visibility = "hidden";
                    document.getElementById(menuItemName + "_Submenu").style.display = "none";
                    document.getElementById(menuItemName + "_Lights").style.visibility = "visible";
                    document.getElementById(menuItemName + "_Extend").style.backgroundImage = "url(" + IMAGES + "/general/plus.gif)";
                } else {
                    document.getElementById(menuItemName + "_Submenu").style.visibility = "visible";
                    document.getElementById(menuItemName + "_Submenu").style.display = "block";
                    document.getElementById(menuItemName + "_Lights").style.visibility = "hidden";
                    document.getElementById(menuItemName + "_Extend").style.backgroundImage = "url(" + IMAGES + "/general/minus.gif)";
                }
            }
        }


        function SaveMenuArray() {
            var stringSlices = "";
            var temp = "";

            for (menuItemName in menu[0]) {
                stringSlices = menuItemName + "=" + menu[0][menuItemName] + "&";
                temp = temp + stringSlices;
            }

            localStorage.setItem('menus', temp);
        }

        // onClick open or close submenus
        function MenuItemAction(sourceId) {
            if (menu[0][sourceId] == 1) {
                CloseMenuItem(sourceId);
            } else {
                $.each(menu[0], function (index, value) {
                    if (value === '1') {
                        CloseMenuItem(index);
                    }
                });
                OpenMenuItem(sourceId);
            }
        }

        function OpenMenuItem(sourceId) {
            menu[0][sourceId] = 1;
            document.getElementById(sourceId + "_Submenu").style.visibility = "visible";
            document.getElementById(sourceId + "_Extend").style.backgroundImage = "url(" + IMAGES + "global/general/minus.gif)";
            document.getElementById(sourceId + "_Lights").style.visibility = "hidden";
            $('#' + sourceId + '_Submenu').slideDown('slow');
            //document.getElementById(sourceId+"_Submenu").style.visibility = "visible";
            //document.getElementById(sourceId+"_Submenu").style.display = "block";
            //document.getElementById(sourceId+"_Lights").style.visibility = "hidden";
            document.getElementById(sourceId + "_Extend").style.backgroundImage = "url(" + IMAGES + "/general/minus.gif)";
        }

        function CloseMenuItem(sourceId) {
            menu[0][sourceId] = 0;
            document.getElementById(sourceId + "_Lights").style.visibility = "visible";
            document.getElementById(sourceId + "_Extend").style.backgroundImage = "url(" + IMAGES + "global/general/plus.gif)";
            $('#' + sourceId + '_Submenu').slideUp('fast', function () {
                document.getElementById(sourceId + "_Submenu").style.visibility = "hidden";
            });
            //document.getElementById(sourceId+"_Submenu").style.visibility = "hidden";
            //document.getElementById(sourceId+"_Submenu").style.display = "none";
            //document.getElementById(sourceId+"_Lights").style.visibility = "visible";
            document.getElementById(sourceId + "_Extend").style.backgroundImage = "url(" + IMAGES + "/general/plus.gif)";
        }

        // mouse-over effects of menubuttons and submenuitems
        function MouseOverMenuItem(source) {
            if (source.firstChild.style) {
                source.firstChild.style.visibility = "visible";
            }
        }

        function MouseOutMenuItem(source) {
            if (source.firstChild.style) {
                source.firstChild.style.visibility = "hidden";
            }
        }

        function MouseOverSubmenuItem(source) {
            if (source.style) {
                source.style.backgroundColor = "#285A45";
            }
        }

        function MouseOutSubmenuItem(source) {
            if (source.style) {
                source.style.backgroundColor = source.classList.contains("zealot-menu-current") ? "#234B3B" : "#183A2E";
            }
        }
    </script>
    <?= template_place_holder('head_end'); ?>
</head>
<body onBeforeUnLoad="SaveMenu();" onUnload="SaveMenu();" style="background-color:#0b1514;
    background-image:url(<?= $template_path ?>/images/themeboxes/box_bg.png);
    background-size: auto;
    background-position: top left;
    background-repeat: repeat;
    background-attachment: fixed;
    width: 100%;
    height: 100%;
    ">
<?= template_place_holder('body_start'); ?>
<?php if (!empty($config['network_facebook'])) { ?>
    <script type="text/javascript">
        window.fbAsyncInit = function () {
            FB.init({
                appId: 497232093667125, // App ID
                status: true,              // check login status
                cookie: true,              // enable cookies to allow the server to access the session
                xfbml: true               // parse XFBML
            });
            FB.Event.subscribe('auth.login', function () {
                var URLHelper = "?";
                if (window.location.search.replace("?", "").length > 0) {
                    URLHelper = "&";
                }
                if (FB_TryLogin == 1) {
                    window.location = window.location + URLHelper + "step=facebooktrylogin&wasreloaded=1";
                } else if (FB_TryLogin == 2) {
                    window.location = window.location + URLHelper + "page=facebooktrylogin&wasreloaded=1";
                } else {
                    window.location = window.location + URLHelper + "wasreloaded=1";
                }
            });
            FB.Event.subscribe('auth.logout', function (a_Response) {
                if (a_Response.status !== 'connected') {
                    window.location.href = window.location.href;
                } else {
                    /* nothing to do here*/
                }
            });
            FB.Event.subscribe('auth.statusChange', function (response) {
                if (FB_ForceReload == 1 && response.status == "connected") {
                    var URLHelper = "?";
                    if (window.location.search.replace("?", "").length > 0) {
                        URLHelper = "&";
                    }
                    window.location = window.location + URLHelper + "step=facebooktrylogin&wasreloaded=1";
                }
            });
        };
        (function (d) {
            var js, id = 'facebook-jssdk', ref = d.getElementsByTagName('script')[0];
            if (d.getElementById(id)) {
                return;
            }
            js = d.createElement('script');
            js.id = id;
            js.async = true;
            js.src = "//connect.facebook.net/en_US/all.js";
            ref.parentNode.insertBefore(js, ref);
        }(document));
    </script>
<?php } ?>
<div id="top"></div>
<div id="ArtworkHelper">
    <div id="Bodycontainer">
        <div id="ContentRow">
            <div id="MenuColumn">
                <div id="LeftArtwork">
                    <img id="TibiaLogoArtworkTop"
                         src="<?= $template_path; ?>/images/header/<?= $config['logo_image']; ?>"
                         onClick="window.location = '<?= getLink('news') ?>';" alt="logoartwork"/>
                    <!-- <img id="LogoLink" src="<?= $template_path; ?>/images/header/tibia-logo-artwork-string.gif"
                         onClick="window.location = 'mailto:<?= $config['mail_address']; ?>';" alt="logoartwork"/> -->
                </div>

                <div id="Loginbox">
                    <div id="LoginTop"
                         style="background-image:url(<?= $template_path; ?>/images/general/box-top.gif)"></div>
                    <div id="BorderLeft" class="LoginBorder"
                         style="background-image:url(<?= $template_path; ?>/images/general/chain.gif)"></div>


                    <div id="LoginButtonContainer"
                         style="background-image:url(<?= $template_path; ?>/images/loginbox/loginbox-textfield-background.gif)">
                        <div id="LoginButton"
                             style="background-image:url(<?= $template_path; ?>/images/global/buttons/mediumbutton.gif)">
                            <div onClick="LoginButtonAction();" onMouseOver="MouseOverBigButton('LoginButtonOver');"
                                 onMouseOut="MouseOutBigButton('LoginButtonOver');">
                                <div id="LoginButtonOver" class="Button"
                                     style="background-image:url(<?= $template_path; ?>/images/global/buttons/mediumbutton-over.gif); visibility: hidden;"></div>
                                <div id="ButtonText" data-zealot-label="<?= htmlspecialchars(t('nav.account'), ENT_QUOTES, 'UTF-8'); ?>" <?= !$logged ? "style='background-image:url(\"$template_path/images/global/buttons/mediumbutton_login.png\")'" : '' ?>></div>
                            </div>
                        </div>

                    </div>

                    <div style="clear:both"></div>

                    <div class="Loginstatus"
                         style="background-image:url(<?= $template_path; ?>/images/loginbox/loginbox-textfield-background.gif)">
                        <div id="LoginstatusText_2" data-zealot-label="<?= htmlspecialchars($logged ? t('nav.logout', 'Logout') : t('account.create', 'Create account'), ENT_QUOTES, 'UTF-8'); ?>" onClick="LoginstatusTextAction(this);"
                             onMouseOver="MouseOverLoginBoxText(this);" onMouseOut="MouseOutLoginBoxText(this);">
                            <div id="LoginstatusText_2_1" class="LoginstatusText"
                                 style="background-image:url(<?= $template_path; ?>/images/loginbox/loginbox-font-create-account.gif)"></div>
                            <div id="LoginstatusText_2_2" class="LoginstatusText"
                                 style="background-image:url(<?= $template_path; ?>/images/loginbox/loginbox-font-create-account-over.gif)"></div>
                        </div>
                    </div>

                    <div id="BorderRight" class="LoginBorder"
                         style="background-image:url(<?= $template_path; ?>/images/general/chain.gif)"></div>
                    <div id="LoginBottom" class="Loginstatus"
                         style="background-image:url(<?= $template_path; ?>/images/general/box-bottom.gif)"></div>
                </div>

                <div class="SmallMenuBox" id="DownloadBox">
                    <div class="SmallBoxTop"
                         style="background-image:url(<?= $template_path; ?>/images/global/general/box-top.gif)"></div>
                    <div class="SmallBoxBorder"
                         style="background-image:url(<?= $template_path; ?>/images/global/general/chain.gif);"></div>
                    <div class="SmallBoxButtonContainer"
                         style="background-image:url(<?= $template_path; ?>/images/global/loginbox/loginbox-textfield-background.gif)">
                        <a href="?subtopic=downloadclient&step=downloadagreement">
                            <div id="PlayNowContainer">
                                <div class="MediumButtonBackground zealot-medium-button--download" data-zealot-label="<?= htmlspecialchars(t('download.title', 'Download Client'), ENT_QUOTES, 'UTF-8'); ?>"
                                     style="background-image:url(<?= $template_path; ?>/images/global/buttons/mediumbutton.gif)"
                                     onmouseover="MouseOverBigButton('DownloadButtonOver');"
                                     onmouseout="MouseOutBigButton('DownloadButtonOver');">
                                    <div id="DownloadButtonOver" class="MediumButtonOver"
                                         style="background-image: url(<?= $template_path; ?>/images/global/buttons/mediumbutton-over.gif); visibility: hidden;"></div>
                                    <input class="MediumButtonText" type="image" name="Download" alt="<?= htmlspecialchars(t('download.title', 'Download Client'), ENT_QUOTES, 'UTF-8'); ?>"
                                           src="<?= $template_path; ?>/images/global/buttons/mediumbutton_download.png">
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="SmallBoxBorder BorderRight"
                         style="background-image:url(<?= $template_path; ?>/images/global/general/chain.gif);"></div>
                    <div class="Loginstatus SmallBoxBottom"
                         style="background-image:url(<?= $template_path; ?>/images/global/general/box-bottom.gif);"></div>
                </div>

                <div id='Menu'>
                    <div id='MenuTop'
                         style='background-image:url(<?= $template_path; ?>/images/general/box-top.gif);'></div>

                    <?php
                    $menus = get_template_menus();

                    foreach ($config['menu_categories'] as $id => $cat) {
                        if (!isset($menus[$id]) || ($id == MENU_CATEGORY_SHOP && !$config['gifts_system'])) {
                            continue;
                        }
                        ?>
                        <div id='<?= $cat['id']; ?>' class='menuitem'>
                            <span onClick="MenuItemAction('<?= $cat['id']; ?>')">
                                <div class='MenuButton'
                                     style='background-image:url(<?= $template_path ?>/images/menu/button-background.gif);'>
                                    <div onMouseOver='MouseOverMenuItem(this);' onMouseOut='MouseOutMenuItem(this);'><div
                                                class='Button'
                                                style='background-image:url(<?= $template_path; ?>/images/menu/button-background-over.gif);'></div>
                                        <span id='<?= $cat['id']; ?>_Lights' class='Lights'>
                                            <div class='light_lu'
                                                 style='background-image:url(<?= $template_path; ?>/images/menu/green-light.gif);'></div>
                                            <div class='light_ld'
                                                 style='background-image:url(<?= $template_path; ?>/images/menu/green-light.gif);'></div>
                                            <div class='light_ru'
                                                 style='background-image:url(<?= $template_path; ?>/images/menu/green-light.gif);'></div>
                                        </span>
                                        <div id='<?= $cat['id']; ?>_Icon' class='Icon'
                                             style='background-image:url(<?= $template_path ?><?= getImageMenuRandom($cat['id']) ?>);'></div>
                                        <div id='<?= $cat['id']; ?>_Label' class='Label zealot-menu-category-label'><?= htmlspecialchars($cat['name']); ?></div>
                                        <div id='<?= $cat['id']; ?>_Extend' class='Extend'
                                             style='background-image:url(<?= $template_path; ?>/images/general/plus.gif);'></div>
                                    </div>
                                </div>
                            </span>
                            <div id='<?= $cat['id']; ?>_Submenu' class='Submenu'>
                                <?php
                                $default_menu_color = "ffffff";

                                foreach ($menus[$id] as $category => $menu) {
                                    $link_color = '#' . (strlen($menu['color']) == 0 ? $default_menu_color : $menu['color']);
                                    ?>
                                    <a href='<?= $menu['link_full']; ?>'<?= $menu['blank'] ? ' target="_blank"' : '' ?>>
                                        <div id='submenu_<?= str_replace('/', '', $menu['link']); ?>'
                                             class='Submenuitem' onMouseOver='MouseOverSubmenuItem(this)'
                                             onMouseOut='MouseOutSubmenuItem(this)' style="color: <?= $link_color; ?>;">
                                            <div class='LeftChain'
                                                 style='background-image:url(<?= $template_path; ?>/images/general/chain.gif);'></div>
                                            <div id='ActiveSubmenuItemIcon_<?= str_replace('/', '', $menu['link']); ?>'
                                                 class='ActiveSubmenuItemIcon'
                                                 style='background-image:url(<?= $template_path; ?>/images/menu/icon-activesubmenu.gif);'></div>
                                            <div class='SubmenuitemLabel'
                                                 style="color: <?= $link_color; ?>;"><?= $menu['name']; ?></div>
                                            <div class='RightChain'
                                                 style='background-image:url(<?= $template_path; ?>/images/general/chain.gif);'></div>
                                        </div>
                                    </a>
                                    <?php
                                }
                                ?>
                            </div>
                            <?php
                            if ($id == MENU_CATEGORY_SHOP || (!$config['gifts_system'] && $id == MENU_CATEGORY_SHOP - 1)) {
                                ?>
                                <div id='MenuBottom'
                                     style='background-image:url(<?= $template_path; ?>/images/general/box-bottom.gif);'></div>
                                <?php
                            }
                            ?>
                        </div>
                        <?php
                    }
                    ?>
                    <script type="text/javascript">
                        InitializePage();
                    </script>
                </div>
            </div>

            <div id="ContentColumn">
                <div class="Content">

                    <?php if ($config['status_bar']) { ?>
                        <div class="Box zealot-status-shell">
                            <div class="Corner-tl"
                                 style="background-image:url(<?= $template_path; ?>/images/global/content/corner-tl.gif);"></div>
                            <div class="Corner-tr"
                                 style="background-image:url(<?= $template_path; ?>/images/global/content/corner-tr.gif);"></div>
                            <div class="Border_1"
                                 style="background-image:url(<?= $template_path; ?>/images/global/content/border-1.gif);"></div>
                            <div class="BorderTitleText zealot-status-bar"
                                 style="background-image:url(<?= $template_path; ?>/images/global/content/newsheadline_background.gif); height: 28px;">
                                <div class="InfoBar">
                                    <div class="zealot-quick-actions">
                                        <a class="zealot-quick-action" href="?subtopic=downloadclient">
                                            <svg class="zealot-quick-action__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <path d="M12 3v11m0 0 4-4m-4 4-4-4M5 19h14"/>
                                            </svg>
                                            <span><?= htmlspecialchars(t('quick.download_client'), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </a>

                                        <?php if (!empty($config['discord_link'])) { ?>
                                            <a class="zealot-quick-action" href="<?= $config['discord_link']; ?>" target="new" rel="noopener">
                                                <svg class="zealot-quick-action__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <path d="M6 7.5c1.7-1.2 3.7-1.8 6-1.8s4.3.6 6 1.8c.8 1.8 1.2 3.8 1.2 6-1.1 1-2.5 1.7-4.2 2.1l-1-1.4c-1.3.2-2.7.2-4 0l-1 1.4c-1.7-.4-3.1-1.1-4.2-2.1 0-2.2.4-4.2 1.2-6Z"/>
                                                    <path d="M9.5 10.8h.01M14.5 10.8h.01"/>
                                                </svg>
                                                <span>Discord</span>
                                            </a>
                                        <?php } ?>
                                        <nav class="zealot-language-switcher" aria-label="<?= htmlspecialchars(t('language.label'), ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php foreach (siteLanguages() as $languageCode => $language): ?>
                                                <a class="zealot-language-option<?= siteLanguage() === $languageCode ? ' is-active' : ''; ?>"
                                                   href="<?= htmlspecialchars(siteLanguageUrl($languageCode), ENT_QUOTES, 'UTF-8'); ?>"
                                                   hreflang="<?= htmlspecialchars($languageCode, ENT_QUOTES, 'UTF-8'); ?>"
                                                   lang="<?= htmlspecialchars($languageCode, ENT_QUOTES, 'UTF-8'); ?>"
                                                   title="<?= htmlspecialchars(t('language.' . $languageCode), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <span aria-hidden="true"><?= $language['flag']; ?></span>
                                                    <span class="zealot-language-option__label"><?= htmlspecialchars(t('language.' . $languageCode), ENT_QUOTES, 'UTF-8'); ?></span>
                                                </a>
                                            <?php endforeach; ?>
                                        </nav>
                                    </div>
                                    <span class="zealot-status-toggle-wrap">
                                        <?php if ($config['collapse_status']) { ?>
                                            <a class="zealot-status-toggle" data-bs-toggle="collapse" href="#statusbar" role="button" aria-expanded="false" aria-controls="statusbar" aria-label="<?= htmlspecialchars(t('quick.show_status'), ENT_QUOTES, 'UTF-8'); ?>">
                                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m7 10 5 5 5-5"/></svg>
                                            </a>
                                        <?php } ?>
                                    </span>
                                </div>
                            </div>
                            <!-- COLLAPSE STATUS BAR -->
                            <?php if ($config['collapse_status']) { ?>
                                <div class="collapse" id="statusbar" style="background-color: #d4c0a1;">
                                    <table class="Table3" cellpadding="0" cellspacing="0" style="width: 100%;">
                                        <tbody>
                                        <tr>
                                            <td>
                                                <div class="InnerTableContainer"
                                                     style="display: flex; flex-wrap: wrap; font-family: Verdana;">
                                                    <?php if ($config['carousel_status']) { ?>
                                                        <table style="width:100%;">
                                                            <tbody>
                                                            <tr>
                                                                <td>
                                                                    <div class="TableContentContainer">
                                                                        <table class="TableContent" width="100%"
                                                                               style="border:1px solid #faf0d7; font-size: 12px;">
                                                                            <tbody>
                                                                            <tr bgcolor="#F1E0C6">
                                                                                <td>
                                                                                    <div class="container">
                                                                                        <div
                                                                                            id="carouselExampleCaptions"
                                                                                            class="carousel slide"
                                                                                            data-bs-ride="carousel">
                                                                                            <div class="carousel-inner">
                                                                                                <?php
                                                                                                $count = 1;
                                                                                                foreach ($config['carousel'] as $carousel) {
                                                                                                    if ($count == 1) {
                                                                                                        ?>
                                                                                                        <div
                                                                                                            class="carousel-item active">
                                                                                                            <img
                                                                                                                src="<?= $template_path ?>/images/carousel/<?= $carousel ?>"
                                                                                                                style="width: 100%;">
                                                                                                        </div>
                                                                                                        <?php
                                                                                                    } elseif ($count > 1) {
                                                                                                        ?>
                                                                                                        <div
                                                                                                            class="carousel-item">
                                                                                                            <img
                                                                                                                src="<?= $template_path ?>/images/carousel/<?= $carousel ?>"
                                                                                                                style="width: 100%;">
                                                                                                        </div>
                                                                                                        <?php
                                                                                                    }
                                                                                                    $count++;
                                                                                                }
                                                                                                ?>
                                                                                            </div>
                                                                                            <button
                                                                                                class="carousel-control-prev"
                                                                                                type="button"
                                                                                                data-bs-target="#carouselExampleCaptions"
                                                                                                data-bs-slide="prev">
                                                                                                <span
                                                                                                    class="carousel-control-prev-icon"
                                                                                                    aria-hidden="true"></span>
                                                                                            </button>
                                                                                            <button
                                                                                                class="carousel-control-next"
                                                                                                type="button"
                                                                                                data-bs-target="#carouselExampleCaptions"
                                                                                                data-bs-slide="next">
                                                                                                <span
                                                                                                    class="carousel-control-next-icon"
                                                                                                    aria-hidden="true"></span>
                                                                                            </button>
                                                                                        </div>
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
                                                    <?php } ?>
                                                </div>
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            <?php } ?>
                            <!-- COLLAPSE STATUS BAR -->
                            <div class="Border_1"
                                 style="background-image:url(<?= $template_path; ?>/images/global/content/border-1.gif);"></div>
                            <div class="CornerWrapper-b">
                                <div class="Corner-bl"
                                     style="background-image:url(<?= $template_path; ?>/images/global/content/corner-bl.gif);"></div>
                            </div>
                            <div class="CornerWrapper-b">
                                <div class="Corner-br"
                                     style="background-image:url(<?= $template_path; ?>/images/global/content/corner-br.gif);"></div>
                            </div>
                        </div>
                    <?php } ?>

                    <div id="ContentHelper">
                        <?= tickers(); ?>
                        <div id="<?= PAGE; ?>" class="Box">
                            <div class="Corner-tl"
                                 style="background-image:url(<?= $template_path; ?>/images/content/corner-tl.gif);"></div>
                            <div class="Corner-tr"
                                 style="background-image:url(<?= $template_path; ?>/images/content/corner-tr.gif);"></div>
                            <div class="Border_1"
                                 style="background-image:url(<?= $template_path; ?>/images/content/border-1.gif);"></div>
                            <div class="BorderTitleText"
                                 style="background-image:url(<?= $template_path; ?>/images/content/title-background-green.gif);"></div>
                            <div class="Title TitleText"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="Border_2">
                                <div class="Border_3">
                                    <?php $hooks->trigger(HOOK_TIBIACOM_BORDER_3); ?>
                                    <div class="BoxContent"
                                         style="background-image:url(<?= $template_path; ?>/images/content/scroll.gif);">
                                        <?= template_place_holder('center_top') . $content; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="Border_1"
                                 style="background-image:url(<?= $template_path; ?>/images/content/border-1.gif);"></div>

                            <div class="CornerWrapper-b">
                                <div class="Corner-bl"
                                     style="background-image:url(<?= $template_path; ?>/images/content/corner-bl.gif);"></div>
                            </div>
                            <div class="CornerWrapper-b">
                                <div class="Corner-br"
                                     style="background-image:url(<?= $template_path; ?>/images/content/corner-br.gif);"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="Footer"><?= template_footer(); ?></div>
            </div>

            <div id="ThemeboxesColumn">
                <?php
                $creaturequery = $db->query("SELECT `boostname`, `looktype` FROM `boosted_creature`")->fetch();
                $creaturename = $creaturequery ? $creaturequery['boostname'] : 'Boosted Creature';
                $creatureimage = getDailyBoostSpriteUrl($creaturename, $creaturequery['looktype'] ?? null);
                $creatureimageurl = $creatureimage === null ? null : $creatureimage . '?v=' . filemtime(BASE . $creatureimage);
                $creatureimagesize = getDailyBoostSpriteDisplaySize($creatureimage);

                $bossquery = $db->query("SELECT `boostname`, `looktype`, `looktypeEx` FROM `boosted_boss`")->fetch();
                $bossname = $bossquery ? $bossquery['boostname'] : 'Boosted Boss';
                $bossimage = getDailyBoostSpriteUrl($bossname, $bossquery['looktype'] ?? null, $bossquery['looktypeEx'] ?? null);
                $bossimageurl = $bossimage === null ? null : $bossimage . '?v=' . filemtime(BASE . $bossimage);
                $bossimagesize = getDailyBoostSpriteDisplaySize($bossimage);

                $onlinePlayers = (int)($status['playersTotal'] ?? $status['players'] ?? 0);
                if ($db->hasTable('players_online')) {
                    $onlineQuery = $db->query('SELECT COUNT(`player_id`) AS `playersOnline` FROM `players_online`;')->fetch();
                    $onlinePlayers = (int)($onlineQuery['playersOnline'] ?? $onlinePlayers);
                } elseif ($db->hasColumn('players', 'online')) {
                    $onlineQuery = $db->query('SELECT COUNT(`id`) AS `playersOnline` FROM `players` WHERE `online` > 0;')->fetch();
                    $onlinePlayers = (int)($onlineQuery['playersOnline'] ?? $onlinePlayers);
                }

                $playersOnlineLabel = $onlinePlayers === 1
                    ? t('daily.player_online', null, ['count' => $onlinePlayers])
                    : t('daily.players_online', null, ['count' => $onlinePlayers]);
                ?>
                <div id="RightArtwork">
                    <section id="DailyHighlights" aria-label="<?= htmlspecialchars(t('daily.title'), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="DailyHighlightsTitle"><?= htmlspecialchars(t('daily.title'), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="DailyBoostStages">
                            <a class="DailyBoostStage" href="?subtopic=killstatistics"
                               title="<?= htmlspecialchars(t('daily.boosted_creature', null, ['name' => ucwords(strtolower(trim($creaturename)))]), ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="DailyBoostSprite<?= $creatureimage === null ? ' DailyBoostSprite--missing' : ''; ?>" style="--daily-boost-sprite-size: <?= $creatureimagesize; ?>px;">
                                    <?php if ($creatureimage !== null): ?>
                                    <img src="<?= htmlspecialchars($creatureimageurl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars(ucwords(strtolower(trim($creaturename))), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php else: ?>
                                    <span class="DailyBoostSpritePlaceholder" aria-label="<?= htmlspecialchars(t('daily.sprite_unavailable'), ENT_QUOTES, 'UTF-8'); ?>">?</span>
                                    <?php endif; ?>
                                </span>
                                <span class="DailyBoostName"><?= htmlspecialchars(ucwords(strtolower(trim($creaturename))), ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="DailyBoostType"><?= htmlspecialchars(t('daily.creature'), ENT_QUOTES, 'UTF-8'); ?></span>
                            </a>
                            <a class="DailyBoostStage" href="?subtopic=killstatistics"
                               title="<?= htmlspecialchars(t('daily.boosted_boss', null, ['name' => ucwords(strtolower(trim($bossname)))]), ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="DailyBoostSprite<?= $bossimage === null ? ' DailyBoostSprite--missing' : ''; ?>" style="--daily-boost-sprite-size: <?= $bossimagesize; ?>px;">
                                    <?php if ($bossimage !== null): ?>
                                    <img src="<?= htmlspecialchars($bossimageurl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars(ucwords(strtolower(trim($bossname))), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php else: ?>
                                    <span class="DailyBoostSpritePlaceholder" aria-label="<?= htmlspecialchars(t('daily.sprite_unavailable'), ENT_QUOTES, 'UTF-8'); ?>">?</span>
                                    <?php endif; ?>
                                </span>
                                <span class="DailyBoostName"><?= htmlspecialchars(ucwords(strtolower(trim($bossname))), ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="DailyBoostType"><?= htmlspecialchars(t('daily.boss'), ENT_QUOTES, 'UTF-8'); ?></span>
                            </a>
                        </div>
                        <a id="PlayersOnline" href="?online"
                           data-online-count-endpoint="<?= htmlspecialchars(BASE_URL . 'tools/players_online.php', ENT_QUOTES, 'UTF-8'); ?>"
                           data-online-count="<?= $onlinePlayers; ?>"
                           aria-live="polite"><?= htmlspecialchars($playersOnlineLabel, ENT_QUOTES, 'UTF-8'); ?></a>
                    </section>
                </div>

                <div id="Themeboxes">
                    <?php
                    $twig_loader->prependPath(__DIR__ . '/boxes/templates');

                    foreach ($config['boxes'] as $box) {
                        /** @var string $template_name */
                        $file = TEMPLATES . $template_name . '/boxes/' . $box . '.php';
                        if (file_exists($file)) {
                            include($file); ?>
                            <?php
                        }
                    }
                    if ($config['template_allow_change'])
                        echo '<span style="color: white">Template:</span><br/>' . template_form();
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?= template_place_holder('body_end'); ?>

<script>
    $(document).ready(function () {
        //Check to see if the window is top if not then display button
        $(window).scroll(function () {
            if ($(this).scrollTop() > 100) {
                $('.scrollToTop').fadeIn();
            } else {
                $('.scrollToTop').fadeOut();
            }
        });
        //Click event to scroll to top
        $('.scrollToTop').click(function () {
            $('html, body').animate({scrollTop: 0}, 800);
            return false;
        });

        // Keep every section caption visible. The control lives in that caption
        // and only collapses the body below it, never the section title itself.
        $('.TopButtonContainer .TopButton a').each(function (index) {
            const $trigger = $(this);
            const $control = $trigger.closest('.TopButtonContainer');
            const $content = $control.next('.TableContainer, table');
            const $panel = $content.hasClass('TableContainer')
                ? $content
                : $content.find('.TableContainer').first();
            const $caption = $panel.children('.CaptionContainer').first();
            const $body = $panel.children().not('.CaptionContainer');

            if (!$panel.length || !$caption.length || !$body.length) {
                return;
            }

            const sectionId = 'zealot-collapsible-section-' + index;
            $body.attr('id', sectionId);
            $control.appendTo($caption.find('.CaptionInnerContainer').first());
            $trigger
                .attr('href', '#' + sectionId)
                .attr('role', 'button')
                .attr('title', 'Recolher seção')
                .attr('aria-label', 'Recolher seção')
                .attr('aria-controls', sectionId)
                .attr('aria-expanded', 'true')
                .on('click', function (event) {
                    event.preventDefault();
                    const isExpanded = $trigger.attr('aria-expanded') === 'true';
                    $body.stop(true, true).slideToggle(160);
                    $trigger
                        .attr('aria-expanded', String(!isExpanded))
                        .attr('title', isExpanded ? 'Expandir seção' : 'Recolher seção')
                        .attr('aria-label', isExpanded ? 'Expandir seção' : 'Recolher seção')
                        .toggleClass('is-collapsed', isExpanded);
                });
        });

    });
</script>
<button class="scrollToTop" type="button" title="<?= htmlspecialchars(t('scroll_to_top'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?= htmlspecialchars(t('scroll_to_top'), ENT_QUOTES, 'UTF-8'); ?>">
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 19V5m0 0-5 5m5-5 5 5"/></svg>
</button>
<script>
    // The Daily Boosts card must not wait for a full-page refresh. This small,
    // same-origin request reads the game-maintained online list directly and
    // deliberately bypasses browser/proxy caches.
    (function () {
        const counter = document.getElementById('PlayersOnline');
        if (!counter || !window.fetch || !counter.dataset.onlineCountEndpoint) {
            return;
        }

        let isRefreshing = false;
        const setCount = function (players) {
            if (!Number.isInteger(players) || players < 0) {
                return;
            }

            const label = players === 1
                ? <?= json_encode(t('daily.player_online', null, ['count' => '{count}']), JSON_UNESCAPED_UNICODE); ?>.replace('{count}', players)
                : <?= json_encode(t('daily.players_online', null, ['count' => '{count}']), JSON_UNESCAPED_UNICODE); ?>.replace('{count}', players);
            counter.textContent = label;
            counter.dataset.onlineCount = String(players);
            counter.title = <?= json_encode(t('online.live_updated'), JSON_UNESCAPED_UNICODE); ?>;
        };

        const refreshCount = function () {
            if (isRefreshing || document.hidden) {
                return;
            }

            isRefreshing = true;
            const separator = counter.dataset.onlineCountEndpoint.indexOf('?') === -1 ? '?' : '&';
            fetch(counter.dataset.onlineCountEndpoint + separator + 't=' + Date.now(), {
                cache: 'no-store',
                credentials: 'same-origin',
                headers: {'Accept': 'application/json'}
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Online count request failed.');
                    }
                    return response.json();
                })
                .then(function (data) {
                    setCount(data.players);
                })
                .catch(function () {
                    // Keep the last known total visible if the site is briefly
                    // unavailable; the next polling cycle retries silently.
                })
                .finally(function () {
                    isRefreshing = false;
                });
        };

        refreshCount();
        window.setInterval(refreshCount, 5000);
        window.addEventListener('focus', refreshCount);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                refreshCount();
            }
        });
    }());
</script>
<script src="<?= $template_path; ?>/js/generic.js"></script>
<div id="HelperDivContainer"
     style="background-image: url(<?= $template_path; ?>/images/global/content/scroll.gif);">
    <div class="HelperDivArrow"
         style="background-image: url(<?= $template_path; ?>/images/global/content/helper-div-arrow.png);"></div>
    <div id="HelperDivHeadline"></div>
    <div id="HelperDivText"></div>
    <center><img class="Ornament" src="<?= $template_path; ?>/images/global/content/ornament.gif"></center>
    <br>
</div>

</body>
</html>
<?php

/**
 * @param $menu
 * @return string
 */
function getImageMenuRandom($menu): string
{
    global $config;
    if (!$config['allow_menu_animated']) {
        return $menu === 'bgs' ? "/images/header/{$config['background_image']}" : "/images/menu/icon-{$menu}.gif";
    }

    $images = [
        'bgs'            => ['00.jpg', '01.jpg', '02.jpg', '03.jpg', '04.jpg', '05.jpg', '06.jpg', '07.jpg', '08.jpg', '09.jpg', '10.jpg', '11.jpg', '12.jpg'],
        'news'           => ['icon-news01.gif', 'icon-news02.gif', 'icon-news03.gif', 'icon-news04.gif', 'icon-news05.gif', 'icon-news06.gif'],
        'community'      => ['icon-community01.gif', 'icon-community02.gif', 'icon-community03.gif', 'icon-community04.gif', 'icon-community05.gif', 'icon-community06.gif', 'icon-community07.gif', 'icon-community08.gif'],
        'forum'          => ['icon-forum01.gif', 'icon-forum02.gif', 'icon-forum03.gif', 'icon-forum04.gif', 'icon-forum05.gif', 'icon-forum06.gif', 'icon-forum07.gif', 'icon-forum08.gif', 'icon-forum09.gif', 'icon-forum10.gif'],
        'account'        => ['icon-account01.gif', 'icon-account02.gif', 'icon-account03.gif', 'icon-account04.gif', 'icon-account05.gif'],
        'library'        => ['icon-library01.gif', 'icon-library02.gif', 'icon-library03.gif', 'icon-library04.gif', 'icon-library05.gif'],
        'wars'           => ['icon-wars01.gif', 'icon-wars02.gif', 'icon-wars03.gif', 'icon-wars04.gif', 'icon-wars05.gif', 'icon-wars06.gif', 'icon-wars07.gif', 'icon-wars08.gif', 'icon-wars09.gif', 'icon-wars10.gif', 'icon-wars11.gif', 'icon-wars12.gif', 'icon-wars13.gif', 'icon-wars14.gif'],
        'events'         => ['icon-events01.gif', 'icon-events02.gif', 'icon-events03.gif', 'icon-events04.gif', 'icon-events05.gif', 'icon-events06.gif', 'icon-events07.gif', 'icon-events08.gif', 'icon-events09.gif', 'icon-events10.gif', 'icon-events11.gif', 'icon-events12.gif', 'icon-events13.gif'],
        'support'        => ['icon-support01.gif', 'icon-support02.gif', 'icon-support03.gif', 'icon-support04.gif', 'icon-support05.gif', 'icon-support06.gif', 'icon-support07.gif', 'icon-support08.gif', 'icon-support09.gif', 'icon-support10.gif', 'icon-support11.gif'],
        'shops'          => ['icon-shops01.gif', 'icon-shops03.gif', 'icon-shops05.gif'],
        'charactertrade' => ['icon-bazaar01.gif', 'icon-bazaar02.gif'],
    ];
    if (!$images[$menu]) {
        return "/images/menu/icon-{$menu}.gif";
    }

    // generate random number size of the array
    $img = $images[$menu][rand(0, count($images[$menu]) - 1)];
    return $menu !== 'bgs' ? "/images/menu/anim/{$img}" : "/images/header/bgs/{$img}";
}
