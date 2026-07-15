<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Persists the last known organization validation result.
 */
class KursOrganizer_Validation_State
{
    public const OPTION_NAME = 'kursorganizer_validation_state';
    public const CRON_HOOK = 'kursorganizer_daily_validation';

    public static function fingerprint($url, $organization_id)
    {
        return hash('sha256', trim((string) $url) . "\n" . strtolower(trim((string) $organization_id)));
    }

    public static function get()
    {
        $state = get_option(self::OPTION_NAME, array());
        return is_array($state) ? $state : array();
    }

    public static function is_current($url, $organization_id)
    {
        $state = self::get();
        return isset($state['fingerprint'])
            && is_string($state['fingerprint'])
            && $state['fingerprint'] !== ''
            && hash_equals($state['fingerprint'], self::fingerprint($url, $organization_id));
    }

    public static function is_blocked($url, $organization_id)
    {
        $state = self::get();
        return self::is_current($url, $organization_id)
            && isset($state['match_status'])
            && $state['match_status'] === 'mismatch';
    }

    public static function mark_valid($url, $organization_id)
    {
        $now = time();
        self::save(array(
            'fingerprint' => self::fingerprint($url, $organization_id),
            'match_status' => 'valid',
            'last_check_status' => 'success',
            'checked_at' => $now,
            'last_success_at' => $now,
            'error_code' => '',
        ));
    }

    public static function mark_mismatch($url, $organization_id)
    {
        $state = self::get();
        $last_success_at = self::is_current($url, $organization_id) && isset($state['last_success_at'])
            ? absint($state['last_success_at'])
            : 0;
        self::save(array(
            'fingerprint' => self::fingerprint($url, $organization_id),
            'match_status' => 'mismatch',
            'last_check_status' => 'success',
            'checked_at' => time(),
            'last_success_at' => $last_success_at,
            'error_code' => 'organization_mismatch',
        ));
    }

    public static function mark_error($url, $organization_id, $error_code)
    {
        $state = self::get();
        $is_current = self::is_current($url, $organization_id);
        self::save(array(
            'fingerprint' => self::fingerprint($url, $organization_id),
            'match_status' => $is_current && isset($state['match_status'])
                ? $state['match_status']
                : 'unverified',
            'last_check_status' => 'error',
            'checked_at' => time(),
            'last_success_at' => $is_current && isset($state['last_success_at'])
                ? absint($state['last_success_at'])
                : 0,
            'error_code' => sanitize_key($error_code),
        ));
    }

    public static function mark_unverified($url, $organization_id)
    {
        self::save(array(
            'fingerprint' => self::fingerprint($url, $organization_id),
            'match_status' => 'unverified',
            'last_check_status' => '',
            'checked_at' => 0,
            'last_success_at' => 0,
            'error_code' => '',
        ));
    }

    private static function save($state)
    {
        update_option(self::OPTION_NAME, $state, false);
    }
}
