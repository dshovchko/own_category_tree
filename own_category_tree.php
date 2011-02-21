<?php
 
$plugin['version'] = '0.0.1';
$plugin['author'] = 'Dmitry Shovchko';
$plugin['author_uri'] = 'http://github.com/dshovchko/own_category_tree';
$plugin['description'] = 'Creates a hierarchical list of categories';
$plugin['type'] = '1';
$plugin['allow_html_help'] = '1';
 
if (!defined('txpinterface'))
	@include_once('zem_tpl.php');
 
if(0){
?>
# --- BEGIN PLUGIN HELP ---

<!-- *** BEGIN PLUGIN CSS *** -->
<!-- *** END PLUGIN CSS *** -->

# --- END PLUGIN HELP ---
<?php
}
# --- BEGIN PLUGIN CODE ---

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