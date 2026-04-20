<?php
/**
 * Datenschutzerklärung — Germany / DSGVO (basic starter).
 *
 * @package EuroComply\LegalPages
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title' => 'Datenschutzerklärung',
	'body'  => <<<HTML
<h2>1. Datenschutz auf einen Blick</h2>

<h3>Allgemeine Hinweise</h3>
<p>
	Die folgenden Hinweise geben einen einfachen Überblick darüber, was mit Ihren
	personenbezogenen Daten passiert, wenn Sie diese Website besuchen.
	Personenbezogene Daten sind alle Daten, mit denen Sie persönlich identifiziert werden können.
</p>

<h3>Verantwortliche Stelle</h3>
<p>
	<strong>{{company_name}}</strong><br />
	{{street}}<br />
	{{postal_code}} {{city}}<br />
	E-Mail: <a href="mailto:{{email}}">{{email}}</a><br />
	Telefon: {{phone}}
</p>

<h2>2. Ihre Rechte</h2>
<p>
	Sie haben jederzeit das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der
	Verarbeitung, Datenübertragbarkeit und Widerspruch gemäß Art. 15–21 DSGVO.
	Darüber hinaus besteht ein Beschwerderecht bei der zuständigen Datenschutzbehörde.
</p>

<h2>3. Hosting &amp; Server-Logfiles</h2>
<p>
	Beim Aufrufen der Website werden durch den Hostinganbieter automatisch Informationen in sogenannten
	Server-Logfiles gespeichert (IP-Adresse, Datum/Uhrzeit, User-Agent, Referrer).
	Rechtsgrundlage ist Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an der sicheren Bereitstellung
	und Optimierung der Website).
</p>

<h2>4. Cookies</h2>
<p>
	Diese Website nutzt Cookies. Die Einwilligung erfolgt über unser Cookie-Banner gemäß
	§ 25 TDDDG (ehemals TTDSG) sowie Art. 6 Abs. 1 lit. a und f DSGVO.
	Sie können Ihre Einwilligung jederzeit über die Cookie-Einstellungen widerrufen.
</p>

<h2>5. Kontakt per E-Mail oder Kontaktformular</h2>
<p>
	Wenn Sie uns per E-Mail oder Kontaktformular kontaktieren, verarbeiten wir Ihre Angaben zur
	Bearbeitung der Anfrage (Art. 6 Abs. 1 lit. b bzw. f DSGVO).
	Die Daten werden gelöscht, sobald die Anfrage abschließend bearbeitet ist und keine gesetzlichen
	Aufbewahrungspflichten entgegenstehen.
</p>

<h2>6. Änderungen dieser Datenschutzerklärung</h2>
<p>
	Wir behalten uns vor, diese Datenschutzerklärung anzupassen, damit sie stets den aktuellen rechtlichen
	Anforderungen entspricht.
</p>

<hr />
<p><small>
	Diese Datenschutzerklärung ist eine Basisvorlage aus dem <strong>EuroComply Legal-Pages-Plugin</strong>.
	In der Pro-Version stehen Ihnen detaillierte, juristisch geprüfte Klauseln für gängige
	Drittdienste (Google, Meta, Matomo, Shopify, WooCommerce, Mailing-Provider) zur Verfügung,
	inklusive automatischer Aktualisierung bei Rechtsprechungsänderungen.
</small></p>
HTML
,
);
