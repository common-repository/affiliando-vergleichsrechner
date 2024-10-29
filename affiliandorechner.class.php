<?php
if (strnatcmp(phpversion(),'5') < 0) {
	die('Fehler: Diese Klasse benötigt PHP 5.');
}
if(!function_exists('curl_init')) {
	die('Fehler: Diese Klasse benötigt die cURL-Erweiterung für PHP.');
}
if(!function_exists('simplexml_load_string')) {
	die('Fehler: Diese Klasse benötigt die SimpleXML-Erweiterung für PHP.');
}
/**
 * Rechnerklasse für Einbindung eines Zieltraffic-Expertenrechner innerhalb
 * Ihrer eigenen Seite mit PHP.<br/>
 * <br/>
 * Die Klasse wird mit folgenden Parametern initialisiert:
 * <ul>
 *   <li>PID: Ihre Partner-ID im Zieltracker (auch als Login auf Affiliando genutzt)</li>
 *   <li>Campaign-ID: ID der Konfiguration ihres Rechners</li>
 *   <li>Typ: Typ des Rechners. Mögliche Werte: 'kredit', 'tagesgeld', 'depot', 'girokonto', 'festgeld'</li>
 * </ul>
 * Folgende Einstellungen lassen sich vornehmen (optional):
 * <ul>
 *   <li>htmlWrap: Soll um den Rechner ein HTML-Rahmen mit HEAD und BODY ausgegeben werden?</li>
 *   <li>expand: Soll der Rechner per default auf- oder zugeklappt dargestellt werden?</li>
 *   <li>smallLayout: Soll der Rechner imn der breiten oder schmalen Version dargestellt werden?</li>
 *   <li>visibleResults: Wie viele Ergebnisse sollen direkt sichtbar sein (Rest kann per JavaScript eingeblendet werden)?</li>
 *   <li>to_encoding: Welche Kodierung hat die Webseite, in die der Rechner eingebunden werden soll?</li>
 *   <li>hideProducts: Welche Fremdprodukte sollen ausgeblendet werden (Produktkürzel)?</li>
 * </ul>
 * Anwendungsbeispiel:<br/>
 * <pre>
 * $pid = 2345;
 * $campaign = 123;
 *
 * $r = new Rechner($pid, $campaign, 'kredit'); // anlegen
 * $r->htmlWrap(true); // <html>...</html>
 * $r->to_encoding('ISO-8859-1'); // Kodierung
 * $r->expand(true); // ausgeklappt?
 * $r->smallLayout(true); // schmales Layout?
 * $r->visibleResults(10);  // Anzahl sichtbarer Ergebnisse
 * $r->hideProducts(array('produkt_1', 'produkt_2'));  // nicht sichtbare Fremdprodukte
 * $r->defaultValues(array('formularfeldname_1' => 'wert_1', 'formularfeldname_2' => 'wert_2'));  // default-Werte für Formularfelder
 *
 * $r->render(); // Ausgabe
 * </pre>
 *
 * @version 2010-11-29
 */
class Rechner {

	/**
	 * @var integer Partner-ID (intern genutzt)
	 */
	private $pid;

	/**
	 * @var integer ID der Rechner-Konfiguration (intern genutzt)
	 */
	private $campaign;

	/**
	 * @var string Basis-Domain der Schnittstelle (intern verwendet)
	 */
	public $domain;

	/**
	 * @var boolean Ausgabe eines HTML-Rahmens um den Rechner?
	 */
	private $htmlWrap;

	/**
	 * @var boolean Darstellung in schmalem oder breitem Layout?
	 */
	private $smallLayout;

	/**
	 * @var boolean Darstellung in auf- oder zugeklapptem Zustand?
	 */
	private $expand = false;

	/**
	 * @var integer Anzahl der per default sichtbaren Ergebnisse
	 */
	private $visibleResults = 10;

	/**
	 * @var string Encoding der Ziel-Seite
	 */
	private $to_encoding = 'UTF-8';

	/**
	 * @var array Nicht sichtbare Produkte
	 */
	private $hideProducts = array();

	/**
	 * @var array Default-Werte für die Formularfelder
	 */
	private $defaultValues = array();

	/**
	 * @var array Assoziatives Array mit Einstellungen für alle Rechnertypen
	 */
	private $config = array(
		'kredit'	=> array(
			'url' => "http://kx.affiliando.de/kreditrechner/vergleich/(request)/xml/(pid)/"
		),
		'tagesgeld'	=> array(
			'url' => "http://tx.affiliando.de/tagesgeld/vergleich/(request)/xml/(pid)/"
		),
		'depot'		=> array(
			'url' => "http://dx.affiliando.de/depot/vergleich/(request)/xml/(pid)/"
		),
		'girokonto'	=> array(
			'url' => "http://gx.affiliando.de/girokonto/vergleich/(request)/xml/(pid)/"
		),
		'festgeld'	=> array(
			'url' => "http://fx.affiliando.de/festgeld/vergleich/(request)/xml/(pid)/"
		)
	);


	/**
	 * Konstruktor
	 *
	 * @param integer $pid Prozess-ID
	 * @param integer $campaign Kampagnen-ID
	 * @param string $rechner Rechnertyp, mögliche Werte: 'kredit', 'tagesgeld', 'depot', 'girokonto', 'festgeld'
	 */
	public function __construct($pid, $campaign, $rechner) {
		$rechner = strtolower($rechner);
		if(!in_array($rechner, array_keys($this->config))) {
			throw new Exception('Unbekannter Rechnertyp (mögliche Werte: kredit, tagesgeld, depot, girokonto, festgeld)') ;
		}
		$this->pid = $pid;
		$this->campaign = $campaign;
		$this->rechner = $rechner;
		$this->domain = 'http://' . parse_url($this->config[$this->rechner]['url'], PHP_URL_HOST);
	}

	/**
	 * Stellt ein, ob der Rechner per default auf- oder zugeklappt sein soll.
	 *
	 * @param boolean $arg Neuer Wert
	 * @return boolean Wert der Einstellung "expand", wenn arg == NULL
	 */
	public function expand($arg = null) {
		if($arg == null) {
			return $this->expand;
		} else {
			$this->expand = $arg === true;
		}
	}

	/**
	 * Stellt ein, ob das breite oder schmale Layout des Rechners dargestellt werden soll
	 *
	 * @param boolean $arg Neuer Wert
	 * @return boolean Wert der Einstellung "smallLayout", wenn arg == NULL
	 */
	public function smallLayout($arg = null) {
		if($arg == null) {
			return $this->smallLayout;
		} else {
			$this->smallLayout = $arg === true;
		}
	}

	/**
	 * Stellt ein, wie viele Ergebniszeilen per default sichtbar sein sollen. Weitere
	 * Zeilen werden ausgeblendet und sind per JavaScript am Tabellenende einblendbar.
	 *
	 * @param integer $arg Neuer Wert
	 * @return integer Wert der Einstellung "visibleResults", wenn arg == NULL
	 */
	public function visibleResults($arg = null) {
		if($arg == null) {
			return $this->visibleResults;
		} else {
			$this->visibleResults = (int) $arg;
		}
	}

	/**
	 * Stellt ein, ob um den Rechner auch ein HTML-Rahmen mit HEAD und BODY ausgegeben
	 * werden soll.
	 *
	 * @param boolean $arg Neuer Wert
	 * @return boolean Wert der Einstellung "htmlWrap", wenn arg == NULL
	 */
	public function htmlWrap($arg = null) {
		if($arg == null) {
			return $this->htmlWrap;
		} else {
			$this->htmlWrap = $arg === true;
		}
	}

	/**
	 * Stellt das Encoding der Zielseite ein.
	 *
	 * @param string $arg Neuer Wert
	 * @return string Wert der Einstellung "to_encoding", wenn arg == NULL
	 */
	public function to_encoding($arg = null) {
		if($arg == null) {
			return $this->to_encoding;
		} else {
			$this->to_encoding = $arg;
		}
	}
	
	/**
	 * Stellt ausblendbare Produkte in der Ergebnisliste auf unsichtbar.
	 *
	 * @param array $arg Neuer Wert
	 * @return array Wert der Einstellung "hideProducts", wenn arg == NULL
	 */
	public function hideProducts($arg = null) {
		if($arg == null) {
			return $this->hideProducts;
		} else {
			$this->hideProducts = $arg;
		}
	}
	
	/**
	 * Setzt Default-Werte für die Formularfelder
	 *
	 * @param array $arg Neuer Wert
	 * @return array Wert der Einstellung "defaultValues", wenn arg == NULL
	 */
	public function defaultValues($arg = null) {
		if($arg == null) {
			return $this->defaultValues;
		} else {
			$this->defaultValues = $arg;
		}
	}
	
	/**
	 * Ruft die XML-Ergebnisliste mit Hilfe von cURL ab.
	 *
	 * @param string $url Diezu holende URL
	 * @param integer $timeout Timeout für Connect und Abruf in Sekunden, default ist 10
	 * @return string Inhalt der abgerufenen Seite
	 */
	private function fetchXml($url, $timeout = 10) {
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

		$data = curl_exec($ch);
		$error = curl_error($ch);
		curl_close($ch);

		if($error == '') {
			return $data;
		} else {
			return 'Leider konnten keine Daten geladen werden.<br />' . $error;
		}
	}

	
	private function convert_entities($data) {
		$data = str_replace('€', '&euro;', $data);
		if(function_exists('mb_convert_encoding')) {
			return mb_convert_encoding($data, $this->to_encoding(), 'UTF8');
		} else {
			return utf8_decode($data);
		}
	}
	
	/* =========================================================================================== */

	/**
	 * Gibt den Rechner aus.
	 */
	public function render() {
		if(empty($_POST)) {
			foreach($this->defaultValues() as $key => $value) {
				$_POST[$key] = $value;
			} 
		}
		$_REQUEST = array_merge($_GET, $_POST);
		$querystring = '';

		foreach($_REQUEST as $key => $value)
			$querystring .= urlencode($key) .'='. urlencode($value) .'&';

		$xmlstring = $this->fetchXml( $this->config[$this->rechner]['url'] . $this->pid . '/(campaign)/' . $this->campaign .'?'. $querystring );

		if (substr ( $xmlstring, 0, 5 ) == '<?xml' && $xml = simplexml_load_string($xmlstring)) {

			$c = $xml->config;

			$values = array();
			foreach($c->defaultValues->children() as $fieldName => $defaultValue) {
				if(array_key_exists($fieldName, $_REQUEST) && $_REQUEST[$fieldName]) {
					$values[$fieldName] = $_REQUEST[$fieldName];
				} else {
					$values[$fieldName] = (string) $defaultValue;
				}
			}
			ob_start();			
			
			?>
			<?php if($this->htmlWrap()): ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Expertenrechner</title>
<style type="text/css">
@import url(<?php echo $this->domain?>/extension/site_konsumentenkredite/design/konsumentenkredite_user/stylesheets/vergleich_base.css);
</style>
<!--[if IE 6]>
<link rel="stylesheet" href="<?php echo $this->domain?>/extension/site_konsumentenkredite/design/konsumentenkredite_user/stylesheets/vergleich_base_ie6.css" />
<![endif]-->
<style type="text/css">
@import url(<?php echo $this->domain?>/extension/zt_kreditrechner/design/konfigurator/stylesheets/additional/<?php echo $c->CSS?>_<?php echo $this->pid?>_<?php echo $this->campaign?>.css);
</style>
</head>
<body>
			<?php endif; ?>


<script type="text/javascript" src="<?php echo $this->domain?>/extension/site_konsumentenkredite/design/konsumentenkredite_user/javascript/rechner_extern_jquery.js"></script>
<script type="text/javascript" src="<?php echo $this->domain?>/extension/site_konsumentenkredite/design/konsumentenkredite_user/javascript/rechner_extern_xml.js"></script>

<script type="text/javascript">
	jQuery.noConflict();
	var dropdownButtonUpImageSrc	= "<?php echo $this->domain?>/extension/site_konsumentenkredite/design/konsumentenkredite_user/images/button_up.gif"; //URL für Button "Schließen"
	var dropdownButtonDownImageSrc	= "<?php echo $this->domain?>/extension/site_konsumentenkredite/design/konsumentenkredite_user/images/button_dropdown.gif"; //URL für Button "Öffnen"
	var infoButtonUpImageSrc		= "<?php echo $this->domain?>/extension/site_konsumentenkredite/design/konsumentenkredite_user/images/button_x.gif"; //URL für Button "Schließen"
	var infoButtonDownImageSrc		= "<?php echo $this->domain?>/extension/site_konsumentenkredite/design/konsumentenkredite_user/images/button_info.gif"; //URL für Button "Öffnen"

	var spid=<?php echo $this->pid; ?>

</script>

			<?php

			$sortJS = array();
			$nr = 0;

			foreach($c->resultColumns->children() as $name => $column) {
				$sorterMap = array('Number' => 'digit', 'CaseInsensitiveString' => 'text');

				if($this->smallLayout() && ($name == 'award' || $name == 'name'))
				continue;

				if (isset($column->sort)) {
					$sortJS[] = $nr.': { sorter: "'. $sorterMap[(string) $column->sort] .'" }';
				} else {
					$sortJS[] = $nr.': { sorter: false }';
				}
				$nr++;
			}
			$sortJS[] = $nr.': { sorter: false }';
			$sortFields = '{'. implode(", ", $sortJS) .'}';
			?>

<script type="text/javascript">
	var sortFields = <?php echo $sortFields ?>;
	<?php if(isset($c->additionalJavascript)) echo $c->additionalJavascript ?>
	<?php foreach($_REQUEST as $key => $val) : ?>
		var <?php echo htmlspecialchars($key) ?> = '<?php echo htmlspecialchars($val) ?>';
	<?php endforeach; ?>
</script>

	<?php if(isset($c->additionalJavascriptFile)) : ?>
	<?php foreach($c->additionalJavascriptFile as $filename) : ?>
<script type="text/javascript" src="<?php echo $filename ?>"></script>
	<?php endforeach; ?>
	<?php endif; ?>

<div id="vergleichsrechner">
<div class="vergleichsrechner <?php echo $c->cssClass?> <?php echo $this->smallLayout() ? 'small_layout' : ''?>">
<!-- <h1><?php echo Rechner::convert_entities($c->headline)?></h1> -->

	<?php if($c->rechtshinweis): ?>
	<div class="rechtshinweis"><?php echo Rechner::convert_entities($c->rechtshinweis) ?></div>
	<?php endif; ?>

	<div id="rechner_toolbar" style="display:<?php echo ($this->expand() ? 'none' : 'block') ?>;" class="toolbar">
	<form accept-charset="utf-8" action="" method="post" name="rechner_toolbar_form">
	<ul class="el-floated-left">
		<li class="toolbar-title"><?php echo Rechner::convert_entities($c->toolbar) ?></li>
		<li class="toolbar-field-list">
			<ul class="el-floated-left">
			<?php
			$nr = 0;
			foreach($c->rechnerToolbarFields->children() as $name => $field) {
				if($this->smallLayout() && $field->hideOnSmallLayout) $field->type = 'hidden';
				if($field->type == 'hidden') { ?>
					<li style="display: none;"><input type="hidden" name="<?php echo $name?>" value="<?php echo Rechner::convert_entities(htmlspecialchars($values[$name])) ?>" /></li>
				<?php } else {
					$nr++;
					?>
					<li class="toolbar-field-col<?php echo $nr?>"><label><?php echo $field->label?></label><?php echo $this->smallLayout() ? '<br />' : ''?>
					<?php if($field->type == 'text'): ?>
						<input type="text" name="<?php echo $name?>"
						<?php if($field->maxlength) echo 'maxlength="'.$field->maxlength.'"';?> 
						value="<?php echo Rechner::convert_entities(htmlspecialchars($values[$name])) ?>"
						class="default-input-text void"
						onfocus="save=this.value;this.value='';"
						onblur="if (save) this.value=save;" onchange="save=this.value;" /><?php echo $field->unit ? '&nbsp;'. $field->unit : ''?>&nbsp;
		
					<?php elseif($field->type == 'select') : ?>
						<select name="<?php echo $name?>" class="default-select-text">
						<?php foreach($field->option as $option): ?>
							<option value="<?php echo Rechner::convert_entities($option->value)?>"
								<?php echo ($values[$name]==$option->value)?' selected="selected"':''?>><?php echo Rechner::convert_entities($option->label)?></option>
						<?php endforeach; ?>
						</select>
					<?php endif; ?>
					</li>
				<?php }
			} ?>
					<li class="toolbar-field-col<?php echo ++$nr?>">
						<button type="submit" onfocus="blur();" value="Berechnen" class="button_small">Berechnen &raquo;</button>
					</li>
			</ul>
		</li>
		<li class="toolbar-detail-open" title="Details einblenden">DETAILS</li>
	</ul>
	</form>
	</div>


	<div id="rechner_standard" style="display:<?php echo ($this->expand() ? 'block' : 'none') ?>;" class="toolbar_open">
	<form accept-charset="utf-8" action="" method="post" name="rechner_standard_form">
	<div class="toolbar">
		<ul class="el-floated-left">
			<li class="toolbar-title"><?php echo Rechner::convert_entities($c->toolbar) ?></li>
			<li class="toolbar-field-list"><?php
				foreach($c->rechnerStandardHeadline->arg as $name) $args[] = $values[(string)$name];
				vprintf($c->rechnerStandardHeadline->format, $args);
			?></li>
			<li class="toolbar-detail-close">DETAILS</li>
		</ul>
	</div>

	<div class="rechner-detail">
		<ul class="el-floated-left">
			<li class="rechner-detail-data"><?php
			$nr = 0;
			foreach($c->rechnerStandardFields->children() as $name => $field) {
				if($field->type == 'headline') { ?>
					<div class="rechner-detail-info-row">
						<h3 class="heading-underline"><?php echo Rechner::convert_entities($field->value)?></h3>
					</div>
				<?php } else {
					$nr++;
				?>
					<div class="rechner-detail-info-row">
						<div class="rechner-detail-info-label"><label><?php echo Rechner::convert_entities($field->label)?></label></div>
						<div class="rechner-detail-info-formfield"><?php
						if($field->type == 'text') { ?>
							<input type="text" name="<?php echo $name?>"
								<?php if($field->maxlength) echo 'maxlength="'.$field->maxlength.'"'?>
								<?php if($field->size) echo 'size="'.$field->size.'"'?>
								value="<?php echo Rechner::convert_entities(htmlspecialchars($values[$name])) ?>"
								class="default-input-text rechner-detail-input"
								onfocus="save=this.value;this.value='';"
								onblur="if (save) this.value=save;" onchange="save=this.value;" /> <?php
						} else if($field->type == 'radio') { ?>
							<?php foreach($field->option as $option) { ?>
							<input type="radio" name="<?php echo $name?>"
								value="<?php echo $option->value?>"
								<?php echo ($values[$name]==$option->value)?' checked="checked"':''?> /><label>&nbsp;<?php echo Rechner::convert_entities($option->label)?></label>
							<?php }
							if($field->children) {
								foreach($field->children->children() as $childName => $childField) { ?>
									<div id="<?php echo $childName?>_container"><?php
									if($childField->type == 'text') { ?>
									<input type="text" name="<?php echo $childName?>"
										<?php if($childField->maxlength) echo 'maxlength="'.$childField->maxlength.'"'?>
										<?php if($childField->size) echo 'size="'.$childField->size.'"'?>
										value="<?php echo Rechner::convert_entities(htmlspecialchars($values[$childName])) ?>"
										class="default-input-text rechner-detail-input"
										onfocus="save=this.value;this.value='';"
										onblur="if (save) this.value=save;" onchange="save=this.value;" /> <?php
									} else if($childField->type == 'select') { ?>
									<select name="<?php echo $childName?>"
										class="default-select-text rechner-detail-select">
										<?php foreach($childField->option as $option) { ?>
										<option value="<?php echo Rechner::convert_entities($option->value)?>"
											<?php echo ($values[$childName]==$option->value)?' selected="selected"':''?>><?php echo Rechner::convert_entities($option->label)?></option>
										<?php } ?>
									</select> <?php
									} // if ?>
								</div>
								<?php } // foreach
							} // has children
						} else if($field->type == 'select') { ?>
							<select
								name="<?php echo $name?>"
								class="default-select-text rechner-detail-select">
								<?php foreach($field->option as $option) { ?>
									<option value="<?php echo Rechner::convert_entities($option->value)?>"
								<?php echo ($values[$name]==$option->value)?' selected="selected"':''?>><?php echo Rechner::convert_entities($option->label)?></option>
								<?php } ?>
							</select> <?php
						} ?>
						</div>
					</div>
				<?php
				} // !heading
			} // foreach ?>
			</li>

			<li class="rechner-detail-info">
			<?php foreach($c->rechnerStandardFields->children() as $name => $field): ?>
				<?php if($field->type != 'headline'): ?>
				<div class="hidden help-container">
					<h3 class="heading-underline"><span class="help-icon"><?php echo Rechner::convert_entities($field->info[0])?></span></h3>
					<div class="help-text"><?php echo Rechner::convert_entities($field->info[1])?></div>
				</div>
				<?php else: ?>
					<div class="help-container"></div>
				<?php endif; ?>
			<?php endforeach; ?>
			</li>
		</ul>

		<div class="clear-fix"></div>
	</div>

	<div class="button-bar">
	<button type="submit" onfocus="blur();" value="Vergleich aktualisieren"
		name="button" class="button_big_hlcolor">Vergleich aktualisieren
	&raquo;</button>
	</div>

</form>
</div>

<?php if ( $xml->error != '' ) : ?>
	<div id="error">
		<?php echo $xml->error; ?>
	</div>
<?php endif; ?>

<table border="0" cellspacing="0" cellpadding="0"
	class="ergebnisliste sort-table" id="ergebnisliste">
	<thead>
		<tr>
		<?php
		$nr = 0;
		foreach($c->resultColumns->children() as $name => $column) {
			if($this->smallLayout() && ($name == 'award' || $name == 'name'))
			continue;
			$nr++;
			$first = isset($column->first_column) && $column->first_column == true;
			$sortable = isset($column->sort) && $column->sort != 'none';
			?>
			<th class="<?php if($first) echo 'first_column'?> <?php if($sortable) echo 'sortable'?>"><?php echo Rechner::convert_entities($column->label)?></th>
				<?php } // foreach
				?>
			<th>Online-Antrag</th>
		</tr>
	</thead>

	<?php if(count($xml->products->product) > $this->visibleResults) { ?>
	<tfoot>
		<tr class="altRow showall">
			<td colspan="<?php echo ($nr+1)?>" class="showall"><a
				class="linkshowall" href="#">Alle Ergebnisse einblenden &raquo;</a>
			<a class="linknotshowall hidden" href="#">TOP <?php echo $this->visibleResults ?>
			anzeigen &raquo;</a></td>
		</tr>
	</tfoot>
	<?php } ?>

	<tbody>
	<?php
	$link_suffix = isset($_GET["subid"]) ? ('&subid='. $_GET["subid"]) : '';
	//Produkte durchlaufen
	$nr=0;
	foreach ( $xml->products->product as $product ) {
		$attributes = $product->attributes();
		
		if($attributes['own'] == '0' && in_array($attributes['short_name'], $this->hideProducts())) {
			continue;
		}
		$attributes ['link'] .= $link_suffix;
		
		$nr++;

		?>
		<tr class="<?php if($attributes ['recommend'] == '1') echo 'highlighted'; ?><?php if($nr > $this->visibleResults) echo ' additional hidden'?>">
		<?php foreach($c->resultColumns->children() as $name => $column) {
			if($this->smallLayout() && ($name == 'award' || $name == 'name'))
				continue;
			$first = isset($column->first_column) && $column->first_column == true;
			?>
			<td class="<?php if($first) echo 'first_column'?>"
			<?php if($column->hasPopup) echo ' onclick="showHideInfoBox($(\'infobox_container_'. $nr .'\'));"'?>>
			<?php
				if($column->hasLink) echo '<a href="'. htmlspecialchars($attributes ['link']) .'" onclick="'. $attributes ['tracking'] .'" target="_blank" rel="nofollow" title="Weitere Informationen einblenden">';
				if($name == 'bank') { ?> <img
					src="<?php echo $product->images->small?>" alt="bankenlogo"
					width="70" height="26" class="bankenlogo"
					title="<?php echo Rechner::convert_entities($attributes ['bank'])?>" /> <?php echo $this->smallLayout() ? '<br/>'. Rechner::convert_entities($attributes ['name']) : ($column->showName ? Rechner::convert_entities($attributes['name']) : '')?>
				<?php } else if($name == 'award') {
					if($product->productaward) {
						?><img class="testsiegel pimg"
							src="<?php echo $product->productaward->images->small?>" alt=""
							title="<?php echo Rechner::convert_entities(htmlspecialchars($product->productaward->name)) ?>"
							longdesc="<?php echo $product->productaward->images->big?>" /><?php
					}
					?>
				<?php } else if($name == 'info') { ?>
					<a href="#"
						onfocus="blur();" class="info_button_link"
						title="Info ein-/ausblenden"></a>
					<div class="infobox_container">
						<div class="infobox_top"></div>
						<div class="infobox_content">
							<a href="#" onfocus="blur();" class="button_close" title="Fenster schlie&szlig;en"></a>
							<img class="infobox_banklogo" src="<?php echo $product->images->big;?>" alt="Logo" title="<?php echo Rechner::convert_entities($attributes ['bank']);?>" />
							<div class="accordion">
								<h3 class="heading-underline">
									<img class="dropdown_button" src="<?php echo $this->domain?>/extension/site_konsumentenkredite/design/konsumentenkredite_user/images/button_up.gif"
									alt="aufklappen" width="18" height="17" />Produktbeschreibung: <?php echo Rechner::convert_entities($attributes ['name']);?>
								</h3>
								<div class="infolayer"><?php echo Rechner::convert_entities($product->infos->div->asXML());?></div>
							<?php if($product->infos->bankawards) { ?>
								<h3 class="heading-underline">
									<img class="dropdown_button" src="<?php echo $this->domain?>/extension/site_konsumentenkredite/design/konsumentenkredite_user/images/button_dropdown.gif"
									alt="aufklappen" width="18" height="17" />Auszeichnungen
								</h3>
								<div class="infolayer">
									<ul class="arrow-list">
									<?php
									foreach ( $product->infos->bankawards as $bankaward ) { ?>
										<li><?php echo Rechner::convert_entities($bankaward->bankaward) ?></li>
									<?php }	?>
									</ul>
								</div>
							<?php } ?>
							</div>
							<div class="infobox_buttons">
								<a href="<?php echo htmlspecialchars($attributes ['link']);?>" onclick="<?php echo $attributes ['tracking'];?>"
								target="_blank" rel="nofollow" class="button_big_hlcolor"><?php echo Rechner::convert_entities($c->resultLink)?></a>
							</div>
						</div>
						<div class="infobox_bottom"></div>
					</div>
				<?php } else if($name == 'rating') { ?>
					<div class="quotes quote_<?php echo $attributes ['rating'];?>">
						Bewertung: <?php echo Rechner::convert_entities($attributes ['rating']);?>
					</div>
				<?php } else {
					echo Rechner::convert_entities($attributes [$name]);
				} // else ?>
				<?php if($column->hasLink) echo '</a>';?>
			</td>
		<?php } // foreach ?>
	
			<td class="right_column">
				<a href="<?php echo htmlspecialchars($attributes ['link']);?>" onclick="<?php echo $attributes ['tracking'];?>" 
				target="_blank" rel="nofollow" class="button_small"
				title="<?php echo Rechner::convert_entities($attributes ['name']);?> (<?php echo Rechner::convert_entities($attributes ['bank']);?>) jetzt beantragen!">Zum Antrag &raquo;</a>
			</td>
		</tr>

		<?php
	} // foreach
	?>

	</tbody>
</table>
<p class="hinweis"><?php echo Rechner::convert_entities($c->resultNotice)?></p>
</div>
<?php if(isset($c->tracking)) { ?><img src="<?php echo str_replace("____", $this->pid, $c->tracking); ?>" height="1" width="1" border="0" /><?php } ?>
</div>
	<?php if($this->htmlWrap()) { ?>
</body>
</html>
	<?php } // if
$ausgabe = ob_get_contents();
ob_end_clean();
		} // valid XML

return $ausgabe;
	} // render()

} // class

#-------------------------------------------------------------------------------------------------------------

?>

