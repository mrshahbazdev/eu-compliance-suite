<?php
/**
 * Factur-X / ZUGFeRD 2.x CII XML generator (MINIMUM profile).
 *
 * Produces a Cross-Industry Invoice (CII, UN/CEFACT) XML document conforming
 * to the Factur-X 1.0.07 MINIMUM profile (a.k.a. ZUGFeRD 2.2 MINIMUM).
 *
 * The MINIMUM profile is a reduced-content variant of the EN 16931-compliant
 * BASIC / EN 16931 / EXTENDED profiles. It carries only the fields strictly
 * required for French "Facture électronique" and German B2B pre-validation:
 * seller, buyer, invoice number/date, total amounts, tax totals, currency,
 * and a reference to the full human-readable PDF rendering. Line-item detail
 * is NOT transmitted in MINIMUM — only the aggregate amounts.
 *
 * @see https://fnfe-mpe.org/factur-x/factur-x_en/
 *
 * @package EuroComply\EInvoicing
 */

declare( strict_types = 1 );

namespace EuroComply\EInvoicing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FacturxXml {

	public const GUIDELINE_MINIMUM = 'urn:factur-x.eu:1p0:minimum';
	public const GUIDELINE_BASIC   = 'urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:basic';
	public const GUIDELINE_EN16931 = 'urn:cen.eu:en16931:2017';
	public const GUIDELINE_EXT     = 'urn:cen.eu:en16931:2017#conformant#urn:factur-x.eu:1p0:extended';

	/**
	 * @param array<string,mixed> $data
	 */
	public static function build( array $data ) : string {
		$profile       = isset( $data['profile'] ) ? (string) $data['profile'] : 'minimum';
		$guideline     = self::guideline_for( $profile );
		$invoice_num   = self::esc( (string) ( $data['invoice_number'] ?? '' ) );
		$issue_date    = self::esc( self::format_date( (string) ( $data['issue_date'] ?? '' ) ) );
		$type_code     = self::esc( (string) ( $data['type_code'] ?? '380' ) ); // 380 = Commercial invoice
		$currency      = self::esc( strtoupper( (string) ( $data['currency'] ?? 'EUR' ) ) );
		$seller_name   = self::esc( (string) ( $data['seller']['name'] ?? '' ) );
		$seller_vat    = self::esc( (string) ( $data['seller']['vat_id'] ?? '' ) );
		$seller_country = self::esc( strtoupper( (string) ( $data['seller']['country'] ?? 'DE' ) ) );
		$buyer_name    = self::esc( (string) ( $data['buyer']['name'] ?? '' ) );
		$buyer_ref     = self::esc( (string) ( $data['buyer']['reference'] ?? '' ) );

		$line_total    = self::money( (float) ( $data['totals']['line_total'] ?? 0 ) );
		$tax_basis     = self::money( (float) ( $data['totals']['tax_basis'] ?? 0 ) );
		$tax_total     = self::money( (float) ( $data['totals']['tax_total'] ?? 0 ) );
		$grand_total   = self::money( (float) ( $data['totals']['grand_total'] ?? 0 ) );
		$due_payable   = self::money( (float) ( $data['totals']['due_payable'] ?? $data['totals']['grand_total'] ?? 0 ) );

		$seller_block = '<ram:SellerTradeParty>' .
			'<ram:Name>' . $seller_name . '</ram:Name>' .
			'<ram:PostalTradeAddress><ram:CountryID>' . $seller_country . '</ram:CountryID></ram:PostalTradeAddress>' .
			( $seller_vat
				? '<ram:SpecifiedTaxRegistration><ram:ID schemeID="VA">' . $seller_vat . '</ram:ID></ram:SpecifiedTaxRegistration>'
				: '' ) .
			'</ram:SellerTradeParty>';

		$buyer_block = '<ram:BuyerTradeParty>' .
			'<ram:Name>' . $buyer_name . '</ram:Name>' .
			'</ram:BuyerTradeParty>';

		$buyer_reference = $buyer_ref
			? '<ram:BuyerReference>' . $buyer_ref . '</ram:BuyerReference>'
			: '';

		$xml = '<?xml version="1.0" encoding="UTF-8"?>'
			. '<rsm:CrossIndustryInvoice'
			. ' xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"'
			. ' xmlns:qdt="urn:un:unece:uncefact:data:standard:QualifiedDataType:100"'
			. ' xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100"'
			. ' xmlns:udt="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100">'
			. '<rsm:ExchangedDocumentContext>'
			. '<ram:GuidelineSpecifiedDocumentContextParameter>'
			. '<ram:ID>' . self::esc( $guideline ) . '</ram:ID>'
			. '</ram:GuidelineSpecifiedDocumentContextParameter>'
			. '</rsm:ExchangedDocumentContext>'
			. '<rsm:ExchangedDocument>'
			. '<ram:ID>' . $invoice_num . '</ram:ID>'
			. '<ram:TypeCode>' . $type_code . '</ram:TypeCode>'
			. '<ram:IssueDateTime><udt:DateTimeString format="102">' . $issue_date . '</udt:DateTimeString></ram:IssueDateTime>'
			. '</rsm:ExchangedDocument>'
			. '<rsm:SupplyChainTradeTransaction>'
			. '<ram:ApplicableHeaderTradeAgreement>'
			. $buyer_reference
			. $seller_block
			. $buyer_block
			. '</ram:ApplicableHeaderTradeAgreement>'
			. '<ram:ApplicableHeaderTradeDelivery/>'
			. '<ram:ApplicableHeaderTradeSettlement>'
			. '<ram:InvoiceCurrencyCode>' . $currency . '</ram:InvoiceCurrencyCode>'
			. '<ram:SpecifiedTradeSettlementHeaderMonetarySummation>'
			. '<ram:LineTotalAmount>' . $line_total . '</ram:LineTotalAmount>'
			. '<ram:TaxBasisTotalAmount>' . $tax_basis . '</ram:TaxBasisTotalAmount>'
			. '<ram:TaxTotalAmount currencyID="' . $currency . '">' . $tax_total . '</ram:TaxTotalAmount>'
			. '<ram:GrandTotalAmount>' . $grand_total . '</ram:GrandTotalAmount>'
			. '<ram:DuePayableAmount>' . $due_payable . '</ram:DuePayableAmount>'
			. '</ram:SpecifiedTradeSettlementHeaderMonetarySummation>'
			. '</ram:ApplicableHeaderTradeSettlement>'
			. '</rsm:SupplyChainTradeTransaction>'
			. '</rsm:CrossIndustryInvoice>';

		return $xml;
	}

	public static function guideline_for( string $profile ) : string {
		switch ( $profile ) {
			case 'basic':
				return self::GUIDELINE_BASIC;
			case 'en16931':
				return self::GUIDELINE_EN16931;
			case 'extended':
				return self::GUIDELINE_EXT;
			case 'minimum':
			default:
				return self::GUIDELINE_MINIMUM;
		}
	}

	private static function esc( string $value ) : string {
		return htmlspecialchars( $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}

	private static function money( float $value ) : string {
		return number_format( $value, 2, '.', '' );
	}

	private static function format_date( string $date ) : string {
		if ( '' === $date ) {
			$date = gmdate( 'Y-m-d' );
		}
		$ts = strtotime( $date );
		if ( false === $ts ) {
			$ts = time();
		}
		return gmdate( 'Ymd', $ts );
	}
}
