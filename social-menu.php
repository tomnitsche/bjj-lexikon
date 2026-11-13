/**
 * Kopfzeile: Kurzlinks zu YouTube, Instagram, Patreon im Hauptmenü (13.11.2026)
 */
add_filter( 'wp_nav_menu_items', function ( $items, $args ) {
	if ( empty( $args->theme_location ) || 'primary' !== $args->theme_location ) { return $items; }
	$links = array(
		array( 'YouTube', 'https://youtube.com/@greyground9543', '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8zM9.6 15.6V8.4l6.2 3.6-6.2 3.6z"/></svg>', 'yt' ),
		array( 'Instagram', 'https://instagram.com/greyground.de', '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2.2" y="2.2" width="19.6" height="19.6" rx="5.5" fill="none" stroke="currentColor" stroke-width="2.2"/><circle cx="12" cy="12" r="4.6" fill="none" stroke="currentColor" stroke-width="2.2"/><circle cx="17.6" cy="6.4" r="1.4" fill="currentColor"/></svg>', 'ig' ),
		array( 'Patreon', 'https://www.patreon.com/Greyground?utm_source=greyground.de&utm_medium=website&utm_campaign=menue', '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M22.957 7.21c-.004-3.064-2.391-5.576-5.191-6.482-3.478-1.125-8.064-.962-11.384.604C2.357 3.231 1.093 7.391 1.046 11.54c-.039 3.411.302 12.395 5.369 12.459 3.765.047 4.326-4.804 6.068-7.141 1.24-1.662 2.836-2.132 4.801-2.618 3.376-.836 5.678-3.501 5.673-7.03Z"/></svg>', 'pt' ),
	);
	$h = '';
	foreach ( $links as $i => $l ) {
		$h .= '<li class="menu-item gg-menu-social gg-ms-' . $l[3] . ( 0 === $i ? ' gg-ms-first' : '' ) . '"><a href="' . esc_url( $l[1] ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( $l[0] ) . '" title="' . esc_attr( $l[0] ) . '">' . $l[2] . '<span class="gg-ms-label">' . esc_html( $l[0] ) . '</span></a></li>';
	}
	return $items . $h;
}, 20, 2 );

add_action( 'wp_head', function () { ?>
<style id="gg-menu-social">
.gg-menu-social > a,.gg-menu-social > .wrap > a{display:inline-flex!important;align-items:center;gap:8px;padding-left:6px!important;padding-right:6px!important;color:rgba(241,239,234,.75)!important;transition:color .2s}
.gg-menu-social svg{width:18px;height:18px;display:block}
.gg-menu-social.gg-ms-yt > a:hover{color:#FF0033!important}
.gg-menu-social > a:hover{color:#F2C230!important}
.gg-menu-social.gg-ms-first{margin-left:14px;padding-left:14px;border-left:1px solid rgba(241,239,234,.18)}
.gg-ms-label{display:none}
@media (max-width:959px){.gg-menu-social.gg-ms-first{margin-left:0;padding-left:0;border-left:0;border-top:1px solid rgba(241,239,234,.15);margin-top:10px;padding-top:10px}.gg-ms-label{display:inline}}
</style>
<?php } );
