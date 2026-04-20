<?php
/**
 * AGB (General Terms) template — Germany.
 *
 * Pro-only — shipped with the plugin so the gating path can be demoed end-to-end,
 * but only rendered when a Pro license is active (enforced in Generator).
 *
 * @package EuroComply\LegalPages
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title' => 'Allgemeine Geschäftsbedingungen',
	'body'  => <<<HTML
<h2>§ 1 Geltungsbereich</h2>
<p>
	Für alle Bestellungen über unseren Online-Shop durch Verbraucher und Unternehmer gelten die
	nachfolgenden AGB. Verbraucher ist jede natürliche Person, die ein Rechtsgeschäft zu Zwecken
	abschließt, die überwiegend weder ihrer gewerblichen noch ihrer selbständigen beruflichen Tätigkeit
	zugerechnet werden können.
</p>

<h2>§ 2 Vertragspartner, Vertragsschluss</h2>
<p>
	Der Kaufvertrag kommt zustande mit <strong>{{company_name}}</strong>, {{street}},
	{{postal_code}} {{city}}. Die Darstellung der Produkte im Online-Shop stellt kein rechtlich
	bindendes Angebot, sondern einen unverbindlichen Online-Katalog dar.
	Durch Anklicken des Bestellbuttons geben Sie eine verbindliche Bestellung der im Warenkorb
	enthaltenen Waren ab. Die Bestätigung des Eingangs Ihrer Bestellung erfolgt zusammen mit der
	Annahme der Bestellung unmittelbar nach dem Absenden durch automatisierte E-Mail.
</p>

<h2>§ 3 Preise und Versandkosten</h2>
<p>
	Die auf den Produktseiten genannten Preise enthalten die gesetzliche Mehrwertsteuer und sonstige
	Preisbestandteile. Zusätzlich zu den angegebenen Preisen berechnen wir für die Lieferung
	Versandkosten, die im Bestellformular ausgewiesen werden.
</p>

<h2>§ 4 Lieferung, Zahlungsarten</h2>
<p>
	Lieferungen erfolgen innerhalb der von uns ausgewiesenen Lieferländer. Die akzeptierten
	Zahlungsarten werden im Bestellformular angezeigt.
</p>

<h2>§ 5 Eigentumsvorbehalt</h2>
<p>
	Die Ware bleibt bis zur vollständigen Bezahlung unser Eigentum.
</p>

<h2>§ 6 Gewährleistung</h2>
<p>
	Es gelten die gesetzlichen Mängelhaftungsrechte.
</p>

<h2>§ 7 Streitbeilegung</h2>
<p>
	Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung bereit:
	<a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noopener noreferrer">https://ec.europa.eu/consumers/odr/</a>.
</p>

<hr />
<p><small>EuroComply Pro · Version {{year}} — diese AGB-Vorlage ersetzt keine individuelle Rechtsberatung.</small></p>
HTML
,
);
