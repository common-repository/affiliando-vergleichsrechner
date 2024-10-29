<?php
/*
Plugin Name: Affiliando Vergleichsrechner
Plugin URI: http://www.loremipsum.at/produkte/wordpress-plugins/
Description: WordPress Plugin f&uuml;r den Affiliando.de Vergleichsrechner
Version: 1.0
Author: Karl Kowald
Author URI: http://www.loremipsum.at/author/karl.kowald/
*/

class Affiliandovglrechner {
	var $base_name;
	function Affiliandovglrechner() 
	{
		$this->localCache = true;
		$this->base_name = plugin_basename(__FILE__);
		if(!is_admin())
		{
			add_action('wp_head', array($this,'init_css_js'));
			add_shortcode('affiliandovergleich',array($this,'shortcode_affiliandovergleich'));
		}
		else
		{
			add_action('admin_menu',array($this,'init_admin_menu'));
			if ($this->is_current_page('plugins'))
			{
				add_action('admin_notices',array($this,'show_version_notice'));
			}
		}
	}
		
	/* Funktionsaufrufe aus der Hauptfunktion heraus */
	function init_css_js()
	{
		global $post;
		$this->post = $post;
		$this->shortcode_found = false;
		if (isset($this->post->ID))
		{
			$pos = preg_match('/\[affiliandovergleich(.)*\]/', $this->post->post_content, $matches);
			if(!$pos)
			{
				return;
			}
			$matches[0] = trim(substr($matches[0],20,strlen($matches[0])));
			$split1 = preg_split('/ /', trim($matches[0]));
			for($x = 0; $x < count($split1); $x++)
			{
				$split2 = preg_split('/=/',trim($split1[$x]));
				switch(trim($split2[0]))
				{
					case "pid":
						$this->pid = substr(trim($split2[1]), 1, -1);
						break;
					case "campaign":
						$this->campaign = substr(trim($split2[1]), 1, -1);
						break;
					case "rtype":
						$this->rtype = substr(trim($split2[1]), 1, -1);
						break;
					default:
						break;
				}
			}
	    require_once("affiliandorechner.class.php");
			$this->r = new Rechner($this->pid, $this->campaign, $this->rtype); // anlegen
			$this->csstype = $this->rtype;
			if($this->csstype == "kredit")
			{
				$this->csstype = "kreditrechner";
			}
			$this->css1 = $this->r->domain.'/extension/site_konsumentenkredite/design/konsumentenkredite_user/stylesheets/vergleich_base.css';
			$this->cssie = $this->r->domain.'/extension/site_konsumentenkredite/design/konsumentenkredite_user/stylesheets/vergleich_base_ie6.css';
			$this->css2 = $this->r->domain.'/extension/zt_kreditrechner/design/konfigurator/stylesheets/additional/'.$this->csstype.'_'.$this->pid.'_'.$this->campaign.'.css';
			if($this->localCache == true)
			{
				$jetzt = time();
				@$nextupdate = filemtime($_SERVER['DOCUMENT_ROOT'].'/wp-content/plugins/affiliandovergleichsrechner/files/'.md5($this->css1).'.css')+(3600*24);
				if($jetzt > $nextupdate)
				{
					$css = file_get_contents($this->css1);
					preg_match_all("|url\(([^\)]*)|i",$css, $urls);
					foreach($urls[0] as $url)
					{
						$dl = $this->r->domain.'/extension/site_konsumentenkredite/design/konsumentenkredite_user'.substr($url,6,strlen($url));
						file_put_contents($_SERVER['DOCUMENT_ROOT'].'/wp-content/plugins/affiliandovergleichsrechner'.substr($url,6,strlen($url)), file_get_contents($dl));
					}
					file_put_contents($_SERVER['DOCUMENT_ROOT'].'/wp-content/plugins/affiliandovergleichsrechner/files/'.md5($this->css1).'.css', $css);
				}
				@$nextupdate = filemtime($_SERVER['DOCUMENT_ROOT'].'/wp-content/plugins/affiliandovergleichsrechner/files/'.md5($this->cssie).'.css')+(3600*24);
				if($jetzt > $nextupdate)
				{
					$css = file_get_contents($this->cssie);
					preg_match_all("|url\(([^\)]*)|i",$css, $urls);
					foreach($urls[0] as $url)
					{
						$dl = $this->r->domain.'/extension/site_konsumentenkredite/design/konsumentenkredite_user'.substr($url,6,strlen($url));
						file_put_contents($_SERVER['DOCUMENT_ROOT'].'/wp-content/plugins/affiliandovergleichsrechner'.substr($url,6,strlen($url)), file_get_contents($dl));
					}
					file_put_contents($_SERVER['DOCUMENT_ROOT'].'/wp-content/plugins/affiliandovergleichsrechner/files/'.md5($this->cssie).'.css', $css);
				}
				@$nextupdate = filemtime($_SERVER['DOCUMENT_ROOT'].'/wp-content/plugins/affiliandovergleichsrechner/files/'.md5($this->css2).'.css')+(3600*24);
				if($jetzt > $nextupdate)
				{
					$css = file_get_contents($this->css2);
					file_put_contents($_SERVER['DOCUMENT_ROOT'].'/wp-content/plugins/affiliandovergleichsrechner/files/'.md5($this->css2).'.css', $css);
				}
				$this->css1 = get_bloginfo('wpurl').'/wp-content/plugins/affiliandovergleichsrechner/files/'.md5($this->css1).'.css';
				$this->cssie = get_bloginfo('wpurl').'/wp-content/plugins/affiliandovergleichsrechner/files/'.md5($this->cssie).'.css';
				$this->css2 = get_bloginfo('wpurl').'/wp-content/plugins/affiliandovergleichsrechner/files/'.md5($this->css2).'.css';
			}
			echo '
		<link rel="stylesheet" type="text/css" media="screen" href="'.$this->css1.'" />
		<!--[if IE 6]>
		<link rel="stylesheet" href="'.$this->cssie.'" />
		<![endif]-->
		<link rel="stylesheet" type="text/css" media="screen" href="'.$this->css2.'" />
';
		}
	}

	function shortcode_affiliandovergleich($atts)
	{
		extract(shortcode_atts(array(
			'pid' => 0,
			'campaign' => 0,
			'rtype' => 'kredit',
			'anzahl' => 10,
			'small' => false,
			'expand' => false,
		), $atts));
		if($small == 1 or $small == 'true')
		{
			$small = true;	
		}
		if($expand == 1 or $expand == 'true')
		{
			$expand = true;
		}
    require_once("affiliandorechner.class.php");
		$this->r->to_encoding(get_bloginfo('charset')); // Zielkodierung (falls nicht UTF-8)
		$this->r->htmlWrap(false); // <html>...</html>
		$this->r->expand($expand); // ausgeklappt?
		$this->r->smallLayout($small); // schmales Layout?
		$this->r->visibleResults($anzahl); // Anzahl sichtbarer Ergebnisse
		// $r->hideProducts(array("produkt_1", "produkt_2")); // nicht sichtbare Fremdprodukte
		$content = $this->r->render(); // Ausgabe
		if($this->localCache == true)
		{
			$jetzt = time();
			preg_match_all("|".$this->r->domain."([^\"]*)|i",$content, $urls);
			$newurl = array();
			foreach ($urls[0] as $url)
			{
				$newurl[] = get_bloginfo('wpurl').'/wp-content/plugins/affiliandovergleichsrechner/files/'.md5($url).strrchr($url, ".");
				@$next = filemtime($_SERVER['DOCUMENT_ROOT'].'/wp-content/plugins/affiliandovergleichsrechner/files/'.md5($url).strrchr($url, "."))+(3600*24);
				if($jetzt > $next)
				{
					file_put_contents($_SERVER['DOCUMENT_ROOT'].'/wp-content/plugins/affiliandovergleichsrechner/files/'.md5($url).strrchr($url, "."), file_get_contents($url));
				}
			}
			$content = str_replace($urls[0],$newurl,$content);
		}		
		return $content;
}


	function is_current_page($page) 
	{
		switch($page) 
		{
			case 'home':
			return (!empty($_REQUEST['page']) && $_REQUEST['page'] == $this->base_name);
			case 'index':
			case 'plugins':
			return (!empty($GLOBALS['pagenow']) && $GLOBALS['pagenow'] == sprintf('%s.php', $page));
			default:
			return false;
		}
	}
	function show_version_notice() {
		if ($this->is_min_wp('2.7')) {
			return;
		}
		echo sprintf(
		'<div class="error"><p><strong>%s</strong> %s</p></div>',
		'Affiliando Vergleichsrechner f&uuml; WordPress',
		'ben&ouml;tigt WP 3.0 oder h&ouml;her'
		);
	}
	function init_admin_menu() 
	{
		$pages = array(
            'li_affiliando' => array(
                'Affiliando Vergleichsrechner', 
                'Affiliando Vergleichsrechner',
                'show_admin_menu'
            ));
        global $li_create_mainmenu;
        if($li_create_mainmenu != 'done')
        {
	        li_create_mainmenu();
	      }

        $submenu_pages = array();
        
        foreach ($pages as $slug => $titles) {
            $submenu_pages[] = add_submenu_page('li_tools', $titles[0] . ' | Lorem Ipsum Plugins', $titles[1], ($this->is_min_wp('2.8') ? 'manage_options' : 9),$slug,array($this,$titles[2]));
        }	
	}
	function is_min_wp($version) 
	{
		return version_compare($GLOBALS['wp_version'],$version. 'alpha','>=');
	}
		function check_user_can() 
	{
		if (current_user_can('manage_options') === false || current_user_can('edit_plugins') === false || !is_user_logged_in()) 
		{
			wp_die('You do not have permission to access!');
		}
	}

	function show_admin_menu() 
	{
		if (!$this->is_min_wp('2.8')) 
		{
			$this->check_user_can();
		}
		if (!empty($_POST)) 
		{
			check_admin_referer('loremipsummenu');
			?>
			<div id="message" class="updated fade">
				<p>
					<strong>
						Einstellungen gespeichert
					</strong>
				</p>
			</div>
			<?php } ?>
			<div class="wrap">
				<div class="icon32"></div>
				<h2>
					Lorem Ipsum Plugins
				</h2>
				<form method="post" action="">
					<?php wp_nonce_field('loremipsummenu') ?>
					<div id="poststuff">
						<div class="postbox">
							<h3>
								Lorem Ipsum Plugins
							</h3>
							<div class="inside">
								<br />
								Anleitung zur Verwendung der Plugins:
								<a href="http://www.loremipsum.at/produkte/wordpress-plugins/affiliando-vergleichsrechner/">Pluginbeschreibung mit Bebilderung</a><br />
								- Affiliando Vergleichsrechner:<br /><br />
								via Shortcode:<br /><br />
								<ul>
									<li><b>[affiliandovergleich pid="pid" campaign="campaignid" rtype="kredit" anzahl="10" small="false" expand="false"]</b><br /></li>
									<li>&nbsp;</li>
									<li><b>Beschreibung</b></li>
									<li><b>pid</b> [Pflichtfeld]: Die Affiliando PartnerID</li>
									<li><b>campaign</b> [Pflichtfeld]: Entscheidet welcher Rechner genommen wird (Produkte &amp; Aussehen). Zu finden auf der Affiliando-Seite im Code des Rechners.</li>
									<li><b>rtype</b> [Pflichtfeld]: Welcher Vergleichsrechner es werden soll. Zu finden auf der Affiliando-Seite im Code des Rechners</li>
									<li><b>anzahl</b> [Optional]: Wieviele Produkte von Beginn an gezeigt werden sollen. Standardwert: 10</li>
									<li><b>small</b> [Optional]: Ob die schmale Rechnervariante verwendet werden kann. Wert kann &quot;true/false&quot; annehmen. Standardwert: false</li>
									<li><b>expand</b> [Optional]: Ob die Berechnungsdetails zu Beginn angezeigt werden sollen. Wert kann &quot;true/false&quot; annehmen. Standardwert: false</li>
								</ul>
							</div>
						</div>
						<div class="postbox">
							<h3>Unterst&uuml;tze uns</h3>
							<div class="inside">
								Wenn dir unser Plugin gef&auml;llt, dann w&uuml;rden wir uns &uuml;ber einen Backlink eurerseits sehr freuen. Anbei ein Vorschlag. Danke<br />
								<br />
								<textarea style="width: 600px; height: 100px">&lt;a href=&quot;http://www.loremipsum.at/&quot; title=&quot;Internetagentur &amp; IT-Consulting&quot;&gt;Lorem Ipsum IT-Consulting&lt;/a&gt;</textarea>
							</div>
						</div>
						<div class="postbox">
							<h3>
								&Uuml;ber Affiliando Vergleichsrechner f&uuml;r WordPress
							</h3>
						<div class="inside">
							<p>
								<?php $this->show_plugin_info() ?>
							</p>
						</div>
					</div>
				</div>
			</form>
		</div>
<?php 
	}




	function show_plugin_info() 
	{
		$data = get_plugin_data(__FILE__);
		echo sprintf('%s %s %s <a href="http://www.loremipsum.at/produkte/wordpress-plugins/" target="_blank">WP Plugins by Lorem Ipsum</a> | <a href="http://twitter.com/LI_GmbH" target="_blank">%s</a> | <a href="http://www.loremipsum.at/" target="_blank">%s</a>',
		'Affiliando Vergleichsrechner f&uuml;r WordPress',
		$data['Version'],
		'von',
		'Folge uns via Twitter',
		'Mehr &uuml;ber Lorem Ipsum'
		);
	}
}
new Affiliandovglrechner();

if(!function_exists('li_create_mainmenu'))
{
	function li_create_mainmenu()
	{
		global $li_create_mainmenu;
		$li_create_mainmenu = "done";
		add_menu_page('Lorem Ipsum','Lorem Ipsum','manage_options','li_tools','', plugins_url('affiliandovergleichsrechner/li-icon.jpg'));	
		add_submenu_page('li_tools', 'LI Plugins', 'LI Plugins', 'manage_options', 'li_tools', 'li_mainmenu_mainpage');
	}
}
if(!function_exists('li_mainmenu_mainpage'))
{
	function li_mainmenu_mainpage()
	{
		?>
			<div class="wrap">
				<div class="icon32"><img src="<?php echo plugins_url('subidtracking/li-icon-28.jpg'); ?>" width="28" height="28" alt="Lorem Ipsum" /></div>
				<h2>
					Lorem Ipsum Plugins
				</h2>
				<form method="post" action="">
					<?php wp_nonce_field('loremipsummenu') ?>
					<div id="poststuff">
						<div class="postbox">
							<h3>
								Lorem Ipsum Plugins
							</h3>
							<div class="inside">
								Hier befinden sich alle Subseiten der Plugins die von der Lorem Ipsum Medienges.m.b.H. erstellt wurden.
							</div>
						</div>
						<div class="postbox">
							<h3>
								&Uuml;ber Lorem Ipsum Medienges.m.b.H.
							</h3>
						<div class="inside">
							<p>
<?php
		echo sprintf('%s %s <a href="http://www.loremipsum.at/produkte/wordpress-plugins/" target="_blank">WP Plugins by Lorem Ipsum</a> | <a href="http://twitter.com/LI_GmbH" target="_blank">%s</a> | <a href="http://www.loremipsum.at/" target="_blank">%s</a>',
		'Lorem Ipsum Plugins f&uuml;r WordPress',
		'von',
		'Folge uns via Twitter',
		'Mehr &uuml;ber Lorem Ipsum'
		);
?>
							</p>
						</div>
					</div>
				</div>
			</form>
		</div>
<?php 
		
	}
}

?>