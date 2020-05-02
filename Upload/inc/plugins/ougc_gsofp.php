<?php 

/***************************************************************************
 *
 *	OUGC Guests See Only First Post plugin (/inc/plugins/ougc_showgivedrep.php)
 *	Author: Omar Gonzalez
 *	Copyright: © 2012 - 2020 Omar Gonzalez
 *
 *	Website: https://ougc.network
 *
 *	Stops guests from seeing more that the first post of each thread.
 *
 ***************************************************************************

****************************************************************************
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
****************************************************************************/

// Die if IN_MYBB is not defined, for security reasons.
defined('IN_MYBB') or die('This file cannot be accessed directly.');

// Run the required hooks.
if(!defined('IN_ADMINCP'))
{
	$plugins->add_hook('forumdisplay_start', 'ougc_gsofp_hack');
	$plugins->add_hook('showthread_start', 'ougc_gsofp_hack');
	$plugins->add_hook('printthread_start', 'ougc_gsofp_hack');
	$plugins->add_hook('archive_start', 'ougc_gsofp_hack');
	$plugins->add_hook('search_results_start', 'ougc_gsofp_hack');
}

// Array of information about the plugin.
function ougc_gsofp_info()
{
	global $lang;

	isset($lang->ougc_gsofp) || $lang->load('ougc_gsofp', true, false);

	return array(
		'name'			=> 'OUGC Guests See Only First Post',
		'description'	=> $lang->ougc_gsofp_desc,
		'website'		=> 'https://ougc.network',
		'author'		=> 'Omar G.',
		'authorsite'	=> 'https://ougc.network',
		'version'		=> '1.8.20',
		'versioncode'	=> 1820,
		'compatibility'	=> '18*',
		'codename'		=> 'ougc_gsofp'
	);
}

// _activate() routine
function ougc_gsofp_activate()
{
	global $cache;

	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';

	find_replace_templatesets('showthread', '#'.preg_quote('{$quickreply}').'#', '{$ougc_gsofp}{$quickreply}');
	find_replace_templatesets('printthread', '#'.preg_quote('</body>').'#', '{$ougc_gsofp}</body>');

	// Insert/update version into cache
	$plugins = $cache->read('ougc_plugins');
	if(!$plugins)
	{
		$plugins = array();
	}

	$info = ougc_gsofp_info();

	if(!isset($plugins['gsofp']))
	{
		$plugins['gsofp'] = $info['versioncode'];
	}

	/*~*~* RUN UPDATES START *~*~*/

	/*~*~* RUN UPDATES END *~*~*/

	$plugins['gsofp'] = $info['versioncode'];
	$cache->update('ougc_plugins', $plugins);
}

// _deactivate() routine
function ougc_gsofp_deactivate()
{
	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	
	find_replace_templatesets('showthread', '#'.preg_quote('{$ougc_gsofp}').'#', '', 0);
	find_replace_templatesets('printthread', '#'.preg_quote('{$ougc_gsofp}').'#', '', 0);
}

// _is_installed() routine
function ougc_gsofp_is_installed()
{
	global $cache;

	$plugins = (array)$cache->read('ougc_plugins');

	return isset($plugins['gsofp']);
}

// _uninstall() routine
function ougc_gsofp_uninstall()
{
	global $cache;

	// Delete version from cache
	$plugins = (array)$cache->read('ougc_plugins');

	if(isset($plugins['gsofp']))
	{
		unset($plugins['gsofp']);
	}

	if(!empty($plugins))
	{
		$cache->update('ougc_plugins', $plugins);
	}
	else
	{
		$cache->delete('ougc_plugins');
	}
}

// Removes the {$thread['multipage']} from forumdisplay
function ougc_gsofp_hack()
{
	global $mybb, $db;

	if(!$mybb->user['uid'])
	{
		global $plugins;
		$mybb->settings['postsperpage'] = 999*999;

		if(THIS_SCRIPT == 'showthread.php')
		{
			// Hide postbit content
			$plugins->add_hook('postbit', 'ougc_gsofp_postbit');
		}
		elseif(THIS_SCRIPT == 'printthread.php')
		{
			// Hide from print thread.
			$plugins->add_hook('printthread_post', 'ougc_gsofp_printthread');
		}
		elseif(THIS_SCRIPT == 'search.php')
		{
			control_object($db, '
				function query($string, $hide_errors=0, $write_query=0)
				{
					static $done = false;
					if(!$done && !$write_query && strpos($string, \'p.*, u.username AS userusername\') !== false)
					{
						$done = true;
						$string = strtr($string, array(
							\'p.*, u.username AS userusername\' => \'p.*, u.username AS userusername, t.firstpost as thread_firstpost\'
						));
					}
					return parent::query($string, $hide_errors, $write_query);
				}
			');

			// Hide from print thread.
			$plugins->add_hook('search_results_post', 'ougc_gsofp_search');
		}
		elseif(defined('IN_ARCHIVE'))
		{
			// Hide from archive
			// Seems like the best and nicer way of doing it...
			$plugins->add_hook('archive_thread_post', 'ougc_gsofp_printthread_archive');
		}
	}
}

function ougc_gsofp_postbit(&$post)
{
	global $thread, $templates, $plugins, $mybb, $lang, $ougc_gsofp;

	if($post['pid'] != $thread['firstpost'])
	{
		$templates->cache['postbit'] = $templates->cache['postbit_classic'] = '';
	}

	if(!isset($ougc_gsofp))
	{
		isset($lang->ougc_gsofp) || $lang->load('ougc_gsofp');

		$moderation_text = $lang->sprintf($lang->ougc_gsofp_notification, $mybb->settings['bburl']);

		$ougc_gsofp = eval($templates->render('global_moderation_notice'));
	}
}

function ougc_gsofp_printthread()
{
	global $postrow, $thread, $templates, $plugins, $mybb, $lang, $ougc_gsofp;

	if($postrow['pid'] != $thread['firstpost'])
	{
		global $templates;

		$templates->cache['printthread_post'] = '';
	}

	if(!isset($ougc_gsofp))
	{
		isset($lang->ougc_gsofp) || $lang->load('ougc_gsofp');

		$moderation_text = $lang->sprintf($lang->ougc_gsofp_notification, $mybb->settings['bburl']);

		$ougc_gsofp = eval($templates->render('global_moderation_notice'));
	}
}

function ougc_gsofp_printthread_archive()
{
	global $post, $thread, $lang;

	if($post['pid'] != $thread['firstpost'])
	{
		isset($lang->ougc_gsofp) || $lang->load('ougc_gsofp');

		$moderation_text = $lang->sprintf($lang->ougc_gsofp_notification, $mybb->settings['bburl']);

		archive_error($moderation_text);
	}
}

function ougc_gsofp_search()
{
	global $post, $thread, $lang, $prev;

	if($post['pid'] != $post['thread_firstpost'])
	{
		$post['message'] = $prev = '';
	}
}
