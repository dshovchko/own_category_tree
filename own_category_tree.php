<?php

// This is a PLUGIN TEMPLATE.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Plugin names should start with a three letter prefix which is
// unique and reserved for each plugin author ('abc' is just an example).
// Uncomment and edit this line to override:
# $plugin['name'] = 'abc_plugin';

// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
# $plugin['allow_html_help'] = 0;

$plugin['version'] = '4.6.2.20161213';
$plugin['author'] = 'Dmitry Shovchko';
$plugin['author_uri'] = 'http://github.com/dshovchko/own_category_tree';
$plugin['description'] = 'Creates a hierarchical list of categories';

// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment
// spam evaluators or URL redirectors would probably want to run earlier
// (1...4) to prepare the environment for everything else that follows.
// Values 6...9 should be considered for plugins which would work late.
// This order is user-overrideable.
# $plugin['order'] = 5;

// Plugin 'type' defines where the plugin is loaded
// 0 = public       : only on the public side of the website (default)
// 1 = public+admin : on both the public and non-AJAX admin side
// 2 = library      : only when include_plugin() or require_plugin() is called
// 3 = admin        : only on the non-AJAX admin side
// 4 = admin+ajax   : only on admin side
// 5 = public+admin+ajax   : on both the public and admin side
$plugin['type'] = 1;

// Plugin 'flags' signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use.
if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

$plugin['flags'] = '0';


if (!defined('txpinterface'))
    @include_once('zem_tpl.php');

if (0) {
?>
# --- BEGIN PLUGIN HELP ---

# --- END PLUGIN HELP ---
<?php
}

# --- BEGIN PLUGIN CODE ---

### TAG REGISTRATION ###
########################

Txp::get('\Textpattern\Tag\Registry')
 ->register('own_category_tree');


### PRIMARY TAG FUNCTIONS ###
#############################

function own_category_tree($atts) {
	global $pretext;
	
	extract(lAtts(array(
		'start'		=> 'root',
		'section'	=> 0,
		'type'		=> 'article',
		'cssid'		=> '',
		'onclass'	=> '',
		'wraptag'	=> 'ul',
		'break'		=> 'li',
		'locale'	=> '',
	),$atts));
	
	if ($start == '*') $start = $pretext['c'] ? $pretext['c'] : 'root';
	if ($start == '*s') $start = $GLOBALS['s'];
	if ($section == '*') $section = $GLOBALS['s'];
	if ($wraptag == ' ') $wraptag = '';
	if ($break == ' ') $break = '';
	
	$vars = compact('start','section','type','cssid','onclass','wraptag','break');
	
	if ($locale) {
		setlocale(LC_ALL, $locale);
	}
	
	$l = own_category_tree_get_list($type);
	$r = own_category_tree_make_tree($l, $vars);

	return $r;
}

function own_category_tree_make_tree($list, $vars) {
	global $pretext;

	$categories = array();
	if (intval($pretext['id']) == 0) {
		$categories[] = $pretext['c'];
	} else {
		global $thisarticle;
		
		$categories[] = $thisarticle['category1'];
		$categories[] = $thisarticle['category2'];
	}
	
	$t = own_category_tree_get_branch($list, $vars['start']);
	$l = 0;
	
	return own_category_tree_make_subtree($t, $categories, $l, $vars);
}

function own_category_tree_get_branch($list, $from) {
	$r = '';
	
	$a = array();
	foreach($list as $v) {
		if ($v['parent'] == $from) {
			$a[$v['name']] = $v['title'];
		}
	}
	
	asort($a, SORT_LOCALE_STRING);
	
	$s = array();
	foreach($a as $name=>$title) {
		foreach($list as $v) {
			if ($v['name'] == $name) {
				$v['children'] = own_category_tree_get_branch($list, $name);
				$s[] = $v;
			}
		}
	}
	
	return $s;
}

function own_category_tree_check_subtree($list, $categories) {
	
	$l = 0;
	foreach($list as $v) {
		if (in_array($v['name'], $categories)) {
			$l++;
		}
		$l += own_category_tree_check_subtree($v['children'], $categories);
	}
	
	return $l;
}

function own_category_tree_make_subtree($list, $categories, $l, $vars) {
	
	$r = '';
	foreach($list as $v) {
		$rc = '';
		if (own_category_tree_check_subtree($v['children'], $categories) > 0 || in_array($v['name'],$categories)) {
			$rc = own_category_tree_make_subtree($v['children'], $categories, $l+1, $vars);
		}
		$class = $vars['onclass'] && in_array($v['name'],$categories) ? $vars['onclass'] : '';
		$link = own_category_tree_link($v['name'], $vars['section'], $v['title']);
		$break = $vars['break'];
		if ($break == 'br' || $break == 'hr') {
			$r .= own_category_tree_tab($l).join(doTag('', $break), array($link, $rc));
		} else {
			$r .= own_category_tree_tab($l).doWrap(array($link, $rc), $break, '', $class);
		}
	}
	
	$atts = ($vars['cssid'] && $l == 0)?' id="'.$vars['cssid'].'"':'';
	
	return own_category_tree_tab($l).doWrap(array($r), $vars['wraptag'], '', '', '', $atts);
}

function own_category_tree_get_list($type) {
	global $own_category_tree_cache;
	
	if (!isset($own_category_tree_cache)){
		$own_category_tree_cache = array();
	}
	
	if (!isset($own_category_tree_cache[$type])) {
		$r = safe_rows("name,parent,title", "txp_category", "type='$type' order by id asc");
		$own_category_tree_cache[$type] = $r;
	} else {
		$r = $own_category_tree_cache[$type];
	}
	
	return $r;
}

function own_category_tree_link($category, $section, $title)
{
	$cat_link = pagelinkurl(array('c'=>$category, 's'=>$section));
	
	return tag(htmlspecialchars($title), 'a', ' href="'.$cat_link.'" title="'.htmlspecialchars($title).'"');
}

function own_category_tree_tab($l) {
	return str_repeat("\t", $l);
}
# --- END PLUGIN CODE ---

?>