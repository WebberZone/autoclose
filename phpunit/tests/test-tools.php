<?php
/**
 * Tests for the Tools page preview rendering.
 *
 * @package AutoClose
 */

use WebberZone\AutoClose\Admin\Tools;

/**
 * Preview panel rendering tests.
 */
class ToolsPreviewTest extends WP_UnitTestCase
{

    /**
     * Tools instance.
     *
     * @var Tools
     */
    private $tools;

    /**
     * Set up each test.
     */
    public function set_up()
    {
        parent::set_up();

        $this->tools = new Tools();
    }

    /**
     * A run-mode preview renders each operation's affected count and sample.
     */
    public function test_run_preview_renders_operation_counts_and_sample()
    {
        $preview = array(
        'mode'       => 'dry-run',
        'components' => array(
        'comments'  => array(
        'operations' => array(
         'comments_close' => array(
          'status'     => 'success',
          'affected'   => 2,
          'type_ages'  => array( 'post' => 90 ),
          'post_types' => array( 'post' ),
          'sample'     => array(
                                array(
                                    'ID'         => 12,
                                    'post_title' => 'Example post',
           ),
          ),
          'errors'     => array(),
         ),
                    ),
        ),
        'revisions' => array(),
        ),
        );

        $html = $this->tools->render_preview($preview);

        $this->assertStringContainsString('Preview only', $html);
        $this->assertStringContainsString('Close comments', $html);
        $this->assertStringContainsString('2', $html);
        $this->assertStringContainsString('Example post', $html);
    }
}
