<?php

use SimpleSAML\Auth\Source;
use SimpleSAML\Auth\State;
use SimpleSAML\Configuration;
use SimpleSAML\Error\NoState;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Session;
use SimpleSAML\XHTML\Template;

Logger::debug("Loading privacyIDEA form..");
// Load $state from the earlier position
$stateId = $_REQUEST['stateId'];
try
{
    $state = State::loadState($stateId, 'privacyidea:privacyidea');
}
catch (NoState $e)
{
    Logger::error("Unable to load state information because stateId is lost");
}
catch (Exception $e)
{
    Logger::error("Unable to load state information. " . $e->getMessage());
}

// Find the username
if (isset($state['privacyidea:privacyidea']['uidKey']))
{
    $uidKey = $state['privacyidea:privacyidea']['uidKey'];
    $username = $state['Attributes'][$uidKey][0];
}
elseif (isset($state['privacyidea:privacyidea']['username']))
{
    $username = $state['privacyidea:privacyidea']['username'];
}
elseif (isset($state['core:username']))
{
    $username = (string)$state['core:username'];
}
else
{
    $username = '';
}

// Prepare the form to show
try
{
    $tpl = new Template(Configuration::getInstance(), 'privacyidea:LoginForm.php');
}
catch (Exception $e)
{
    Logger::error("Unable to prepare the login form. " . $e->getMessage());
}

// Prepare error to show in UI
$tpl->data['errorCode'] = null;
$tpl->data['errorMessage'] = null;

if (!empty($state['privacyidea:privacyidea']['errorCode']) || !empty($state['privacyidea:privacyidea']['errorMessage']))
{
    if (!empty($state['privacyidea:privacyidea']['errorCode']))
    {
        $tpl->data['errorCode'] = $state['privacyidea:privacyidea']['errorCode'];
        $state['privacyidea:privacyidea']['errorCode'] = "";
    }
    else
    {
        $tpl->data['errorCode'] = "";
    }
    $tpl->data['errorMessage'] = $state['privacyidea:privacyidea']['errorMessage'];
    $state['privacyidea:privacyidea']['errorMessage'] = "";
    $stateId = State::saveState($state, 'privacyidea:privacyidea');
}

// AuthProc
if ($state['privacyidea:privacyidea']['authenticationMethod'] === "authprocess")
{
    $tpl->data['authProcFilterScenario'] = true;
    $tpl->data['rememberUsernameEnabled'] = true;
    $tpl->data['rememberUsernameChecked'] = true;
    $tpl->data['forceUsername'] = true;

    // Enroll token's QR
    if (isset($state['privacyidea:tokenEnrollment']['tokenQR']))
    {
        $tpl->data['tokenQR'] = $state['privacyidea:tokenEnrollment']['tokenQR'];
    }
    else
    {
        $tpl->data['tokenQR'] = null;
    }
}
elseif ($state['privacyidea:privacyidea']['authenticationMethod'] === "authsource")
{
    // AuthSource
    try
    {
        $source = Source::getById($state["privacyidea:privacyidea"]["AuthId"]);
    }
    catch (\SimpleSAML\Error\Exception $e)
    {
        Logger::error("Unable to access the AuthSource. " . $e->getMessage());
    }

    if ($source == NULL)
    {
        Logger::error("Could not find authentication source with ID: " . $state["privacyidea:privacyidea"]["AuthId"]);
    }

    $tpl->data['username'] = $username;
    $tpl->data['rememberMeEnabled'] = $source->isRememberMeEnabled();
    $tpl->data['rememberMeChecked'] = $source->isRememberMeChecked();
    $tpl->data['links'] = $source->getLoginLinks();

    if (array_key_exists('forcedUsername', $state))
    {
        $tpl->data['forceUsername'] = true;
        $tpl->data['rememberUsernameEnabled'] = false;
        $tpl->data['rememberUsernameChecked'] = false;
    }
    else
    {
        $tpl->data['forceUsername'] = false;
        $tpl->data['rememberUsernameEnabled'] = $source->getRememberUsernameEnabled();
        $tpl->data['rememberUsernameChecked'] = $source->getRememberUsernameChecked();
    }

    $tpl->data['SPMetadata'] = @$state['SPMetadata'];
}

// Get all the ui data placed in state and set it to $tpl->data for future use in LoginForm.php
if (!empty($state['privacyidea:privacyidea:ui']))
{
    foreach ($state['privacyidea:privacyidea:ui'] as $key => $value)
    {
        $tpl->data[$key] = $value;
    }
}

// Make sure every required key exists, even with just default values
if (!array_key_exists('head', $tpl->data))
{
    $tpl->data['head'] = "";
}

if (empty($_REQUEST['loadCounter']))
{
    $tpl->data['loadCounter'] = 1;
}

if ($state['privacyidea:privacyidea']['authenticationMethod'] === "authprocess")
{
    $tpl->data['LogoutURL'] = Module::getModuleURL('core/authenticate.php', array('as' => $state['Source']['auth'])) . "&logout";
}

try
{
    Session::getSessionFromRequest()->setData("privacyidea:privacyidea", "stateId", $stateId);
}
catch (Exception $e)
{
    Logger::error("No access to request session. " . $e->getMessage());
}
$tpl->show();