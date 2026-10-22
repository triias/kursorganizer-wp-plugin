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

    public function testResolveModePrefersValidAttributeOverSettingAndDefaultsToIframe(): void
    {
        self::assertSame('embed', KursOrganizer_Shortcode_URL_Builder::resolve_mode(' Embed ', 'iframe'));
        self::assertSame('iframe', KursOrganizer_Shortcode_URL_Builder::resolve_mode('iframe', 'embed'));
        self::assertSame('embed', KursOrganizer_Shortcode_URL_Builder::resolve_mode('', 'embed'));
        self::assertSame('iframe', KursOrganizer_Shortcode_URL_Builder::resolve_mode('popup', 'unknown'));
        self::assertSame('iframe', KursOrganizer_Shortcode_URL_Builder::resolve_mode(null, null));
    }

    public function testEmbedLoaderUrlLivesNextToTheBuildPath(): void
    {
        self::assertSame(
            'https://app.example.kursorganizer.com/embed/embed.js?_v=1.2.12.5',
            KursOrganizer_Shortcode_URL_Builder::embed_loader_url('https://app.example.kursorganizer.com/build/', '1.2.12.5')
        );
    }

    public function testRenderEmbedMapsParamsToDataAttributesAndKeepsIframeOnlyParamsOut(): void
    {
        $atts = array(
            'city' => 'Köln "Süd"',
            'locationid' => 'location-1',
            'coursetypeids' => 'type-1,type-2',
            'dayfilter' => 'Montag,Dienstag',
            'listtype' => 'interest',
            'showfiltermenu' => 'false',
        );
        $context = array(
            'parent_url' => 'https://wordpress.example/kurs',
            'cache_version' => '1.2.12.5',
            'cache_buster' => 4,
            'custom_css_url' => 'https://wordpress.example/custom.css',
            'max_width' => '90%',
        );
        $params = KursOrganizer_Shortcode_URL_Builder::params($atts, $context);
        $fallback = KursOrganizer_Shortcode_URL_Builder::build('https://app.example.kursorganizer.com/build/', $atts, $context);
        $html = KursOrganizer_Shortcode_URL_Builder::render_embed('kursorganizer-embed-1', $params, $fallback);

        self::assertStringStartsWith('<div data-ko-embed id="kursorganizer-embed-1" class="kursorganizer-embed"', $html);
        self::assertStringContainsString('data-city="Köln &quot;Süd&quot;"', $html);
        self::assertStringContainsString('data-location-id="location-1"', $html);
        self::assertStringContainsString('data-course-type-ids="type-1,type-2"', $html);
        self::assertStringContainsString('data-day-filter="Montag,Dienstag"', $html);
        self::assertStringContainsString('data-list-type="interest"', $html);
        self::assertStringContainsString('data-show-filter-menu="false"', $html);
        self::assertStringContainsString('data-max-width="90%"', $html);
        self::assertStringContainsString('data-fallback-src="' . esc_attr($fallback) . '"', $html);
        self::assertStringContainsString(' style="min-height:400px"', $html);
        self::assertStringEndsWith('></div>', $html);
        // iFrame-only: parentUrl, Cache-Parameter, customCssUrl
        self::assertStringNotContainsString('data-parent-url', $html);
        self::assertStringNotContainsString('data-custom-css-url', $html);
        self::assertStringNotContainsString('data-_v', $html);
        self::assertStringNotContainsString('data-_cb', $html);
    }

    public function testRenderEmbedEscapesAttributeBreakouts(): void
    {
        $params = array('city' => 'x" onmouseover="alert(1)', 'maxWidth' => '1200px');
        $html = KursOrganizer_Shortcode_URL_Builder::render_embed('id-1', $params, 'https://app.example.kursorganizer.com/build/?x=1');
        self::assertStringNotContainsString('" onmouseover="', $html);
        self::assertStringContainsString('data-city="x&quot; onmouseover=&quot;alert(1)"', $html);
    }

    public function testRenderEmbedWrapsTheIframeInNoscript(): void
    {
        $iframe = KursOrganizer_Shortcode_URL_Builder::render_iframe('kursorganizer-iframe-1', array('kursorganizer-iframe'), 'width:1px', 'https://app.example.kursorganizer.com/build/?x=1');
        $html = KursOrganizer_Shortcode_URL_Builder::render_embed('kursorganizer-embed-1', array('maxWidth' => '1200px'), 'https://app.example.kursorganizer.com/build/?x=1', $iframe);
        self::assertStringContainsString('<noscript><iframe id="kursorganizer-iframe-1"', $html);
        self::assertStringEndsWith('</iframe></noscript></div>', $html);
    }
}
