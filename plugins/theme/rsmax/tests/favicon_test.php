<?php
namespace theme_rsmax;

#[\PHPUnit\Framework\Attributes\CoversClass(\theme_rsmax\output\core_renderer::class)]
final class favicon_test extends \advanced_testcase {
    public function test_missing_child_icon_uses_the_real_boost_asset(): void {
        $this->resetAfterTest();
        unset_config('favicon', 'core_admin');
        $page = new \moodle_page();
        $page->set_context(\context_system::instance());
        $page->force_theme('rsmax');
        $renderer = $page->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        $this->assertInstanceOf(\theme_rsmax\output\core_renderer::class, $renderer);
        $this->assertSame(\theme_config::load('boost')->image_url('favicon', 'theme')->out(false),
            $renderer->favicon()->out(false));
    }

    public function test_configured_institutional_icon_stays_in_the_native_file_area(): void {
        $this->resetAfterTest();
        set_config('favicon', 'synthetic-institutional.ico', 'core_admin');
        $page = new \moodle_page();
        $page->set_context(\context_system::instance());
        $page->force_theme('rsmax');
        $url = $page->get_renderer('core', null, RENDERER_TARGET_GENERAL)->favicon()->out(false);
        $this->assertStringContainsString('/core_admin/favicon/', $url);
        $this->assertStringContainsString('synthetic-institutional.ico', $url);
        $this->assertStringNotContainsString('/boost/', $url);
    }
}
