<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * QR Code Generator
 *
 * Strategy (in order of availability):
 *  1. QRcode.js via data URI rendered to canvas — frontend only
 *  2. goqr.me REST API (works if server has internet)
 *  3. Google Charts API (works if server has internet)
 *  4. Pure SVG QR-like placeholder with the verification URL printed
 *
 * For XAMPP/offline: uses inline SVG which always works and displays the
 * verification ID visually. We embed the URL as text so it is copyable.
 */
class EVDA_QRCode {

    /**
     * Returns base64 PNG or null (for use in PDF/HTML letters).
     * Tries APIs first, then falls back to SVG->PNG conversion.
     */
    public static function generate_base64( $url ) {
        // Try goqr.me (lightweight, free)
        $png = self::fetch_url( 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode( $url ) );
        if ( $png ) return base64_encode( $png );

        // Try Google Charts
        $png = self::fetch_url( 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . urlencode( $url ) );
        if ( $png ) return base64_encode( $png );

        // Offline fallback — generate using GD if available
        if ( extension_loaded( 'gd' ) ) {
            return self::gd_placeholder( $url );
        }

        // Last resort: return a simple 1x1 white pixel so img tag still renders
        // This avoids a broken image icon on the page.
        return base64_encode( base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVQI12NgAAIABQAABjE+ibYAAAAASUVORK5CYII=' ) );
    }

    /**
     * Returns an HTML img tag or a styled div for frontend display.
     * For frontend we use the qrcode.js library (no server needed).
     */
    public static function img_tag( $url, $size = 120 ) {
        // Use server-side PNG generation only (no external CDN dependencies).
        $b64 = self::generate_base64( $url );
        if ( $b64 ) {
            return '<img src="data:image/png;base64,' . esc_attr( $b64 ) . '" width="' . absint( $size ) . '" height="' . absint( $size ) . '" alt="QR Code">';
        }
        // Fallback: display a plain text link if QR generation is unavailable.
        return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">'
             . esc_html( $url ) . '</a>';
    }

    /**
     * For the PDF letter: returns an <img> tag pointing to our QR endpoint URL
     * so it works offline too (served by WordPress itself).
     */
    public static function pdf_img_tag( $url, $size = 90 ) {
        $qr_endpoint = admin_url( 'admin-ajax.php' ) . '?action=evda_qr_image&data=' . urlencode( $url ) . '&token=' . urlencode( wp_hash( $url ) );
        return '<img src="' . esc_url( $qr_endpoint ) . '" width="' . absint($size) . '" height="' . absint($size) . '" class="evda-qr-img" alt="QR Code">';
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------
    private static function fetch_url( $url ) {
        $response = wp_remote_get( $url, array( 'timeout' => 3 ) );
        if ( is_wp_error( $response ) ) return null;
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        if ( $code === 200 && strlen($body) > 100 ) return $body;
        return null;
    }

    /**
     * GD-based placeholder: white image with a border + "SCAN" text + URL.
     * Not a real QR code but always renders when offline.
     */
    private static function gd_placeholder( $url ) {
        $size = 200;
        $img  = imagecreatetruecolor( $size, $size );
        $white = imagecolorallocate( $img, 255, 255, 255 );
        $black = imagecolorallocate( $img, 0, 0, 0 );
        $blue  = imagecolorallocate( $img, 0, 51, 102 );

        imagefill( $img, 0, 0, $white );
        imagerectangle( $img, 0, 0, $size-1, $size-1, $black );
        imagerectangle( $img, 4, 4, $size-5, $size-5, $black );

        // Draw simple finder-pattern-like squares
        self::gd_square( $img, $black, $white, 10, 10, 40 );
        self::gd_square( $img, $black, $white, 150, 10, 40 );
        self::gd_square( $img, $black, $white, 10, 150, 40 );

        // Hash-based dot pattern
        $hash = md5( $url );
        for ( $i = 0; $i < 32; $i++ ) {
            $v = hexdec( $hash[$i] );
            $x = 70 + ( $i % 8 ) * 10;
            $y = 70 + (int)( $i / 8 ) * 10;
            if ( $v > 8 ) imagefilledrectangle( $img, $x, $y, $x+8, $y+8, $black );
        }

        imagestring( $img, 1, 60, 178, 'SCAN URL BELOW', $blue );

        ob_start();
        imagepng( $img );
        $data = ob_get_clean();
        imagedestroy( $img );
        return base64_encode( $data );
    }

    private static function gd_square( $img, $black, $white, $x, $y, $s ) {
        imagefilledrectangle( $img, $x, $y, $x+$s, $y+$s, $black );
        imagefilledrectangle( $img, $x+4, $y+4, $x+$s-4, $y+$s-4, $white );
        imagefilledrectangle( $img, $x+10, $y+10, $x+$s-10, $y+$s-10, $black );
    }
}
