<?php
/**
 * Tests: Configuration Tests.
 *
 * @since      3.0.0
 * @package    Wordlift
 * @subpackage Wordlift/tests
 */

/**
 * Define the
 *
 * @since      3.0.0
 * @package    Wordlift
 * @subpackage Wordlift/tests
 */
class Wordlift_Configuration_Test extends Wordlift_Unit_Test_Case {

	/**
	 * The {@link Wordlift_Configuration_Service} instance.
	 *
	 * @since  3.13.0
	 * @access private
	 * @var \Wordlift_Configuration_Service $configuration_service The {@link Wordlift_Configuration_Service} instance.
	 */
	private $configuration_service;

	private $http_request_count = 0;

	/**
	 * @inheritdoc
	 */
	function setUp() {
		parent::setUp();

		$this->configuration_service = $this->get_wordlift_test()->get_configuration_service();

	}

	function test_wl_configuration_key() {

		$value = uniqid();
		$this->configuration_service->set_key( $value );

		$this->assertEquals( $value, $this->configuration_service->get_key() );
	}

	function test_wl_configuration_site_language() {

		$value = uniqid();
		$this->configuration_service->set_language_code( $value );

		$this->assertEquals( $value, $this->configuration_service->get_language_code() );
	}

	function test_wl_configuration_analyzer_url() {

		// Set the WordLift Key.
		$wordlift_key = uniqid();
		$this->configuration_service->set_key( $wordlift_key );

		$this->assertEquals( WL_CONFIG_WORDLIFT_API_URL_DEFAULT_VALUE . "analyses?key=$wordlift_key", wl_configuration_get_analyzer_url() );

	}

	/**
	 * Test the `link by default` setting.
	 *
	 * @since 3.13.0
	 */
	public function test_is_link_by_default() {

		// Check that the default setting is `true`.
		$this->assertTrue( $this->configuration_service->is_link_by_default() );

		$this->configuration_service->set_link_by_default( true );

		$this->assertTrue( $this->configuration_service->is_link_by_default() );

		$this->configuration_service->set_link_by_default( false );

		$this->assertFalse( $this->configuration_service->is_link_by_default() );

		$this->configuration_service->set_link_by_default( 1 );

		$this->assertFalse( $this->configuration_service->is_link_by_default() );

	}

	/**
	 */
	function test_maybe_update_dataset_uri_new_key_empty() {

		add_filter( 'pre_http_request', array(
			$this,
			'pre_http_request',
		), 10, 3 );

		$this->configuration_service->maybe_update_dataset_uri(
			array( 'key' => uniqid() ),
			array( 'key' => '' )
		);

		$this->assertEquals( 0, $this->http_request_count );

	}

	/**
	 */
	function test_maybe_update_dataset_uri_new_key_not_equal_to_old_key() {

		add_filter( 'pre_http_request', array(
			$this,
			'pre_http_request',
		), 10, 3 );

		$old_key = uniqid( true );
		$new_key = uniqid( true );

		$this->configuration_service->maybe_update_dataset_uri(
			array( 'key' => $old_key ),
			array( 'key' => $new_key )
		);

		$this->assertEquals( 0, $this->http_request_count );

	}

	/**
	 */
	function test_maybe_update_dataset_uri_dataset_uri_not_empty() {

		add_filter( 'pre_http_request', array(
			$this,
			'pre_http_request',
		), 10, 3 );

		$old_key = $new_key = uniqid( true );

		$this->configuration_service->maybe_update_dataset_uri(
			array( 'key' => $old_key ),
			array( 'key' => $new_key )
		);

		$this->assertNotEmpty( $this->configuration_service->get_dataset_uri() );

		$this->assertEquals( 0, $this->http_request_count );

	}

	/**
	 */
	function test_maybe_update_dataset_uri() {

		add_filter( 'pre_http_request', array(
			$this,
			'pre_http_request',
		), 10, 3 );

		$old_dataset_uri = $this->configuration_service->get_dataset_uri();
		$this->configuration_service->set_dataset_uri( '' );

		$old_key = $new_key = uniqid( true );

		$this->configuration_service->maybe_update_dataset_uri(
			array( 'key' => $old_key ),
			array( 'key' => $new_key )
		);

		$this->assertEquals( 1, $this->http_request_count );

		$this->configuration_service->set_dataset_uri( $old_dataset_uri );

	}

	/**
	 * @param bool   $preempt Whether to preempt an HTTP request return. Default false.
	 * @param array  $r       HTTP request arguments.
	 * @param string $url     The request URL.
	 *
	 * @return bool
	 */
	function pre_http_request( $preempt, $r, $url ) {

		$this->http_request_count ++;

		return true;
	}

}
