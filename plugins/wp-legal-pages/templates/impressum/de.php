<?php
/**
 * Impressum template — Germany (§5 DDG / §5 TMG, §18 MStV, §5 UStG).
 *
 * Placeholders use {{field_name}} which are filled from the business-info
 * settings. Unfilled non-required placeholders are rendered as empty strings
 * and the surrounding line (if wrapped with <!--IF:field-->...<!--/IF-->)
 * can be stripped — for MVP we keep it simple and let empty lines render.
 *
 * NOTE: This template is an informed starting point, not legal advice.
 *
 * @package EuroComply\LegalPages
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title' => 'Impressum',
	'body'  => <<<HTML
<h2>Angaben gemäß § 5 DDG</h2>
<p>
	<strong>{{company_name}}</strong><br />
	{{street}}<br />
	{{postal_code}} {{city}}<br />
	Deutschland
</p>

<h3>Vertreten durch</h3>
<p>{{representative}}</p>

<h3>Kontakt</h3>
<p>
	Telefon: {{phone}}<br />
	E-Mail: <a href="mailto:{{email}}">{{email}}</a><br />
	Telefax: {{fax}}
</p>

<h3>Registereintrag</h3>
<p>
	Eintragung im Handelsregister.<br />
	Registergericht: {{registry_court}}<br />
	Registernummer: {{registry_number}}
</p>

<h3>Umsatzsteuer-ID</h3>
<p>
	Umsatzsteuer-Identifikationsnummer gemäß § 27 a Umsatzsteuergesetz:<br />
	{{vat_id}}
</p>

<h3>Steuernummer</h3>
<p>{{tax_number}}</p>

<h3>Redaktionell verantwortlich (§ 18 Abs. 2 MStV)</h3>
<p>
	{{responsible_name}}<br />
	{{responsible_addr}}
</p>

<h3>Aufsichtsbehörde / Berufsaufsicht</h3>
<p>{{professional_chamber}}</p>

<h3>Berufsbezeichnung und berufsrechtliche Regelungen</h3>
<p>{{professional_title}}</p>

<h3>EU-Streitschlichtung</h3>
<p>
	Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit:
	<a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noopener noreferrer">https://ec.europa.eu/consumers/odr/</a>.
	Unsere E-Mail-Adresse finden Sie oben im Impressum.
</p>

<h3>Verbraucherstreitbeilegung / Universalschlichtungsstelle</h3>
<p>
	Wir sind nicht bereit oder verpflichtet, an Streitbeilegungsverfahren vor einer
	Verbraucherschlichtungsstelle teilzunehmen.
</p>

<hr />
<p><small>
	Dieses Impressum wurde mit dem <strong>EuroComply Legal-Pages-Plugin</strong> erstellt.
	Die Vorlage ist ein rechtlich informierter Startpunkt und ersetzt keine individuelle Rechtsberatung.
</small></p>
HTML
,
);
