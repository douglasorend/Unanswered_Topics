<?php
/**********************************************************************************
* add_remove_hooks.php                                                            *
***********************************************************************************
* This mod is licensed under the 2-clause BSD License, which can be found here:
*	http://opensource.org/licenses/BSD-2-Clause
***********************************************************************************
* This program is distributed in the hope that it is and will be useful, but	  *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY	  *
* or FITNESS FOR A PARTICULAR PURPOSE.											  *
***********************************************************************************
* This file is a simplified database installer. It does what it is suppoed to.    *
**********************************************************************************/
global $modSettings;

// If we have found SSI.php and we are outside of SMF, then we are running standalone.
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF')) // If we are outside SMF and can't find SSI.php, then throw an error
	die('<b>Error:</b> Cannot install - please verify you put this file in the same place as SMF\'s SSI.php.');
db_extend('packages');
	
// Define the hooks
$hook_functions = array(
// BBCode stuff:
	'integrate_pre_include' => '$sourcedir/Subs-Unanswered.php',
	'integrate_load_permissions' => 'UTM_permissions',
	'integrate_pre_load' => 'UTM_Load',
	'integrate_actions' => 'UTM_Actions',
	'integrate_buffer' => 'UTM_Buffer',
// Admin stuff:
	'integrate_general_mod_settings' => 'UTM_Settings',
);

// Adding or removing them?
if (!empty($context['uninstalling']))
	$call = 'remove_integration_function';
else
	$call = 'add_integration_function';

// Do the deed
foreach ($hook_functions as $hook => $function)
	$call($hook, $function);

// Insert the default time limit for unanswered topics if not already defined:
if (!isset($modSettings['unanswered_time_limit']))
	updateSettings(array(
		'unanswered_time_limit' => 90,
	));

if (SMF == 'SSI')
   echo 'Congratulations! You have successfully installed this mod!';

?>