<?php

defined('ABSPATH') || exit;

class LC_STV_Mobile_Detect {

    private function lc_stv_get_user_agent(): string {
        $StvUserAgent = filter_input(INPUT_SERVER, 'HTTP_USER_AGENT', FILTER_UNSAFE_RAW);

        if (!is_string($StvUserAgent)) {
            return '';
        }

        return sanitize_text_field(wp_unslash($StvUserAgent));
    }

    public function StvIsMobile(): bool {
        $StvUserAgent = $this->lc_stv_get_user_agent();

        if ($StvUserAgent === '') {
            return false;
        }

        return (bool) preg_match('/(android|mobile|ipad|iphone|ipod|silk|Windows Phone)/i', $StvUserAgent);
    }

    public function StvIsTablet(): bool {
        $StvUserAgent = $this->lc_stv_get_user_agent();

        if ($StvUserAgent === '') {
            return false;
        }

        if (!preg_match('/(mobile)/i', $StvUserAgent)) {
            if (preg_match('/(android|silk)/i', $StvUserAgent)) {
                return true;
            }
        }

        if (preg_match('/(ipad)/i', $StvUserAgent)) {
            if (preg_match('/(mobile)/i', $StvUserAgent)) {
                return true;
            }
        }

        return false;
    }
}
