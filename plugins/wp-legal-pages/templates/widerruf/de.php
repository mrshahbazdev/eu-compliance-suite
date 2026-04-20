<?php
/**
 * Widerrufsbelehrung — Germany, EU-27 B2C distance-selling (Pro).
 *
 * @package EuroComply\LegalPages
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title' => 'Widerrufsbelehrung',
	'body'  => <<<HTML
<h2>Widerrufsrecht</h2>
<p>
	Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen.
	Die Widerrufsfrist beträgt vierzehn Tage ab dem Tag, an dem Sie oder ein von Ihnen benannter Dritter,
	der nicht der Beförderer ist, die Waren in Besitz genommen haben bzw. hat.
</p>

<p>
	Um Ihr Widerrufsrecht auszuüben, müssen Sie uns
	(<strong>{{company_name}}</strong>, {{street}}, {{postal_code}} {{city}},
	E-Mail: <a href="mailto:{{email}}">{{email}}</a>, Telefon: {{phone}})
	mittels einer eindeutigen Erklärung (z. B. ein mit der Post versandter Brief oder E-Mail) über
	Ihren Entschluss, diesen Vertrag zu widerrufen, informieren.
</p>

<h3>Folgen des Widerrufs</h3>
<p>
	Wenn Sie diesen Vertrag widerrufen, haben wir Ihnen alle Zahlungen, die wir von Ihnen erhalten
	haben, einschließlich der Lieferkosten (mit Ausnahme der zusätzlichen Kosten, die sich daraus ergeben,
	dass Sie eine andere Art der Lieferung als die von uns angebotene, günstigste Standardlieferung gewählt
	haben), unverzüglich und spätestens binnen vierzehn Tagen ab dem Tag zurückzuzahlen, an dem die
	Mitteilung über Ihren Widerruf dieses Vertrags bei uns eingegangen ist.
</p>

<h3>Muster-Widerrufsformular</h3>
<p>
	(Wenn Sie den Vertrag widerrufen wollen, dann füllen Sie bitte dieses Formular aus und senden Sie es
	zurück.)
</p>
<pre>
An:   {{company_name}}
      {{street}}
      {{postal_code}} {{city}}
      E-Mail: {{email}}

Hiermit widerrufe(n) ich/wir den von mir/uns abgeschlossenen Vertrag über
den Kauf der folgenden Waren:
________________________________________________

Bestellt am: _______  Erhalten am: _______

Name:        ________________________________________________
Anschrift:   ________________________________________________

Datum / Unterschrift: _______________
</pre>

<hr />
<p><small>EuroComply Pro · Stand {{year}} — diese Widerrufsbelehrung ist rechtlich informiert, ersetzt jedoch keine individuelle Rechtsberatung.</small></p>
HTML
,
);
