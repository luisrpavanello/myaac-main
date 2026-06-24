<?php

use Twig\Environment as Twig_Environment;
use Twig\Extension\DebugExtension as Twig_DebugExtension;
use Twig\Loader\FilesystemLoader as Twig_FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

function myaacTwigHook($context, $hook, array $params = [])
{
  global $hooks;

  if (is_string($hook)) {
    if (defined($hook)) {
      $hook = constant($hook);
    } else {
      // plugin/template has a hook that this version of myaac does not support
      // just silently return
      return;
    }
  }

  $params['context'] = $context;
  $hooks->trigger($hook, $params);
}

function myaacTwigGetCustomPage($name)
{
  $success = false;
  return getCustomPage($name, $success);
}

$dev_mode = config('env') === 'dev';
$twig_loader = new Twig_FilesystemLoader(SYSTEM . 'templates');
$twig = new Twig_Environment($twig_loader, [
  'cache' => CACHE . 'twig/',
  'auto_reload' => $dev_mode,
  'debug' => $dev_mode,
]);

$twig_loader->addPath(PLUGINS);

$twig->addGlobal('logged', false);
$twig->addGlobal('account_logged', new OTS_Account());

if ($dev_mode) {
  $twig->addExtension(new Twig_DebugExtension());
}
unset($dev_mode);

$function = new TwigFunction('getStyle', 'getStyle');
$twig->addFunction($function);

$function = new TwigFunction('getLink', 'getLink');
$twig->addFunction($function);

$function = new TwigFunction('getPlayerLink', 'getPlayerLink');
$twig->addFunction($function);

$function = new TwigFunction('getGuildLink', 'getGuildLink');
$twig->addFunction($function);

$function = new TwigFunction(
  'hook',
  'myaacTwigHook',
  ['needs_context' => true]
);
$twig->addFunction($function);

$function = new TwigFunction('config', 'config');
$twig->addFunction($function);

$function = new TwigFunction('getCustomPage', 'myaacTwigGetCustomPage');
$twig->addFunction($function);

$filter = new TwigFilter('urlencode', 'urlencode');

$twig->addFilter($filter);
unset($function, $filter);
