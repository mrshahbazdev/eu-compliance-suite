<?php
/**
 * Impressum template — Switzerland (Art. 3 UWG, Datenschutzgesetz).
 *
 * @package EuroComply\LegalPages
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title' => 'Impressum',
	'body'  => <<<HTML
<h2>Impressum</h2>

<p>
	<strong>{{company_name}}</strong><br />
	{{street}}<br />
	{{postal_code}} {{city}}<br />
	Schweiz
</p>

<h3>Vertreten durch</h3>
<p>{{representative}}</p>

<h3>Kontakt</h3>
<p>
	Telefon: {{phone}}<br />
	E-Mail: <a href="mailto:{{email}}">{{email}}</a><br />
	Telefax: {{fax}}
</p>

<h3>Handelsregister / UID</h3>
<p>
	Handelsregister: {{registry_court}}<br />
	Registernummer: {{registry_number}}<br />
	Mehrwertsteuernummer / UID: {{vat_id}}
</p>

<h3>Berufsaufsicht / Verbände</h3>
<p>{{professional_chamber}}</p>

<h3>Haftungsausschluss</h3>
<p>
	Der Autor übernimmt keinerlei Gewähr hinsichtlich der inhaltlichen Richtigkeit, Genauigkeit,
	Aktualität, Zuverlässigkeit und Vollständigkeit der Informationen.
	Haftungsansprüche gegen den Autor wegen Schäden materieller oder immaterieller Art, welche aus
	dem Zugriff oder der Nutzung bzw. Nichtnutzung der veröffentlichten Informationen, durch
	Missbrauch der Verbindung oder durch technische Störungen entstanden sind, werden ausgeschlossen.
</p>

<hr />
<p><small>
	Dieses Impressum wurde mit dem <strong>EuroComply Legal-Pages-Plugin</strong> erstellt.
	Die Vorlage ist ein rechtlich informierter Startpunkt und ersetzt keine individuelle Rechtsberatung.
</small></p>
HTML
,
);
