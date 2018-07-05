<?php

// exit if file access directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Jetpack_Company {
	
	
	const CUSTOM_POST_TYPE       = 'company';
	const CUSTOM_TAXONOMY_TYPE   = 'jetpack-company-category';
	const CUSTOM_TAXONOMY_TAG    = 'jetpack-company-tag';
	const OPTION_NAME            = 'jetpack_company';
	const OPTION_READING_SETTING = 'jetpack_company_posts_per_page';
	public $version = '0.1';
	static function init() {
		static $instance = false;
		if ( ! $instance ) {
			$instance = new Jetpack_Company;
		}
		return $instance;
	}
	/**
	 * Conditionally hook into WordPress.
	 *
	 * Setup user option for enabling CPT
	 * If user has CPT enabled, show in admin
	 */
	function __construct() {
		// Add an option to enable the CPT
		add_action( 'admin_init',                                                      array( $this, 'settings_api_init' ) );
		// Check on theme switch if theme supports CPT and setting is disabled
		add_action( 'after_switch_theme',                                              array( $this, 'activation_post_type_support' ) );
		// Make sure the post types are loaded for imports
		add_action( 'import_start',                                                    array( $this, 'register_post_types' ) );
		// Add to REST API post type whitelist
		add_filter( 'rest_api_allowed_post_types',                                     array( $this, 'allow_company_article_rest_api_type' ) );
		$setting = Jetpack_Options::get_option_and_ensure_autoload( self::OPTION_NAME, '0' );
		// Bail early if Company option is not set and the theme doesn't declare support
		if ( empty( $setting ) && ! $this->site_supports_custom_post_type() ) {
			return;
		}
		// CPT magic
		$this->register_post_types();
		add_action( sprintf( 'add_option_%s', self::OPTION_NAME ),                     array( $this, 'flush_rules_on_enable' ), 10 );
		add_action( sprintf( 'update_option_%s', self::OPTION_NAME ),                  array( $this, 'flush_rules_on_enable' ), 10 );
		add_action( sprintf( 'publish_%s', self::CUSTOM_POST_TYPE),                    array( $this, 'flush_rules_on_first_project' ) );
		add_action( 'after_switch_theme',                                              array( $this, 'flush_rules_on_switch' ) );
		// Admin Customization
		add_filter( 'post_updated_messages',                                           array( $this, 'updated_messages'   ) );
		add_filter( sprintf( 'manage_%s_posts_columns', self::CUSTOM_POST_TYPE),       array( $this, 'edit_admin_columns' ) );
		add_filter( sprintf( 'manage_%s_posts_custom_column', self::CUSTOM_POST_TYPE), array( $this, 'image_column'       ), 10, 2 );
		add_action( 'customize_register',                                              array( $this, 'customize_register' ) );
		if ( defined( 'IS_WPCOM' ) && IS_WPCOM ) {
			// Track all the things
			add_action( sprintf( 'add_option_%s', self::OPTION_NAME ),                 array( $this, 'new_activation_stat_bump' ) );
			add_action( sprintf( 'update_option_%s', self::OPTION_NAME ),              array( $this, 'update_option_stat_bump' ), 11, 2 );
			add_action( sprintf( 'publish_%s', self::CUSTOM_POST_TYPE),                array( $this, 'new_project_stat_bump' ) );
		}
		add_image_size( 'jetpack-company-admin-thumb', 50, 50, true );
		add_action( 'admin_enqueue_scripts',                                           array( $this, 'enqueue_admin_styles'  ) );
		// register jetpack_company shortcode and company shortcode (legacy)
		add_shortcode( 'company_article',                                                    array( $this, 'company_shortcode' ) );
		add_shortcode( 'jetpack_company',                                            array( $this, 'company_article_shortcode' ) );
		// Adjust CPT archive and custom taxonomies to obey CPT reading setting
		add_filter( 'infinite_scroll_settings',                                        array( $this, 'infinite_scroll_click_posts_per_page' ) );
		add_filter( 'infinite_scroll_results',                                         array( $this, 'infinite_scroll_results' ), 10, 3 );
		if ( defined( 'IS_WPCOM' ) && IS_WPCOM ) {
			// Add to Dotcom XML sitemaps
			add_filter( 'wpcom_sitemap_post_types',                                    array( $this, 'add_to_sitemap' ) );
		} else {
			// Add to Jetpack XML sitemap
			add_filter( 'jetpack_sitemap_post_types',                                  array( $this, 'add_to_sitemap' ) );
		}
		// Adjust CPT archive and custom taxonomies to obey CPT reading setting
		add_filter( 'pre_get_posts',                                                   array( $this, 'query_reading_setting' ) );
		// If CPT was enabled programatically and no CPT items exist when user switches away, disable
		if ( $setting && $this->site_supports_custom_post_type() ) {
			add_action( 'switch_theme',                                                array( $this, 'deactivation_post_type_support' ) );
		}
	}
	/**
	 * Add a checkbox field in 'Settings' > 'Writing'
	 * for enabling CPT functionality.
	 *
	 * @return null
	 */
	function settings_api_init() {
		add_settings_field(
			self::OPTION_NAME,
			'<span class="cpt-options">' . __( 'Company', 'jetpack' ) . '</span>',
			array( $this, 'setting_html' ),
			'writing',
			'jetpack_cpt_section'
		);
		register_setting(
			'writing',
			self::OPTION_NAME,
			'intval'
		);
		// Check if CPT is enabled first so that intval doesn't get set to NULL on re-registering
		if ( get_option( self::OPTION_NAME, '0' ) || current_theme_supports( self::CUSTOM_POST_TYPE ) ) {
			register_setting(
				'writing',
				self::OPTION_READING_SETTING,
				'intval'
			);
		}
	}
	/**
	 * HTML code to display a checkbox true/false option
	 * for the Company CPT setting.
	 *
	 * @return html
	 */
	function setting_html() {
		if ( current_theme_supports( self::CUSTOM_POST_TYPE ) ) : ?>
			<p><?php printf( /* translators: %s is the name of a custom post type such as "jetpack-company" */ __( 'Your theme supports <strong>%s</strong>', 'jetpack' ), self::CUSTOM_POST_TYPE ); ?></p>
		<?php else : ?>
			<label for="<?php echo esc_attr( self::OPTION_NAME ); ?>">
				<input name="<?php echo esc_attr( self::OPTION_NAME ); ?>" id="<?php echo esc_attr( self::OPTION_NAME ); ?>" <?php echo checked( get_option( self::OPTION_NAME, '0' ), true, false ); ?> type="checkbox" value="1" />
				<?php esc_html_e( 'Enable Company for this site.', 'jetpack' ); ?>
				<a target="_blank" href="http://en.support.wordpress.com/company/"><?php esc_html_e( 'Learn More', 'jetpack' ); ?></a>
			</label>
		<?php endif;
		if ( get_option( self::OPTION_NAME, '0' ) || current_theme_supports( self::CUSTOM_POST_TYPE ) ) :
			printf( '<p><label for="%1$s">%2$s</label></p>',
				esc_attr( self::OPTION_READING_SETTING ),
				/* translators: %1$s is replaced with an input field for numbers */
				sprintf( __( 'Company pages display at most %1$s articles', 'jetpack' ),
					sprintf( '<input name="%1$s" id="%1$s" type="number" step="1" min="1" value="%2$s" class="small-text" />',
						esc_attr( self::OPTION_READING_SETTING ),
						esc_attr( get_option( self::OPTION_READING_SETTING, '10' ) )
					)
				)
			);
		endif;
	}
	/*
	 * Bump Company > New Activation stat
	 */
	function new_activation_stat_bump() {
		bump_stats_extras( 'company_articles', 'new-activation' );
	}
	/*
	 * Bump Company > Option On/Off stats to get total active
	 */
	function update_option_stat_bump( $old, $new ) {
		if ( empty( $old ) && ! empty( $new ) ) {
			bump_stats_extras( 'company_articles', 'option-on' );
		}
		if ( ! empty( $old ) && empty( $new ) ) {
			bump_stats_extras( 'company_articles', 'option-off' );
		}
	}
	/*
	 * Bump Company > Published Projects stat when projects are published
	 */
	function new_project_stat_bump() {
		bump_stats_extras( 'company_articles', 'published-projects' );
	}
	/**
	* Should this Custom Post Type be made available?
	*/
	function site_supports_custom_post_type() {
		// If the current theme requests it.
		if ( current_theme_supports( self::CUSTOM_POST_TYPE ) || get_option( self::OPTION_NAME, '0' ) ) {
			return true;
		}
		// Otherwise, say no unless something wants to filter us to say yes.
		/** This action is documented in modules/custom-post-types/nova.php */
		return (bool) apply_filters( 'jetpack_enable_cpt', false, self::CUSTOM_POST_TYPE );
	}
	/*
	 * Flush permalinks when CPT option is turned on/off
	 */
	function flush_rules_on_enable() {
		flush_rewrite_rules();
	}
	/*
	 * Count published projects and flush permalinks when first projects is published
	 */
	function flush_rules_on_first_project() {
		$projects = get_transient( 'jetpack-company-count-cache' );
		if ( false === $projects ) {
			flush_rewrite_rules();
			$projects = (int) wp_count_posts( self::CUSTOM_POST_TYPE )->publish;
			if ( ! empty( $projects ) ) {
				set_transient( 'jetpack-company-count-cache', $projects, HOUR_IN_SECONDS * 12 );
			}
		}
	}
	/*
	 * Flush permalinks when CPT supported theme is activated
	 */
	function flush_rules_on_switch() {
		if ( current_theme_supports( self::CUSTOM_POST_TYPE ) ) {
			flush_rewrite_rules();
		}
	}
	/**
	 * On plugin/theme activation, check if current theme supports CPT
	 */
	static function activation_post_type_support() {
		if ( current_theme_supports( self::CUSTOM_POST_TYPE ) ) {
			update_option( self::OPTION_NAME, '1' );
		}
	}
	/**
	 * On theme switch, check if CPT item exists and disable if not
	 */
	function deactivation_post_type_support() {
		$company = get_posts( array(
			'fields'           => 'ids',
			'posts_per_page'   => 1,
			'post_type'        => self::CUSTOM_POST_TYPE,
			'suppress_filters' => false
		) );
		if ( empty( $company ) ) {
			update_option( self::OPTION_NAME, '0' );
		}
	}
	/**
	 * Register Post Type
	 */
	function register_post_types() {		
		if ( post_type_exists( self::CUSTOM_POST_TYPE ) ) {
			return;
		}
		register_post_type( self::CUSTOM_POST_TYPE, array(
			'description' => __( 'Company Items', 'jetpack' ),
			'labels' => array(
				'name'                  => esc_html__( 'Company Articles',                   'jetpack' ),
				'singular_name'         => esc_html__( 'Company Article',                    'jetpack' ),
				'menu_name'             => esc_html__( 'Company',                  'jetpack' ),
				'all_items'             => esc_html__( 'All Articles',               'jetpack' ),
				'add_new'               => esc_html__( 'Add New',                    'jetpack' ),
				'add_new_item'          => esc_html__( 'Add New Article',            'jetpack' ),
				'edit_item'             => esc_html__( 'Edit Article',               'jetpack' ),
				'new_item'              => esc_html__( 'New Article',                'jetpack' ),
				'view_item'             => esc_html__( 'View Article',               'jetpack' ),
				'search_items'          => esc_html__( 'Search Articles',            'jetpack' ),
				'not_found'             => esc_html__( 'No Articles found',          'jetpack' ),
				'not_found_in_trash'    => esc_html__( 'No Articles found in Trash', 'jetpack' ),
				'filter_items_list'     => esc_html__( 'Filter articles list',       'jetpack' ),
				'items_list_navigation' => esc_html__( 'Article list navigation',    'jetpack' ),
				'items_list'            => esc_html__( 'Articles list',              'jetpack' ),
			),
			'supports' => array(
				'title',
				'editor',
				'thumbnail',
				'author',
				'comments',
				'publicize',
				'wpcom-markdown',
				'revisions',
				'excerpt',
			),
			'rewrite' => array(
				'slug'       => 'company',
				'with_front' => false,
				'feeds'      => true,
				'pages'      => true,
			),
			'public'          => false,
			'show_in_nav_menus' => true,
			'show_ui'         => true,
			'menu_position'   => '',                    // below Pages
			'menu_icon'       => 'dashicons-admin-post', // 3.8+ dashicon option
			'capability_type' => 'page',
 'capabilities' => array( // allow only to editors and up
 'publish_posts' => 'edit_others_posts',
 'edit_posts' => 'edit_others_posts',
 'edit_others_posts' => 'edit_others_posts',
 'delete_posts' => 'edit_others_posts',
 'delete_others_posts' => 'edit_others_posts',
 'read_private_posts' => 'edit_others_posts',
 'edit_post' => 'edit_others_posts',
 'delete_post' => 'edit_others_posts',
 'read_post' => 'edit_others_posts',
 ),
			
			'taxonomies'      => array( self::CUSTOM_TAXONOMY_TYPE, self::CUSTOM_TAXONOMY_TAG ),
			'has_archive'     => true,
			'query_var'       => 'company',
			'show_in_rest'    => true,
		) );
		register_taxonomy( self::CUSTOM_TAXONOMY_TYPE, self::CUSTOM_POST_TYPE, array(
			'hierarchical'      => true,
			'labels'            => array(
				'name'                  => esc_html__( 'Categories',                 'jetpack' ),
				'singular_name'         => esc_html__( 'Category',                  'jetpack' ),
				'menu_name'             => esc_html__( 'Company Categories',                 'jetpack' ),
				'all_items'             => esc_html__( 'All Categories',             'jetpack' ),
				'edit_item'             => esc_html__( 'Edit Category',             'jetpack' ),
				'view_item'             => esc_html__( 'View Category',             'jetpack' ),
				'update_item'           => esc_html__( 'Update Category',           'jetpack' ),
				'add_new_item'          => esc_html__( 'Add New Category',          'jetpack' ),
				'new_item_name'         => esc_html__( 'New Category Name',         'jetpack' ),
				'parent_item'           => esc_html__( 'Parent Category',           'jetpack' ),
				'parent_item_colon'     => esc_html__( 'Parent Category:',          'jetpack' ),
				'search_items'          => esc_html__( 'Search Categories',          'jetpack' ),
				'items_list_navigation' => esc_html__( 'Category list navigation',  'jetpack' ),
				'items_list'            => esc_html__( 'Category list',             'jetpack' ),
			),
			'public'          => false,
			'show_in_nav_menus' => true,
			'show_ui'           => true,
			'show_in_nav_menus' => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'project-type' ),
		) );
		register_taxonomy( self::CUSTOM_TAXONOMY_TAG, self::CUSTOM_POST_TYPE, array(
			'hierarchical'      => false,
			'labels'            => array(
				'name'                       => esc_html__( 'Tags',                   'jetpack' ),
				'singular_name'              => esc_html__( 'Tag',                    'jetpack' ),
				'menu_name'                  => esc_html__( 'Company Tags',                   'jetpack' ),
				'all_items'                  => esc_html__( 'All Tags',               'jetpack' ),
				'edit_item'                  => esc_html__( 'Edit Tag',               'jetpack' ),
				'view_item'                  => esc_html__( 'View Tag',               'jetpack' ),
				'update_item'                => esc_html__( 'Update Tag',             'jetpack' ),
				'add_new_item'               => esc_html__( 'Add New Tag',            'jetpack' ),
				'new_item_name'              => esc_html__( 'New Tag Name',           'jetpack' ),
				'search_items'               => esc_html__( 'Search Tags',            'jetpack' ),
				'popular_items'              => esc_html__( 'Popular Tags',           'jetpack' ),
				'separate_items_with_commas' => esc_html__( 'Separate tags with commas',      'jetpack' ),
				'add_or_remove_items'        => esc_html__( 'Add or remove tags',             'jetpack' ),
				'choose_from_most_used'      => esc_html__( 'Choose from the most used tags', 'jetpack' ),
				'not_found'                  => esc_html__( 'No tags found.',                 'jetpack' ),
				'items_list_navigation'      => esc_html__( 'Tag list navigation',    'jetpack' ),
				'items_list'                 => esc_html__( 'Tag list',               'jetpack' ),
			),
			'public'          => false,
			'show_in_nav_menus' => true,
			'show_ui'           => true,
			'show_in_nav_menus' => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'project-tag' ),
		) );
	}
	/**
	 * Update messages for the Company admin.
	 */
	function updated_messages( $messages ) {
		global $post;
		$messages[self::CUSTOM_POST_TYPE] = array(
			0  => '', // Unused. Messages start at index 1.
			1  => sprintf( __( 'Article updated. <a href="%s">View item</a>', 'jetpack'), esc_url( get_permalink( $post->ID ) ) ),
			2  => esc_html__( 'Custom field updated.', 'jetpack' ),
			3  => esc_html__( 'Custom field deleted.', 'jetpack' ),
			4  => esc_html__( 'Article updated.', 'jetpack' ),
			/* translators: %s: date and time of the revision */
			5  => isset( $_GET['revision'] ) ? sprintf( esc_html__( 'Article restored to revision from %s', 'jetpack'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6  => sprintf( __( 'Article published. <a href="%s">View article</a>', 'jetpack' ), esc_url( get_permalink( $post->ID ) ) ),
			7  => esc_html__( 'Article saved.', 'jetpack' ),
			8  => sprintf( __( 'Article submitted. <a target="_blank" href="%s">Preview article</a>', 'jetpack'), esc_url( add_query_arg( 'preview', 'true', get_permalink( $post->ID ) ) ) ),
			9  => sprintf( __( 'Article scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview article</a>', 'jetpack' ),
			// translators: Publish box date format, see http://php.net/date
			date_i18n( __( 'M j, Y @ G:i', 'jetpack' ), strtotime( $post->post_date ) ), esc_url( get_permalink( $post->ID ) ) ),
			10 => sprintf( __( 'Article item draft updated. <a target="_blank" href="%s">Preview article</a>', 'jetpack' ), esc_url( add_query_arg( 'preview', 'true', get_permalink( $post->ID ) ) ) ),
		);
		return $messages;
	}
	/**
	 * Change ‘Title’ column label
	 * Add Featured Image column
	 */
	function edit_admin_columns( $columns ) {
		// change 'Title' to 'Articles'
		$columns['title'] = __( 'Articles', 'jetpack' );
		if ( current_theme_supports( 'post-thumbnails' ) ) {
			// add featured image before 'Articles'
			$columns = array_slice( $columns, 0, 1, true ) + array( 'thumbnail' => '' ) + array_slice( $columns, 1, NULL, true );
		}
		return $columns;
	}
	/**
	 * Add featured image to column
	 */
	function image_column( $column, $post_id ) {
		global $post;
		switch ( $column ) {
			case 'thumbnail':
				echo get_the_post_thumbnail( $post_id, 'jetpack-company-admin-thumb' );
				break;
		}
	}
	/**
	 * Adjust image column width
	 */
	function enqueue_admin_styles( $hook ) {
		$screen = get_current_screen();
		if ( 'edit.php' == $hook && self::CUSTOM_POST_TYPE == $screen->post_type && current_theme_supports( 'post-thumbnails' ) ) {
			wp_add_inline_style( 'wp-admin', '.manage-column.column-thumbnail { width: 50px; } @media screen and (max-width: 360px) { .column-thumbnail{ display:none; } }' );
		}
	}
	/**
	 * Adds company section to the Customizer.
	 */
	function customize_register( $wp_customize ) {
		$options = get_theme_support( self::CUSTOM_POST_TYPE );
		if ( ( ! isset( $options[0]['title'] ) || true !== $options[0]['title'] ) && ( ! isset( $options[0]['content'] ) || true !== $options[0]['content'] ) && ( ! isset( $options[0]['featured-image'] ) || true !== $options[0]['featured-image'] ) ) {
			return;
		}
		$wp_customize->add_section( 'jetpack_company', array(
			'title'                    => esc_html__( 'Company', 'jetpack' ),
			'theme_supports'           => self::CUSTOM_POST_TYPE,
			'priority'                 => 130,
		) );
		if ( isset( $options[0]['title'] ) && true === $options[0]['title'] ) {
			$wp_customize->add_setting( 'jetpack_company_title', array(
				'default'              => esc_html__( 'Articles', 'jetpack' ),
				'type'                 => 'option',
				'sanitize_callback'    => 'sanitize_text_field',
				'sanitize_js_callback' => 'sanitize_text_field',
			) );
			$wp_customize->add_control( 'jetpack_company_title', array(
				'section'              => 'jetpack_company',
				'label'                => esc_html__( 'Company Archive Title', 'jetpack' ),
				'type'                 => 'text',
			) );
		}
		if ( isset( $options[0]['content'] ) && true === $options[0]['content'] ) {
			$wp_customize->add_setting( 'jetpack_company_content', array(
				'default'              => '',
				'type'                 => 'option',
				'sanitize_callback'    => 'wp_kses_post',
				'sanitize_js_callback' => 'wp_kses_post',
			) );
			$wp_customize->add_control( 'jetpack_company_content', array(
				'section'              => 'jetpack_company',
				'label'                => esc_html__( 'Company Archive Content', 'jetpack' ),
				'type'                 => 'textarea',
			) );
		}
		if ( isset( $options[0]['featured-image'] ) && true === $options[0]['featured-image'] ) {
			$wp_customize->add_setting( 'jetpack_company_featured_image', array(
				'default'              => '',
				'type'                 => 'option',
				'sanitize_callback'    => 'attachment_url_to_postid',
				'sanitize_js_callback' => 'attachment_url_to_postid',
				'theme_supports'       => 'post-thumbnails',
			) );
			$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'jetpack_company_featured_image', array(
				'section'              => 'jetpack_company',
				'label'                => esc_html__( 'Company Archive Featured Image', 'jetpack' ),
			) ) );
		}
	}
	/**
	 * Follow CPT reading setting on CPT archive and taxonomy pages
	 */
	function query_reading_setting( $query ) {
		if ( ( ! is_admin() || ( is_admin() && defined( 'DOING_AJAX' ) && DOING_AJAX ) )
			&& $query->is_main_query()
			&& ( $query->is_post_type_archive( self::CUSTOM_POST_TYPE )
				|| $query->is_tax( self::CUSTOM_TAXONOMY_TYPE )
				|| $query->is_tax( self::CUSTOM_TAXONOMY_TAG ) )
		) {
			$query->set( 'posts_per_page', get_option( self::OPTION_READING_SETTING, '10' ) );
		}
	}
	/*
	 * If Infinite Scroll is set to 'click', use our custom reading setting instead of core's `posts_per_page`.
	 */
	function infinite_scroll_click_posts_per_page( $settings ) {
		global $wp_query;
		if ( ( ! is_admin() || ( is_admin() && defined( 'DOING_AJAX' ) && DOING_AJAX ) )
			&& true === $settings['click_handle']
			&& ( $wp_query->is_post_type_archive( self::CUSTOM_POST_TYPE )
				|| $wp_query->is_tax( self::CUSTOM_TAXONOMY_TYPE )
				|| $wp_query->is_tax( self::CUSTOM_TAXONOMY_TAG ) )
		) {
			$settings['posts_per_page'] = get_option( self::OPTION_READING_SETTING, $settings['posts_per_page'] );
		}
		return $settings;
	}
	/*
	 * Filter the results of infinite scroll to make sure we get `lastbatch` right.
	 */
	function infinite_scroll_results( $results, $query_args, $query ) {
		$results['lastbatch'] = $query_args['paged'] >= $query->max_num_pages;
		return $results;
	}
	/**
	 * Add CPT to Dotcom sitemap
	 */
	function add_to_sitemap( $post_types ) {
		$post_types[] = self::CUSTOM_POST_TYPE;
		return $post_types;
	}
	/**
	 * Add to REST API post type whitelist
	 */
	function allow_company_rest_api_type( $post_types ) {
		$post_types[] = self::CUSTOM_POST_TYPE;
		return $post_types;
	}
	/**
	 * Our [company] shortcode.
	 * Prints Company data styled to look good on *any* theme.
	 *
	 * @return company_shortcode_html
	 */
	static function company_shortcode( $atts ) {
		// Default attributes
		$atts = shortcode_atts( array(
			'display_types'   => true,
			'display_tags'    => true,
			'display_content' => true,
			'display_author'  => false,
			'show_filter'     => false,
			'include_type'    => false,
			'include_tag'     => false,
			'columns'         => 2,
			'showposts'       => -1,
			'order'           => 'asc',
			'orderby'         => 'date',
		), $atts, 'company' );
		// A little sanitization
		if ( $atts['display_types'] && 'true' != $atts['display_types'] ) {
			$atts['display_types'] = false;
		}
		if ( $atts['display_tags'] && 'true' != $atts['display_tags'] ) {
			$atts['display_tags'] = false;
		}
		if ( $atts['display_author'] && 'true' != $atts['display_author'] ) {
			$atts['display_author'] = false;
		}
		if ( $atts['display_content'] && 'true' != $atts['display_content'] && 'full' != $atts['display_content'] ) {
			$atts['display_content'] = false;
		}
		if ( $atts['include_type'] ) {
			$atts['include_type'] = explode( ',', str_replace( ' ', '', $atts['include_type'] ) );
		}
		if ( $atts['include_tag'] ) {
			$atts['include_tag'] = explode( ',', str_replace( ' ', '', $atts['include_tag'] ) );
		}
		$atts['columns'] = absint( $atts['columns'] );
		$atts['showposts'] = intval( $atts['showposts'] );
		if ( $atts['order'] ) {
			$atts['order'] = urldecode( $atts['order'] );
			$atts['order'] = strtoupper( $atts['order'] );
			if ( 'DESC' != $atts['order'] ) {
				$atts['order'] = 'ASC';
			}
		}
		if ( $atts['orderby'] ) {
			$atts['orderby'] = urldecode( $atts['orderby'] );
			$atts['orderby'] = strtolower( $atts['orderby'] );
			$allowed_keys = array( 'author', 'date', 'title', 'rand' );
			$parsed = array();
			foreach ( explode( ',', $atts['orderby'] ) as $company_index_number => $orderby ) {
				if ( ! in_array( $orderby, $allowed_keys ) ) {
					continue;
				}
				$parsed[] = $orderby;
			}
			if ( empty( $parsed ) ) {
				unset( $atts['orderby'] );
			} else {
				$atts['orderby'] = implode( ' ', $parsed );
			}
		}
		// enqueue shortcode styles when shortcode is used
		wp_enqueue_style( 'jetpack-company-style', plugins_url( 'css/portfolio-shortcode.css', __FILE__ ), array(), '20140326' );
		return self::company_shortcode_html( $atts );
	}
	/**
	 * Query to retrieve entries from the Company post_type.
	 *
	 * @return object
	 */
	static function company_query( $atts ) {
		// Default query arguments
		$default = array(
			'order'          => $atts['order'],
			'orderby'        => $atts['orderby'],
			'posts_per_page' => $atts['showposts'],
		);
		$args = wp_parse_args( $atts, $default );
		$args['post_type'] = self::CUSTOM_POST_TYPE; // Force this post type
		if ( false != $atts['include_type'] || false != $atts['include_tag'] ) {
			$args['tax_query'] = array();
		}
		// If 'include_type' has been set use it on the main query
		if ( false != $atts['include_type'] ) {
			array_push( $args['tax_query'], array(
				'taxonomy' => self::CUSTOM_TAXONOMY_TYPE,
				'field'    => 'slug',
				'terms'    => $atts['include_type'],
			) );
		}
		// If 'include_tag' has been set use it on the main query
		if ( false != $atts['include_tag'] ) {
			array_push( $args['tax_query'], array(
				'taxonomy' => self::CUSTOM_TAXONOMY_TAG,
				'field'    => 'slug',
				'terms'    => $atts['include_tag'],
			) );
		}
		if ( false != $atts['include_type'] && false != $atts['include_tag'] ) {
			$args['tax_query']['relation'] = 'AND';
		}
		// Run the query and return
		$query = new WP_Query( $args );
		return $query;
	}
	/**
	 * The Company shortcode loop.
	 *
	 * @todo add theme color styles
	 * @return html
	 */
	static function company_shortcode_html( $atts ) {
		$query = self::company_query( $atts );
		$company_index_number = 0;
		ob_start();
		// If we have posts, create the html
		// with company markup
		if ( $query->have_posts() ) {
			// Render styles
			//self::themecolor_styles();
		?>
			<div class="jetpack-company-shortcode column-<?php echo esc_attr( $atts['columns'] ); ?>">
			<?php  // open .jetpack-company
			// Construct the loop...
			while ( $query->have_posts() ) {
				$query->the_post();
				$post_id = get_the_ID();
				?>
				<div class="company-entry <?php echo esc_attr( self::get_project_class( $company_article_index_number, $atts['columns'] ) ); ?>">
					<header class="company-entry-header">
					<?php
					// Featured image
					echo self::get_company_thumbnail_link( $post_id );
					?>

					<h2 class="company-entry-title"><a href="<?php echo esc_url( get_permalink() ); ?>" title="<?php echo esc_attr( the_title_attribute( ) ); ?>"><?php the_title(); ?></a></h2>

						<div class="company-entry-meta">
						<?php
						if ( false != $atts['display_types'] ) {
							echo self::get_project_type( $post_id );
						}
						if ( false != $atts['display_tags'] ) {
							echo self::get_project_tags( $post_id );
						}
						if ( false != $atts['display_author'] ) {
							echo self::get_project_author( $post_id );
						}
						?>
						</div>

					</header>

				<?php
				// The content
				if ( false !== $atts['display_content'] ) {
					add_filter( 'wordads_inpost_disable', '__return_true', 20 );
					if ( 'full' === $atts['display_content'] ) {
					?>
						<div class="company-entry-content"><?php the_content(); ?></div>
					<?php
					} else {
					?>
						<div class="company-entry-content"><?php the_excerpt(); ?></div>
					<?php
					}
					remove_filter( 'wordads_inpost_disable', '__return_true', 20 );
				}
				?>
				</div><!-- close .company-entry -->
				<?php $company_index_number++;
			} // end of while loop
			wp_reset_postdata();
			?>
			</div><!-- close .jetpack-company -->
		<?php
		} else { ?>
			<p><em><?php _e( 'Your Company Archive currently has no entries. You can start creating them on your dashboard.', 'jetpack' ); ?></p></em>
		<?php
		}
		$html = ob_get_clean();
		// If there is a [company] within a [company], remove the shortcode
		if ( has_shortcode( $html, 'company' ) ){
			remove_shortcode( 'company' );
		}
		// Return the HTML block
		return $html;
	}
	/**
	 * Individual project class
	 *
	 * @return string
	 */
	static function get_project_class( $company_index_number, $columns ) {
		$project_types = wp_get_object_terms( get_the_ID(), self::CUSTOM_TAXONOMY_TYPE, array( 'fields' => 'slugs' ) );
		$class = array();
		$class[] = 'company-entry-column-'.$columns;
		// add a type- class for each project type
		foreach ( $project_types as $project_type ) {
			$class[] = 'type-' . esc_html( $project_type );
		}
		if( $columns > 1) {
			if ( ( $company_index_number % 2 ) == 0 ) {
				$class[] = 'company-entry-mobile-first-item-row';
			} else {
				$class[] = 'company-entry-mobile-last-item-row';
			}
		}
		// add first and last classes to first and last items in a row
		if ( ( $company_index_number % $columns ) == 0 ) {
			$class[] = 'company-entry-first-item-row';
		} elseif ( ( $company_index_number % $columns ) == ( $columns - 1 ) ) {
			$class[] = 'company-entry-last-item-row';
		}
		/**
		 * Filter the class applied to project div in the company
		 *
		 * @module custom-content-types
		 *
		 * @since 3.1.0
		 *
		 * @param string $class class name of the div.
		 * @param int $company_index_number iterator count the number of columns up starting from 0.
		 * @param int $columns number of columns to display the content in.
		 *
		 */
		return apply_filters( 'company-project-post-class', implode( " ", $class ) , $company_index_number, $columns );
	}
	/**
	 * Displays the project type that a project belongs to.
	 *
	 * @return html
	 */
	static function get_project_type( $post_id ) {
		$project_types = get_the_terms( $post_id, self::CUSTOM_TAXONOMY_TYPE );
		// If no types, return empty string
		if ( empty( $project_types ) || is_wp_error( $project_types ) ) {
			return;
		}
		$html = '<div class="project-types"><span>' . __( 'Types', 'jetpack' ) . ':</span>';
		$types = array();
		// Loop thorugh all the types
		foreach ( $project_types as $project_type ) {
			$project_type_link = get_term_link( $project_type, self::CUSTOM_TAXONOMY_TYPE );
			if ( is_wp_error( $project_type_link ) ) {
				return $project_type_link;
			}
			$types[] = '<a href="' . esc_url( $project_type_link ) . '" rel="tag">' . esc_html( $project_type->name ) . '</a>';
		}
		$html .= ' '.implode( ', ', $types );
		$html .= '</div>';
		return $html;
	}
	/**
	 * Displays the project tags that a project belongs to.
	 *
	 * @return html
	 */
	static function get_project_tags( $post_id ) {
		$project_tags = get_the_terms( $post_id, self::CUSTOM_TAXONOMY_TAG );
		// If no tags, return empty string
		if ( empty( $project_tags ) || is_wp_error( $project_tags ) ) {
			return false;
		}
		$html = '<div class="project-tags"><span>' . __( 'Tags', 'jetpack' ) . ':</span>';
		$tags = array();
		// Loop thorugh all the tags
		foreach ( $project_tags as $project_tag ) {
			$project_tag_link = get_term_link( $project_tag, self::CUSTOM_TAXONOMY_TYPE );
			if ( is_wp_error( $project_tag_link ) ) {
				return $project_tag_link;
			}
			$tags[] = '<a href="' . esc_url( $project_tag_link ) . '" rel="tag">' . esc_html( $project_tag->name ) . '</a>';
		}
		$html .= ' '. implode( ', ', $tags );
		$html .= '</div>';
		return $html;
	}
	/**
	 * Displays the author of the current company project.
	 *
	 * @return html
	 */
	static function get_project_author() {
		$html = '<div class="project-author">';
		/* translators: %1$s is link to author posts, %2$s is author display name */
		$html .= sprintf( __( '<span>Author:</span> <a href="%1$s">%2$s</a>', 'jetpack' ),
			esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ),
			esc_html( get_the_author() )
		);
		$html .= '</div>';
		return $html;
	}
	/**
	 * Display the featured image if it's available
	 *
	 * @return html
	 */
	static function get_company_thumbnail_link( $post_id ) {
		if ( has_post_thumbnail( $post_id ) ) {
			/**
			 * Change the Company thumbnail size.
			 *
			 * @module custom-content-types
			 *
			 * @since 3.4.0
			 *
			 * @param string|array $var Either a registered size keyword or size array.
			 */
			return '<a class="company-featured-image" href="' . esc_url( get_permalink( $post_id ) ) . '">' . get_the_post_thumbnail( $post_id, apply_filters( 'jetpack_company_thumbnail_size', 'large' ) ) . '</a>';
		}
	}
}
add_action( 'init', array( 'Jetpack_Company', 'init' ) );
// Check on plugin activation if theme supports CPT
register_activation_hook( __FILE__,                         array( 'Jetpack_Company', 'activation_post_type_support' ) );
add_action( 'jetpack_activate_module_custom-content-types', array( 'Jetpack_Company', 'activation_post_type_support' ) );
