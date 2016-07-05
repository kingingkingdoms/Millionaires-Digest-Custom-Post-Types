<?php

/**
 * User Groups Widget
 *
 * Class BP_User_Group_Widget
 */
class BP_Extended_User_Groups_Widget extends WP_Widget {

	public function __construct() {

		$widget_ops = array(
			'description' => __( 'A Dynamic list of groups of created, joined by the logged in user and control to show no. of groups', 'bp-extended-user-groups-widget' ),
		);

		parent::__construct( false, _x( 'BuddyPress Extended User Groups', 'widget name', 'bp-extended-user-groups-widget' ), $widget_ops );

	}

	public function widget( $args, $instance ) {

		extract( $args );

		//don't show to non logged in user
		if ( ! is_user_logged_in() ) {
			return;//don't show
		}

		$user_id   = get_current_user_id();
		$list_type = $instance['list_type'];
		//type: active,random  etc
		if ( empty( $instance['type'] ) ) {
			$instance['type'] = 'popular';
		}

		if ( empty( $instance['order'] ) ) {
			$instance['order'] = 'ASC';
		}

		if ( empty( $instance['limit'] ) ) {
			$instance['limit'] = 5;
		}

		if ( empty( $instance['title'] ) ) {
			$instance['title'] = __( 'Your Groups', 'bp-extended-user-groups-widget' );
		}

		$title = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );

		echo $before_widget;

		echo $before_title . $title . $after_title;

		$group_args = array(
			'user_id'  => $user_id,
			'type'     => $instance['type'],
			'order'    => $instance['order'],
			'per_page' => $instance['limit'],
			'max'      => $instance['limit'],
		);

		//modify list for groups  when we need t list all groups of which the current user is admin
		if ( $list_type == 'admin' ) {

			unset( $group_args['user_id'] );

			$groups     = BP_Groups_Member::get_is_admin_of( $user_id, $instance['limit'] );
			$groups     = $groups['groups'];
			$group_ids  = wp_list_pluck( $groups, 'id' );

			if ( empty( $group_ids ) ) {
				$group_ids  = array( 0, 0 );
			}

			$group_args['include']  = $group_ids;

		}
		$group_args['show_hidden'] = true;//show hidden groups too

		?>

		<?php if ( bp_has_groups( $group_args ) ) : ?>

			<ul id="extended-groups-list" class="item-list">

				<?php while ( bp_groups() ) : bp_the_group(); ?>

					<li <?php bp_group_class( array( 'bp-extended-user-groups-widget-item bp-extended-groups-clearfix' ) ); ?>>

						<div class="item-avatar">

							<a href="<?php bp_group_permalink() ?>"
							   title="<?php bp_group_name() ?>"><?php bp_group_avatar_thumb() ?></a>

						</div>

						<div class="item">

							<div class="item-title">

								<a href="<?php bp_group_permalink() ?>" title="<?php bp_group_name() ?>"><?php bp_group_name() ?></a>

							</div>

							<div class="item-meta">
                                                 
								<span class="activity">
									<?php
										if ( 'newest' == $instance['type'] ) {
											printf( __( 'created %s', 'bp-extended-user-groups-widget' ), bp_get_group_date_created() );
										} elseif ( 'active' == $instance['type'] ) {
											printf( __( 'active %s', 'bp-extended-user-groups-widget' ), bp_get_group_last_active() );
										} elseif ( 'popular' == $instance['type'] ) {
											bp_group_member_count();
										}
									?>
								</span>

							</div>

						</div>

					</li>

				<?php endwhile; ?>

			</ul>

			<?php wp_nonce_field( 'groups_widget_groups_list', '_wpnonce-groups' ); ?>

			<input type="hidden" name="groups_widget_max" id="groups_widget_max" value="<?php echo esc_attr( $instance['limit'] ); ?>"/>

		<?php else: ?>

			<div class="widget-error">

				<?php _e( 'There are no groups to display.', 'bp-extended-user-groups-widget' ) ?>

			</div>

		<?php endif; ?>
		
		<style type="text/css">
			.bp-extended-groups-clearfix:after {
				content:"";
				display:table;
				clear:both;
			}
			#extended-groups-list{
				list-style: none;
				margin-left:0;
			}
		</style>
		
		<?php echo $after_widget; ?>

		<?php

	}

	public function update( $new_instance, $old_instance ) {

		$instance               = $old_instance;
		$instance['title']      = strip_tags( $new_instance['title'] );
		$instance['type']       = $new_instance['type'];
		$instance['order']      = $new_instance['order'];
		$instance['limit']      = $new_instance['limit'];
		$instance['list_type']  = $new_instance['list_type'];

		return $instance;

	}

	public function form( $instance ) {

		$defaults = array(
			'title'     => __( 'Your Groups', 'bp-extended-user-groups-widget' ),
			'list_type' => 'member',
			'type'      => 'active',
			'order'     => 'ASC',
			'limit'     => 5,
		);

		$instance = wp_parse_args( (array) $instance, $defaults );

		$title      = strip_tags( $instance['title'] );
		$limit      = strip_tags( $instance['limit'] );
		$type       = strip_tags( $instance['type'] );
		$order      = strip_tags( $instance['order'] );
		$list_type  = $instance['list_type'];

		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>">
				<?php _e( 'Title:', 'bp-extended-user-groups-widget' ); ?>
				<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" style="width: 100%"/>
			</label>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'list_type' ); ?>">
				<?php _e( 'List Groups of which user is:', 'bp-extended-user-groups-widget' ); ?>
			</label>
			<label>
				<input name="<?php echo $this->get_field_name( 'list_type' ); ?>" type="radio" value="member" <?php checked( $list_type, 'member' ); ?> /> <?php _e( 'Member', 'bp-extended-user-groups-widget' );?>
			</label>
			<label>
				<input name="<?php echo $this->get_field_name( 'list_type' ); ?>" type="radio" value="admin" <?php checked( $list_type, 'admin' ); ?> /> <?php _e( 'Admin', 'bp-extended-user-groups-widget'); ?>
			</label>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'type' ); ?>">
				<?php _e( 'List Type', 'bp-extended-user-groups-widget' ); ?>
			</label>
			<select name="<?php echo $this->get_field_name( 'type' ); ?>" id="<?php echo $this->get_field_id( 'type' ); ?>">

				<option value="active" <?php selected( 'active', $type, true ) ?>>
					<?php _e( 'Most Recent Active', 'bp-extended-user-groups-widget' ) ?>
				</option>

				<option value="popular" <?php selected( 'popular', $type, true ) ?>>
					<?php _e( 'Most Popular', 'bp-extended-user-groups-widget' ) ?>
				</option>
				<option value="alphabetical" <?php selected( 'alphabetical', $type, true ) ?>>
					<?php _e( 'Alphabetical', 'bp-extended-user-groups-widget' ) ?>
				</option>
				<option value="newest" <?php selected( 'newest', $type, true ) ?>>
					<?php _e( 'New Groups', 'bp-extended-user-groups-widget' ) ?>
				</option>
				<option value="random" <?php selected( 'random', $type, true ) ?>>
					<?php _e( 'Random', 'bp-extended-user-groups-widget' ) ?>
				</option>

			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'order' ); ?>">
				<?php _e( 'Order', 'bp-extended-user-groups-widget' ); ?>
			</label>
			<select name="<?php echo $this->get_field_name( 'order' ); ?>" id="<?php echo $this->get_field_id( 'order' ); ?>">
				<option value="ASC" <?php selected( 'ASC', $order, true ) ?>>
					<?php _e( 'Ascending Order', 'bp-extended-user-groups-widget' ) ?>
				</option>
				<option value="DESC" <?php selected( 'DESC', $order, true ) ?>>
					<?php _e( 'Descending Order', 'bp-extended-user-groups-widget' ) ?>
				</option>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'limit' ); ?>">
				<?php _e( 'Limit Group To Show:', 'bp-extended-user-groups-widget' ); ?>
				<input id="<?php echo $this->get_field_name( 'limit' ); ?>" name="<?php echo $this->get_field_name( 'limit' ); ?>"  type="text" value="<?php echo esc_attr( $limit ); ?>" style="width: 30%" />
			</label>
		</p>
		<?php
	}

}
