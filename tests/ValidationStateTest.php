<?php
use PHPUnit\Framework\TestCase;

final class ValidationStateTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['ko_test_options'] = array();
    }

    public function testValidStateSurvivesTemporaryError(): void
    {
        KursOrganizer_Validation_State::mark_valid('https://example.org/build/', 'org-1');
        KursOrganizer_Validation_State::mark_error('https://example.org/build/', 'org-1', 'api_unavailable');

        $state = KursOrganizer_Validation_State::get();
        self::assertSame('valid', $state['match_status']);
        self::assertSame('error', $state['last_check_status']);
        self::assertFalse(KursOrganizer_Validation_State::is_blocked('https://example.org/build/', 'org-1'));
    }

    public function testMismatchRemainsBlockedAcrossTemporaryError(): void
    {
        KursOrganizer_Validation_State::mark_mismatch('https://example.org/build/', 'org-1');
        KursOrganizer_Validation_State::mark_error('https://example.org/build/', 'org-1', 'http_error');

        self::assertTrue(KursOrganizer_Validation_State::is_blocked('https://example.org/build/', 'org-1'));
    }

    public function testChangedConfigurationBecomesUnverified(): void
    {
        KursOrganizer_Validation_State::mark_valid('https://example.org/build/', 'org-1');
        KursOrganizer_Validation_State::mark_error('https://example.org/build/', 'org-2', 'api_unavailable');

        $state = KursOrganizer_Validation_State::get();
        self::assertSame('unverified', $state['match_status']);
        self::assertFalse(KursOrganizer_Validation_State::is_blocked('https://example.org/build/', 'org-2'));
    }
}
