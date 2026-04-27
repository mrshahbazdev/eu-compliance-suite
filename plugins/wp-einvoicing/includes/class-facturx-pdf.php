<?php
/**
 * Minimal PDF generator with embedded Factur-X XML attachment.
 *
 * Produces a single-page PDF 1.7 document with the Factur-X CII XML
 * embedded as a PDF file attachment named "factur-x.xml". This is
 * sufficient for parsers that accept non-PDF/A-3 hybrid Factur-X files.
 *
 * Full PDF/A-3 conformance (required for archival-grade compliance
 * in some jurisdictions) is a Pro feature — see Pro Features tab.
 *
 * Design constraints:
 * - No TCPDF / FPDF / mPDF dependency — PHP core only.
 * - Uses built-in PDF base-14 font "Helvetica" (no font embedding).
 * - ASCII-only text in the visible body (the embedded XML is UTF-8).
 *
 * @package EuroComply\EInvoicing
 */

declare( strict_types = 1 );

namespace EuroComply\EInvoicing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FacturxPdf {

	/**
	 * @param array<string,mixed> $invoice_data Invoice payload used to render the visible PDF body.
	 * @param string              $xml          Factur-X CII XML to embed.
	 */
	public static function build( array $invoice_data, string $xml ) : string {
		$lines = self::format_body( $invoice_data );

		// Build page content stream (single page, Helvetica 10pt).
		$stream  = "BT /F1 14 Tf 50 780 Td (" . self::pdf_escape( 'Invoice ' . (string) ( $invoice_data['invoice_number'] ?? '' ) ) . ") Tj ET\n";
		$stream .= "BT /F1 10 Tf 50 760 Td (" . self::pdf_escape( 'Date: ' . (string) ( $invoice_data['issue_date'] ?? '' ) ) . ") Tj ET\n";
		$y       = 730;
		foreach ( $lines as $line ) {
			$stream .= 'BT /F1 10 Tf 50 ' . $y . ' Td (' . self::pdf_escape( $line ) . ") Tj ET\n";
			$y      -= 16;
			if ( $y < 60 ) {
				break;
			}
		}
		$stream .= "BT /F1 8 Tf 50 50 Td (" . self::pdf_escape( 'Factur-X / ZUGFeRD hybrid invoice — machine-readable XML attached (factur-x.xml).' ) . ") Tj ET\n";

		// PDF object table is assembled manually with byte offsets for the xref table.
		$objects = array();
		$objects[] = '';

		// 1: Catalog
		$objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names 7 0 R /AF [8 0 R] >>\nendobj";
		// 2: Pages
		$objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj";
		// 3: Page
		$objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj";
		// 4: Font Helvetica
		$objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj";
		// 5: Content stream
		$length    = strlen( $stream );
		$objects[] = "5 0 obj\n<< /Length {$length} >>\nstream\n{$stream}endstream\nendobj";
		// 6: Embedded file stream (XML payload, stored uncompressed)
		$xml_length = strlen( $xml );
		$objects[]  = "6 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size {$xml_length} >> /Length {$xml_length} >>\nstream\n{$xml}\nendstream\nendobj";
		// 7: Names tree
		$objects[] = "7 0 obj\n<< /EmbeddedFiles << /Names [(factur-x.xml) 8 0 R] >> >>\nendobj";
		// 8: Filespec (AFRelationship Data as per Factur-X spec)
		$objects[] = "8 0 obj\n<< /Type /Filespec /F (factur-x.xml) /UF (factur-x.xml) /AFRelationship /Data /EF << /F 6 0 R /UF 6 0 R >> /Desc (Factur-X CII XML) >>\nendobj";

		// Assemble the PDF with xref table.
		$out     = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n"; // binary comment per PDF spec
		$offsets = array( 0 );
		for ( $i = 1; $i < count( $objects ); $i++ ) {
			$offsets[ $i ] = strlen( $out );
			$out          .= $objects[ $i ] . "\n";
		}
		$xref_start = strlen( $out );
		$count      = count( $objects );
		$out       .= "xref\n0 {$count}\n";
		$out       .= "0000000000 65535 f \n";
		for ( $i = 1; $i < $count; $i++ ) {
			$out .= sprintf( "%010d 00000 n \n", $offsets[ $i ] );
		}
		$out .= "trailer\n<< /Size {$count} /Root 1 0 R >>\n";
		$out .= "startxref\n{$xref_start}\n%%EOF\n";

		return $out;
	}

	/**
	 * @param array<string,mixed> $d
	 *
	 * @return array<int,string>
	 */
	private static function format_body( array $d ) : array {
		$seller  = (array) ( $d['seller'] ?? array() );
		$buyer   = (array) ( $d['buyer'] ?? array() );
		$totals  = (array) ( $d['totals'] ?? array() );
		$cur     = (string) ( $d['currency'] ?? 'EUR' );

		return array(
			'Seller: ' . (string) ( $seller['name'] ?? '' ),
			'Seller VAT ID: ' . (string) ( $seller['vat_id'] ?? '' ),
			'Seller country: ' . (string) ( $seller['country'] ?? '' ),
			'',
			'Buyer: ' . (string) ( $buyer['name'] ?? '' ),
			'Buyer reference: ' . (string) ( $buyer['reference'] ?? '' ),
			'',
			'Currency: ' . $cur,
			'Line total: ' . number_format( (float) ( $totals['line_total'] ?? 0 ), 2, '.', '' ),
			'Tax basis: ' . number_format( (float) ( $totals['tax_basis'] ?? 0 ), 2, '.', '' ),
			'Tax total: ' . number_format( (float) ( $totals['tax_total'] ?? 0 ), 2, '.', '' ),
			'Grand total: ' . number_format( (float) ( $totals['grand_total'] ?? 0 ), 2, '.', '' ),
			'Due payable: ' . number_format( (float) ( $totals['due_payable'] ?? 0 ), 2, '.', '' ),
		);
	}

	/**
	 * Escape a string for PDF text operators. ASCII-only by design.
	 */
	private static function pdf_escape( string $s ) : string {
		// Strip non-ASCII to keep the base-14 Helvetica encoding happy.
		$s = preg_replace( '/[^\x20-\x7E]/', '?', $s );
		$s = str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), (string) $s );
		return $s;
	}
}
