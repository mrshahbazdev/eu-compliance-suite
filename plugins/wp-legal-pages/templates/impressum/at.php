<?php
/**
 * Impressum template — Austria (§ 5 ECG, § 25 MedienG, § 14 UGB).
 *
 * @package EuroComply\LegalPages
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title' => 'Impressum',
	'body'  => <<<HTML
<h2>Informationen gemäß § 5 E-Commerce-Gesetz, § 25 Mediengesetz und § 14 UGB</h2>

<p>
	<strong>{{company_name}}</strong><br />
	{{street}}<br />
	{{postal_code}} {{city}}<br />
	Österreich
</p>

<h3>Inhaber / Vertretung</h3>
<p>{{representative}}</p>

<h3>Kontakt</h3>
<p>
	Telefon: {{phone}}<br />
	E-Mail: <a href="mailto:{{email}}">{{email}}</a><br />
	Telefax: {{fax}}
</p>

<h3>Firmenbuch / Register</h3>
<p>
	Firmenbuchnummer: {{registry_number}}<br />
	Firmenbuchgericht: {{registry_court}}
</p>

<h3>UID-Nummer</h3>
<p>{{vat_id}}</p>

<h3>Aufsichtsbehörde / Mitgliedschaften</h3>
<p>{{professional_chamber}}</p>

<h3>Berufsbezeichnung &amp; berufsrechtliche Regelungen</h3>
<p>{{professional_title}}</p>

<h3>Online-Streitbeilegung (Art. 14 Abs. 1 ODR-VO)</h3>
<p>
	Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) zur Verfügung,
	die Sie hier finden: <a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noopener noreferrer">https://ec.europa.eu/consumers/odr/</a>.
	Unsere E-Mail-Adresse finden Sie oben im Impressum.
</p>

<hr />
<p><small>
	Dieses Impressum wurde mit dem <strong>EuroComply Legal-Pages-Plugin</strong> erstellt.
	Die Vorlage ist ein rechtlich informierter Startpunkt und ersetzt keine individuelle Rechtsberatung.
</small></p>
HTML
,
);
