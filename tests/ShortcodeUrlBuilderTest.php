<?php
use PHPUnit\Framework\TestCase;

final class ShortcodeUrlBuilderTest extends TestCase
{
    public function testBuildSanitizesAndEncodesAllParameters(): void
    {
        $url = KursOrganizer_Shortcode_URL_Builder::build(
            'https://app.example.kursorganizer.com/build/',
            array(
                'city' => 'Köln "Süd"',
                'instructorid' => 'trainer_1',
                'coursetypeid' => 'type-1',
                'coursetypeids' => 'type-1,bad value,type-2,type-1',
                'locationid' => 'location-1',
                'dayfilter' => 'Montag,Invalid,Dienstag,Montag" onload="alert(1)',
                'coursecategoryid' => 'category-1',
                'listtype' => ' Interest ',
                'showfiltermenu' => 'false',
            ),
            array(
                'parent_url' => 'https://wordpress.example/kurs?source=test',
                'cache_version' => '1.2.6.123',
                'cache_buster' => 4,
                'custom_css_url' => 'https://wordpress.example/custom.css',
                'max_width' => '90%',
            )
        );

        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame('Köln "Süd"', $query['city']);
        self::assertSame('trainer_1', $query['instructorId']);
        self::assertSame('type-1,type-2', $query['courseTypeIds']);
        self::assertSame('Montag,Dienstag', $query['dayFilter']);
        self::assertSame('interest', $query['listType']);
        self::assertSame('false', $query['showFilterMenu']);
        self::assertSame('90%', $query['maxWidth']);
        self::assertArrayNotHasKey('onload', $query);
    }

    public function testOpaqueIdAndListLimits(): void
    {
        self::assertSame('abc_DEF-123', KursOrganizer_Shortcode_URL_Builder::sanitize_id('abc_DEF-123'));
        self::assertSame('', KursOrganizer_Shortcode_URL_Builder::sanitize_id('abc def'));

        $ids = array();
        for ($index = 0; $index < 60; $index++) {
            $ids[] = 'id-' . $index;
        }
        self::assertCount(50, KursOrganizer_Shortcode_URL_Builder::sanitize_id_list(implode(',', $ids)));
        self::assertSame('true', KursOrganizer_Shortcode_URL_Builder::normalize_boolean('not-a-boolean'));
        self::assertSame('all', KursOrganizer_Shortcode_URL_Builder::normalize_list_type(' ALL '));
        self::assertSame('interest', KursOrganizer_Shortcode_URL_Builder::normalize_list_type('Interest'));
        self::assertSame('courses', KursOrganizer_Shortcode_URL_Builder::normalize_list_type('courses'));
        self::assertSame('', KursOrganizer_Shortcode_URL_Builder::normalize_list_type('unknown'));
        self::assertSame(array('Montag', 'Sonntag'), KursOrganizer_Shortcode_URL_Builder::sanitize_day_filter('montag,Sonntag,unknown'));
    }

    public function testListTypeIsOmittedSoTheTenantDefaultCanApply(): void
    {
        foreach (array('', 'unknown') as $list_type) {
            $url = KursOrganizer_Shortcode_URL_Builder::build(
                'https://app.example.kursorganizer.com/build/',
                array('listtype' => $list_type),
                array()
            );

            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            self::assertArrayNotHasKey('listType', $query);
        }
    }

    /** @dataProvider maxWidthProvider */
    public function testMaxWidthNormalization($input, $expected): void
    {
        self::assertSame($expected, KursOrganizer_Shortcode_URL_Builder::format_max_width($input));
    }

    public function maxWidthProvider(): array
    {
        return array(
            array('800', '800px'),
            array('80%', '80%'),
            array('42.5rem', '42.5rem'),
            array('1px;color:red;2px', '1200px'),
            array('url(javascript:alert(1))', '1200px'),
            array('', '1200px'),
        );
    }

    public function testIframeAttributesCannotBeBroken(): void
    {
        $html = KursOrganizer_Shortcode_URL_Builder::render_iframe(
            'frame" onload="alert(1)',
            array('kursorganizer-iframe'),
            'width: 1px;',
            'https://example.org/build/?dayFilter=Montag" onload="alert(1)'
        );

        self::assertStringNotContainsString('" onload="', $html);
        self::assertStringContainsString('&quot;', $html);
    }

    public function testPublicShortcodeContainsNoValidationOrRemoteCall(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/kursorganizer-wp-plugin.php');
        $start = strpos($source, 'function kursOrganizer_iframe_shortcode');
        $end = strpos($source, "add_shortcode('kursorganizer_iframe'", $start);
        $shortcode_source = substr($source, $start, $end - $start);

        self::assertStringNotContainsString('validate_organization_id(', $shortcode_source);
        self::assertStringNotContainsString('wp_remote_', $shortcode_source);
    }
}
